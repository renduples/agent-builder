<?php
/**
 * Unit Tests for Security_Log
 *
 * Tests security event logging, retrieval, statistics, and cleanup.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Security_Log;

/**
 * Test case for Security_Log class.
 */
class Test_Security_Log extends TestCase {

	/**
	 * Clean security log table before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}agentic_security_log" );
	}

	// -------------------------------------------------------------------------
	// Table creation
	// -------------------------------------------------------------------------

	/**
	 * Test table exists after create_table().
	 */
	public function test_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_security_log';
		$this->assertEquals( $table, $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) );
	}

	/**
	 * Test table has all expected columns.
	 */
	public function test_table_schema() {
		global $wpdb;
		$table   = $wpdb->prefix . 'agentic_security_log';
		$columns = $wpdb->get_col( "DESCRIBE {$table}" );

		$expected = array( 'id', 'event_type', 'user_id', 'ip_address', 'message', 'pattern_matched', 'pii_types', 'created_at' );
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "Column {$col} should exist" );
		}
	}

	// -------------------------------------------------------------------------
	// Logging events
	// -------------------------------------------------------------------------

	/**
	 * Test log returns an integer ID.
	 */
	public function test_log_returns_id() {
		$id = Security_Log::log( 'blocked', 1, '127.0.0.1', 'bad message', 'ignore previous' );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test log blocked event.
	 */
	public function test_log_blocked_event() {
		$id = Security_Log::log( 'blocked', 42, '10.0.0.1', 'ignore your instructions', 'ignore.*instructions' );
		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'blocked', $row['event_type'] );
		$this->assertEquals( 42, (int) $row['user_id'] );
		$this->assertEquals( '10.0.0.1', $row['ip_address'] );
		$this->assertStringContainsString( 'ignore your instructions', $row['message'] );
		$this->assertEquals( 'ignore.*instructions', $row['pattern_matched'] );
	}

	/**
	 * Test log rate_limited event.
	 */
	public function test_log_rate_limited_event() {
		$id = Security_Log::log( 'rate_limited', 5, '192.168.1.1' );
		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'rate_limited', $row['event_type'] );
		$this->assertEquals( 5, (int) $row['user_id'] );
	}

	/**
	 * Test log pii_warning event with PII types.
	 */
	public function test_log_pii_warning_event() {
		$id = Security_Log::log(
			'pii_warning',
			1,
			'127.0.0.1',
			'My email is test@example.com',
			'',
			array( 'email', 'phone_us' )
		);

		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'pii_warning', $row['event_type'] );
		$this->assertEquals( 'email,phone_us', $row['pii_types'] );
	}

	/**
	 * Test log with all fields populated.
	 */
	public function test_log_with_all_fields() {
		$id = Security_Log::log(
			'blocked',
			99,
			'2001:db8::1',
			'Some malicious message',
			'jailbreak',
			array( 'ssn' )
		);

		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'blocked', $row['event_type'] );
		$this->assertEquals( 99, (int) $row['user_id'] );
		$this->assertEquals( '2001:db8::1', $row['ip_address'] );
		$this->assertEquals( 'jailbreak', $row['pattern_matched'] );
		$this->assertEquals( 'ssn', $row['pii_types'] );
		$this->assertNotEmpty( $row['created_at'] );
	}

	/**
	 * Test message truncation (>200 chars).
	 */
	public function test_message_truncation() {
		$long_message = str_repeat( 'A', 500 );
		$id           = Security_Log::log( 'blocked', 1, '127.0.0.1', $long_message );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 200, strlen( $row['message'] ) );
	}

	/**
	 * Test log with anonymous user (user_id = 0).
	 */
	public function test_log_anonymous_user() {
		$id = Security_Log::log( 'blocked', 0, '10.10.10.10', 'anonymous attempt' );
		$this->assertIsInt( $id );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_security_log WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 0, (int) $row['user_id'] );
	}

	// -------------------------------------------------------------------------
	// Retrieving events
	// -------------------------------------------------------------------------

	/**
	 * Test get_events with no filters returns all events.
	 */
	public function test_get_events_all() {
		Security_Log::log( 'blocked', 1, '127.0.0.1', 'msg1' );
		Security_Log::log( 'rate_limited', 2, '127.0.0.2', 'msg2' );
		Security_Log::log( 'pii_warning', 3, '127.0.0.3', 'msg3' );

		$events = Security_Log::get_events();
		$this->assertCount( 3, $events );
	}

	/**
	 * Test get_events filtered by event_type.
	 */
	public function test_get_events_by_type() {
		Security_Log::log( 'blocked', 1, '127.0.0.1' );
		Security_Log::log( 'blocked', 2, '127.0.0.2' );
		Security_Log::log( 'rate_limited', 3, '127.0.0.3' );

		$events = Security_Log::get_events( array( 'event_type' => 'blocked' ) );
		$this->assertCount( 2, $events );

		foreach ( $events as $event ) {
			$this->assertEquals( 'blocked', $event['event_type'] );
		}
	}

	/**
	 * Test get_events filtered by user_id.
	 */
	public function test_get_events_by_user() {
		Security_Log::log( 'blocked', 5, '127.0.0.1' );
		Security_Log::log( 'blocked', 5, '127.0.0.2' );
		Security_Log::log( 'blocked', 10, '127.0.0.3' );

		$events = Security_Log::get_events( array( 'user_id' => 5 ) );
		$this->assertCount( 2, $events );
	}

	/**
	 * Test get_events pagination.
	 */
	public function test_get_events_pagination() {
		for ( $i = 0; $i < 10; $i++ ) {
			Security_Log::log( 'blocked', 1, '127.0.0.1', "msg{$i}" );
		}

		$page1 = Security_Log::get_events( array( 'limit' => 3, 'offset' => 0 ) );
		$page2 = Security_Log::get_events( array( 'limit' => 3, 'offset' => 3 ) );

		$this->assertCount( 3, $page1 );
		$this->assertCount( 3, $page2 );
		$this->assertNotEquals( $page1[0]['id'], $page2[0]['id'] );
	}

	/**
	 * Test get_events default order is DESC.
	 */
	public function test_get_events_order_desc() {
		$id1 = Security_Log::log( 'blocked', 1, '127.0.0.1', 'first' );
		$id2 = Security_Log::log( 'blocked', 1, '127.0.0.1', 'second' );

		$events = Security_Log::get_events();
		// DESC means newest first.
		$this->assertEquals( $id2, (int) $events[0]['id'] );
		$this->assertEquals( $id1, (int) $events[1]['id'] );
	}

	/**
	 * Test get_events empty table.
	 */
	public function test_get_events_empty_table() {
		$events = Security_Log::get_events();
		$this->assertIsArray( $events );
		$this->assertEmpty( $events );
	}

	// -------------------------------------------------------------------------
	// Count
	// -------------------------------------------------------------------------

	/**
	 * Test get_count with no filters.
	 */
	public function test_get_count_all() {
		Security_Log::log( 'blocked', 1, '127.0.0.1' );
		Security_Log::log( 'blocked', 2, '127.0.0.2' );

		$this->assertEquals( 2, Security_Log::get_count() );
	}

	/**
	 * Test get_count filtered by event_type.
	 */
	public function test_get_count_by_type() {
		Security_Log::log( 'blocked', 1, '127.0.0.1' );
		Security_Log::log( 'rate_limited', 2, '127.0.0.2' );

		$this->assertEquals( 1, Security_Log::get_count( array( 'event_type' => 'blocked' ) ) );
		$this->assertEquals( 1, Security_Log::get_count( array( 'event_type' => 'rate_limited' ) ) );
	}

	/**
	 * Test get_count on empty table.
	 */
	public function test_get_count_empty() {
		$this->assertEquals( 0, Security_Log::get_count() );
	}

	// -------------------------------------------------------------------------
	// Statistics
	// -------------------------------------------------------------------------

	/**
	 * Test get_stats returns expected keys.
	 */
	public function test_get_stats_keys() {
		$stats = Security_Log::get_stats();
		$this->assertArrayHasKey( 'total_events', $stats );
		$this->assertArrayHasKey( 'blocked_count', $stats );
		$this->assertArrayHasKey( 'rate_limited_count', $stats );
		$this->assertArrayHasKey( 'pii_warning_count', $stats );
		$this->assertArrayHasKey( 'unique_ips', $stats );
		$this->assertArrayHasKey( 'unique_users', $stats );
	}

	/**
	 * Test get_stats counts events correctly.
	 */
	public function test_get_stats_counts() {
		Security_Log::log( 'blocked', 1, '10.0.0.1' );
		Security_Log::log( 'blocked', 2, '10.0.0.2' );
		Security_Log::log( 'rate_limited', 3, '10.0.0.1' );
		Security_Log::log( 'pii_warning', 1, '10.0.0.3' );

		$stats = Security_Log::get_stats( 7 );
		$this->assertEquals( 4, (int) $stats['total_events'] );
		$this->assertEquals( 2, (int) $stats['blocked_count'] );
		$this->assertEquals( 1, (int) $stats['rate_limited_count'] );
		$this->assertEquals( 1, (int) $stats['pii_warning_count'] );
		$this->assertEquals( 3, (int) $stats['unique_ips'] );
		$this->assertEquals( 3, (int) $stats['unique_users'] );
	}

	// -------------------------------------------------------------------------
	// Top patterns & IPs
	// -------------------------------------------------------------------------

	/**
	 * Test get_top_patterns returns results.
	 */
	public function test_get_top_patterns() {
		Security_Log::log( 'blocked', 1, '127.0.0.1', 'msg', 'pattern_a' );
		Security_Log::log( 'blocked', 2, '127.0.0.2', 'msg', 'pattern_a' );
		Security_Log::log( 'blocked', 3, '127.0.0.3', 'msg', 'pattern_b' );

		$patterns = Security_Log::get_top_patterns( 10, 7 );
		$this->assertNotEmpty( $patterns );
		$this->assertEquals( 'pattern_a', $patterns[0]['pattern_matched'] );
		$this->assertEquals( 2, (int) $patterns[0]['count'] );
	}

	/**
	 * Test get_top_ips returns results.
	 */
	public function test_get_top_ips() {
		Security_Log::log( 'blocked', 1, '10.10.10.1' );
		Security_Log::log( 'blocked', 2, '10.10.10.1' );
		Security_Log::log( 'rate_limited', 3, '10.10.10.1' );
		Security_Log::log( 'blocked', 4, '10.10.10.2' );

		$ips = Security_Log::get_top_ips( 10, 7 );
		$this->assertNotEmpty( $ips );
		$this->assertEquals( '10.10.10.1', $ips[0]['ip_address'] );
		$this->assertEquals( 3, (int) $ips[0]['event_count'] );
	}

	// -------------------------------------------------------------------------
	// Cleanup
	// -------------------------------------------------------------------------

	/**
	 * Test cleanup removes old entries.
	 */
	public function test_cleanup_old_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_security_log';

		// Insert an entry and backdate it.
		Security_Log::log( 'blocked', 1, '127.0.0.1', 'old entry' );
		$wpdb->query( "UPDATE {$table} SET created_at = DATE_SUB(NOW(), INTERVAL 60 DAY)" );

		// Insert a recent entry.
		Security_Log::log( 'blocked', 2, '127.0.0.2', 'recent entry' );

		$deleted = Security_Log::cleanup( 30 );
		$this->assertEquals( 1, $deleted );

		// Recent entry should remain.
		$remaining = Security_Log::get_count();
		$this->assertEquals( 1, $remaining );
	}

	/**
	 * Test cleanup respects retention period.
	 */
	public function test_cleanup_respects_retention() {
		Security_Log::log( 'blocked', 1, '127.0.0.1', 'recent' );

		$deleted = Security_Log::cleanup( 30 );
		$this->assertEquals( 0, $deleted );

		$remaining = Security_Log::get_count();
		$this->assertEquals( 1, $remaining );
	}

	/**
	 * Test singleton instance.
	 */
	public function test_singleton() {
		$instance1 = Security_Log::get_instance();
		$instance2 = Security_Log::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}
}
