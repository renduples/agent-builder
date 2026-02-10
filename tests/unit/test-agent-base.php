<?php
/**
 * Unit Tests for Agent_Base
 *
 * Tests the abstract base class that all agents extend.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Agent_Base;

/**
 * Concrete test implementation of Agent_Base.
 */
class Stub_Agent extends Agent_Base {

	public function get_id(): string {
		return 'stub-agent';
	}

	public function get_name(): string {
		return 'Stub Agent';
	}

	public function get_description(): string {
		return 'A stub agent for testing.';
	}

	public function get_system_prompt(): string {
		return 'You are a stub agent used for testing.';
	}
}

/**
 * Concrete test implementation with custom overrides.
 */
class Custom_Agent extends Agent_Base {

	public function get_id(): string {
		return 'custom-agent';
	}

	public function get_name(): string {
		return 'Custom Agent';
	}

	public function get_description(): string {
		return 'A custom test agent.';
	}

	public function get_system_prompt(): string {
		return 'You are custom.';
	}

	public function get_icon(): string {
		return '🔧';
	}

	public function get_category(): string {
		return 'developer';
	}

	public function get_version(): string {
		return '2.5.0';
	}

	public function get_author(): string {
		return 'Test Author';
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'search_code',
					'description' => 'Search code in the codebase',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'query' => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		if ( 'search_code' === $tool_name ) {
			return array( 'results' => array( 'file.php:10: match' ) );
		}
		return null;
	}

	public function get_suggested_prompts(): array {
		return array( 'Find bugs', 'Refactor code' );
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options', 'edit_posts' );
	}
}

/**
 * Test case for Agent_Base abstract class.
 */
class Test_Agent_Base extends TestCase {

	/**
	 * @var Stub_Agent
	 */
	private Stub_Agent $stub;

	/**
	 * @var Custom_Agent
	 */
	private Custom_Agent $custom;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->stub   = new Stub_Agent();
		$this->custom = new Custom_Agent();
	}

	// -------------------------------------------------------------------------
	// Abstract methods (required)
	// -------------------------------------------------------------------------

	/**
	 * Test get_id returns a string.
	 */
	public function test_get_id() {
		$this->assertEquals( 'stub-agent', $this->stub->get_id() );
		$this->assertEquals( 'custom-agent', $this->custom->get_id() );
	}

	/**
	 * Test get_name returns a string.
	 */
	public function test_get_name() {
		$this->assertEquals( 'Stub Agent', $this->stub->get_name() );
	}

	/**
	 * Test get_description returns a string.
	 */
	public function test_get_description() {
		$this->assertEquals( 'A stub agent for testing.', $this->stub->get_description() );
	}

	/**
	 * Test get_system_prompt returns a string.
	 */
	public function test_get_system_prompt() {
		$this->assertStringContainsString( 'stub agent', $this->stub->get_system_prompt() );
	}

	// -------------------------------------------------------------------------
	// Default values
	// -------------------------------------------------------------------------

	/**
	 * Test default icon is robot emoji.
	 */
	public function test_default_icon() {
		$this->assertEquals( '🤖', $this->stub->get_icon() );
	}

	/**
	 * Test default category is 'admin'.
	 */
	public function test_default_category() {
		$this->assertEquals( 'admin', $this->stub->get_category() );
	}

	/**
	 * Test default version is '1.0.0'.
	 */
	public function test_default_version() {
		$this->assertEquals( '1.0.0', $this->stub->get_version() );
	}

	/**
	 * Test default author is 'Unknown'.
	 */
	public function test_default_author() {
		$this->assertEquals( 'Unknown', $this->stub->get_author() );
	}

	/**
	 * Test default tools is empty array.
	 */
	public function test_default_tools() {
		$this->assertIsArray( $this->stub->get_tools() );
		$this->assertEmpty( $this->stub->get_tools() );
	}

	/**
	 * Test default execute_tool returns null.
	 */
	public function test_default_execute_tool() {
		$this->assertNull( $this->stub->execute_tool( 'anything', array() ) );
	}

	/**
	 * Test default suggested_prompts is empty.
	 */
	public function test_default_suggested_prompts() {
		$this->assertIsArray( $this->stub->get_suggested_prompts() );
		$this->assertEmpty( $this->stub->get_suggested_prompts() );
	}

	/**
	 * Test default required_capabilities is ['read'].
	 */
	public function test_default_required_capabilities() {
		$this->assertEquals( array( 'read' ), $this->stub->get_required_capabilities() );
	}

	// -------------------------------------------------------------------------
	// Custom overrides
	// -------------------------------------------------------------------------

	/**
	 * Test custom icon override.
	 */
	public function test_custom_icon() {
		$this->assertEquals( '🔧', $this->custom->get_icon() );
	}

	/**
	 * Test custom category override.
	 */
	public function test_custom_category() {
		$this->assertEquals( 'developer', $this->custom->get_category() );
	}

	/**
	 * Test custom version override.
	 */
	public function test_custom_version() {
		$this->assertEquals( '2.5.0', $this->custom->get_version() );
	}

	/**
	 * Test custom author override.
	 */
	public function test_custom_author() {
		$this->assertEquals( 'Test Author', $this->custom->get_author() );
	}

	/**
	 * Test custom tools override.
	 */
	public function test_custom_tools() {
		$tools = $this->custom->get_tools();
		$this->assertCount( 1, $tools );
		$this->assertEquals( 'search_code', $tools[0]['function']['name'] );
	}

	/**
	 * Test custom execute_tool.
	 */
	public function test_custom_execute_tool() {
		$result = $this->custom->execute_tool( 'search_code', array( 'query' => 'test' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'results', $result );
	}

	/**
	 * Test execute_tool returns null for unknown tool.
	 */
	public function test_execute_tool_unknown() {
		$this->assertNull( $this->custom->execute_tool( 'unknown_tool', array() ) );
	}

	/**
	 * Test custom suggested_prompts.
	 */
	public function test_custom_suggested_prompts() {
		$prompts = $this->custom->get_suggested_prompts();
		$this->assertCount( 2, $prompts );
		$this->assertContains( 'Find bugs', $prompts );
	}

	/**
	 * Test custom required_capabilities.
	 */
	public function test_custom_required_capabilities() {
		$caps = $this->custom->get_required_capabilities();
		$this->assertContains( 'manage_options', $caps );
		$this->assertContains( 'edit_posts', $caps );
	}

	// -------------------------------------------------------------------------
	// Welcome message
	// -------------------------------------------------------------------------

	/**
	 * Test welcome message includes agent name and description.
	 */
	public function test_welcome_message() {
		$msg = $this->stub->get_welcome_message();
		$this->assertStringContainsString( 'Stub Agent', $msg );
		$this->assertStringContainsString( 'A stub agent for testing.', $msg );
		$this->assertStringContainsString( 'How can I help you today?', $msg );
	}

	// -------------------------------------------------------------------------
	// Metadata
	// -------------------------------------------------------------------------

	/**
	 * Test get_metadata returns expected keys.
	 */
	public function test_get_metadata_keys() {
		$meta = $this->stub->get_metadata();
		$this->assertArrayHasKey( 'id', $meta );
		$this->assertArrayHasKey( 'name', $meta );
		$this->assertArrayHasKey( 'description', $meta );
		$this->assertArrayHasKey( 'icon', $meta );
		$this->assertArrayHasKey( 'category', $meta );
		$this->assertArrayHasKey( 'tools', $meta );
	}

	/**
	 * Test get_metadata values.
	 */
	public function test_get_metadata_values() {
		$meta = $this->stub->get_metadata();
		$this->assertEquals( 'stub-agent', $meta['id'] );
		$this->assertEquals( 'Stub Agent', $meta['name'] );
		$this->assertEquals( '🤖', $meta['icon'] );
		$this->assertEquals( 'admin', $meta['category'] );
		$this->assertEmpty( $meta['tools'] );
	}

	/**
	 * Test get_metadata includes tool names for custom agent.
	 */
	public function test_get_metadata_tool_names() {
		$meta = $this->custom->get_metadata();
		$this->assertContains( 'search_code', $meta['tools'] );
	}

	// -------------------------------------------------------------------------
	// Access control
	// -------------------------------------------------------------------------

	/**
	 * Test current_user_can_access with default caps (read).
	 */
	public function test_access_default_subscriber() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( $this->stub->current_user_can_access() );
	}

	/**
	 * Test current_user_can_access denies when user lacks required cap.
	 */
	public function test_access_denied_subscriber_for_admin_agent() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( $this->custom->current_user_can_access() );
	}

	/**
	 * Test current_user_can_access passes for admin user with admin agent.
	 */
	public function test_access_granted_admin() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( $this->custom->current_user_can_access() );
	}

	/**
	 * Test current_user_can_access denied when logged out.
	 */
	public function test_access_denied_logged_out() {
		wp_set_current_user( 0 );
		$this->assertFalse( $this->stub->current_user_can_access() );
	}
}
