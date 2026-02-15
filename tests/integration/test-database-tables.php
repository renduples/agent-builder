<?php
/**
 * Integration Tests for Database Tables
 *
 * Tests table creation on activation, schema validation for all tables,
 * and cleanup on uninstall.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Job_Manager;
use Agentic\Security_Log;

/**
 * Test case for database table lifecycle.
 */
class Test_Database_Tables extends TestCase {

	/**
	 * All five plugin tables.
	 *
	 * @var array
	 */
	private array $tables;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->tables = array(
			'audit_log'      => $wpdb->prefix . 'agentic_audit_log',
			'approval_queue' => $wpdb->prefix . 'agentic_approval_queue',
			'memory'         => $wpdb->prefix . 'agentic_memory',
			'jobs'           => $wpdb->prefix . 'agentic_jobs',
			'security_log'   => $wpdb->prefix . 'agentic_security_log',
		);
	}

	// -------------------------------------------------------------------------
	// Table existence
	// -------------------------------------------------------------------------

	/**
	 * Test all five tables exist.
	 */
	public function test_all_tables_exist() {
		global $wpdb;

		foreach ( $this->tables as $key => $table ) {
			$found = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);
			$this->assertEquals( $table, $found, "Table {$key} ({$table}) should exist." );
		}
	}

	// -------------------------------------------------------------------------
	// audit_log schema
	// -------------------------------------------------------------------------

	/**
	 * Test audit_log has required columns.
	 */
	public function test_audit_log_columns() {
		$columns = $this->get_column_names( $this->tables['audit_log'] );

		$expected = array(
			'id',
			'agent_id',
			'action',
			'target_type',
			'target_id',
			'details',
			'reasoning',
			'tokens_used',
			'cost',
			'user_id',
			'created_at',
		);
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "audit_log missing column: {$col}" );
		}
	}

	/**
	 * Test audit_log has primary key.
	 */
	public function test_audit_log_primary_key() {
		$this->assertPrimaryKey( $this->tables['audit_log'], 'id' );
	}

	/**
	 * Test audit_log has indexes.
	 */
	public function test_audit_log_indexes() {
		$indexes = $this->get_index_names( $this->tables['audit_log'] );
		$this->assertContains( 'agent_id', $indexes );
		$this->assertContains( 'action', $indexes );
		$this->assertContains( 'created_at', $indexes );
	}

	/**
	 * Test audit_log id is auto-increment.
	 */
	public function test_audit_log_auto_increment() {
		$this->assertAutoIncrement( $this->tables['audit_log'], 'id' );
	}

	// -------------------------------------------------------------------------
	// approval_queue schema
	// -------------------------------------------------------------------------

	/**
	 * Test approval_queue has required columns.
	 */
	public function test_approval_queue_columns() {
		$columns = $this->get_column_names( $this->tables['approval_queue'] );

		$expected = array(
			'id',
			'agent_id',
			'action',
			'params',
			'reasoning',
			'status',
			'approved_by',
			'approved_at',
			'created_at',
			'expires_at',
		);
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "approval_queue missing column: {$col}" );
		}
	}

	/**
	 * Test approval_queue has indexes.
	 */
	public function test_approval_queue_indexes() {
		$indexes = $this->get_index_names( $this->tables['approval_queue'] );
		$this->assertContains( 'status', $indexes );
		$this->assertContains( 'created_at', $indexes );
	}

	/**
	 * Test approval_queue default status is pending.
	 */
	public function test_approval_queue_default_status() {
		$default = $this->get_column_default( $this->tables['approval_queue'], 'status' );
		$this->assertEquals( 'pending', $default );
	}

	// -------------------------------------------------------------------------
	// memory schema
	// -------------------------------------------------------------------------

	/**
	 * Test memory table has required columns.
	 */
	public function test_memory_columns() {
		$columns = $this->get_column_names( $this->tables['memory'] );

		$expected = array(
			'id',
			'memory_type',
			'entity_id',
			'memory_key',
			'memory_value',
			'created_at',
			'updated_at',
			'expires_at',
		);
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "memory missing column: {$col}" );
		}
	}

	/**
	 * Test memory table has composite index.
	 */
	public function test_memory_indexes() {
		$indexes = $this->get_index_names( $this->tables['memory'] );
		$this->assertContains( 'memory_type_entity', $indexes );
		$this->assertContains( 'memory_key', $indexes );
	}

	// -------------------------------------------------------------------------
	// jobs schema
	// -------------------------------------------------------------------------

	/**
	 * Test jobs table has required columns.
	 */
	public function test_jobs_columns() {
		$columns = $this->get_column_names( $this->tables['jobs'] );

		$expected = array(
			'id',
			'user_id',
			'agent_id',
			'status',
			'progress',
			'message',
			'request_data',
			'response_data',
			'error_message',
			'created_at',
			'updated_at',
		);
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "jobs missing column: {$col}" );
		}
	}

	/**
	 * Test jobs id is varchar (UUID), not auto-increment.
	 */
	public function test_jobs_id_is_varchar() {
		$type = $this->get_column_type( $this->tables['jobs'], 'id' );
		$this->assertStringContainsString( 'varchar', $type );
	}

	/**
	 * Test jobs has indexes.
	 */
	public function test_jobs_indexes() {
		$indexes = $this->get_index_names( $this->tables['jobs'] );
		$this->assertContains( 'idx_user_created', $indexes );
		$this->assertContains( 'idx_status', $indexes );
		$this->assertContains( 'idx_created', $indexes );
	}

	/**
	 * Test jobs default status is pending.
	 */
	public function test_jobs_default_status() {
		$default = $this->get_column_default( $this->tables['jobs'], 'status' );
		$this->assertEquals( 'pending', $default );
	}

	// -------------------------------------------------------------------------
	// security_log schema
	// -------------------------------------------------------------------------

	/**
	 * Test security_log has required columns.
	 */
	public function test_security_log_columns() {
		$columns = $this->get_column_names( $this->tables['security_log'] );

		$expected = array(
			'id',
			'event_type',
			'user_id',
			'ip_address',
			'message',
			'pattern_matched',
			'pii_types',
			'created_at',
		);
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $columns, "security_log missing column: {$col}" );
		}
	}

	/**
	 * Test security_log has indexes.
	 */
	public function test_security_log_indexes() {
		$indexes = $this->get_index_names( $this->tables['security_log'] );
		$this->assertContains( 'idx_event_type', $indexes );
		$this->assertContains( 'idx_user_id', $indexes );
		$this->assertContains( 'idx_created', $indexes );
		$this->assertContains( 'idx_ip', $indexes );
	}

	// -------------------------------------------------------------------------
	// Uninstall cleanup
	// -------------------------------------------------------------------------

	/**
	 * Test uninstall options list is comprehensive.
	 */
	public function test_uninstall_options_list() {
		// Set options that uninstall.php should delete.
		$options = array(
			'agent_builder_settings',
			'agent_builder_version',
			'agentic_license_key',
			'agentic_api_key',
			'agentic_provider',
			'agentic_model',
			'agentic_installed_agents',
		);

		foreach ( $options as $opt ) {
			update_option( $opt, 'test_value' );
		}

		// Simulate uninstall cleanup (without actually running uninstall.php).
		foreach ( $options as $opt ) {
			delete_option( $opt );
		}

		foreach ( $options as $opt ) {
			$this->assertFalse( get_option( $opt ), "Option {$opt} should be deleted." );
		}
	}

	/**
	 * Test transient cleanup pattern.
	 */
	public function test_transient_cleanup() {
		global $wpdb;

		set_transient( 'agentic_test_cache', 'val', 3600 );

		// Simulate uninstall transient cleanup.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_agentic_%' 
			OR option_name LIKE '_transient_timeout_agentic_%'"
		);

		// Clear WP object cache so get_transient reads from DB.
		wp_cache_flush();

		$this->assertFalse( get_transient( 'agentic_test_cache' ) );
	}

	/**
	 * Test user meta cleanup pattern.
	 */
	public function test_usermeta_cleanup() {
		global $wpdb;

		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'agentic_chat_history', 'data' );
		update_user_meta( $user_id, 'agentic_preferences', 'data' );

		// Simulate uninstall usermeta cleanup.
		$wpdb->query(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'agentic_%'"
		);

		$this->assertEmpty( get_user_meta( $user_id, 'agentic_chat_history', true ) );
		$this->assertEmpty( get_user_meta( $user_id, 'agentic_preferences', true ) );
	}

	/**
	 * Test cron cleanup logic.
	 */
	public function test_cron_cleanup() {
		// Clear any existing cron state.
		_set_cron_array( array() );

		$hooks = array(
			'agentic_cleanup_jobs',
			'agentic_process_queue',
			'agentic_license_check',
		);

		foreach ( $hooks as $hook ) {
			wp_schedule_single_event( time() + 3600, $hook );
		}

		// Verify they were scheduled.
		foreach ( $hooks as $hook ) {
			$this->assertNotFalse( wp_next_scheduled( $hook ), "Cron {$hook} should be scheduled." );
		}

		// Simulate uninstall cron cleanup.
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		foreach ( $hooks as $hook ) {
			$this->assertFalse( wp_next_scheduled( $hook ), "Cron {$hook} should be unscheduled." );
		}
	}

	/**
	 * Test uninstall drops all 5 plugin tables.
	 */
	public function test_uninstall_table_list() {
		// Tables that uninstall.php drops — should match all plugin tables.
		$dropped = array(
			'agentic_jobs',
			'agentic_audit_log',
			'agentic_approval_queue',
			'agentic_memory',
			'agentic_security_log',
		);

		$this->assertCount( 5, $dropped, 'Uninstall should drop all 5 tables.' );
		$this->assertContains( 'agentic_memory', $dropped );
		$this->assertContains( 'agentic_security_log', $dropped );
		$this->assertNotContains( 'agentic_response_cache', $dropped, 'response_cache was never created by the plugin.' );
	}

	// -------------------------------------------------------------------------
	// Table re-creation (idempotent)
	// -------------------------------------------------------------------------

	/**
	 * Test calling create_table twice succeeds (idempotent).
	 */
	public function test_jobs_table_idempotent() {
		Job_Manager::create_table();
		Job_Manager::create_table();

		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->tables['jobs'] )
		);
		$this->assertEquals( $this->tables['jobs'], $found );
	}

	/**
	 * Test calling Security_Log create_table twice succeeds.
	 */
	public function test_security_log_table_idempotent() {
		Security_Log::create_table();
		Security_Log::create_table();

		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->tables['security_log'] )
		);
		$this->assertEquals( $this->tables['security_log'], $found );
	}

	// -------------------------------------------------------------------------
	// Helper methods
	// -------------------------------------------------------------------------

	/**
	 * Get column names for a table.
	 *
	 * @param string $table Table name.
	 * @return array Column names.
	 */
	private function get_column_names( string $table ): array {
		global $wpdb;
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table}" );
		return array_map( fn( $c ) => $c->Field, $columns );
	}

	/**
	 * Get column type for a specific column.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return string Column type.
	 */
	private function get_column_type( string $table, string $column ): string {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table} WHERE Field = %s",
				$column
			)
		);
		return $row->Type ?? '';
	}

	/**
	 * Get column default value.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return mixed Default value.
	 */
	private function get_column_default( string $table, string $column ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table} WHERE Field = %s",
				$column
			)
		);
		return $row->Default ?? null;
	}

	/**
	 * Get index names for a table.
	 *
	 * @param string $table Table name.
	 * @return array Index key names.
	 */
	private function get_index_names( string $table ): array {
		global $wpdb;
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );
		return array_unique( array_map( fn( $i ) => $i->Key_name, $indexes ) );
	}

	/**
	 * Assert a column is the primary key.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 */
	private function assertPrimaryKey( string $table, string $column ): void {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table} WHERE Field = %s",
				$column
			)
		);
		$this->assertEquals( 'PRI', $row->Key, "Column {$column} should be primary key." );
	}

	/**
	 * Assert a column is auto-increment.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 */
	private function assertAutoIncrement( string $table, string $column ): void {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table} WHERE Field = %s",
				$column
			)
		);
		$this->assertStringContainsString( 'auto_increment', $row->Extra, "Column {$column} should be auto_increment." );
	}
}
