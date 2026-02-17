<?php
/**
 * Unit Tests for Phase 1 improvements
 *
 * Tests agent mode enum sanitizer and audit log retention cron.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Audit_Log;
use Agentic\Plugin;

/**
 * Test case for Phase 1 features.
 */
class Test_Plugin_Phase1 extends TestCase {

	// -------------------------------------------------------------------------
	// Agent mode enum sanitizer
	// -------------------------------------------------------------------------

	/**
	 * Test valid agent mode 'supervised' passes through.
	 */
	public function test_sanitize_agent_mode_supervised(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( 'supervised' ) );
	}

	/**
	 * Test valid agent mode 'autonomous' passes through.
	 */
	public function test_sanitize_agent_mode_autonomous(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'autonomous', $plugin->sanitize_agent_mode( 'autonomous' ) );
	}

	/**
	 * Test valid agent mode 'restricted' passes through.
	 */
	public function test_sanitize_agent_mode_restricted(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'restricted', $plugin->sanitize_agent_mode( 'restricted' ) );
	}

	/**
	 * Test invalid agent mode falls back to 'supervised'.
	 */
	public function test_sanitize_agent_mode_invalid_falls_back(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( 'hacker_mode' ) );
	}

	/**
	 * Test empty string falls back to 'supervised'.
	 */
	public function test_sanitize_agent_mode_empty(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( '' ) );
	}

	/**
	 * Test HTML injection is sanitized and falls back.
	 */
	public function test_sanitize_agent_mode_html_injection(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( '<script>alert(1)</script>' ) );
	}

	/**
	 * Test integer input is cast and falls back.
	 */
	public function test_sanitize_agent_mode_integer(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( 42 ) );
	}

	/**
	 * Test null input falls back to 'supervised'.
	 */
	public function test_sanitize_agent_mode_null(): void {
		$plugin = Plugin::get_instance();

		$this->assertSame( 'supervised', $plugin->sanitize_agent_mode( null ) );
	}

	// -------------------------------------------------------------------------
	// Audit log retention cleanup
	// -------------------------------------------------------------------------

	/**
	 * Test cleanup_expired uses filterable retention days.
	 */
	public function test_cleanup_expired_uses_default_90_days(): void {
		global $wpdb;

		$audit = new Audit_Log();
		$table = $wpdb->prefix . 'agentic_audit_log';

		// Insert an old entry (100 days ago).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test setup.
		$wpdb->insert(
			$table,
			array(
				'agent_id'   => 'test_retention',
				'action'     => 'test_action',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
			)
		);
		$old_id = $wpdb->insert_id;

		// Insert a recent entry (10 days ago).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test setup.
		$wpdb->insert(
			$table,
			array(
				'agent_id'   => 'test_retention',
				'action'     => 'test_action',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
			)
		);
		$recent_id = $wpdb->insert_id;

		$deleted = $audit->cleanup_expired();

		// Old entry should be deleted, recent should remain.
		$this->assertGreaterThanOrEqual( 1, $deleted );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion.
		$remaining = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $old_id )
		);
		$this->assertEquals( 0, $remaining, 'Old entry should be deleted' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion.
		$kept = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $recent_id )
		);
		$this->assertEquals( 1, $kept, 'Recent entry should be kept' );

		// Clean up.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test cleanup.
		$wpdb->delete( $table, array( 'agent_id' => 'test_retention' ) );
	}

	/**
	 * Test cleanup_expired respects filter to change retention days.
	 */
	public function test_cleanup_expired_respects_filter(): void {
		global $wpdb;

		$audit = new Audit_Log();
		$table = $wpdb->prefix . 'agentic_audit_log';

		// Insert an entry 40 days ago.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test setup.
		$wpdb->insert(
			$table,
			array(
				'agent_id'   => 'test_retention',
				'action'     => 'test_filter',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-40 days' ) ),
			)
		);
		$entry_id = $wpdb->insert_id;

		// With default 90 days, 40-day-old entry should survive.
		$audit->cleanup_expired();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion.
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $entry_id )
		);
		$this->assertEquals( 1, $exists, '40-day entry should survive with 90-day retention' );

		// Now filter to 30 days — 40-day-old entry should be deleted.
		$filter = function () {
			return 30;
		};
		add_filter( 'agentic_audit_retention_days', $filter );

		$audit->cleanup_expired();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion.
		$gone = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $entry_id )
		);
		$this->assertEquals( 0, $gone, '40-day entry should be deleted with 30-day retention' );

		remove_filter( 'agentic_audit_retention_days', $filter );

		// Clean up.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test cleanup.
		$wpdb->delete( $table, array( 'agent_id' => 'test_retention' ) );
	}

	/**
	 * Test cleanup_expired with invalid filter value falls back to 90 days.
	 */
	public function test_cleanup_expired_invalid_filter_falls_back(): void {
		global $wpdb;

		$audit = new Audit_Log();
		$table = $wpdb->prefix . 'agentic_audit_log';

		// Insert an entry 50 days ago.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test setup.
		$wpdb->insert(
			$table,
			array(
				'agent_id'   => 'test_retention',
				'action'     => 'test_invalid_filter',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-50 days' ) ),
			)
		);
		$entry_id = $wpdb->insert_id;

		// Filter returns 0 — should fall back to 90 days.
		$filter = function () {
			return 0;
		};
		add_filter( 'agentic_audit_retention_days', $filter );

		$audit->cleanup_expired();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion.
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $entry_id )
		);
		$this->assertEquals( 1, $exists, '50-day entry should survive when filter returns invalid 0' );

		remove_filter( 'agentic_audit_retention_days', $filter );

		// Clean up.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test cleanup.
		$wpdb->delete( $table, array( 'agent_id' => 'test_retention' ) );
	}

	/**
	 * Test that the cron event is scheduled on activation.
	 */
	public function test_audit_cleanup_cron_scheduled_on_activate(): void {
		// Clear any existing schedule.
		wp_clear_scheduled_hook( 'agentic_cleanup_audit_log' );

		$this->assertFalse( wp_next_scheduled( 'agentic_cleanup_audit_log' ) );

		// Activate the plugin.
		$plugin = Plugin::get_instance();
		$plugin->activate();

		$this->assertNotFalse( wp_next_scheduled( 'agentic_cleanup_audit_log' ) );
	}

	/**
	 * Test that the cron event is cleared on deactivation.
	 */
	public function test_audit_cleanup_cron_cleared_on_deactivate(): void {
		// Schedule it first.
		if ( ! wp_next_scheduled( 'agentic_cleanup_audit_log' ) ) {
			wp_schedule_event( time(), 'daily', 'agentic_cleanup_audit_log' );
		}

		$this->assertNotFalse( wp_next_scheduled( 'agentic_cleanup_audit_log' ) );

		// Deactivate.
		$plugin = Plugin::get_instance();
		$plugin->deactivate();

		$this->assertFalse( wp_next_scheduled( 'agentic_cleanup_audit_log' ) );
	}

	// -------------------------------------------------------------------------
	// Hook splitting (Phase 3): admin-only vs always-loaded
	// -------------------------------------------------------------------------

	/**
	 * Test that universal hooks are always registered.
	 *
	 * These hooks must be present on every request (frontend, REST, cron).
	 */
	public function test_universal_hooks_registered(): void {
		$this->assertNotFalse( has_action( 'init', array( Plugin::get_instance(), 'init' ) ) );
		$this->assertNotFalse( has_action( 'rest_api_init', array( Plugin::get_instance(), 'register_rest_routes' ) ) );
		$this->assertNotFalse( has_filter( 'cron_schedules', array( Plugin::get_instance(), 'add_cron_schedules' ) ) );
		$this->assertNotFalse( has_action( 'agentic_cleanup_audit_log', array( Plugin::get_instance(), 'run_audit_cleanup' ) ) );
		$this->assertNotFalse( has_filter( 'the_content', array( Plugin::get_instance(), 'render_chat_interface' ) ) );
	}

	/**
	 * Test that admin hooks are NOT registered in non-admin context.
	 *
	 * The WP test suite runs in a non-admin context (is_admin() === false),
	 * so admin-only hooks should be absent — proving the split works.
	 */
	public function test_admin_hooks_not_registered_on_frontend(): void {
		// WP test suite runs with is_admin() === false.
		$this->assertFalse( is_admin(), 'Test suite should run in non-admin context' );

		$plugin = Plugin::get_instance();
		$this->assertFalse( has_action( 'admin_init', array( $plugin, 'admin_init' ) ) );
		$this->assertFalse( has_action( 'admin_menu', array( $plugin, 'admin_menu' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_agentic_toggle_tool', array( $plugin, 'ajax_toggle_tool' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_agentic_run_task', array( $plugin, 'ajax_run_task' ) ) );
	}

	/**
	 * Test that init_admin_hooks is a separate callable method.
	 */
	public function test_init_admin_hooks_method_exists(): void {
		$this->assertTrue(
			method_exists( Plugin::class, 'init_admin_hooks' ),
			'Plugin should have init_admin_hooks method for admin-only hook registration'
		);
	}
}
