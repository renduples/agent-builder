<?php
/**
 * Integration Tests for Agent Lifecycle
 *
 * Tests agent installation, activation, deactivation, and deletion
 * using the Agentic_Agent_Registry class.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

/**
 * Test case for complete agent lifecycle.
 *
 * Uses Agentic_Agent_Registry (global namespace, singleton pattern).
 */
class Test_Agent_Lifecycle extends TestCase {

	/**
	 * Agent registry instance.
	 *
	 * @var \Agentic_Agent_Registry
	 */
	private $registry;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->registry = \Agentic_Agent_Registry::get_instance();

		// Reset active agents option.
		delete_option( \Agentic_Agent_Registry::ACTIVE_AGENTS_OPTION );
	}

	/**
	 * Test registry is a singleton.
	 */
	public function test_registry_singleton() {
		$instance1 = \Agentic_Agent_Registry::get_instance();
		$instance2 = \Agentic_Agent_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test get_installed_agents returns an array.
	 */
	public function test_get_installed_agents() {
		$agents = $this->registry->get_installed_agents();
		$this->assertIsArray( $agents );
	}

	/**
	 * Test discover and activate a test agent.
	 */
	public function test_agent_activation() {
		$agent_id = 'test-lifecycle-agent';
		$this->create_test_agent( $agent_id );

		// Force refresh to pick up the new agent.
		$agents = $this->registry->get_installed_agents( true );
		$this->assertArrayHasKey( $agent_id, $agents );

		// Activate the agent.
		$result = $this->registry->activate_agent( $agent_id );
		$this->assertTrue( $result );

		// Verify agent is active.
		$this->assertTrue( $this->registry->is_agent_active( $agent_id ) );
		$this->assertContains( $agent_id, $this->registry->get_active_agents() );
	}

	/**
	 * Test activating an already active agent returns WP_Error.
	 */
	public function test_activate_already_active() {
		$agent_id = 'test-already-active';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );
		$this->registry->activate_agent( $agent_id );

		$result = $this->registry->activate_agent( $agent_id );
		$this->assertWPError( $result );
		$this->assertEquals( 'already_active', $result->get_error_code() );
	}

	/**
	 * Test activating a nonexistent agent returns WP_Error.
	 */
	public function test_activate_nonexistent_agent() {
		$result = $this->registry->activate_agent( 'does-not-exist' );
		$this->assertWPError( $result );
		$this->assertEquals( 'agent_not_found', $result->get_error_code() );
	}

	/**
	 * Test agent deactivation.
	 */
	public function test_agent_deactivation() {
		$agent_id = 'test-deactivate-agent';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );
		$this->registry->activate_agent( $agent_id );

		// Deactivate the agent.
		$result = $this->registry->deactivate_agent( $agent_id );
		$this->assertTrue( $result );

		// Verify agent is not active.
		$this->assertFalse( $this->registry->is_agent_active( $agent_id ) );
		$this->assertNotContains( $agent_id, $this->registry->get_active_agents() );
	}

	/**
	 * Test deactivating an inactive agent returns WP_Error.
	 */
	public function test_deactivate_inactive_agent() {
		$result = $this->registry->deactivate_agent( 'not-active-agent' );
		$this->assertWPError( $result );
		$this->assertEquals( 'not_active', $result->get_error_code() );
	}

	/**
	 * Test agent deletion.
	 */
	public function test_agent_deletion() {
		$agent_id   = 'test-delete-agent';
		$agent_file = $this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );

		// Delete the agent.
		$result = $this->registry->delete_agent( $agent_id );
		$this->assertTrue( $result );

		// Verify agent file is gone.
		$this->assertFileDoesNotExist( $agent_file );

		// Verify agent is no longer in the registry.
		$agents = $this->registry->get_installed_agents( true );
		$this->assertArrayNotHasKey( $agent_id, $agents );
	}

	/**
	 * Test deleting a nonexistent agent returns WP_Error.
	 */
	public function test_delete_nonexistent_agent() {
		$result = $this->registry->delete_agent( 'does-not-exist' );
		$this->assertWPError( $result );
		$this->assertEquals( 'not_installed', $result->get_error_code() );
	}

	/**
	 * Test deleting an active agent deactivates it first.
	 */
	public function test_delete_active_agent_deactivates_first() {
		$agent_id = 'test-delete-active';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );
		$this->registry->activate_agent( $agent_id );

		$this->assertTrue( $this->registry->is_agent_active( $agent_id ) );

		$result = $this->registry->delete_agent( $agent_id );
		$this->assertTrue( $result );

		$this->assertFalse( $this->registry->is_agent_active( $agent_id ) );
	}

	/**
	 * Test is_agent_installed.
	 */
	public function test_is_agent_installed() {
		$agent_id = 'test-installed-check';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );

		$this->assertTrue( $this->registry->is_agent_installed( $agent_id ) );
		$this->assertFalse( $this->registry->is_agent_installed( 'nonexistent' ) );
	}

	/**
	 * Test multiple agents can be activated simultaneously.
	 */
	public function test_multiple_agents_activation() {
		$agent_ids = array( 'multi-agent-1', 'multi-agent-2', 'multi-agent-3' );

		foreach ( $agent_ids as $agent_id ) {
			$this->create_test_agent( $agent_id );
		}

		$this->registry->get_installed_agents( true );

		foreach ( $agent_ids as $agent_id ) {
			$result = $this->registry->activate_agent( $agent_id );
			$this->assertTrue( $result, "Failed to activate: {$agent_id}" );
		}

		$active = $this->registry->get_active_agents();
		foreach ( $agent_ids as $agent_id ) {
			$this->assertContains( $agent_id, $active );
		}
	}

	/**
	 * Test agent metadata is correctly parsed.
	 */
	public function test_agent_metadata() {
		$agent_id = 'test-metadata-agent';
		$this->create_test_agent( $agent_id );
		$agents = $this->registry->get_installed_agents( true );

		$this->assertArrayHasKey( $agent_id, $agents );
		$agent = $agents[ $agent_id ];

		$this->assertEquals( 'Test Agent', $agent['name'] );
		$this->assertEquals( '1.0.0', $agent['version'] );
		$this->assertNotEmpty( $agent['description'] );
	}

	/**
	 * Test invalid agent file is not registered.
	 */
	public function test_invalid_agent_not_registered() {
		$agent_id  = 'invalid-agent';
		$agent_dir = WP_CONTENT_DIR . '/agents/' . $agent_id;

		if ( ! file_exists( $agent_dir ) ) {
			mkdir( $agent_dir, 0755, true );
		}

		// Create agent file without required headers.
		$agent_file = $agent_dir . '/agent.php';
		file_put_contents( $agent_file, '<?php // No headers' );

		$agents = $this->registry->get_installed_agents( true );
		$this->assertArrayNotHasKey( $agent_id, $agents );

		// Cleanup.
		unlink( $agent_file );
		rmdir( $agent_dir );
	}

	/**
	 * Test active agent state persists across registry instances.
	 */
	public function test_activation_persists() {
		$agent_id = 'test-persist-agent';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );
		$this->registry->activate_agent( $agent_id );

		// Get active agents directly from database.
		$active = get_option( \Agentic_Agent_Registry::ACTIVE_AGENTS_OPTION, array() );
		$this->assertContains( $agent_id, $active );
	}

	/**
	 * Test get_agents_dir returns a path.
	 */
	public function test_get_agents_dir() {
		$dir = $this->registry->get_agents_dir();
		$this->assertNotEmpty( $dir );
		$this->assertIsString( $dir );
	}

	/**
	 * Test get_library_dir returns a path.
	 */
	public function test_get_library_dir() {
		$dir = $this->registry->get_library_dir();
		$this->assertNotEmpty( $dir );
		$this->assertIsString( $dir );
	}

	/**
	 * Test activation hook fires.
	 */
	public function test_activation_hook_fires() {
		$agent_id = 'test-hooks-agent';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );

		$hook_fired = false;
		add_action(
			'agentic_agent_activated',
			function ( $slug ) use ( $agent_id, &$hook_fired ) {
				if ( $slug === $agent_id ) {
					$hook_fired = true;
				}
			}
		);

		$this->registry->activate_agent( $agent_id );
		$this->assertTrue( $hook_fired, 'agentic_agent_activated hook should fire' );
	}

	/**
	 * Test deactivation hook fires.
	 */
	public function test_deactivation_hook_fires() {
		$agent_id = 'test-deactivation-hooks';
		$this->create_test_agent( $agent_id );
		$this->registry->get_installed_agents( true );
		$this->registry->activate_agent( $agent_id );

		$hook_fired = false;
		add_action(
			'agentic_agent_deactivated',
			function ( $slug ) use ( $agent_id, &$hook_fired ) {
				if ( $slug === $agent_id ) {
					$hook_fired = true;
				}
			}
		);

		$this->registry->deactivate_agent( $agent_id );
		$this->assertTrue( $hook_fired, 'agentic_agent_deactivated hook should fire' );
	}

	/**
	 * Cleanup after tests.
	 */
	public function tearDown(): void {
		$agents = array(
			'test-lifecycle-agent',
			'test-already-active',
			'test-deactivate-agent',
			'test-delete-agent',
			'test-delete-active',
			'test-installed-check',
			'multi-agent-1',
			'multi-agent-2',
			'multi-agent-3',
			'test-metadata-agent',
			'test-persist-agent',
			'test-hooks-agent',
			'test-deactivation-hooks',
		);

		foreach ( $agents as $agent_id ) {
			$this->delete_test_agent( $agent_id );
		}

		delete_option( \Agentic_Agent_Registry::ACTIVE_AGENTS_OPTION );
		parent::tearDown();
	}
}
