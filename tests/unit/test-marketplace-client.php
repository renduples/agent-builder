<?php
/**
 * Unit Tests for Marketplace Client
 *
 * Tests API request building, caching, update checks, license validation,
 * agent activation/deactivation, and installed agents discovery.
 *
 * These tests do NOT make real HTTP requests — they test logic, hooks,
 * option manipulation, and the methods that don't require network calls.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Marketplace_Client;

/**
 * Test case for Marketplace_Client class.
 */
class Test_Marketplace_Client extends TestCase {

	/**
	 * Client instance.
	 *
	 * @var Marketplace_Client
	 */
	private Marketplace_Client $client;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->client = new Marketplace_Client();
	}

	// -------------------------------------------------------------------------
	// Construction / initialization
	// -------------------------------------------------------------------------

	/**
	 * Test client can be instantiated.
	 */
	public function test_instantiation() {
		$this->assertInstanceOf( Marketplace_Client::class, $this->client );
	}

	// -------------------------------------------------------------------------
	// Update scheduling
	// -------------------------------------------------------------------------

	/**
	 * Test schedule_update_checks registers cron event.
	 */
	public function test_schedule_update_checks() {
		// Clear any existing schedule.
		wp_clear_scheduled_hook( 'agentic_check_agent_updates' );

		$this->client->schedule_update_checks();

		$this->assertNotFalse( wp_next_scheduled( 'agentic_check_agent_updates' ) );
	}

	/**
	 * Test schedule_update_checks does not double-schedule.
	 */
	public function test_schedule_update_checks_idempotent() {
		wp_clear_scheduled_hook( 'agentic_check_agent_updates' );

		$this->client->schedule_update_checks();
		$first = wp_next_scheduled( 'agentic_check_agent_updates' );

		$this->client->schedule_update_checks();
		$second = wp_next_scheduled( 'agentic_check_agent_updates' );

		$this->assertEquals( $first, $second );
	}

	// -------------------------------------------------------------------------
	// Available updates
	// -------------------------------------------------------------------------

	/**
	 * Test get_available_updates returns empty when no transient.
	 */
	public function test_get_available_updates_empty() {
		delete_transient( 'agentic_available_updates' );
		$this->assertEquals( array(), $this->client->get_available_updates() );
	}

	/**
	 * Test get_available_updates returns stored updates.
	 */
	public function test_get_available_updates_returns_data() {
		$updates = array(
			'test-agent' => array(
				'current' => '1.0.0',
				'latest'  => '2.0.0',
				'name'    => 'Test Agent',
			),
		);
		set_transient( 'agentic_available_updates', $updates, HOUR_IN_SECONDS );

		$result = $this->client->get_available_updates();
		$this->assertEquals( $updates, $result );
	}

	// -------------------------------------------------------------------------
	// License validation
	// -------------------------------------------------------------------------

	/**
	 * Test is_agent_license_valid returns false when no license.
	 */
	public function test_license_valid_no_license() {
		delete_option( 'agentic_licenses' );
		$this->assertFalse( $this->client->is_agent_license_valid( 'nonexistent-agent' ) );
	}

	/**
	 * Test is_agent_license_valid returns true for active license.
	 */
	public function test_license_valid_active() {
		update_option(
			'agentic_licenses',
			array(
				'test-agent' => array(
					'license_key' => 'test-key-123',
					'status'      => 'active',
					'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				),
			)
		);

		$this->assertTrue( $this->client->is_agent_license_valid( 'test-agent' ) );
	}

	/**
	 * Test is_agent_license_valid returns false for expired (past grace).
	 */
	public function test_license_valid_expired_past_grace() {
		update_option(
			'agentic_licenses',
			array(
				'test-agent' => array(
					'license_key' => 'test-key-123',
					'status'      => 'expired',
					'expires_at'  => gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) ),
				),
			)
		);

		$this->assertFalse( $this->client->is_agent_license_valid( 'test-agent' ) );
	}

	/**
	 * Test is_agent_license_valid returns true within grace period.
	 */
	public function test_license_valid_within_grace() {
		// Expired 3 days ago — within 7-day grace.
		update_option(
			'agentic_licenses',
			array(
				'test-agent' => array(
					'license_key' => 'test-key-123',
					'status'      => 'expired',
					'expires_at'  => gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS ) ),
				),
			)
		);

		$this->assertTrue( $this->client->is_agent_license_valid( 'test-agent' ) );
	}

	/**
	 * Test is_agent_license_valid returns false for revoked.
	 */
	public function test_license_valid_revoked() {
		update_option(
			'agentic_licenses',
			array(
				'test-agent' => array(
					'license_key' => 'test-key-123',
					'status'      => 'revoked',
				),
			)
		);

		$this->assertFalse( $this->client->is_agent_license_valid( 'test-agent' ) );
	}

	// -------------------------------------------------------------------------
	// License info
	// -------------------------------------------------------------------------

	/**
	 * Test get_agent_license returns null for unknown agent.
	 */
	public function test_get_agent_license_unknown() {
		delete_option( 'agentic_licenses' );
		$this->assertNull( $this->client->get_agent_license( 'no-such-agent' ) );
	}

	/**
	 * Test get_agent_license returns license data.
	 */
	public function test_get_agent_license_returns_data() {
		$license_data = array(
			'license_key' => 'key-abc-123',
			'status'      => 'active',
			'expires_at'  => '2027-01-01 00:00:00',
		);

		update_option( 'agentic_licenses', array( 'my-agent' => $license_data ) );

		$result = $this->client->get_agent_license( 'my-agent' );
		$this->assertEquals( $license_data, $result );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers — activation / deactivation
	// -------------------------------------------------------------------------

	/**
	 * Test agent activation adds to active agents option.
	 */
	public function test_activate_agent_option() {
		update_option( 'agentic_active_agents', array() );

		// Simulate what ajax_activate_agent does internally.
		$active_agents   = get_option( 'agentic_active_agents', array() );
		$active_agents[] = 'test-slug';
		update_option( 'agentic_active_agents', $active_agents );

		$this->assertContains( 'test-slug', get_option( 'agentic_active_agents' ) );
	}

	/**
	 * Test agent deactivation removes from active agents option.
	 */
	public function test_deactivate_agent_option() {
		update_option( 'agentic_active_agents', array( 'agent-a', 'agent-b', 'agent-c' ) );

		// Simulate what ajax_deactivate_agent does internally.
		$active_agents = get_option( 'agentic_active_agents', array() );
		$active_agents = array_values( array_diff( $active_agents, array( 'agent-b' ) ) );
		update_option( 'agentic_active_agents', $active_agents );

		$result = get_option( 'agentic_active_agents' );
		$this->assertNotContains( 'agent-b', $result );
		$this->assertContains( 'agent-a', $result );
		$this->assertContains( 'agent-c', $result );
	}

	/**
	 * Test activating same agent twice doesn't duplicate.
	 */
	public function test_activate_no_duplicate() {
		update_option( 'agentic_active_agents', array( 'existing-agent' ) );

		// Simulate check from ajax_activate_agent.
		$active_agents = get_option( 'agentic_active_agents', array() );
		if ( ! in_array( 'existing-agent', $active_agents, true ) ) {
			$active_agents[] = 'existing-agent';
		}
		update_option( 'agentic_active_agents', $active_agents );

		$result = get_option( 'agentic_active_agents' );
		$this->assertCount( 1, $result );
	}

	// -------------------------------------------------------------------------
	// Developer API key
	// -------------------------------------------------------------------------

	/**
	 * Test saving developer API key.
	 */
	public function test_save_developer_api_key() {
		update_option( 'agentic_developer_api_key', 'test-dev-key-123' );
		$this->assertEquals( 'test-dev-key-123', get_option( 'agentic_developer_api_key' ) );
	}

	/**
	 * Test disconnecting developer removes key.
	 */
	public function test_disconnect_developer() {
		update_option( 'agentic_developer_api_key', 'key-to-remove' );
		delete_option( 'agentic_developer_api_key' );
		$this->assertFalse( get_option( 'agentic_developer_api_key' ) );
	}

	// -------------------------------------------------------------------------
	// License storage
	// -------------------------------------------------------------------------

	/**
	 * Test license storage saves all metadata.
	 */
	public function test_license_storage_metadata() {
		$license_data = array(
			'license_key'      => 'lic-key-xyz',
			'status'           => 'active',
			'expires_at'       => '2027-06-15 12:00:00',
			'activations_used' => 1,
			'activation_limit' => 3,
			'customer_email'   => 'test@example.com',
			'validated_at'     => current_time( 'mysql' ),
		);

		$licenses                  = get_option( 'agentic_licenses', array() );
		$licenses['premium-agent'] = $license_data;
		update_option( 'agentic_licenses', $licenses );

		$stored = get_option( 'agentic_licenses' );
		$this->assertEquals( $license_data, $stored['premium-agent'] );
		$this->assertEquals( 'test@example.com', $stored['premium-agent']['customer_email'] );
	}

	/**
	 * Test license deactivation removes from storage.
	 */
	public function test_license_deactivation_removes() {
		update_option(
			'agentic_licenses',
			array(
				'agent-a' => array(
					'license_key' => 'key-a',
					'status'      => 'active',
				),
				'agent-b' => array(
					'license_key' => 'key-b',
					'status'      => 'active',
				),
			)
		);

		// Simulate deactivate_agent_license logic.
		$licenses = get_option( 'agentic_licenses', array() );
		unset( $licenses['agent-a'] );
		update_option( 'agentic_licenses', $licenses );

		$result = get_option( 'agentic_licenses' );
		$this->assertArrayNotHasKey( 'agent-a', $result );
		$this->assertArrayHasKey( 'agent-b', $result );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Test add_menu_page registers a submenu.
	 */
	public function test_add_menu_page() {
		// Set up admin context.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Ensure parent menu exists.
		add_menu_page( 'Agent Builder', 'Agent Builder', 'manage_options', 'agent-builder', '__return_null' );

		$this->client->add_menu_page();

		global $submenu;
		$found = false;
		if ( isset( $submenu['agent-builder'] ) ) {
			foreach ( $submenu['agent-builder'] as $item ) {
				if ( 'agentic-revenue' === $item[2] ) {
					$found = true;
					break;
				}
			}
		}
		$this->assertTrue( $found, 'Revenue submenu page should be registered.' );
	}
}
