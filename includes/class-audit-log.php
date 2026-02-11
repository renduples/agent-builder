<?php
/**
 * Audit Log
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.1.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Audit logging for agent actions
 */
class Audit_Log {

	/**
	 * Log an action
	 *
	 * @param string $agent_id    Agent identifier.
	 * @param string $action      Action type.
	 * @param string $target_type Target type.
	 * @param mixed  $details     Action details.
	 * @param string $reasoning   Reasoning for action.
	 * @param int    $tokens      Tokens used.
	 * @param float  $cost        Estimated cost.
	 * @return int|false Log entry ID or false.
	 */
	public function log(
		string $agent_id,
		string $action,
		string $target_type = '',
		mixed $details = null,
		string $reasoning = '',
		int $tokens = 0,
		float $cost = 0.0
	): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert(
			$wpdb->prefix . 'agentic_audit_log',
			array(
				'agent_id'    => $agent_id,
				'action'      => $action,
				'target_type' => $target_type,
				'target_id'   => is_array( $details ) && isset( $details['id'] ) ? (string) $details['id'] : '',
				'details'     => wp_json_encode( $details ),
				'reasoning'   => $reasoning,
				'tokens_used' => $tokens,
				'cost'        => $cost,
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get recent log entries
	 *
	 * @param int    $limit    Maximum entries.
	 * @param string $agent_id Filter by agent.
	 * @param string $action   Filter by action.
	 * @return array Log entries.
	 */
	public function get_recent( int $limit = 50, ?string $agent_id = null, ?string $action = null ): array {
		global $wpdb;

		$where  = array();
		$params = array();

		if ( $agent_id ) {
			$where[]  = 'agent_id = %s';
			$params[] = $agent_id;
		}

		if ( $action ) {
			$where[]  = 'action = %s';
			$params[] = $action;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$params[]     = $limit;

		$query = 'SELECT * FROM ' . $wpdb->prefix . 'agentic_audit_log ' . $where_clause . ' ORDER BY created_at DESC LIMIT %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, where clause built from known safe values.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
	}

	/**
	 * Get usage statistics
	 *
	 * @param string $period Period (day, week, month).
	 * @return array Statistics.
	 */
	public function get_stats( string $period = 'day' ): array {
		global $wpdb;

		$days = match ( $period ) {
			'week'  => 7,
			'month' => 30,
			default => 1,
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table stats.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(*) as total_actions,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    COUNT(DISTINCT agent_id) as active_agents
                FROM {$wpdb->prefix}agentic_audit_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			),
			ARRAY_A
		);

		return null !== $stats ? $stats : array(
			'total_actions' => 0,
			'total_tokens'  => 0,
			'total_cost'    => 0,
			'active_agents' => 0,
		);
	}

	/**
	 * Clear old log entries
	 *
	 * @param int $days Entries older than this will be deleted.
	 * @return int Number of deleted entries.
	 */
	public function cleanup( int $days = 30 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table cleanup.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}agentic_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
