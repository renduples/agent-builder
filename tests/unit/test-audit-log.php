<?php
/**
 * Unit Tests for Audit_Log
 *
 * Tests action logging, retrieval, statistics, and cleanup.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Audit_Log;

/**
 * Test case for Audit_Log class.
 */
class Test_Audit_Log extends TestCase {

	/**
	 * Audit_Log instance.
	 *
	 * @var Audit_Log
	 */
	private Audit_Log $log;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->log = new Audit_Log();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}agentic_audit_log" );
	}

	// -------------------------------------------------------------------------
	// Logging
	// -------------------------------------------------------------------------

	/**
	 * Test log returns an integer ID.
	 */
	public function test_log_returns_id() {
		$id = $this->log->log( 'test-agent', 'chat', 'message' );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test log with all parameters.
	 */
	public function test_log_with_all_parameters() {
		$details = array( 'id' => 42, 'content' => 'Hello world' );
		$id      = $this->log->log(
			'seo-analyzer',
			'analyze_page',
			'post',
			$details,
			'User requested SEO analysis',
			1500,
			0.003
		);

		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_audit_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'seo-analyzer', $row['agent_id'] );
		$this->assertEquals( 'analyze_page', $row['action'] );
		$this->assertEquals( 'post', $row['target_type'] );
		$this->assertEquals( '42', $row['target_id'] );
		$this->assertEquals( 'User requested SEO analysis', $row['reasoning'] );
		$this->assertEquals( 1500, (int) $row['tokens_used'] );
		$this->assertEquals( 0.003, (float) $row['cost'] );
		$this->assertNotEmpty( $row['created_at'] );

		$stored_details = json_decode( $row['details'], true );
		$this->assertEquals( 42, $stored_details['id'] );
	}

	/**
	 * Test log with minimal parameters.
	 */
	public function test_log_minimal() {
		$id = $this->log->log( 'test-agent', 'generic_action' );
		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_audit_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'test-agent', $row['agent_id'] );
		$this->assertEquals( 'generic_action', $row['action'] );
		$this->assertEquals( 0, (int) $row['tokens_used'] );
		$this->assertEquals( 0.0, (float) $row['cost'] );
	}

	/**
	 * Test log stores current user ID.
	 */
	public function test_log_stores_user_id() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$id = $this->log->log( 'test-agent', 'user_action' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_audit_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( $user_id, (int) $row['user_id'] );
	}

	/**
	 * Test log details are JSON encoded.
	 */
	public function test_log_details_json_encoded() {
		$details = array( 'file' => 'style.css', 'changes' => 3 );
		$id      = $this->log->log( 'developer-agent', 'code_change', 'file', $details );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_audit_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$decoded = json_decode( $row['details'], true );
		$this->assertEquals( 'style.css', $decoded['file'] );
		$this->assertEquals( 3, $decoded['changes'] );
	}

	/**
	 * Test log tokens and cost tracking.
	 */
	public function test_log_tokens_and_cost() {
		$this->log->log( 'agent-a', 'chat', '', null, '', 500, 0.001 );
		$this->log->log( 'agent-a', 'chat', '', null, '', 1000, 0.002 );
		$this->log->log( 'agent-b', 'chat', '', null, '', 750, 0.0015 );

		$stats = $this->log->get_stats( 'day' );
		$this->assertEquals( 2250, (int) $stats['total_tokens'] );
		$this->assertEqualsWithDelta( 0.0045, (float) $stats['total_cost'], 0.0001 );
	}

	// -------------------------------------------------------------------------
	// Retrieval
	// -------------------------------------------------------------------------

	/**
	 * Test get_recent returns entries in DESC order.
	 */
	public function test_get_recent() {
		$this->log->log( 'agent-a', 'action1' );
		$this->log->log( 'agent-b', 'action2' );
		$this->log->log( 'agent-c', 'action3' );

		$entries = $this->log->get_recent( 50 );
		$this->assertCount( 3, $entries );
		// Most recent first.
		$this->assertEquals( 'agent-c', $entries[0]['agent_id'] );
	}

	/**
	 * Test get_recent filtered by agent_id.
	 */
	public function test_get_recent_by_agent() {
		$this->log->log( 'agent-a', 'action1' );
		$this->log->log( 'agent-b', 'action2' );
		$this->log->log( 'agent-a', 'action3' );

		$entries = $this->log->get_recent( 50, 'agent-a' );
		$this->assertCount( 2, $entries );

		foreach ( $entries as $entry ) {
			$this->assertEquals( 'agent-a', $entry['agent_id'] );
		}
	}

	/**
	 * Test get_recent filtered by action.
	 */
	public function test_get_recent_by_action() {
		$this->log->log( 'agent-a', 'chat' );
		$this->log->log( 'agent-b', 'code_change' );
		$this->log->log( 'agent-c', 'chat' );

		$entries = $this->log->get_recent( 50, null, 'chat' );
		$this->assertCount( 2, $entries );

		foreach ( $entries as $entry ) {
			$this->assertEquals( 'chat', $entry['action'] );
		}
	}

	/**
	 * Test get_recent with both agent and action filter.
	 */
	public function test_get_recent_by_agent_and_action() {
		$this->log->log( 'agent-a', 'chat' );
		$this->log->log( 'agent-a', 'code_change' );
		$this->log->log( 'agent-b', 'chat' );

		$entries = $this->log->get_recent( 50, 'agent-a', 'chat' );
		$this->assertCount( 1, $entries );
		$this->assertEquals( 'agent-a', $entries[0]['agent_id'] );
		$this->assertEquals( 'chat', $entries[0]['action'] );
	}

	/**
	 * Test get_recent respects limit.
	 */
	public function test_get_recent_limit() {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->log->log( 'agent-a', "action{$i}" );
		}

		$entries = $this->log->get_recent( 5 );
		$this->assertCount( 5, $entries );
	}

	// -------------------------------------------------------------------------
	// Statistics
	// -------------------------------------------------------------------------

	/**
	 * Test get_stats returns expected keys.
	 */
	public function test_get_stats_keys() {
		$stats = $this->log->get_stats();
		$this->assertArrayHasKey( 'total_actions', $stats );
		$this->assertArrayHasKey( 'total_tokens', $stats );
		$this->assertArrayHasKey( 'total_cost', $stats );
		$this->assertArrayHasKey( 'active_agents', $stats );
	}

	/**
	 * Test get_stats period filtering (day/week/month).
	 */
	public function test_get_stats_period() {
		$this->log->log( 'agent-a', 'chat', '', null, '', 100, 0.001 );

		$day_stats = $this->log->get_stats( 'day' );
		$this->assertEquals( 1, (int) $day_stats['total_actions'] );

		$week_stats = $this->log->get_stats( 'week' );
		$this->assertEquals( 1, (int) $week_stats['total_actions'] );

		$month_stats = $this->log->get_stats( 'month' );
		$this->assertEquals( 1, (int) $month_stats['total_actions'] );
	}

	/**
	 * Test get_stats active_agents count.
	 */
	public function test_get_stats_active_agents() {
		$this->log->log( 'agent-a', 'action1' );
		$this->log->log( 'agent-b', 'action2' );
		$this->log->log( 'agent-a', 'action3' );

		$stats = $this->log->get_stats( 'day' );
		$this->assertEquals( 2, (int) $stats['active_agents'] );
	}

	/**
	 * Test get_stats on empty table.
	 */
	public function test_get_stats_empty() {
		$stats = $this->log->get_stats();
		$this->assertEquals( 0, (int) $stats['total_actions'] );
		$this->assertEquals( 0, (int) $stats['total_tokens'] );
	}

	// -------------------------------------------------------------------------
	// Cleanup
	// -------------------------------------------------------------------------

	/**
	 * Test cleanup removes old entries.
	 */
	public function test_cleanup_old_entries() {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_audit_log';

		$this->log->log( 'agent-a', 'old_action' );
		$wpdb->query( "UPDATE {$table} SET created_at = DATE_SUB(NOW(), INTERVAL 120 DAY)" );

		$this->log->log( 'agent-b', 'recent_action' );

		$deleted = $this->log->cleanup( 90 );
		$this->assertGreaterThanOrEqual( 1, $deleted );

		$entries = $this->log->get_recent( 50 );
		$this->assertCount( 1, $entries );
		$this->assertEquals( 'agent-b', $entries[0]['agent_id'] );
	}

	/**
	 * Test cleanup preserves recent entries.
	 */
	public function test_cleanup_preserves_recent() {
		$this->log->log( 'agent-a', 'recent_action' );

		$deleted = $this->log->cleanup( 90 );
		$this->assertEquals( 0, $deleted );
	}
}
