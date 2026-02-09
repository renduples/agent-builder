<?php
/**
 * Agent Name: Plugin Builder
 * Version: 1.0.0
 * Description: Creates complete WordPress plugins from natural language descriptions. Generates WPCS-compliant code with security best practices.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Developer
 * Tags: plugin, generator, scaffold, development, wordpress, wpcs
 * Capabilities: manage_options
 * Icon: 🔌
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Builder Agent
 *
 * Creates complete WordPress plugins from natural language descriptions.
 * Generates WPCS-compliant, security-hardened plugin code.
 */
class Agentic_Plugin_Builder extends \Agentic\Agent_Base {

	/**
	 * Load system prompt from template file
	 */
	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	/**
	 * Load and render a template file with variable substitution.
	 *
	 * @param string $template_name Template file name (without path, e.g., 'main-plugin.php.template').
	 * @param array  $vars          Associative array of placeholder => value pairs.
	 * @return string Rendered template content.
	 */
	private function load_template( string $template_name, array $vars = array() ): string {
		$template_path = __DIR__ . '/templates/' . $template_name;

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		$content = file_get_contents( $template_path );

		// Replace {{placeholder}} with values.
		foreach ( $vars as $key => $value ) {
			$content = str_replace( '{{' . $key . '}}', $value, $content );
		}

		return $content;
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'plugin-builder';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Plugin Builder';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Creates complete WordPress plugins from natural language descriptions.';
	}

	/**
	 * Get system prompt
	 */
	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	/**
	 * Get agent icon
	 */
	public function get_icon(): string {
		return '🔌';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'Developer';
	}

	/**
	 * Get agent version
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Get agent author
	 */
	public function get_author(): string {
		return 'Agentic Community';
	}

	/**
	 * Get required capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "🔌 **Plugin Builder**\n\n" .
			"I help you create WordPress plugins from scratch!\n\n" .
			"I can generate:\n" .
			"- **Complete plugin scaffolds** from descriptions\n" .
			"- **Custom Post Types** with meta boxes\n" .
			"- **Settings pages** with the Settings API\n" .
			"- **Shortcodes** for frontend output\n" .
			"- **REST API endpoints** for custom data\n" .
			"- **Admin menus** and dashboard widgets\n\n" .
			"All code follows WordPress Coding Standards and security best practices.\n\n" .
			"What plugin would you like to build?";
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'Create a plugin that adds a testimonials post type',
			'Build a plugin for managing FAQ sections',
			'Generate a settings page for an API integration',
			'Create a shortcode that displays recent posts',
		);
	}

	/**
	 * Get available tools
	 */
	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_plugin_scaffold',
					'description' => 'Generate a complete plugin scaffold with main file, folder structure, and base classes',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => 'Plugin display name (e.g., "My Awesome Plugin")',
							),
							'slug'        => array(
								'type'        => 'string',
								'description' => 'Plugin slug/directory name (e.g., "my-awesome-plugin")',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'Plugin description for the header',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Unique prefix for functions and classes (e.g., "map" for my-awesome-plugin)',
							),
							'author'      => array(
								'type'        => 'string',
								'description' => 'Plugin author name',
							),
							'author_uri'  => array(
								'type'        => 'string',
								'description' => 'Plugin author URI',
							),
							'features'    => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Features to include: cpt, taxonomy, settings, shortcode, rest-api, admin-menu, uninstall',
							),
						),
						'required'   => array( 'name', 'slug', 'description', 'prefix' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_custom_post_type',
					'description' => 'Generate code for a custom post type with labels, arguments, and optional meta boxes',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'slug'          => array(
								'type'        => 'string',
								'description' => 'Post type slug (max 20 chars, no capitals, no spaces)',
							),
							'singular'      => array(
								'type'        => 'string',
								'description' => 'Singular label (e.g., "Testimonial")',
							),
							'plural'        => array(
								'type'        => 'string',
								'description' => 'Plural label (e.g., "Testimonials")',
							),
							'prefix'        => array(
								'type'        => 'string',
								'description' => 'Function/class prefix',
							),
							'icon'          => array(
								'type'        => 'string',
								'description' => 'Dashicon name (e.g., "dashicons-format-quote")',
							),
							'supports'      => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Supported features: title, editor, thumbnail, excerpt, custom-fields, etc.',
							),
							'public'        => array(
								'type'        => 'boolean',
								'description' => 'Whether the post type is public (default: true)',
							),
							'has_archive'   => array(
								'type'        => 'boolean',
								'description' => 'Enable archive page (default: true)',
							),
							'meta_fields'   => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'key'   => array( 'type' => 'string' ),
										'label' => array( 'type' => 'string' ),
										'type'  => array( 'type' => 'string' ),
									),
								),
								'description' => 'Custom meta fields to add',
							),
						),
						'required'   => array( 'slug', 'singular', 'plural', 'prefix' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_taxonomy',
					'description' => 'Generate code for a custom taxonomy',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'slug'        => array(
								'type'        => 'string',
								'description' => 'Taxonomy slug',
							),
							'singular'    => array(
								'type'        => 'string',
								'description' => 'Singular label',
							),
							'plural'      => array(
								'type'        => 'string',
								'description' => 'Plural label',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Function/class prefix',
							),
							'post_types'  => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Post types to attach the taxonomy to',
							),
							'hierarchical' => array(
								'type'        => 'boolean',
								'description' => 'Hierarchical like categories (true) or flat like tags (false)',
							),
						),
						'required'   => array( 'slug', 'singular', 'plural', 'prefix', 'post_types' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_settings_page',
					'description' => 'Generate a settings page using the WordPress Settings API',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'page_title'  => array(
								'type'        => 'string',
								'description' => 'Page title shown in browser',
							),
							'menu_title'  => array(
								'type'        => 'string',
								'description' => 'Menu item text',
							),
							'menu_slug'   => array(
								'type'        => 'string',
								'description' => 'URL slug for the page',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Function/class prefix',
							),
							'parent_slug' => array(
								'type'        => 'string',
								'description' => 'Parent menu slug (options-general.php for Settings, tools.php for Tools, or empty for top-level)',
							),
							'sections'    => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'id'          => array( 'type' => 'string' ),
										'title'       => array( 'type' => 'string' ),
										'description' => array( 'type' => 'string' ),
										'fields'      => array(
											'type'  => 'array',
											'items' => array(
												'type'       => 'object',
												'properties' => array(
													'id'          => array( 'type' => 'string' ),
													'label'       => array( 'type' => 'string' ),
													'type'        => array( 'type' => 'string' ),
													'description' => array( 'type' => 'string' ),
													'options'     => array( 'type' => 'array' ),
												),
											),
										),
									),
								),
								'description' => 'Settings sections with fields',
							),
						),
						'required'   => array( 'page_title', 'menu_title', 'menu_slug', 'prefix', 'sections' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_shortcode',
					'description' => 'Generate a shortcode with attributes and output',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'tag'         => array(
								'type'        => 'string',
								'description' => 'Shortcode tag (e.g., "my_shortcode")',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Function prefix',
							),
							'attributes'  => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'        => array( 'type' => 'string' ),
										'default'     => array( 'type' => 'string' ),
										'description' => array( 'type' => 'string' ),
									),
								),
								'description' => 'Shortcode attributes with defaults',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'What the shortcode outputs',
							),
							'has_content' => array(
								'type'        => 'boolean',
								'description' => 'Whether shortcode wraps content [tag]content[/tag]',
							),
						),
						'required'   => array( 'tag', 'prefix', 'description' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_rest_endpoint',
					'description' => 'Generate a custom REST API endpoint',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'namespace'   => array(
								'type'        => 'string',
								'description' => 'API namespace (e.g., "myplugin/v1")',
							),
							'route'       => array(
								'type'        => 'string',
								'description' => 'Route path (e.g., "/items" or "/items/(?P<id>\\d+)")',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Function/class prefix',
							),
							'methods'     => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'HTTP methods: GET, POST, PUT, DELETE',
							),
							'args'        => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'        => array( 'type' => 'string' ),
										'type'        => array( 'type' => 'string' ),
										'required'    => array( 'type' => 'boolean' ),
										'description' => array( 'type' => 'string' ),
									),
								),
								'description' => 'Endpoint arguments',
							),
							'permission'  => array(
								'type'        => 'string',
								'description' => 'Required capability or "public"',
							),
						),
						'required'   => array( 'namespace', 'route', 'prefix', 'methods' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_admin_menu',
					'description' => 'Generate an admin menu page with content',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'page_title'  => array(
								'type'        => 'string',
								'description' => 'Page title',
							),
							'menu_title'  => array(
								'type'        => 'string',
								'description' => 'Menu title',
							),
							'menu_slug'   => array(
								'type'        => 'string',
								'description' => 'Menu slug',
							),
							'prefix'      => array(
								'type'        => 'string',
								'description' => 'Function/class prefix',
							),
							'icon'        => array(
								'type'        => 'string',
								'description' => 'Dashicon or base64 icon',
							),
							'position'    => array(
								'type'        => 'integer',
								'description' => 'Menu position',
							),
							'submenus'    => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'page_title' => array( 'type' => 'string' ),
										'menu_title' => array( 'type' => 'string' ),
										'menu_slug'  => array( 'type' => 'string' ),
									),
								),
								'description' => 'Submenu pages',
							),
						),
						'required'   => array( 'page_title', 'menu_title', 'menu_slug', 'prefix' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_uninstall',
					'description' => 'Generate an uninstall.php file for clean plugin removal',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'prefix'          => array(
								'type'        => 'string',
								'description' => 'Option/meta prefix to clean up',
							),
							'options'         => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Option names to delete',
							),
							'post_types'      => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Custom post types to remove',
							),
							'tables'          => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Custom database tables to drop (without prefix)',
							),
							'user_meta_keys'  => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'User meta keys to delete',
							),
							'transients'      => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Transient names to delete',
							),
						),
						'required'   => array( 'prefix' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_ajax_handler',
					'description' => 'Generate an AJAX handler with nonce verification',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'action'        => array(
								'type'        => 'string',
								'description' => 'AJAX action name',
							),
							'prefix'        => array(
								'type'        => 'string',
								'description' => 'Function prefix',
							),
							'public'        => array(
								'type'        => 'boolean',
								'description' => 'Allow non-logged-in users (nopriv)',
							),
							'capability'    => array(
								'type'        => 'string',
								'description' => 'Required capability (empty for any logged-in user)',
							),
							'parameters'    => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'       => array( 'type' => 'string' ),
										'type'       => array( 'type' => 'string' ),
										'required'   => array( 'type' => 'boolean' ),
										'sanitizer'  => array( 'type' => 'string' ),
									),
								),
								'description' => 'Expected POST/GET parameters',
							),
						),
						'required'   => array( 'action', 'prefix' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_database_table',
					'description' => 'Generate code to create a custom database table',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'table_name' => array(
								'type'        => 'string',
								'description' => 'Table name without WordPress prefix (e.g., "my_items")',
							),
							'prefix'     => array(
								'type'        => 'string',
								'description' => 'Function prefix',
							),
							'columns'    => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'      => array( 'type' => 'string' ),
										'type'      => array( 'type' => 'string' ),
										'null'      => array( 'type' => 'boolean' ),
										'default'   => array( 'type' => 'string' ),
									),
								),
								'description' => 'Column definitions',
							),
							'primary_key' => array(
								'type'        => 'string',
								'description' => 'Primary key column name',
							),
							'indexes'     => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Columns to index',
							),
						),
						'required'   => array( 'table_name', 'prefix', 'columns', 'primary_key' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'validate_plugin_code',
					'description' => 'Validate generated plugin code for syntax and WPCS compliance',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'code' => array(
								'type'        => 'string',
								'description' => 'PHP code to validate',
							),
						),
						'required'   => array( 'code' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'save_plugin_file',
					'description' => 'Save generated code to a file in the plugins directory',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'plugin_slug' => array(
								'type'        => 'string',
								'description' => 'Plugin directory name',
							),
							'file_path'   => array(
								'type'        => 'string',
								'description' => 'Relative file path within plugin (e.g., "includes/class-cpt.php")',
							),
							'content'     => array(
								'type'        => 'string',
								'description' => 'File content to write',
							),
							'overwrite'   => array(
								'type'        => 'boolean',
								'description' => 'Overwrite if file exists (default: false)',
							),
						),
						'required'   => array( 'plugin_slug', 'file_path', 'content' ),
					),
				),
			),
		);
	}

	/**
	 * Execute a tool
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'create_plugin_scaffold'   => $this->tool_create_plugin_scaffold( $arguments ),
			'generate_custom_post_type' => $this->tool_generate_cpt( $arguments ),
			'generate_taxonomy'        => $this->tool_generate_taxonomy( $arguments ),
			'generate_settings_page'   => $this->tool_generate_settings( $arguments ),
			'generate_shortcode'       => $this->tool_generate_shortcode( $arguments ),
			'generate_rest_endpoint'   => $this->tool_generate_rest( $arguments ),
			'generate_admin_menu'      => $this->tool_generate_admin_menu( $arguments ),
			'generate_uninstall'       => $this->tool_generate_uninstall( $arguments ),
			'generate_ajax_handler'    => $this->tool_generate_ajax( $arguments ),
			'generate_database_table'  => $this->tool_generate_table( $arguments ),
			'validate_plugin_code'     => $this->tool_validate_code( $arguments ),
			'save_plugin_file'         => $this->tool_save_file( $arguments ),
			default                    => array( 'error' => 'Unknown tool: ' . $tool_name ),
		};
	}

	/**
	 * Tool: Create plugin scaffold
	 */
	private function tool_create_plugin_scaffold( array $args ): array {
		$name        = $args['name'] ?? '';
		$slug        = sanitize_file_name( $args['slug'] ?? '' );
		$description = $args['description'] ?? '';
		$prefix      = $args['prefix'] ?? '';
		$author      = $args['author'] ?? 'Plugin Author';
		$author_uri  = $args['author_uri'] ?? '';
		$features    = $args['features'] ?? array();

		if ( empty( $name ) || empty( $slug ) || empty( $description ) || empty( $prefix ) ) {
			return array( 'error' => 'name, slug, description, and prefix are required' );
		}

		// Generate class name
		$class_name = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $slug ) ) );
		$const_prefix = strtoupper( str_replace( '-', '_', $slug ) );

		// Main plugin file
		$main_file = $this->generate_main_plugin_file(
			array(
				'name'         => $name,
				'slug'         => $slug,
				'description'  => $description,
				'prefix'       => $prefix,
				'author'       => $author,
				'author_uri'   => $author_uri,
				'class_name'   => $class_name,
				'const_prefix' => $const_prefix,
				'features'     => $features,
			)
		);

		$files = array(
			$slug . '.php' => $main_file,
		);

		// Generate additional files based on features
		if ( in_array( 'uninstall', $features, true ) ) {
			$files['uninstall.php'] = $this->generate_uninstall_file( $prefix );
		}

		// Create index.php files for security
		$index_content = "<?php\n// Silence is golden.\n";
		$files['index.php'] = $index_content;
		$files['includes/index.php'] = $index_content;
		$files['admin/index.php'] = $index_content;
		$files['assets/css/index.php'] = $index_content;
		$files['assets/js/index.php'] = $index_content;
		$files['languages/index.php'] = $index_content;

		return array(
			'success'     => true,
			'plugin_slug' => $slug,
			'files'       => $files,
			'structure'   => array(
				$slug . '/',
				'├── ' . $slug . '.php (main plugin file)',
				'├── index.php',
				'├── uninstall.php',
				'├── includes/',
				'│   └── index.php',
				'├── admin/',
				'│   └── index.php',
				'├── assets/',
				'│   ├── css/',
				'│   └── js/',
				'└── languages/',
			),
			'next_steps'  => array(
				'Use save_plugin_file to write files to disk',
				'Add CPT with generate_custom_post_type',
				'Add settings with generate_settings_page',
			),
		);
	}

	/**
	 * Generate main plugin file
	 */
	private function generate_main_plugin_file( array $spec ): string {
		$author_line = ! empty( $spec['author_uri'] ) 
			? ' * Author URI:   ' . $spec['author_uri'] 
			: '';

		return $this->load_template(
			'main-plugin.php.template',
			array(
				'name'         => $spec['name'],
				'slug'         => $spec['slug'],
				'description'  => $spec['description'],
				'author'       => $spec['author'],
				'author_line'  => $author_line,
				'class_name'   => $spec['class_name'],
				'const_prefix' => $spec['const_prefix'],
			)
		);
	}

	/**
	 * Generate uninstall.php
	 */
	private function generate_uninstall_file( string $prefix ): string {
		return $this->load_template(
			'uninstall.php.template',
			array( 'prefix' => $prefix )
		);
	}

	/**
	 * Tool: Generate Custom Post Type
	 */
	private function tool_generate_cpt( array $args ): array {
		$slug        = $args['slug'] ?? '';
		$singular    = $args['singular'] ?? '';
		$plural      = $args['plural'] ?? '';
		$prefix      = $args['prefix'] ?? '';
		$icon        = $args['icon'] ?? 'dashicons-admin-post';
		$supports    = $args['supports'] ?? array( 'title', 'editor', 'thumbnail' );
		$public      = $args['public'] ?? true;
		$has_archive = $args['has_archive'] ?? true;
		$meta_fields = $args['meta_fields'] ?? array();

		if ( empty( $slug ) || empty( $singular ) || empty( $plural ) || empty( $prefix ) ) {
			return array( 'error' => 'slug, singular, plural, and prefix are required' );
		}

		$supports_str = "'" . implode( "', '", $supports ) . "'";
		$public_str   = $public ? 'true' : 'false';
		$archive_str  = $has_archive ? 'true' : 'false';

		$meta_box_code  = '';
		$save_meta_code = '';

		if ( ! empty( $meta_fields ) ) {
			$meta_box_code  = $this->generate_meta_box_code( $slug, $singular, $prefix, $meta_fields );
			$save_meta_code = $this->generate_save_meta_code( $slug, $prefix, $meta_fields );
		}

		$code = $this->load_template(
			'cpt.php.template',
			array(
				'singular'       => $singular,
				'plural'         => $plural,
				'slug'           => $slug,
				'prefix'         => $prefix,
				'text_domain'    => $prefix,
				'public'         => $public_str,
				'has_archive'    => $archive_str,
				'icon'           => $icon,
				'supports'       => $supports_str,
				'meta_box_code'  => $meta_box_code,
				'save_meta_code' => $save_meta_code,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'post_type' => $slug,
			'file_name' => "includes/class-{$slug}-cpt.php",
		);
	}

	/**
	 * Generate meta box code for CPT
	 */
	private function generate_meta_box_code( string $slug, string $singular, string $prefix, array $fields ): string {
		$fields_html = '';
		foreach ( $fields as $field ) {
			$key   = $field['key'] ?? '';
			$label = $field['label'] ?? '';
			$type  = $field['type'] ?? 'text';

			$fields_html .= $this->load_template(
				'meta-field.php.template',
				array(
					'key'    => $key,
					'label'  => $label,
					'type'   => $type,
					'prefix' => $prefix,
				)
			);
		}

		return $this->load_template(
			'meta-box.php.template',
			array(
				'slug'        => $slug,
				'singular'    => $singular,
				'prefix'      => $prefix,
				'fields_html' => $fields_html,
			)
		);
	}

	/**
	 * Generate save meta code
	 */
	private function generate_save_meta_code( string $slug, string $prefix, array $fields ): string {
		$save_fields = '';
		foreach ( $fields as $field ) {
			$key  = $field['key'] ?? '';
			$type = $field['type'] ?? 'text';

			$sanitizer = match ( $type ) {
				'email'    => 'sanitize_email',
				'url'      => 'esc_url_raw',
				'textarea' => 'sanitize_textarea_field',
				'number'   => 'absint',
				default    => 'sanitize_text_field',
			};

			$save_fields .= $this->load_template(
				'save-meta-field.php.template',
				array(
					'key'       => $key,
					'prefix'    => $prefix,
					'sanitizer' => $sanitizer,
				)
			);
		}

		return $this->load_template(
			'save-meta.php.template',
			array(
				'slug'        => $slug,
				'prefix'      => $prefix,
				'save_fields' => $save_fields,
			)
		);
	}

	/**
	 * Tool: Generate Taxonomy
	 */
	private function tool_generate_taxonomy( array $args ): array {
		$slug         = $args['slug'] ?? '';
		$singular     = $args['singular'] ?? '';
		$plural       = $args['plural'] ?? '';
		$prefix       = $args['prefix'] ?? '';
		$post_types   = $args['post_types'] ?? array( 'post' );
		$hierarchical = $args['hierarchical'] ?? true;

		if ( empty( $slug ) || empty( $singular ) || empty( $plural ) || empty( $prefix ) ) {
			return array( 'error' => 'slug, singular, plural, prefix, and post_types are required' );
		}

		$post_types_str   = "'" . implode( "', '", $post_types ) . "'";
		$hierarchical_str = $hierarchical ? 'true' : 'false';

		$code = $this->load_template(
			'taxonomy.php.template',
			array(
				'singular'     => $singular,
				'plural'       => $plural,
				'slug'         => $slug,
				'prefix'       => $prefix,
				'post_types'   => $post_types_str,
				'hierarchical' => $hierarchical_str,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'taxonomy'  => $slug,
			'file_name' => "includes/class-{$slug}-taxonomy.php",
		);
	}

	/**
	 * Tool: Generate Settings Page
	 */
	private function tool_generate_settings( array $args ): array {
		$page_title  = $args['page_title'] ?? '';
		$menu_title  = $args['menu_title'] ?? '';
		$menu_slug   = $args['menu_slug'] ?? '';
		$prefix      = $args['prefix'] ?? '';
		$parent_slug = $args['parent_slug'] ?? 'options-general.php';
		$sections    = $args['sections'] ?? array();

		if ( empty( $page_title ) || empty( $menu_title ) || empty( $menu_slug ) || empty( $prefix ) ) {
			return array( 'error' => 'page_title, menu_title, menu_slug, prefix, and sections are required' );
		}

		// Generate sections code
		$sections_code = '';
		$fields_code   = '';

		foreach ( $sections as $section ) {
			$section_id    = $section['id'] ?? '';
			$section_title = $section['title'] ?? '';
			$section_desc  = $section['description'] ?? '';

			$sections_code .= $this->load_template(
				'settings-section.php.template',
				array(
					'section_id'    => $section_id,
					'section_title' => $section_title,
					'section_desc'  => $section_desc,
					'prefix'        => $prefix,
					'menu_slug'     => $menu_slug,
				)
			);

			foreach ( $section['fields'] ?? array() as $field ) {
				$field_id    = $field['id'] ?? '';
				$field_label = $field['label'] ?? '';
				$field_type  = $field['type'] ?? 'text';
				$field_desc  = $field['description'] ?? '';

				$fields_code .= $this->load_template(
					'settings-field.php.template',
					array(
						'field_id'    => $field_id,
						'field_label' => $field_label,
						'prefix'      => $prefix,
						'menu_slug'   => $menu_slug,
						'section_id'  => $section_id,
					)
				);

				// Generate field renderer
				$fields_code .= $this->generate_field_renderer( $field_id, $field_type, $prefix, $field_desc, $field['options'] ?? array() );
			}
		}

		$code = $this->load_template(
			'settings-page.php.template',
			array(
				'page_title'    => $page_title,
				'menu_title'    => $menu_title,
				'menu_slug'     => $menu_slug,
				'prefix'        => $prefix,
				'parent_slug'   => $parent_slug,
				'sections_code' => $sections_code,
				'fields_code'   => $fields_code,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'menu_slug' => $menu_slug,
			'file_name' => 'admin/settings.php',
		);
	}

	/**
	 * Generate field renderer function
	 */
	private function generate_field_renderer( string $field_id, string $type, string $prefix, string $desc, array $options ): string {
		$desc_html = ! empty( $desc ) ? "\n\techo '<p class=\"description\">' . esc_html__( '" . $desc . "', '" . $prefix . "' ) . '</p>';" : '';

		$template_name = match ( $type ) {
			'checkbox' => 'field-checkbox.php.template',
			'textarea' => 'field-textarea.php.template',
			'select'   => 'field-select.php.template',
			default    => 'field-default.php.template',
		};

		return $this->load_template(
			$template_name,
			array(
				'field_id'  => $field_id,
				'prefix'    => $prefix,
				'type'      => $type,
				'desc_html' => $desc_html,
			)
		);
	}

	/**
	 * Tool: Generate Shortcode
	 */
	private function tool_generate_shortcode( array $args ): array {
		$tag         = $args['tag'] ?? '';
		$prefix      = $args['prefix'] ?? '';
		$attributes  = $args['attributes'] ?? array();
		$description = $args['description'] ?? '';
		$has_content = $args['has_content'] ?? false;

		if ( empty( $tag ) || empty( $prefix ) ) {
			return array( 'error' => 'tag and prefix are required' );
		}

		// Build defaults array
		$defaults = array();
		foreach ( $attributes as $attr ) {
			$name    = $attr['name'] ?? '';
			$default = $attr['default'] ?? '';
			$defaults[] = "'{$name}' => '{$default}'";
		}
		$defaults_str = implode( ",\n\t\t\t", $defaults );

		$content_param = $has_content ? ', $content = null' : '';
		$content_var   = $has_content ? "\n\t\$content = ! empty( \$content ) ? wp_kses_post( \$content ) : '';" : '';

		$code = $this->load_template(
			'shortcode.php.template',
			array(
				'tag'           => $tag,
				'prefix'        => $prefix,
				'description'   => $description,
				'defaults'      => $defaults_str,
				'content_param' => $content_param,
				'content_var'   => $content_var,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'shortcode' => "[{$tag}]",
			'file_name' => "includes/shortcode-{$tag}.php",
		);
	}

	/**
	 * Tool: Generate REST Endpoint
	 */
	private function tool_generate_rest( array $args ): array {
		$namespace     = $args['namespace'] ?? '';
		$route         = $args['route'] ?? '';
		$prefix        = $args['prefix'] ?? '';
		$methods       = $args['methods'] ?? array( 'GET' );
		$endpoint_args = $args['args'] ?? array();
		$permission    = $args['permission'] ?? 'manage_options';

		if ( empty( $namespace ) || empty( $route ) || empty( $prefix ) ) {
			return array( 'error' => 'namespace, route, prefix, and methods are required' );
		}

		$methods_str = strtoupper( implode( ', ', $methods ) );

		// Generate args array
		$args_code = '';
		if ( ! empty( $endpoint_args ) ) {
			$args_items = array();
			foreach ( $endpoint_args as $arg ) {
				$required     = ! empty( $arg['required'] ) ? 'true' : 'false';
				$args_items[] = $this->load_template(
					'rest-arg.php.template',
					array(
						'name'        => $arg['name'],
						'required'    => $required,
						'type'        => $arg['type'],
						'description' => $arg['description'],
					)
				);
			}
			$args_code = "'args' => array(\n\t\t\t\t" . implode( ",\n\t\t\t\t", $args_items ) . "\n\t\t\t),";
		}

		$permission_code = $permission === 'public'
			? 'return true;'
			: "return current_user_can( '{$permission}' );";

		$code = $this->load_template(
			'rest-endpoint.php.template',
			array(
				'namespace'       => $namespace,
				'route'           => $route,
				'prefix'          => $prefix,
				'methods'         => $methods_str,
				'args_code'       => $args_code,
				'permission_code' => $permission_code,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'endpoint'  => "/wp-json/{$namespace}{$route}",
			'file_name' => 'includes/rest-api.php',
		);
	}

	/**
	 * Tool: Generate Admin Menu
	 */
	private function tool_generate_admin_menu( array $args ): array {
		$page_title = $args['page_title'] ?? '';
		$menu_title = $args['menu_title'] ?? '';
		$menu_slug  = $args['menu_slug'] ?? '';
		$prefix     = $args['prefix'] ?? '';
		$icon       = $args['icon'] ?? 'dashicons-admin-generic';
		$position   = $args['position'] ?? 65;
		$submenus   = $args['submenus'] ?? array();

		if ( empty( $page_title ) || empty( $menu_title ) || empty( $menu_slug ) || empty( $prefix ) ) {
			return array( 'error' => 'page_title, menu_title, menu_slug, and prefix are required' );
		}

		$submenu_code = '';
		foreach ( $submenus as $submenu ) {
			$sub_page_title = $submenu['page_title'] ?? '';
			$sub_menu_title = $submenu['menu_title'] ?? '';
			$sub_menu_slug  = $submenu['menu_slug'] ?? '';

			$submenu_code .= $this->load_template(
				'submenu.php.template',
				array(
					'menu_slug'      => $menu_slug,
					'sub_page_title' => $sub_page_title,
					'sub_menu_title' => $sub_menu_title,
					'sub_menu_slug'  => $sub_menu_slug,
					'prefix'         => $prefix,
				)
			);
		}

		$code = $this->load_template(
			'admin-menu.php.template',
			array(
				'page_title'   => $page_title,
				'menu_title'   => $menu_title,
				'menu_slug'    => $menu_slug,
				'prefix'       => $prefix,
				'icon'         => $icon,
				'position'     => $position,
				'submenu_code' => $submenu_code,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'menu_slug' => $menu_slug,
			'file_name' => 'admin/menu.php',
		);
	}

	/**
	 * Tool: Generate Uninstall
	 */
	private function tool_generate_uninstall( array $args ): array {
		$prefix         = $args['prefix'] ?? '';
		$options        = $args['options'] ?? array();
		$post_types     = $args['post_types'] ?? array();
		$tables         = $args['tables'] ?? array();
		$user_meta_keys = $args['user_meta_keys'] ?? array();
		$transients     = $args['transients'] ?? array();

		if ( empty( $prefix ) ) {
			return array( 'error' => 'prefix is required' );
		}

		// Build options deletion code
		$options_code = '';
		if ( ! empty( $options ) ) {
			foreach ( $options as $option ) {
				$options_code .= "\tdelete_option( '{$option}' );\n";
				$options_code .= "\tdelete_site_option( '{$option}' );\n";
			}
		}

		// Build post types deletion code
		$cpt_code = '';
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $pt ) {
				$cpt_code .= $this->load_template(
					'uninstall-cpt.php.template',
					array( 'post_type' => $pt )
				);
			}
		}

		// Build tables deletion code
		$tables_code = '';
		if ( ! empty( $tables ) ) {
			$tables_code = "\nglobal \$wpdb;\n";
			foreach ( $tables as $table ) {
				$tables_code .= "\$wpdb->query( \"DROP TABLE IF EXISTS {\$wpdb->prefix}{$table}\" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery\n";
			}
		}

		// Build user meta deletion code
		$user_meta_code = '';
		if ( ! empty( $user_meta_keys ) ) {
			foreach ( $user_meta_keys as $meta_key ) {
				$user_meta_code .= "delete_metadata( 'user', 0, '{$meta_key}', '', true );\n";
			}
		}

		// Build transients deletion code
		$transients_code = '';
		if ( ! empty( $transients ) ) {
			foreach ( $transients as $transient ) {
				$transients_code .= "delete_transient( '{$transient}' );\n";
			}
		}

		$code = $this->load_template(
			'uninstall-full.php.template',
			array(
				'prefix'          => $prefix,
				'options_code'    => $options_code,
				'transients_code' => $transients_code,
				'user_meta_code'  => $user_meta_code,
				'cpt_code'        => $cpt_code,
				'tables_code'     => $tables_code,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'file_name' => 'uninstall.php',
		);
	}

	/**
	 * Tool: Generate AJAX Handler
	 */
	private function tool_generate_ajax( array $args ): array {
		$action     = $args['action'] ?? '';
		$prefix     = $args['prefix'] ?? '';
		$public     = $args['public'] ?? false;
		$capability = $args['capability'] ?? '';
		$parameters = $args['parameters'] ?? array();

		if ( empty( $action ) || empty( $prefix ) ) {
			return array( 'error' => 'action and prefix are required' );
		}

		// Build parameters validation code
		$params_code = '';
		foreach ( $parameters as $param ) {
			$name      = $param['name'] ?? '';
			$type      = $param['type'] ?? 'string';
			$required  = $param['required'] ?? false;
			$sanitizer = $param['sanitizer'] ?? 'sanitize_text_field';

			$params_code .= "\t\${\$name} = isset( \$_POST['{$name}'] ) ? {$sanitizer}( wp_unslash( \$_POST['{$name}'] ) ) : '';\n";

			if ( $required ) {
				$params_code .= $this->load_template(
					'ajax-param-required.php.template',
					array(
						'name'   => $name,
						'prefix' => $prefix,
					)
				);
			}
		}

		$capability_check = ! empty( $capability )
			? $this->load_template(
				'ajax-capability-check.php.template',
				array(
					'capability' => $capability,
					'prefix'     => $prefix,
				)
			)
			: '';

		$public_hook = $public
			? "\nadd_action( 'wp_ajax_nopriv_{$action}', '{$prefix}_ajax_{$action}' );"
			: '';

		$code = $this->load_template(
			'ajax-handler.php.template',
			array(
				'action'           => $action,
				'prefix'           => $prefix,
				'capability_check' => $capability_check,
				'params_code'      => $params_code,
				'public_hook'      => $public_hook,
			)
		);

		return array(
			'success'   => true,
			'code'      => $code,
			'action'    => $action,
			'file_name' => "includes/ajax-{$action}.php",
		);
	}

	/**
	 * Tool: Generate Database Table
	 */
	private function tool_generate_table( array $args ): array {
		$table_name  = $args['table_name'] ?? '';
		$prefix      = $args['prefix'] ?? '';
		$columns     = $args['columns'] ?? array();
		$primary_key = $args['primary_key'] ?? 'id';
		$indexes     = $args['indexes'] ?? array();

		if ( empty( $table_name ) || empty( $prefix ) || empty( $columns ) ) {
			return array( 'error' => 'table_name, prefix, columns, and primary_key are required' );
		}

		// Build columns SQL
		$columns_sql = array();
		foreach ( $columns as $col ) {
			$name    = $col['name'] ?? '';
			$type    = strtoupper( $col['type'] ?? 'VARCHAR(255)' );
			$null    = isset( $col['null'] ) && $col['null'] ? 'NULL' : 'NOT NULL';
			$default = isset( $col['default'] ) ? "DEFAULT '{$col['default']}'" : '';

			$columns_sql[] = "\t{$name} {$type} {$null} {$default}";
		}
		$columns_str = implode( ",\n", $columns_sql );

		// Build indexes
		$indexes_sql = '';
		foreach ( $indexes as $index_col ) {
			$indexes_sql .= ",\n\tKEY {$index_col} ({$index_col})";
		}

		$code = $this->load_template(
			'database-table.php.template',
			array(
				'table_name'  => $table_name,
				'prefix'      => $prefix,
				'columns_str' => $columns_str,
				'primary_key' => $primary_key,
				'indexes_sql' => $indexes_sql,
			)
		);

		return array(
			'success'    => true,
			'code'       => $code,
			'table_name' => $table_name,
			'file_name'  => "includes/database-{$table_name}.php",
		);
	}

	/**
	 * Tool: Validate Plugin Code
	 */
	private function tool_validate_code( array $args ): array {
		$code = $args['code'] ?? '';

		if ( empty( $code ) ) {
			return array( 'error' => 'code is required' );
		}

		$issues   = array();
		$warnings = array();

		// Check for PHP opening tag
		if ( strpos( $code, '<?php' ) !== 0 ) {
			$issues[] = 'Must start with <?php';
		}

		// Check for ABSPATH check
		if ( strpos( $code, "defined( 'ABSPATH' )" ) === false &&
			strpos( $code, 'defined( "ABSPATH" )' ) === false ) {
			$issues[] = 'Missing ABSPATH security check';
		}

		// Check for proper escaping
		if ( preg_match( '/echo\s+\$[^;]+;/', $code ) && ! preg_match( '/esc_|wp_kses/', $code ) ) {
			$warnings[] = 'Consider using escaping functions (esc_html, esc_attr, etc.) for output';
		}

		// Check for direct $_POST/$_GET without sanitization
		if ( preg_match( '/\$_(POST|GET|REQUEST)\s*\[/', $code ) ) {
			if ( ! preg_match( '/sanitize_|absint|intval|wp_unslash/', $code ) ) {
				$warnings[] = 'Sanitize superglobal inputs ($_POST, $_GET, $_REQUEST)';
			}
			if ( ! preg_match( '/wp_verify_nonce|check_ajax_referer|check_admin_referer/', $code ) ) {
				$warnings[] = 'Consider adding nonce verification for form submissions';
			}
		}

		// Check for direct database queries
		if ( preg_match( '/\$wpdb->query\s*\(/', $code ) && ! preg_match( '/\$wpdb->prepare/', $code ) ) {
			$issues[] = 'Use $wpdb->prepare() for database queries to prevent SQL injection';
		}

		// PHP syntax check using tokenizer
		$syntax_error = $this->validate_php_syntax( $code );
		if ( $syntax_error ) {
			$issues[] = 'PHP syntax error: ' . $syntax_error;
		}

		$is_valid = empty( $issues );

		return array(
			'valid'    => $is_valid,
			'issues'   => $issues,
			'warnings' => $warnings,
			'message'  => $is_valid ? 'Code passes validation!' : 'Code has issues that need to be fixed.',
		);
	}

	/**
	 * Validate PHP syntax using tokenizer
	 */
	private function validate_php_syntax( string $code ): ?string {
		$last_error = null;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for syntax validation.
		set_error_handler(
			function ( $errno, $errstr ) use ( &$last_error ) {
				$last_error = $errstr;
			}
		);

		@token_get_all( $code );

		restore_error_handler();

		if ( $last_error ) {
			return $last_error;
		}

		// Check bracket balance
		$brackets = array( '{' => 0, '(' => 0, '[' => 0 );
		$pairs    = array( '}' => '{', ')' => '(', ']' => '[' );

		for ( $i = 0; $i < strlen( $code ); $i++ ) {
			$char = $code[ $i ];
			if ( isset( $brackets[ $char ] ) ) {
				++$brackets[ $char ];
			} elseif ( isset( $pairs[ $char ] ) ) {
				--$brackets[ $pairs[ $char ] ];
			}
		}

		foreach ( $brackets as $bracket => $count ) {
			if ( $count !== 0 ) {
				return "Unbalanced {$bracket} brackets";
			}
		}

		return null;
	}

	/**
	 * Tool: Save Plugin File
	 */
	private function tool_save_file( array $args ): array {
		$plugin_slug = sanitize_file_name( $args['plugin_slug'] ?? '' );
		$file_path   = $args['file_path'] ?? '';
		$content     = $args['content'] ?? '';
		$overwrite   = $args['overwrite'] ?? false;

		if ( empty( $plugin_slug ) || empty( $file_path ) || empty( $content ) ) {
			return array( 'error' => 'plugin_slug, file_path, and content are required' );
		}

		// Sanitize file path
		$file_path = str_replace( '..', '', $file_path );
		$file_path = ltrim( $file_path, '/' );

		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
		$full_path  = $plugin_dir . '/' . $file_path;

		// Check if file exists and overwrite is disabled
		if ( file_exists( $full_path ) && ! $overwrite ) {
			return array(
				'error' => "File already exists: {$file_path}. Set overwrite=true to replace.",
			);
		}

		// Create directory if needed
		$dir = dirname( $full_path );
		if ( ! wp_mkdir_p( $dir ) ) {
			return array( 'error' => 'Failed to create directory: ' . $dir );
		}

		// Write file using WP_Filesystem
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$result = $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE );
		if ( $result === false ) {
			return array( 'error' => 'Failed to write file: ' . $file_path );
		}

		return array(
			'success'     => true,
			'plugin_slug' => $plugin_slug,
			'file_path'   => $file_path,
			'full_path'   => $full_path,
			'size'        => strlen( $content ) . ' bytes',
		);
	}
}

// Register the agent.
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Plugin_Builder() );
	}
);
