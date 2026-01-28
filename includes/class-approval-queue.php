<?php
/**
 * Approval Queue
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Manages the approval queue for agent actions requiring human oversight
 */
class Approval_Queue {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add admin notices for pending approvals.
		add_action( 'admin_notices', array( $this, 'pending_approval_notice' ) );
	}

	/**
	 * Show notice for pending approvals
	 *
	 * @return void
	 */
	public function pending_approval_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count = $this->get_pending_count();

		if ( $count > 0 ) {
			$url = admin_url( 'admin.php?page=agentic-approvals' );
			printf(
				'<div class="notice notice-warning"><p><strong>Agentic:</strong> %s pending approval(s). <a href="%s">Review now</a></p></div>',
				esc_html( $count ),
				esc_url( $url )
			);
		}
	}

	/**
	 * Get count of pending approvals
	 *
	 * @return int
	 */
	public function get_pending_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agentic_approval_queue WHERE status = 'pending'"
		);
	}

	/**
	 * Get all pending approvals
	 *
	 * @return array
	 */
	public function get_pending(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE status = 'pending' ORDER BY created_at DESC",
			ARRAY_A
		);

		foreach ( $results as &$row ) {
			$row['params'] = json_decode( $row['params'], true );
		}

		return $results;
	}

	/**
	 * Add an item to the approval queue
	 *
	 * @param string $agent_id  Agent identifier.
	 * @param string $action    Action type.
	 * @param array  $params    Action parameters.
	 * @param string $reasoning Reasoning for action.
	 * @param int    $expires   Days until expiration.
	 * @return int|false Queue item ID or false.
	 */
	public function add( string $agent_id, string $action, array $params, string $reasoning = '', int $expires = 7 ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'agent_id'   => $agent_id,
				'action'     => $action,
				'params'     => wp_json_encode( $params ),
				'reasoning'  => $reasoning,
				'status'     => 'pending',
				'created_at' => current_time( 'mysql' ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( "+{$expires} days" ) ),
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Approve an item
	 *
	 * @param int $id Queue item ID.
	 * @return bool Success.
	 */
	public function approve( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->update(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'status'      => 'approved',
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Reject an item
	 *
	 * @param int $id Queue item ID.
	 * @return bool Success.
	 */
	public function reject( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->update(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'status'      => 'rejected',
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Clean up expired items
	 *
	 * @return int Number of items deleted.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		return $wpdb->query(
			"DELETE FROM {$wpdb->prefix}agentic_approval_queue 
             WHERE status = 'pending' AND expires_at < NOW()"
		);
	}
}
