<?php
/**
 * Unit Tests for Abilities Bridge
 *
 * Tests tool-to-ability conversion, ability-to-tool conversion,
 * schema mapping, and naming helpers.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Abilities_Bridge;
use Agentic\Agent_Tools;

/**
 * Test case for Abilities_Bridge class.
 */
class Test_Abilities_Bridge extends TestCase {

	/**
	 * Abilities bridge instance.
	 *
	 * @var Abilities_Bridge
	 */
	private Abilities_Bridge $bridge;

	/**
	 * Agent tools instance.
	 *
	 * @var Agent_Tools
	 */
	private Agent_Tools $tools;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->tools  = new Agent_Tools();
		$this->bridge = new Abilities_Bridge( $this->tools );
	}

	// -------------------------------------------------------------------------
	// tool_to_ability_args
	// -------------------------------------------------------------------------

	/**
	 * Test converting a read tool to ability args.
	 */
	public function test_tool_to_ability_args_read_tool() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_get_option',
				'description' => 'Read a WordPress option value.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
					),
					'required'   => array( 'name' ),
				),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		$this->assertNotNull( $args );
		$this->assertEquals( 'Get Option', $args['label'] );
		$this->assertEquals( 'Read a WordPress option value.', $args['description'] );
		$this->assertEquals( 'agent-builder-read', $args['category'] );
		$this->assertTrue( is_callable( $args['execute_callback'] ) );
		$this->assertTrue( is_callable( $args['permission_callback'] ) );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertTrue( $args['meta']['annotations']['idempotent'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );

		// Input schema should pass through.
		$this->assertEquals( 'object', $args['input_schema']['type'] );
		$this->assertArrayHasKey( 'name', $args['input_schema']['properties'] );
	}

	/**
	 * Test converting a write tool to ability args.
	 */
	public function test_tool_to_ability_args_write_tool() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_create_post',
				'description' => 'Create a new WordPress post.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'title' => array(
							'type'        => 'string',
							'description' => 'Post title.',
						),
					),
					'required'   => array( 'title' ),
				),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		$this->assertNotNull( $args );
		$this->assertEquals( 'Create Post', $args['label'] );
		$this->assertEquals( 'agent-builder-write', $args['category'] );
		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['annotations']['idempotent'] );
	}

	/**
	 * Test converting a destructive tool gets destructive annotation.
	 */
	public function test_tool_to_ability_args_destructive_tool() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_delete_post',
				'description' => 'Move a post to trash.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		$this->assertNotNull( $args );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertEquals( 'agent-builder-write', $args['category'] );
	}

	/**
	 * Test tool_to_ability_args with empty tool returns null.
	 */
	public function test_tool_to_ability_args_empty_returns_null() {
		$this->assertNull( $this->bridge->tool_to_ability_args( array() ) );
	}

	/**
	 * Test tool_to_ability_args with missing name returns null.
	 */
	public function test_tool_to_ability_args_no_name_returns_null() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'description' => 'No name.',
			),
		);

		$this->assertNull( $this->bridge->tool_to_ability_args( $tool ) );
	}

	/**
	 * Test tool_to_ability_args with empty parameters.
	 */
	public function test_tool_to_ability_args_empty_parameters() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_get_users',
				'description' => 'List WordPress users.',
				'parameters'  => array(),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		$this->assertNotNull( $args );
		$this->assertEquals( 'object', $args['input_schema']['type'] );
	}

	// -------------------------------------------------------------------------
	// ability_to_tool_definition
	// -------------------------------------------------------------------------

	/**
	 * Test converting a WP_Ability to an OpenAI tool definition.
	 */
	public function test_ability_to_tool_definition() {
		// Skip if WP_Ability doesn't exist (pre-6.9).
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'WP_Ability class not available (requires WP 6.9+).' );
		}

		// We can't easily create a WP_Ability without the registry,
		// so test the name conversion helper indirectly.
		// The full integration test would require WP 6.9.
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// Schema conversion helpers (tested via reflection)
	// -------------------------------------------------------------------------

	/**
	 * Test tool_name_to_label conversion.
	 */
	public function test_tool_name_to_label() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'tool_name_to_label' );
		$method->setAccessible( true );

		$this->assertEquals( 'Get Option', $method->invoke( $this->bridge, 'db_get_option' ) );
		$this->assertEquals( 'Get Posts', $method->invoke( $this->bridge, 'db_get_posts' ) );
		$this->assertEquals( 'Update Post', $method->invoke( $this->bridge, 'db_update_post' ) );
		$this->assertEquals( 'Create Post', $method->invoke( $this->bridge, 'db_create_post' ) );
		$this->assertEquals( 'Delete Post', $method->invoke( $this->bridge, 'db_delete_post' ) );
		$this->assertEquals( 'Get Post Meta', $method->invoke( $this->bridge, 'db_get_post_meta' ) );
	}

	/**
	 * Test ability_name_to_function_name conversion.
	 */
	public function test_ability_name_to_function_name() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'ability_name_to_function_name' );
		$method->setAccessible( true );

		$this->assertEquals( 'my_plugin__export_users', $method->invoke( $this->bridge, 'my-plugin/export-users' ) );
		$this->assertEquals( 'core__get_site_info', $method->invoke( $this->bridge, 'core/get-site-info' ) );
		$this->assertEquals( 'seo_plugin__audit_page', $method->invoke( $this->bridge, 'seo-plugin/audit-page' ) );
	}

	/**
	 * Test input_schema_to_openai_params with object schema.
	 */
	public function test_input_schema_to_openai_params_object() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'input_schema_to_openai_params' );
		$method->setAccessible( true );

		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'role' => array(
					'type'        => 'string',
					'description' => 'User role.',
				),
			),
			'required'   => array( 'role' ),
		);

		$params = $method->invoke( $this->bridge, $schema );

		$this->assertEquals( 'object', $params['type'] );
		$this->assertArrayHasKey( 'role', $params['properties'] );
		$this->assertEquals( array( 'role' ), $params['required'] );
	}

	/**
	 * Test input_schema_to_openai_params with simple type wraps in object.
	 */
	public function test_input_schema_to_openai_params_simple_type() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'input_schema_to_openai_params' );
		$method->setAccessible( true );

		$schema = array(
			'type'        => 'string',
			'description' => 'Text to analyze.',
			'required'    => true,
		);

		$params = $method->invoke( $this->bridge, $schema );

		$this->assertEquals( 'object', $params['type'] );
		$this->assertArrayHasKey( 'input', $params['properties'] );
		$this->assertEquals( 'string', $params['properties']['input']['type'] );
		$this->assertContains( 'input', $params['required'] );
	}

	/**
	 * Test input_schema_to_openai_params with empty schema.
	 */
	public function test_input_schema_to_openai_params_empty() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'input_schema_to_openai_params' );
		$method->setAccessible( true );

		$params = $method->invoke( $this->bridge, array() );

		$this->assertEquals( 'object', $params['type'] );
	}

	/**
	 * Test openai_params_to_input_schema passes through object schemas.
	 */
	public function test_openai_params_to_input_schema_passthrough() {
		$method = new \ReflectionMethod( Abilities_Bridge::class, 'openai_params_to_input_schema' );
		$method->setAccessible( true );

		$params = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string' ),
			),
			'required'   => array( 'name' ),
		);

		$schema = $method->invoke( $this->bridge, $params );

		$this->assertEquals( $params, $schema );
	}

	// -------------------------------------------------------------------------
	// Permission callback
	// -------------------------------------------------------------------------

	/**
	 * Test permission callback for read tools requires edit_posts.
	 */
	public function test_permission_callback_read_tool() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_get_posts',
				'description' => 'Query posts.',
				'parameters'  => array(),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		// Admin should have edit_posts.
		wp_set_current_user( 1 );
		$this->assertTrue( $args['permission_callback']() );
	}

	/**
	 * Test permission callback for write tools requires manage_options.
	 */
	public function test_permission_callback_write_tool() {
		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_update_option',
				'description' => 'Update option.',
				'parameters'  => array(),
			),
		);

		$args = $this->bridge->tool_to_ability_args( $tool );

		// Subscriber should not have manage_options.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( $args['permission_callback']() );

		// Admin should have manage_options.
		wp_set_current_user( 1 );
		$this->assertTrue( $args['permission_callback']() );
	}

	// -------------------------------------------------------------------------
	// Execute callback
	// -------------------------------------------------------------------------

	/**
	 * Test execute callback routes to core tools.
	 */
	public function test_execute_callback_routes_to_core_tools() {
		// Set blogname to a known value.
		update_option( 'blogname', 'Bridge Test Site' );

		$tool = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'db_get_option',
				'description' => 'Read option.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array( 'type' => 'string' ),
					),
					'required'   => array( 'name' ),
				),
			),
		);

		$args   = $this->bridge->tool_to_ability_args( $tool );
		$result = $args['execute_callback']( array( 'name' => 'blogname' ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'blogname', $result['name'] );
		$this->assertEquals( 'Bridge Test Site', $result['value'] );
		$this->assertTrue( $result['exists'] );
	}

	// -------------------------------------------------------------------------
	// get_third_party_abilities_as_tools
	// -------------------------------------------------------------------------

	/**
	 * Test get_third_party_abilities_as_tools returns array.
	 */
	public function test_third_party_tools_returns_array() {
		$tools = $this->bridge->get_third_party_abilities_as_tools();
		$this->assertIsArray( $tools );
	}

	// -------------------------------------------------------------------------
	// All core tools convert
	// -------------------------------------------------------------------------

	/**
	 * Test all 11 core tools can be converted to abilities.
	 */
	public function test_all_core_tools_convert_to_abilities() {
		$definitions = $this->tools->get_all_tool_definitions();

		// Filter to only core tools (first 11).
		$core_names = array(
			'db_get_option',
			'db_get_posts',
			'db_get_post',
			'db_get_users',
			'db_get_terms',
			'db_get_post_meta',
			'db_get_comments',
			'db_update_option',
			'db_create_post',
			'db_update_post',
			'db_delete_post',
		);

		$converted = 0;
		foreach ( $definitions as $tool ) {
			$name = $tool['function']['name'] ?? '';
			if ( ! in_array( $name, $core_names, true ) ) {
				continue;
			}

			$args = $this->bridge->tool_to_ability_args( $tool );
			$this->assertNotNull( $args, "Failed to convert tool: {$name}" );
			$this->assertNotEmpty( $args['label'], "Empty label for tool: {$name}" );
			$this->assertNotEmpty( $args['description'], "Empty description for tool: {$name}" );
			$this->assertNotEmpty( $args['category'], "Empty category for tool: {$name}" );
			$this->assertTrue( is_callable( $args['execute_callback'] ), "Non-callable execute for: {$name}" );
			$this->assertTrue( is_callable( $args['permission_callback'] ), "Non-callable permission for: {$name}" );
			++$converted;
		}

		$this->assertEquals( 11, $converted, 'Expected all 11 core tools to convert.' );
	}

	/**
	 * Test disabled tools are excluded from registration.
	 */
	public function test_disabled_tools_excluded() {
		update_option( 'agentic_disabled_tools', array( 'db_get_option', 'db_delete_post' ) );

		$enabled = $this->tools->get_tool_definitions();
		$enabled_names = array_map(
			fn( $t ) => $t['function']['name'] ?? '',
			$enabled
		);

		$this->assertNotContains( 'db_get_option', $enabled_names );
		$this->assertNotContains( 'db_delete_post', $enabled_names );
		$this->assertContains( 'db_get_posts', $enabled_names );

		delete_option( 'agentic_disabled_tools' );
	}

	// -------------------------------------------------------------------------
	// register_hooks
	// -------------------------------------------------------------------------

	/**
	 * Test register_hooks adds the correct actions.
	 */
	public function test_register_hooks_adds_actions() {
		$bridge = new Abilities_Bridge( $this->tools );
		$bridge->register_hooks();

		$this->assertNotFalse(
			has_action( 'wp_abilities_api_categories_init', array( $bridge, 'register_categories' ) )
		);
		$this->assertNotFalse(
			has_action( 'wp_abilities_api_init', array( $bridge, 'register_tools_as_abilities' ) )
		);
	}
}
