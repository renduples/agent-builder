<?php
/**
 * Agent Proposals — pending change confirmation system
 *
 * When confirmation mode is "Always Confirm", user-space write tools
 * create a proposal instead of executing immediately. The proposal is
 * rendered in the chat UI with a diff view and Approve/Reject buttons.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.6.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Manages pending change proposals for user-space operations.
 */
class Agent_Proposals {

	/**
	 * Transient prefix for pending proposals.
	 */
	private const TRANSIENT_PREFIX = 'agentic_proposal_';

	/**
	 * Proposal expiry in seconds (1 hour).
	 */
	private const EXPIRY = 3600;

	/**
	 * Backup directory name inside wp-content.
	 */
	public const BACKUP_DIR = 'agentic-backups';

	/**
	 * Create a new proposal.
	 *
	 * @param string $tool_name   The tool that generated this proposal.
	 * @param array  $params      Tool parameters (path, content, etc.).
	 * @param string $agent_id    Agent that proposed the change.
	 * @param string $description Human-readable description of the change.
	 * @param string $diff        Diff between current and proposed content.
	 * @return array Proposal data with ID.
	 */
	public static function create( string $tool_name, array $params, string $agent_id, string $description, string $diff = '' ): array {
		$proposal_id = wp_generate_uuid4();

		$proposal = array(
			'id'          => $proposal_id,
			'tool'        => $tool_name,
			'params'      => $params,
			'agent_id'    => $agent_id,
			'description' => $description,
			'diff'        => $diff,
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql' ),
			'created_by'  => get_current_user_id(),
		);

		set_transient( self::TRANSIENT_PREFIX . $proposal_id, $proposal, self::EXPIRY );

		// Log the proposal creation.
		$audit = new Audit_Log();
		$audit->log( $agent_id, 'proposal_created', $tool_name, array(
			'proposal_id' => $proposal_id,
			'description' => $description,
		) );

		return $proposal;
	}

	/**
	 * Get a pending proposal by ID.
	 *
	 * @param string $proposal_id Proposal UUID.
	 * @return array|null Proposal data or null if not found/expired.
	 */
	public static function get( string $proposal_id ): ?array {
		$proposal = get_transient( self::TRANSIENT_PREFIX . $proposal_id );
		return is_array( $proposal ) ? $proposal : null;
	}

	/**
	 * Approve and execute a proposal.
	 *
	 * @param string $proposal_id Proposal UUID.
	 * @return array Result of execution.
	 */
	public static function approve( string $proposal_id ): array {
		$proposal = self::get( $proposal_id );

		if ( ! $proposal ) {
			return array( 'error' => 'Proposal not found or expired.' );
		}

		if ( 'pending' !== $proposal['status'] ) {
			return array( 'error' => 'Proposal already processed.' );
		}

		// Mark as approved before executing.
		$proposal['status']      = 'approved';
		$proposal['approved_at'] = current_time( 'mysql' );
		$proposal['approved_by'] = get_current_user_id();
		set_transient( self::TRANSIENT_PREFIX . $proposal_id, $proposal, self::EXPIRY );

		// Execute the change via Agent_Tools.
		$tools  = new Agent_Tools();
		$result = $tools->execute_user_space(
			$proposal['tool'],
			$proposal['params'],
			$proposal['agent_id'],
			true // Skip confirmation (already approved).
		);

		// Log approval.
		$audit = new Audit_Log();
		$audit->log( $proposal['agent_id'], 'proposal_approved', $proposal['tool'], array(
			'proposal_id' => $proposal_id,
			'result'      => is_array( $result ) ? ( $result['success'] ?? false ) : false,
		) );

		// Clean up transient.
		delete_transient( self::TRANSIENT_PREFIX . $proposal_id );

		return $result;
	}

	/**
	 * Reject a proposal.
	 *
	 * @param string $proposal_id Proposal UUID.
	 * @return array Result.
	 */
	public static function reject( string $proposal_id ): array {
		$proposal = self::get( $proposal_id );

		if ( ! $proposal ) {
			return array( 'error' => 'Proposal not found or expired.' );
		}

		if ( 'pending' !== $proposal['status'] ) {
			return array( 'error' => 'Proposal already processed.' );
		}

		// Log rejection.
		$audit = new Audit_Log();
		$audit->log( $proposal['agent_id'], 'proposal_rejected', $proposal['tool'], array(
			'proposal_id' => $proposal_id,
		) );

		// Clean up.
		delete_transient( self::TRANSIENT_PREFIX . $proposal_id );

		return array(
			'success' => true,
			'message' => 'Proposal rejected.',
		);
	}

	/**
	 * Create a backup of a file before modification.
	 *
	 * @param string $full_path Absolute path to the file.
	 * @return string|null Backup file path or null on failure.
	 */
	public static function backup_file( string $full_path ): ?string {
		if ( ! file_exists( $full_path ) ) {
			return null;
		}

		$backup_dir = WP_CONTENT_DIR . '/' . self::BACKUP_DIR;

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( ! $wp_filesystem->is_dir( $backup_dir ) ) {
			$wp_filesystem->mkdir( $backup_dir, FS_CHMOD_DIR );
		}

		// Create a timestamped backup.
		$relative   = str_replace( WP_CONTENT_DIR . '/', '', $full_path );
		$safe_name  = str_replace( '/', '__', $relative );
		$timestamp  = gmdate( 'Ymd-His' );
		$backup_path = $backup_dir . '/' . $timestamp . '_' . $safe_name;

		if ( $wp_filesystem->copy( $full_path, $backup_path ) ) {
			return $backup_path;
		}

		return null;
	}

	/**
	 * Generate a simple unified diff between two strings.
	 *
	 * @param string $old     Original content.
	 * @param string $new     New content.
	 * @param string $label_old Label for original (e.g., filename).
	 * @param string $label_new Label for new.
	 * @return string Diff output.
	 */
	public static function generate_diff( string $old, string $new, string $label_old = 'original', string $label_new = 'proposed' ): string {
		$old_lines = explode( "\n", $old );
		$new_lines = explode( "\n", $new );

		$diff = "--- {$label_old}\n+++ {$label_new}\n";

		// Simple line-based diff using PHP's built-in function.
		$max_old = count( $old_lines );
		$max_new = count( $new_lines );
		$max     = max( $max_old, $max_new );

		$changes = array();
		$i_old   = 0;
		$i_new   = 0;

		// Build a basic diff using xdiff-style output.
		while ( $i_old < $max_old || $i_new < $max_new ) {
			if ( $i_old < $max_old && $i_new < $max_new && $old_lines[ $i_old ] === $new_lines[ $i_new ] ) {
				$changes[] = ' ' . $old_lines[ $i_old ];
				++$i_old;
				++$i_new;
			} elseif ( $i_old < $max_old && ( $i_new >= $max_new || ! in_array( $old_lines[ $i_old ], array_slice( $new_lines, $i_new, 5 ), true ) ) ) {
				$changes[] = '-' . $old_lines[ $i_old ];
				++$i_old;
			} else {
				$changes[] = '+' . $new_lines[ $i_new ];
				++$i_new;
			}
		}

		// Only show changed regions with 3 lines of context.
		$output       = array();
		$context      = 3;
		$in_change    = false;
		$change_start = -1;

		for ( $i = 0; $i < count( $changes ); $i++ ) {
			$is_change = ' ' !== $changes[ $i ][0];

			if ( $is_change && ! $in_change ) {
				$in_change    = true;
				$change_start = max( 0, $i - $context );
				// Add context before.
				for ( $j = $change_start; $j < $i; $j++ ) {
					$output[] = $changes[ $j ];
				}
			}

			if ( $is_change ) {
				$output[] = $changes[ $i ];
			} elseif ( $in_change ) {
				$output[] = $changes[ $i ];
				// Check if we should close this hunk.
				$next_change = false;
				for ( $j = $i + 1; $j <= $i + $context && $j < count( $changes ); $j++ ) {
					if ( ' ' !== $changes[ $j ][0] ) {
						$next_change = true;
						break;
					}
				}
				if ( ! $next_change ) {
					$in_change = false;
				}
			}
		}

		$diff .= implode( "\n", $output );

		return $diff;
	}
}
