<?php
/**
 * Abilities Bridge - Connects Agent Builder tools to the WordPress Abilities API
 *
 * On WordPress 6.9+, registers Agent Builder core tools as WordPress abilities
 * and ingests third-party abilities as agent tools. On older WordPress versions,
 * this class is never loaded.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.9.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Bridge between Agent Builder tools and the WordPress Abilities API (6.9+).
 *
 * Two-way integration:
 * 1. Registers Agent Builder core tools as WP abilities (outbound)
 *    → Makes tools discoverable via MCP, REST API, Command Palette
 * 2. Ingests third-party WP abilities as agent tools (inbound)
 *    → Agents can use abilities registered by other plugins
 */
class Abilities_Bridge {

	/**
	 * Agent tools instance for executing core tools.
	 *
	 * @var Agent_Tools
	 */
	private Agent_Tools $tools;

	/**
	 * Ability namespace prefix.
	 */
	private const NAMESPACE_PREFIX = 'agent-builder/';

	/**
	 * Category slugs.
	 */
	private const CATEGORY_READ  = 'agent-builder-read';
	private const CATEGORY_WRITE = 'agent-builder-write';

	/**
	 * Annotations metadata for each core tool.
	 *
	 * @var array<string, array{readonly: bool, destructive: bool, idempotent: bool}>
	 */
	private const TOOL_ANNOTATIONS = array(
		'db_get_option'   => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_posts'    => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_post'     => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_users'    => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_terms'    => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_post_meta' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_get_comments' => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_update_option' => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_create_post'  => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		),
		'db_update_post'  => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		),
		'db_delete_post'  => array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		),
	);

	/**
	 * Constructor.
	 *
	 * @param Agent_Tools $tools Agent tools instance.
	 */
	public function __construct( Agent_Tools $tools ) {
		$this->tools = $tools;
	}

	/**
	 * Register hooks for the Abilities API.
	 *
	 * Called only when wp_register_ability() exists (WP 6.9+).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_tools_as_abilities' ) );
	}

	/**
	 * Register ability categories for Agent Builder tools.
	 *
	 * @return void
	 */
	public function register_categories(): void {
		wp_register_ability_category(
			self::CATEGORY_READ,
			array(
				'label'       => __( 'Agent Builder — Read', 'agentbuilder' ),
				'description' => __( 'Read-only WordPress data access tools from the Agent Builder plugin.', 'agentbuilder' ),
			)
		);

		wp_register_ability_category(
			self::CATEGORY_WRITE,
			array(
				'label'       => __( 'Agent Builder — Write', 'agentbuilder' ),
				'description' => __( 'WordPress data modification tools from the Agent Builder plugin.', 'agentbuilder' ),
			)
		);
	}

	/**
	 * Register all enabled core tools as WordPress abilities.
	 *
	 * Disabled tools are NOT registered — respects admin's choices.
	 *
	 * @return void
	 */
	public function register_tools_as_abilities(): void {
		$tool_definitions = $this->tools->get_tool_definitions();

		foreach ( $tool_definitions as $tool ) {
			$tool_name = $tool['function']['name'] ?? '';
			if ( empty( $tool_name ) ) {
				continue;
			}

			$ability_args = $this->tool_to_ability_args( $tool );
			if ( $ability_args ) {
				$ability_name = self::NAMESPACE_PREFIX . str_replace( '_', '-', $tool_name );
				wp_register_ability( $ability_name, $ability_args );
			}
		}
	}

	/**
	 * Convert an OpenAI function-calling tool definition to WP ability args.
	 *
	 * @param array $tool Tool definition in OpenAI format.
	 * @return array|null Ability args array, or null if invalid.
	 */
	public function tool_to_ability_args( array $tool ): ?array {
		$function  = $tool['function'] ?? array();
		$tool_name = $function['name'] ?? '';

		if ( empty( $tool_name ) ) {
			return null;
		}

		$description = $function['description'] ?? '';
		$parameters  = $function['parameters'] ?? array();

		// Determine category from tool name.
		$is_write = str_starts_with( $tool_name, 'db_update_' )
			|| str_starts_with( $tool_name, 'db_create_' )
			|| str_starts_with( $tool_name, 'db_delete_' );
		$category = $is_write ? self::CATEGORY_WRITE : self::CATEGORY_READ;

		// Build human-readable label from tool name.
		$label = $this->tool_name_to_label( $tool_name );

		// Convert OpenAI parameters to JSON Schema input_schema.
		$input_schema = $this->openai_params_to_input_schema( $parameters );

		// Get annotations for this tool.
		$annotations = self::TOOL_ANNOTATIONS[ $tool_name ] ?? array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);

		// Build the capability requirement based on write vs read.
		$required_cap = $is_write ? 'manage_options' : 'edit_posts';

		// Capture tool name for closures.
		$captured_tool_name = $tool_name;
		$captured_tools     = $this->tools;

		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => $category,
			'execute_callback'    => function ( $input ) use ( $captured_tool_name, $captured_tools ) {
				$arguments = is_array( $input ) ? $input : array();
				$result    = $captured_tools->execute( $captured_tool_name, $arguments, 'abilities-api' );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return $result;
			},
			'permission_callback' => function () use ( $required_cap ) {
				return current_user_can( $required_cap );
			},
			'input_schema'        => $input_schema,
			'meta'                => array(
				'annotations'  => $annotations,
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * Get third-party abilities as OpenAI function-calling tool definitions.
	 *
	 * Queries all registered WP abilities, filters out our own (agent-builder/*),
	 * and converts them to the OpenAI tool format that agents consume.
	 *
	 * @return array Array of tool definitions in OpenAI function-calling format.
	 */
	public function get_third_party_abilities_as_tools(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();
		$tools     = array();

		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();

			// Skip our own abilities — they're already core tools.
			if ( str_starts_with( $name, self::NAMESPACE_PREFIX ) ) {
				continue;
			}

			$tool_def = $this->ability_to_tool_definition( $ability );
			if ( $tool_def ) {
				$tools[] = $tool_def;
			}
		}

		return $tools;
	}

	/**
	 * Convert a WP_Ability to an OpenAI function-calling tool definition.
	 *
	 * @param \WP_Ability $ability The ability instance.
	 * @return array|null Tool definition, or null if conversion fails.
	 */
	public function ability_to_tool_definition( \WP_Ability $ability ): ?array {
		$name        = $ability->get_name();
		$label       = $ability->get_label();
		$description = $ability->get_description();

		// Convert ability name to a valid function name for OpenAI.
		// e.g., "my-plugin/export-users" → "my_plugin__export_users"
		$function_name = $this->ability_name_to_function_name( $name );

		if ( empty( $function_name ) ) {
			return null;
		}

		// Convert input_schema to OpenAI parameters format.
		$input_schema = $ability->get_input_schema();
		$parameters   = $this->input_schema_to_openai_params( $input_schema );

		// Build description with label context.
		$full_description = $description;
		if ( $label && $label !== $description ) {
			$full_description = $label . ': ' . $description;
		}

		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $function_name,
				'description' => $full_description,
				'parameters'  => $parameters,
			),
			// Store the original ability name for execution routing.
			'_ability_name' => $name,
		);
	}

	/**
	 * Execute a third-party ability by its function name.
	 *
	 * Called when an agent uses a tool that originated from a WP ability.
	 *
	 * @param string $function_name The OpenAI function name.
	 * @param array  $arguments     Tool arguments.
	 * @return array|null Result array, or null if the ability is not found.
	 */
	public function execute_ability( string $function_name, array $arguments ): ?array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return null;
		}

		// Find the ability by scanning registered abilities.
		$abilities = wp_get_abilities();

		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();

			// Skip our own.
			if ( str_starts_with( $name, self::NAMESPACE_PREFIX ) ) {
				continue;
			}

			if ( $this->ability_name_to_function_name( $name ) === $function_name ) {
				// Check permissions before executing.
				$permission = $ability->check_permissions( $arguments ?: null );

				if ( is_wp_error( $permission ) ) {
					return array( 'error' => $permission->get_error_message() );
				}

				if ( false === $permission ) {
					return array( 'error' => 'Permission denied for ability: ' . $name );
				}

				// Execute the ability.
				$result = $ability->execute( $arguments ?: null );

				if ( is_wp_error( $result ) ) {
					return array( 'error' => $result->get_error_message() );
				}

				// Normalize result to array.
				if ( is_array( $result ) ) {
					return $result;
				}

				return array( 'result' => $result );
			}
		}

		return null;
	}

	/**
	 * Get all third-party ability function names for routing.
	 *
	 * @return array<string> Function names of third-party abilities.
	 */
	public function get_third_party_function_names(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$names     = array();
		$abilities = wp_get_abilities();

		foreach ( $abilities as $ability ) {
			$ability_name = $ability->get_name();

			if ( str_starts_with( $ability_name, self::NAMESPACE_PREFIX ) ) {
				continue;
			}

			$fn = $this->ability_name_to_function_name( $ability_name );
			if ( $fn ) {
				$names[] = $fn;
			}
		}

		return $names;
	}

	// -------------------------------------------------------------------------
	// Schema conversion helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert OpenAI function parameters to JSON Schema input_schema.
	 *
	 * OpenAI format:
	 *   { type: "object", properties: { name: { type: "string", description: "..." } }, required: ["name"] }
	 *
	 * WP ability input_schema is JSON Schema v4. The formats are almost identical;
	 * the main difference is that OpenAI wraps everything in a `parameters` key.
	 *
	 * @param array $parameters OpenAI parameters object.
	 * @return array JSON Schema for the ability's input.
	 */
	private function openai_params_to_input_schema( array $parameters ): array {
		if ( empty( $parameters ) ) {
			return array(
				'type' => 'object',
			);
		}

		// OpenAI parameters ARE already JSON Schema objects — pass through.
		// Just ensure the type is set.
		$schema = $parameters;
		if ( ! isset( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}

		return $schema;
	}

	/**
	 * Convert WP ability input_schema to OpenAI function parameters.
	 *
	 * @param array $input_schema JSON Schema from the ability.
	 * @return array OpenAI parameters object.
	 */
	private function input_schema_to_openai_params( array $input_schema ): array {
		if ( empty( $input_schema ) ) {
			return array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			);
		}

		// If the schema is a simple type (string, integer), wrap in object.
		$type = $input_schema['type'] ?? 'object';

		if ( 'object' !== $type ) {
			return array(
				'type'       => 'object',
				'properties' => array(
					'input' => $input_schema,
				),
				'required'   => ! empty( $input_schema['required'] ) ? array( 'input' ) : array(),
			);
		}

		// Already an object schema — use as-is.
		$params = $input_schema;

		// Ensure properties exist (OpenAI requires it).
		if ( ! isset( $params['properties'] ) || empty( $params['properties'] ) ) {
			$params['properties'] = new \stdClass();
		}

		return $params;
	}

	/**
	 * Convert a tool name to a human-readable label.
	 *
	 * e.g., "db_get_option" → "Get Option"
	 *
	 * @param string $tool_name Tool name.
	 * @return string Human-readable label.
	 */
	private function tool_name_to_label( string $tool_name ): string {
		// Remove the db_ prefix.
		$clean = preg_replace( '/^db_/', '', $tool_name );

		// Convert snake_case to Title Case.
		return ucwords( str_replace( '_', ' ', $clean ) );
	}

	/**
	 * Convert a WP ability name to a valid OpenAI function name.
	 *
	 * OpenAI function names must match: ^[a-zA-Z0-9_-]+$
	 * WP ability names use slashes: "my-plugin/export-users"
	 *
	 * @param string $ability_name WP ability name.
	 * @return string Valid function name.
	 */
	private function ability_name_to_function_name( string $ability_name ): string {
		// Replace / with __ and - with _
		return str_replace(
			array( '/', '-' ),
			array( '__', '_' ),
			$ability_name
		);
	}
}
