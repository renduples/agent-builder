<?php
/**
 * Tests for the License_Client class
 *
 * @package    Agent_Builder
 * @subpackage Tests
 */

declare(strict_types=1);

namespace Agentic\Tests;

use Agentic\License_Client;
use WP_UnitTestCase;

/**
 * License Client Test Case
 */
class Test_License_Client extends WP_UnitTestCase {

	/**
	 * Reset license state between tests.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( License_Client::OPTION_LICENSE_KEY );
		delete_option( License_Client::OPTION_LICENSE_DATA );

		// Reset singleton for clean state.
		$reflection = new \ReflectionClass( License_Client::class );
		$instance = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	public function tearDown(): void {
		// Reset singleton.
		$reflection = new \ReflectionClass( License_Client::class );
		$instance = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		parent::tearDown();
	}

	// =========================================================================
	// Status Tests
	// =========================================================================

	public function test_no_license_returns_free_status(): void {
		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertEquals( 'free', $status['status'] );
		$this->assertFalse( $status['is_valid'] );
		$this->assertEquals( 'free', $status['type'] );
	}

	public function test_is_premium_false_without_license(): void {
		$client = License_Client::get_instance();
		$this->assertFalse( $client->is_premium() );
	}

	public function test_active_license_returns_valid(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
			'activations_used'  => 3,
			'activations_limit' => 25,
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertEquals( 'active', $status['status'] );
		$this->assertTrue( $status['is_valid'] );
		$this->assertEquals( 'agency', $status['type'] );
		$this->assertTrue( $client->is_premium() );
	}

	public function test_expired_license_within_grace_period(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'expired',
			'type'         => 'personal',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ), // Expired 3 days ago.
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertEquals( 'grace_period', $status['status'] );
		$this->assertTrue( $status['is_valid'] );
		$this->assertGreaterThan( 0, $status['grace_days_left'] );
	}

	public function test_expired_license_past_grace_period(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'expired',
			'type'         => 'personal',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ), // Expired 30 days ago.
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertEquals( 'expired', $status['status'] );
		$this->assertFalse( $status['is_valid'] );
		$this->assertFalse( $client->is_premium() );
	}

	public function test_revoked_license_is_not_valid(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'revoked',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$this->assertFalse( $client->is_premium() );
	}

	public function test_license_key_with_no_cached_data_returns_pending(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		// No OPTION_LICENSE_DATA set.

		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertEquals( 'pending', $status['status'] );
		$this->assertFalse( $status['is_valid'] );
	}

	public function test_stale_cache_still_valid_when_active(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => date( 'Y-m-d H:i:s', strtotime( '-4 days' ) ), // >72h ago.
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		// Fail-open: still valid but marked as stale.
		$this->assertTrue( $status['is_valid'] );
		$this->assertTrue( $status['cache_stale'] );
	}

	// =========================================================================
	// Key Masking Tests
	// =========================================================================

	public function test_mask_key_format(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-AHRG-SP5H-5GPD-JG2R' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		$this->assertStringStartsWith( 'AGNT-', $status['license_key'] );
		$this->assertStringEndsWith( '-JG2R', $status['license_key'] );
		$this->assertStringContainsString( '****', $status['license_key'] );
		$this->assertStringNotContainsString( 'AHRG', $status['license_key'] );
	}

	// =========================================================================
	// Feature Degradation Tests
	// =========================================================================

	public function test_bundled_agents_always_run(): void {
		$client = License_Client::get_instance();

		// Bundled agents should always be allowed, regardless of license.
		$this->assertTrue( $client->can_agent_run( 'content-builder' ) );
		$this->assertTrue( $client->can_agent_run( 'theme-builder' ) );
		$this->assertTrue( $client->can_agent_run( 'onboarding-agent' ) );
	}

	public function test_premium_agent_blocked_without_license(): void {
		// Store a premium agent license (simulating a previously installed agent).
		update_option( 'agentic_licenses', array(
			'premium-agent' => array(
				'license_key' => 'AGNT-TEST-XXXX-YYYY-ZZZZ',
				'status'      => 'active',
				'expires_at'  => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			),
		) );
		// No plugin license set.

		$client = License_Client::get_instance();
		$this->assertFalse( $client->can_agent_run( 'premium-agent' ) );
	}

	public function test_premium_agent_allowed_with_valid_license(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );
		update_option( 'agentic_licenses', array(
			'premium-agent' => array(
				'license_key' => 'AGNT-TEST-XXXX-YYYY-ZZZZ',
				'status'      => 'active',
				'expires_at'  => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			),
		) );

		$client = License_Client::get_instance();
		$this->assertTrue( $client->can_agent_run( 'premium-agent' ) );
	}

	public function test_unknown_agent_without_license_record_allowed(): void {
		// Agents without a license record (e.g. free agents) should run.
		$client = License_Client::get_instance();
		$this->assertTrue( $client->can_agent_run( 'some-free-agent' ) );
	}

	// =========================================================================
	// Update Gating Tests
	// =========================================================================

	public function test_update_gating_removes_update_without_license(): void {
		$client = License_Client::get_instance();

		$transient = new \stdClass();
		$transient->response = array();
		$transient->no_update = array();

		// Simulate WordPress finding an update for our plugin.
		$update = new \stdClass();
		$update->slug = 'agent-builder';
		$update->new_version = '2.0.0';
		$transient->response[ AGENTIC_PLUGIN_BASENAME ] = $update;

		$result = $client->gate_plugin_updates( $transient );

		// Update should be removed (no license).
		$this->assertArrayNotHasKey( AGENTIC_PLUGIN_BASENAME, $result->response );
		$this->assertArrayHasKey( AGENTIC_PLUGIN_BASENAME, $result->no_update );
	}

	public function test_update_gating_allows_update_with_valid_license(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();

		$transient = new \stdClass();
		$transient->response = array();
		$transient->no_update = array();

		$update = new \stdClass();
		$update->slug = 'agent-builder';
		$update->new_version = '2.0.0';
		$transient->response[ AGENTIC_PLUGIN_BASENAME ] = $update;

		$result = $client->gate_plugin_updates( $transient );

		// Update should remain.
		$this->assertArrayHasKey( AGENTIC_PLUGIN_BASENAME, $result->response );
	}

	// =========================================================================
	// Cron Scheduling Tests
	// =========================================================================

	public function test_revalidation_cron_is_scheduled(): void {
		$client = License_Client::get_instance();
		$client->schedule_revalidation();

		$this->assertNotFalse( wp_next_scheduled( License_Client::CRON_HOOK ) );
	}

	public function test_cron_cleared_on_deactivation(): void {
		$client = License_Client::get_instance();
		$client->schedule_revalidation();

		$this->assertNotFalse( wp_next_scheduled( License_Client::CRON_HOOK ) );

		$client->on_deactivation();

		$this->assertFalse( wp_next_scheduled( License_Client::CRON_HOOK ) );
	}

	// =========================================================================
	// get_type Tests
	// =========================================================================

	public function test_get_type_returns_free_without_license(): void {
		$client = License_Client::get_instance();
		$this->assertEquals( 'free', $client->get_type() );
	}

	public function test_get_type_returns_correct_type(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'agency',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$this->assertEquals( 'agency', $client->get_type() );
	}

	// =========================================================================
	// Singleton Tests
	// =========================================================================

	public function test_singleton_returns_same_instance(): void {
		$a = License_Client::get_instance();
		$b = License_Client::get_instance();
		$this->assertSame( $a, $b );
	}

	// =========================================================================
	// Edge Cases
	// =========================================================================

	public function test_status_cached_for_request(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active',
			'type'         => 'personal',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '+6 months' ) ),
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$status1 = $client->get_status();

		// Change the option — should still return cached.
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status' => 'revoked',
		) );

		$status2 = $client->get_status();
		$this->assertEquals( $status1['status'], $status2['status'] );
	}

	public function test_active_status_with_past_expiry_becomes_expired(): void {
		update_option( License_Client::OPTION_LICENSE_KEY, 'AGNT-TEST-XXXX-YYYY-ZZZZ' );
		update_option( License_Client::OPTION_LICENSE_DATA, array(
			'status'       => 'active', // Server said active but...
			'type'         => 'personal',
			'expires_at'   => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ), // ...it's past expiry.
			'validated_at' => current_time( 'mysql' ),
		) );

		$client = License_Client::get_instance();
		$status = $client->get_status();

		// Should detect the expiry even though status says 'active'.
		$this->assertEquals( 'expired', $status['status'] );
		$this->assertFalse( $status['is_valid'] );
	}

	public function test_gate_plugin_updates_handles_empty_transient(): void {
		$client = License_Client::get_instance();

		$this->assertNull( $client->gate_plugin_updates( null ) );
		$this->assertFalse( $client->gate_plugin_updates( false ) );

		$empty = new \stdClass();
		$result = $client->gate_plugin_updates( $empty );
		$this->assertIsObject( $result );
	}
}
