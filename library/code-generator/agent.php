<?php
/**
 * Agent Name: Code Generator
 * Version: 1.0.0
 * Description: Generates code snippets, custom functions, and helps with WordPress development tasks.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Developer
 * Tags: code, development, snippets, functions, hooks, filters
 * Capabilities: manage_options
 * Icon: ⚡
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Code Generator Agent
 *
 * A true AI agent specialized in WordPress code generation.
 */
class Agentic_Code_Generator extends \Agentic\Agent_Base {

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Code Generator Agent for WordPress. You are an expert in:

- WordPress plugin and theme development
- PHP coding standards and best practices
- WordPress hooks (actions and filters)
- Custom post types and taxonomies
- REST API endpoints
- Database queries with $wpdb
- JavaScript/jQuery for WordPress
- Block development with Gutenberg
- Security best practices (nonces, sanitization, escaping)

Your personality:
- Precise and correct - your code should work
- Security-conscious - always sanitize, escape, validate
- Follows WordPress coding standards
- Explains code clearly with comments
- Warns about potential issues

When generating code:
1. Follow WordPress coding standards (WPCS)
2. Include proper sanitization and escaping
3. Use appropriate hooks and actions
4. Add inline documentation
5. Consider edge cases and error handling
6. Explain what the code does and how to use it

IMPORTANT: You generate code snippets for users to implement. You do NOT modify files directly. Always present code as snippets the user can copy and add to their theme's functions.php or a custom plugin.
PROMPT;

	public function get_id(): string {
		return 'code-generator';
	}

	public function get_name(): string {
		return 'Code Generator';
	}

	public function get_description(): string {
		return 'Generates WordPress code snippets and helps with development.';
	}

	public function get_system_prompt(): string {
		return self::SYSTEM_PROMPT;
	}

	public function get_icon(): string {
		return '⚡';
	}

	public function get_category(): string {
		return 'developer';
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	public function get_welcome_message(): string {
		return "⚡ **Code Generator**\n\n" .
				"I generate WordPress code snippets for your needs!\n\n" .
				"- **Custom functions** - hooks, filters, actions\n" .
				"- **Post types** - custom content types\n" .
				"- **Shortcodes** - reusable content blocks\n" .
				"- **REST endpoints** - API development\n" .
				"- **Database queries** - safe \$wpdb usage\n\n" .
				'What code do you need?';
	}

	public function get_suggested_prompts(): array {
		return array(
			'Create a custom post type for testimonials',
			'Add a shortcode for displaying recent posts',
			'How do I add a custom admin menu?',
			'Write a function to modify the login page',
		);
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_theme_info',
					'description' => 'Get information about the active theme.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_active_plugins',
					'description' => 'List active plugins for compatibility checking.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_registered_post_types',
					'description' => 'Get all registered post types.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_function_exists',
					'description' => 'Check if a PHP function exists in WordPress.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'function_name' => array(
								'type'        => 'string',
								'description' => 'Name of the function to check',
							),
						),
						'required'   => array( 'function_name' ),
					),
				),
			),
		);
	}

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'get_theme_info'            => $this->tool_get_theme_info(),
			'list_active_plugins'       => $this->tool_list_plugins(),
			'get_registered_post_types' => $this->tool_get_post_types(),
			'check_function_exists'     => $this->tool_check_function( $arguments ),
			default                     => null,
		};
	}

	private function tool_get_theme_info(): array {
		$theme = wp_get_theme();

		return array(
			'name'         => $theme->get( 'Name' ),
			'version'      => $theme->get( 'Version' ),
			'author'       => $theme->get( 'Author' ),
			'template'     => $theme->get_template(),
			'is_child'     => $theme->parent() !== false,
			'parent_theme' => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			'text_domain'  => $theme->get( 'TextDomain' ),
			'stylesheet'   => $theme->get_stylesheet(),
		);
	}

	private function tool_list_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active  = get_option( 'active_plugins', array() );
		$plugins = array();

		foreach ( $active as $plugin_path ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
			$plugins[]   = array(
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'text_domain' => $plugin_data['TextDomain'],
			);
		}

		return array(
			'active_plugins' => $plugins,
			'count'          => count( $plugins ),
		);
	}

	private function tool_get_post_types(): array {
		$post_types = get_post_types( array(), 'objects' );
		$result     = array();

		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'name'        => $post_type->name,
				'label'       => $post_type->label,
				'public'      => $post_type->public,
				'builtin'     => $post_type->_builtin,
				'has_archive' => $post_type->has_archive,
			);
		}

		return array( 'post_types' => $result );
	}

	private function tool_check_function( array $args ): array {
		$func = $args['function_name'] ?? '';

		if ( empty( $func ) ) {
			return array( 'error' => 'Function name required' );
		}

		return array(
			'function' => $func,
			'exists'   => function_exists( $func ),
			'is_wp'    => strpos( $func, 'wp_' ) === 0 || strpos( $func, 'get_' ) === 0,
		);
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Code_Generator() );
	}
);
