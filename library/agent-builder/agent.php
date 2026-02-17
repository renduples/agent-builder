<?php
/**
 * Agent Name: Agent Builder
 * Version: 1.1.0
 * Description: Meta-agent that creates new Agents from natural language descriptions. Generates compliant agent code, tools, and documentation.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Developer
 * Tags: meta, generator, scaffold, builder, development, agent-creation
 * Capabilities: manage_options
 * Icon: 🏗️
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent Builder - The Meta-Agent
 *
 * Creates new agents that comply with the Agent Builder architecture.
 * Given a description of what an agent should do, it generates:
 * - Complete agent.php file
 * - System prompt
 * - Tool definitions
 * - README documentation
 */
class Agentic_Agent_Builder extends \Agentic\Agent_Base {

	/**
	 * Get system prompt from template file
	 */
	private function load_system_prompt(): string {
		$template_file = __DIR__ . '/templates/system-prompt.txt';
		if ( file_exists( $template_file ) ) {
			return file_get_contents( $template_file );
		}
		// Fallback if template file not found
		return 'You are the Agent Builder agent.';
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'agent-builder';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Agent Builder';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Creates new Agents from natural language descriptions.';
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
		return '🏗️';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'Developer';
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
		return "I build new AI agents for Wordpress!\n\n" .
				"Tell me what kind of agent you need and I'll:\n" .
				"- Generate compliant code\n" .
				"- Design its tools and capabilities\n" .
				"- Help test and write documentation\n\n" .
				'What agent would you like to build?';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'Create an agent that manages WordPress backups',
			'Build an image optimization agent for media library',
			'Design an agent for managing redirects',
			'Show me the existing agents in the library',
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
					'name'        => 'analyze_requirements',
					'description' => 'Analyze a natural language description and return a structured agent design specification',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'description' => array(
								'type'        => 'string',
								'description' => 'Natural language description of what the agent should do',
							),
							'category'    => array(
								'type'        => 'string',
								'enum'        => array( 'Content', 'Admin', 'E-commerce', 'Frontend', 'Developer', 'Marketing' ),
								'description' => 'Agent category (optional, will be inferred if not provided)',
							),
						),
						'required'   => array( 'description' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_agent',
					'description' => 'Generate complete agent PHP code from a specification',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'                => array(
								'type'        => 'string',
								'description' => 'Agent display name (e.g., "Backup Manager")',
							),
							'slug'                => array(
								'type'        => 'string',
								'description' => 'Agent slug/ID (e.g., "backup-manager")',
							),
							'description'         => array(
								'type'        => 'string',
								'description' => 'Brief description (max 160 chars)',
							),
							'category'            => array(
								'type'        => 'string',
								'enum'        => array( 'Content', 'Admin', 'E-commerce', 'Frontend', 'Developer', 'Marketing' ),
								'description' => 'Agent category',
							),
							'icon'                => array(
								'type'        => 'string',
								'description' => 'Emoji icon for the agent',
							),
							'capabilities'        => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Required WordPress capabilities',
							),
							'tools'               => array(
								'type'        => 'array',
								'description' => 'Array of tool definitions',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'        => array( 'type' => 'string' ),
										'description' => array( 'type' => 'string' ),
										'parameters'  => array(
											'type'  => 'array',
											'items' => array(
												'type' => 'object',
												'properties' => array(
													'name' => array( 'type' => 'string' ),
													'type' => array( 'type' => 'string' ),
													'description' => array( 'type' => 'string' ),
													'required' => array( 'type' => 'boolean' ),
													'enum' => array( 'type' => 'array' ),
												),
											),
										),
									),
								),
							),
							'system_prompt_focus' => array(
								'type'        => 'string',
								'description' => 'Key areas of expertise for the system prompt',
							),
							'suggested_prompts'   => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Example prompts for users (4 max)',
							),
						),
						'required'   => array( 'name', 'slug', 'description', 'category', 'tools' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'validate_agent_code',
					'description' => 'Validate agent PHP code for syntax and compliance',
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
					'name'        => 'create_agent_files',
					'description' => 'Create agent files in the library directory',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'slug'           => array(
								'type'        => 'string',
								'description' => 'Agent slug (directory name)',
							),
							'agent_code'     => array(
								'type'        => 'string',
								'description' => 'Complete agent.php content',
							),
							'readme_content' => array(
								'type'        => 'string',
								'description' => 'README.md content (optional)',
							),
							'overwrite'      => array(
								'type'        => 'boolean',
								'description' => 'Overwrite if exists (default: false)',
							),
						),
						'required'   => array( 'slug', 'agent_code' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_library_agents',
					'description' => 'List all agents in the library for reference',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'include_code_sample' => array(
								'type'        => 'boolean',
								'description' => 'Include code snippets from each agent',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'read_agent_source',
					'description' => 'Read the source code of an existing agent for reference',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'agent_slug' => array(
								'type'        => 'string',
								'description' => 'Agent slug to read (e.g., "content-builder")',
							),
						),
						'required'   => array( 'agent_slug' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_agent_template',
					'description' => 'Get a blank agent template with all required components',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'minimal' => array(
								'type'        => 'boolean',
								'description' => 'Return minimal template vs full template with examples',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_tool_schema',
					'description' => 'Generate OpenAI function-calling schema for a tool from a structured specification',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'tool_name'        => array(
								'type'        => 'string',
								'description' => 'Tool function name (snake_case)',
							),
							'tool_description' => array(
								'type'        => 'string',
								'description' => 'What the tool does',
							),
							'parameters'       => array(
								'type'        => 'array',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'name'        => array( 'type' => 'string' ),
										'type'        => array( 'type' => 'string' ),
										'description' => array( 'type' => 'string' ),
										'required'    => array( 'type' => 'boolean' ),
										'enum'        => array( 'type' => 'array' ),
									),
								),
								'description' => 'Array of parameter definitions',
							),
						),
						'required'   => array( 'tool_name', 'tool_description' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_system_prompt',
					'description' => 'Generate a system prompt for an agent given its purpose',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'agent_name'  => array(
								'type'        => 'string',
								'description' => 'Agent name',
							),
							'purpose'     => array(
								'type'        => 'string',
								'description' => 'What the agent does and its expertise areas',
							),
							'personality' => array(
								'type'        => 'string',
								'description' => 'Agent personality traits (helpful, strict, casual, etc.)',
							),
							'constraints' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Things the agent should NOT do',
							),
							'tool_names'  => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Names of tools available to the agent',
							),
						),
						'required'   => array( 'agent_name', 'purpose' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'delete_agent',
					'description' => 'Delete an agent from the library (use with caution)',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'slug'    => array(
								'type'        => 'string',
								'description' => 'Agent slug to delete',
							),
							'confirm' => array(
								'type'        => 'boolean',
								'description' => 'Must be true to confirm deletion',
							),
						),
						'required'   => array( 'slug', 'confirm' ),
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
			'analyze_requirements'  => $this->tool_analyze_requirements( $arguments ),
			'generate_agent'        => $this->tool_generate_agent( $arguments ),
			'validate_agent_code'   => $this->tool_validate_agent_code( $arguments ),
			'create_agent_files'    => $this->tool_create_agent_files( $arguments ),
			'list_library_agents'   => $this->tool_list_library_agents( $arguments ),
			'read_agent_source'     => $this->tool_read_agent_source( $arguments ),
			'get_agent_template'    => $this->tool_get_agent_template( $arguments ),
			'generate_tool_schema'  => $this->tool_generate_tool_schema( $arguments ),
			'generate_system_prompt'=> $this->tool_generate_system_prompt( $arguments ),
			'delete_agent'          => $this->tool_delete_agent( $arguments ),
			default                 => array( 'error' => 'Unknown tool: ' . $tool_name ),
		};
	}

	/**
	 * Get library path
	 */
	private function get_library_path(): string {
		return AGENT_BUILDER_DIR . 'library/';
	}

	/**
	 * Analyze requirements and suggest agent design
	 */
	private function tool_analyze_requirements( array $args ): array {
		$description = $args['description'] ?? '';
		$category    = $args['category'] ?? null;

		if ( empty( $description ) ) {
			return array( 'error' => 'Description is required' );
		}

		// Infer category if not provided
		if ( ! $category ) {
			$category = $this->infer_category( $description );
		}

		// Analyze keywords for tool suggestions
		$keywords        = $this->extract_keywords( $description );
		$suggested_tools = $this->suggest_tools( $keywords, $description );
		$capabilities    = $this->suggest_capabilities( $description, $category );

		// Generate slug from description
		$words          = array_slice( explode( ' ', preg_replace( '/[^a-z\s]/', '', strtolower( $description ) ) ), 0, 3 );
		$suggested_slug = implode( '-', array_filter( $words ) );

		return array(
			'analysis'    => array(
				'original_description' => $description,
				'inferred_category'    => $category,
				'keywords'             => $keywords,
			),
			'suggestions' => array(
				'name'         => $this->suggest_name( $description ),
				'slug'         => $suggested_slug,
				'icon'         => $this->suggest_icon( $category, $keywords ),
				'tools'        => $suggested_tools,
				'capabilities' => $capabilities,
			),
			'next_step'   => 'Use generate_agent with the refined specifications to create the agent code.',
		);
	}

	/**
	 * Infer category from description
	 */
	private function infer_category( string $description ): string {
		$desc_lower = strtolower( $description );

		$patterns = array(
			'Content'    => array( 'content', 'post', 'page', 'seo', 'writing', 'draft', 'media', 'image' ),
			'Admin'      => array( 'security', 'backup', 'performance', 'monitor', 'admin', 'database', 'maintenance' ),
			'E-commerce' => array( 'product', 'woocommerce', 'shop', 'order', 'inventory', 'payment', 'cart' ),
			'Frontend'   => array( 'visitor', 'chat', 'comment', 'form', 'user', 'support', 'contact' ),
			'Developer'  => array( 'code', 'debug', 'theme', 'plugin', 'scaffold', 'generate', 'build' ),
			'Marketing'  => array( 'social', 'campaign', 'email', 'newsletter', 'marketing', 'analytics' ),
		);

		foreach ( $patterns as $category => $terms ) {
			foreach ( $terms as $term ) {
				if ( strpos( $desc_lower, $term ) !== false ) {
					return $category;
				}
			}
		}

		return 'Admin';
	}

	/**
	 * Extract keywords from description
	 */
	private function extract_keywords( string $description ): array {
		$stop_words = array(
			'a',
			'an',
			'the',
			'and',
			'or',
			'but',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'being',
			'have',
			'has',
			'had',
			'do',
			'does',
			'did',
			'will',
			'would',
			'could',
			'should',
			'may',
			'might',
			'must',
			'that',
			'which',
			'who',
			'whom',
			'this',
			'these',
			'those',
			'i',
			'you',
			'he',
			'she',
			'it',
			'we',
			'they',
			'what',
			'for',
			'to',
			'from',
			'with',
			'without',
			'in',
			'on',
			'at',
			'by',
			'about',
			'agent',
			'create',
			'build',
			'make',
			'help',
			'can',
			'want',
			'need',
		);

		$words    = preg_split( '/\W+/', strtolower( $description ) );
		$keywords = array();

		foreach ( $words as $word ) {
			if ( strlen( $word ) > 2 && ! in_array( $word, $stop_words, true ) ) {
				$keywords[] = $word;
			}
		}

		return array_unique( $keywords );
	}

	/**
	 * Suggest tools based on keywords
	 */
	private function suggest_tools( array $keywords, string $description ): array {
		$tool_patterns = array(
			'list'    => array( 'list', 'show', 'display', 'view', 'get' ),
			'create'  => array( 'create', 'add', 'new', 'generate', 'make' ),
			'update'  => array( 'update', 'edit', 'modify', 'change' ),
			'delete'  => array( 'delete', 'remove', 'clear' ),
			'analyze' => array( 'analyze', 'check', 'scan', 'audit', 'review' ),
			'export'  => array( 'export', 'download', 'backup' ),
			'import'  => array( 'import', 'upload', 'restore' ),
			'config'  => array( 'settings', 'configure', 'options' ),
		);

		$suggested  = array();
		$desc_lower = strtolower( $description );

		// Always include a list/get tool
		$suggested[] = array(
			'name'        => 'list_items',
			'description' => 'List all items managed by this agent',
			'parameters'  => array(
				array(
					'name'        => 'limit',
					'type'        => 'integer',
					'description' => 'Maximum items to return',
					'required'    => false,
				),
			),
		);

		foreach ( $tool_patterns as $action => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( strpos( $desc_lower, $pattern ) !== false ) {
					if ( $action === 'create' ) {
						$suggested[] = array(
							'name'        => 'create_item',
							'description' => 'Create a new item',
							'parameters'  => array(
								array(
									'name'        => 'name',
									'type'        => 'string',
									'description' => 'Item name',
									'required'    => true,
								),
							),
						);
					} elseif ( $action === 'analyze' ) {
						$suggested[] = array(
							'name'        => 'analyze',
							'description' => 'Analyze and provide insights',
							'parameters'  => array(
								array(
									'name'        => 'target',
									'type'        => 'string',
									'description' => 'What to analyze',
									'required'    => true,
								),
							),
						);
					} elseif ( $action === 'delete' ) {
						$suggested[] = array(
							'name'        => 'delete_item',
							'description' => 'Delete an item',
							'parameters'  => array(
								array(
									'name'        => 'id',
									'type'        => 'integer',
									'description' => 'Item ID',
									'required'    => true,
								),
								array(
									'name'        => 'confirm',
									'type'        => 'boolean',
									'description' => 'Confirm deletion',
									'required'    => true,
								),
							),
						);
					}
					break;
				}
			}
		}

		// Add a get_details tool
		$suggested[] = array(
			'name'        => 'get_details',
			'description' => 'Get detailed information about a specific item',
			'parameters'  => array(
				array(
					'name'        => 'id',
					'type'        => 'integer',
					'description' => 'Item ID',
					'required'    => true,
				),
			),
		);

		return array_slice( $suggested, 0, 6 );
	}

	/**
	 * Suggest capabilities
	 */
	private function suggest_capabilities( string $description, string $category ): array {
		$desc_lower = strtolower( $description );

		$base_caps = match ( $category ) {
			'Content'    => array( 'edit_posts' ),
			'Admin'      => array( 'manage_options' ),
			'E-commerce' => array( 'manage_woocommerce' ),
			'Frontend'   => array( 'moderate_comments' ),
			'Developer'  => array( 'edit_themes', 'edit_plugins' ),
			'Marketing'  => array( 'publish_posts' ),
			default      => array( 'read' ),
		};

		if ( strpos( $desc_lower, 'delete' ) !== false ) {
			$base_caps[] = 'delete_posts';
		}
		if ( strpos( $desc_lower, 'user' ) !== false ) {
			$base_caps[] = 'list_users';
		}
		if ( strpos( $desc_lower, 'media' ) !== false || strpos( $desc_lower, 'image' ) !== false ) {
			$base_caps[] = 'upload_files';
		}

		return array_unique( $base_caps );
	}

	/**
	 * Suggest name from description
	 */
	private function suggest_name( string $description ): string {
		// Extract key nouns
		$words     = explode( ' ', $description );
		$key_words = array();

		foreach ( $words as $word ) {
			$word = preg_replace( '/[^a-zA-Z]/', '', $word );
			if ( strlen( $word ) > 3 && ! in_array( strtolower( $word ), array( 'that', 'this', 'with', 'agent', 'creates', 'manages', 'handles' ), true ) ) {
				$key_words[] = ucfirst( strtolower( $word ) );
				if ( count( $key_words ) >= 2 ) {
					break;
				}
			}
		}

		if ( empty( $key_words ) ) {
			return 'Custom Agent';
		}

		return implode( ' ', $key_words ) . ' Manager';
	}

	/**
	 * Suggest icon
	 */
	private function suggest_icon( string $category, array $keywords ): string {
		// Check keywords first
		$keyword_icons = array(
			'backup'      => '💾',
			'security'    => '🔒',
			'image'       => '🖼️',
			'media'       => '📸',
			'email'       => '📧',
			'social'      => '📱',
			'analytics'   => '📊',
			'performance' => '⚡',
			'seo'         => '🔍',
			'content'     => '📝',
			'product'     => '🛒',
			'user'        => '👤',
			'comment'     => '💬',
			'redirect'    => '↩️',
			'form'        => '📋',
			'cache'       => '🗄️',
			'database'    => '🗃️',
		);

		foreach ( $keywords as $keyword ) {
			if ( isset( $keyword_icons[ $keyword ] ) ) {
				return $keyword_icons[ $keyword ];
			}
		}

		// Fall back to category
		return match ( $category ) {
			'Content'    => '📝',
			'Admin'      => '⚙️',
			'E-commerce' => '🛒',
			'Frontend'   => '💬',
			'Developer'  => '🔧',
			'Marketing'  => '📢',
			default      => '🤖',
		};
	}

	/**
	 * Generate complete agent code
	 */
	private function tool_generate_agent( array $args ): array {
		$name                = $args['name'] ?? '';
		$slug                = $args['slug'] ?? sanitize_title( $name );
		$description         = $args['description'] ?? '';
		$category            = $args['category'] ?? 'Admin';
		$icon                = $args['icon'] ?? '🤖';
		$capabilities        = $args['capabilities'] ?? array( 'read' );
		$tools               = $args['tools'] ?? array();
		$system_prompt_focus = $args['system_prompt_focus'] ?? $description;
		$suggested_prompts   = $args['suggested_prompts'] ?? array();

		if ( empty( $name ) || empty( $slug ) || empty( $description ) ) {
			return array( 'error' => 'Name, slug, and description are required' );
		}

		if ( empty( $tools ) ) {
			return array( 'error' => 'At least one tool is required' );
		}

		// Generate class name
		$class_name = 'Agentic_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $slug ) ) );

		// Generate the code
		$code = $this->render_agent_code(
			array(
				'name'                => $name,
				'slug'                => $slug,
				'description'         => $description,
				'category'            => $category,
				'icon'                => $icon,
				'capabilities'        => $capabilities,
				'tools'               => $tools,
				'class_name'          => $class_name,
				'system_prompt_focus' => $system_prompt_focus,
				'suggested_prompts'   => $suggested_prompts,
			)
		);

		return array(
			'success'    => true,
			'agent_code' => $code,
			'metadata'   => array(
				'name'       => $name,
				'slug'       => $slug,
				'class_name' => $class_name,
				'tool_count' => count( $tools ),
				'category'   => $category,
			),
			'next_step'  => 'Use create_agent_files to save this agent to the library.',
		);
	}

	/**
	 * Render agent code from specification
	 */
	private function render_agent_code( array $spec ): string {
		$name                = $spec['name'];
		$slug                = $spec['slug'];
		$description         = $spec['description'];
		$category            = $spec['category'];
		$icon                = $spec['icon'];
		$capabilities        = $spec['capabilities'];
		$tools               = $spec['tools'];
		$class_name          = $spec['class_name'];
		$system_prompt_focus = $spec['system_prompt_focus'];
		$suggested_prompts   = $spec['suggested_prompts'];

		// Generate capabilities string
		$caps_str = implode( ', ', $capabilities );

		// Generate tags from keywords
		$tags = strtolower( str_replace( ' ', ', ', $name ) );

		// Build tool definitions
		$tool_defs        = $this->build_tool_definitions( $tools );
		$tool_handlers    = $this->build_tool_handlers( $tools );
		$tool_match_cases = $this->build_tool_match_cases( $tools );

		// Build suggested prompts array
		$prompts_array = $this->build_suggested_prompts_array( $suggested_prompts );

		// Build capabilities array
		$caps_array = $this->build_capabilities_array( $capabilities );

		// Generate welcome message
		$welcome_features = $this->generate_welcome_features( $tools );

		// Current date
		$date = wp_date( 'Y-m-d' );

		$code = '<?php' . "\n" .
'/**' . "\n" .
' * Agent Name: ' . $name . "\n" .
' * Version: 1.0.0' . "\n" .
' * Description: ' . $description . "\n" .
' * Author: Agentic Community' . "\n" .
' * Author URI: https://agentic-plugin.com' . "\n" .
' * Category: ' . $category . "\n" .
' * Tags: ' . $tags . "\n" .
' * Capabilities: ' . $caps_str . "\n" .
' * Icon: ' . $icon . "\n" .
' * Requires PHP: 8.1' . "\n" .
' * Requires at least: 6.4' . "\n" .
' * License: GPL v2' . "\n" .
' * Generated: ' . $date . "\n" .
' */' . "\n" .
'' . "\n" .
'if ( ! defined( \'ABSPATH\' ) ) {' . "\n" .
'    exit;' . "\n" .
'}' . "\n" .
'' . "\n" .
'/**' . "\n" .
' * ' . $name . ' Agent' . "\n" .
' *' . "\n" .
' * ' . $description . "\n" .
' */' . "\n" .
'class ' . $class_name . ' extends \\Agentic\\Agent_Base {' . "\n" .
'' . "\n" .
'    private const SYSTEM_PROMPT = \'You are the ' . addslashes($name) . ' agent for WordPress. You specialize in:' . "\n\n" .
addslashes($system_prompt_focus) . "\n\n" .
'Your role is to help users manage and automate tasks in this domain.' . "\n\n" .
'When working:' . "\n" .
'1. Always confirm destructive actions before executing' . "\n" .
'2. Provide clear feedback on what you\'ve done' . "\n" .
'3. Suggest improvements when you notice issues' . "\n" .
'4. Follow WordPress best practices' . "\n\n" .
'You have tools to help users accomplish their goals efficiently.\';' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get agent ID' . "\n" .
'     */' . "\n" .
'    public function get_id(): string {' . "\n" .
'        return \'' . $slug . '\';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get agent name' . "\n" .
'     */' . "\n" .
'    public function get_name(): string {' . "\n" .
'        return \'' . addslashes($name) . '\';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get agent description' . "\n" .
'     */' . "\n" .
'    public function get_description(): string {' . "\n" .
'        return \'' . addslashes($description) . '\';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get system prompt' . "\n" .
'     */' . "\n" .
'    public function get_system_prompt(): string {' . "\n" .
'        return self::SYSTEM_PROMPT;' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get agent icon' . "\n" .
'     */' . "\n" .
'    public function get_icon(): string {' . "\n" .
'        return \'' . $icon . '\';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get agent category' . "\n" .
'     */' . "\n" .
'    public function get_category(): string {' . "\n" .
'        return \'' . $category . '\';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get required capabilities' . "\n" .
'     */' . "\n" .
'    public function get_required_capabilities(): array {' . "\n" .
'        return ' . $caps_array . ';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get welcome message' . "\n" .
'     */'  . "\n" .
'    public function get_welcome_message(): string {' . "\n" .
'        return "' . $icon . ' **' . addslashes($name) . '**\\n\\n" .' . "\n" .
'               "' . addslashes($description) . '\\n\\n" .' . "\n" .
$welcome_features .
'               "How can I help you today?";' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get suggested prompts' . "\n" .
'     */' . "\n" .
'    public function get_suggested_prompts(): array {' . "\n" .
'        return ' . $prompts_array . ';' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Get available tools' . "\n" .
'     */' . "\n" .
'    public function get_tools(): array {' . "\n" .
'        return [' . "\n" .
$tool_defs .
'        ];' . "\n" .
'    }' . "\n" .
'' . "\n" .
'    /**' . "\n" .
'     * Execute a tool' . "\n" .
'     */' . "\n" .
'    public function execute_tool( string $tool_name, array $arguments ): ?array {' . "\n" .
'        return match ( $tool_name ) {' . "\n" .
$tool_match_cases .
'            default => [ \'error\' => \'Unknown tool: \' . $tool_name ],' . "\n" .
'        };' . "\n" .
'    }' . "\n" .
'' . "\n" .
$tool_handlers .
'}' . "\n" .
'' . "\n" .
'// Register the agent' . "\n" .
'add_action( \'agentic_register_agents\', function( $registry ) {' . "\n" .
'    $registry->register( new ' . $class_name . '() );' . "\n" .
'} );' . "\n";

		return $code;
	}

	/**
	 * Build tool definitions for get_tools()
	 */
	private function build_tool_definitions( array $tools ): string {
		$defs = array();

		foreach ( $tools as $tool ) {
			$name   = $tool['name'];
			$desc   = $tool['description'];
			$params = $tool['parameters'] ?? array();

			$props    = array();
			$required = array();

			foreach ( $params as $param ) {
				$prop  = "                            '{$param['name']}' => [\n";
				$prop .= "                                'type' => '{$param['type']}',\n";
				$prop .= "                                'description' => '{$param['description']}',\n";
				if ( isset( $param['enum'] ) && is_array( $param['enum'] ) ) {
					$enum_str = "[ '" . implode( "', '", $param['enum'] ) . "' ]";
					$prop    .= "                                'enum' => {$enum_str},\n";
				}
				$prop   .= '                            ],';
				$props[] = $prop;

				if ( ! empty( $param['required'] ) ) {
					$required[] = "'{$param['name']}'";
				}
			}

			$props_str    = implode( "\n", $props );
			$required_str = empty( $required ) ? '[]' : '[ ' . implode( ', ', $required ) . ' ]';

			$def = '            [' . "\n" .
'                \'type\' => \'function\',' . "\n" .
'                \'function\' => [' . "\n" .
'                    \'name\' => \'' . $name . '\',' . "\n" .
'                    \'description\' => \'' . addslashes($desc) . '\',' . "\n" .
'                    \'parameters\' => [' . "\n" .
'                        \'type\' => \'object\',' . "\n" .
'                        \'properties\' => [' . "\n" .
$props_str . "\n" .
'                        ],' . "\n" .
'                        \'required\' => ' . $required_str . ',' . "\n" .
'                    ],' . "\n" .
'                ],' . "\n" .
'            ],';
			$defs[] = $def;
		}

		return implode( "\n", $defs );
	}

	/**
	 * Build tool handlers
	 */
	private function build_tool_handlers( array $tools ): string {
		$handlers = array();

		foreach ( $tools as $tool ) {
			$name        = $tool['name'];
			$method_name = 'tool_' . $name;
			$desc        = $tool['description'];
			$params      = $tool['parameters'] ?? array();

			// Build parameter extraction
			$param_lines = array();
			foreach ( $params as $param ) {
				$pname   = $param['name'];
				$default = match ( $param['type'] ) {
					'string'  => "''",
					'integer' => '0',
					'number'  => '0',
					'boolean' => 'false',
					'array'   => '[]',
					default   => 'null',
				};
				$param_lines[] = "        \${$pname} = \$args['{$pname}'] ?? {$default};";
			}
			$params_code = implode( "\n", $param_lines );

			$handler = "\n" .
'    /**' . "\n" .
'     * Tool: ' . $name . "\n" .
'     *' . "\n" .
'     * ' . $desc . "\n" .
'     */' . "\n" .
'    private function ' . $method_name . '( array $args ): array {' . "\n" .
$params_code . "\n" .
'' . "\n" .
'        // TODO: Implement ' . $name . ' logic' . "\n" .
'' . "\n" .
'        return [' . "\n" .
'            \'success\' => true,' . "\n" .
'            \'message\' => \'' . addslashes($name) . ' executed successfully\',' . "\n" .
'        ];' . "\n" .
'    }';
			$handlers[] = $handler;
		}

		return implode( "\n", $handlers );
	}

	/**
	 * Build match cases for execute_tool
	 */
	private function build_tool_match_cases( array $tools ): string {
		$cases = array();

		foreach ( $tools as $tool ) {
			$name = $tool['name'];
			// Pad for alignment
			$padding = str_repeat( ' ', max( 0, 24 - strlen( $name ) ) );
			$cases[] = "            '{$name}'{$padding}=> \$this->tool_{$name}( \$arguments ),";
		}

		return implode( "\n", $cases );
	}

	/**
	 * Build suggested prompts array
	 */
	private function build_suggested_prompts_array( array $prompts ): string {
		if ( empty( $prompts ) ) {
			return '[]';
		}

		$items = array_map( fn( $p ) => "            '{$p}'", array_slice( $prompts, 0, 4 ) );
		return "[\n" . implode( ",\n", $items ) . ",\n        ]";
	}

	/**
	 * Build capabilities array
	 */
	private function build_capabilities_array( array $caps ): string {
		$items = array_map( fn( $c ) => "'{$c}'", $caps );
		return '[ ' . implode( ', ', $items ) . ' ]';
	}

	/**
	 * Generate welcome message features
	 */
	private function generate_welcome_features( array $tools ): string {
		$lines = array();
		foreach ( array_slice( $tools, 0, 4 ) as $tool ) {
			$name    = ucfirst( str_replace( '_', ' ', $tool['name'] ) );
			$lines[] = "               \"- **{$name}**\\n\" .";
		}
		return implode( "\n", $lines ) . "\n               \"\\n\" .\n";
	}

	/**
	 * Validate agent code
	 */
	private function tool_validate_agent_code( array $args ): array {
		$code = $args['code'] ?? '';

		if ( empty( $code ) ) {
			return array( 'error' => 'Code is required' );
		}

		$issues   = array();
		$warnings = array();

		// Check for PHP opening tag
		if ( strpos( $code, '<?php' ) !== 0 ) {
			$issues[] = 'Must start with <?php';
		}

		// Check for ABSPATH check
		if ( strpos( $code, "if ( ! defined( 'ABSPATH' )" ) === false ) {
			$issues[] = 'Missing ABSPATH security check';
		}

		// Check for Agent_Base extension
		if ( strpos( $code, 'extends \\Agentic\\Agent_Base' ) === false &&
			strpos( $code, 'extends \Agentic\Agent_Base' ) === false ) {
			$issues[] = 'Must extend \\Agentic\\Agent_Base';
		}

		// Check for required methods
		$required_methods = array( 'get_id', 'get_name', 'get_description', 'get_system_prompt' );
		foreach ( $required_methods as $method ) {
			if ( strpos( $code, "function {$method}(" ) === false ) {
				$issues[] = "Missing required method: {$method}()";
			}
		}

		// Check for agent registration
		if ( strpos( $code, 'agentic_register_agents' ) === false ) {
			$warnings[] = 'Consider adding agentic_register_agents hook for auto-registration';
		}

		// Check for get_tools
		if ( strpos( $code, 'function get_tools(' ) === false ) {
			$warnings[] = 'Agent has no tools defined (get_tools method missing)';
		}

		// Check for execute_tool
		if ( strpos( $code, 'function execute_tool(' ) === false ) {
			$warnings[] = 'Agent has no tool execution (execute_tool method missing)';
		}

// PHP syntax check using tokenizer (safer than exec)
	$syntax_error = $this->validate_php_syntax( $code );
	if ( $syntax_error ) {
		$issues[] = 'PHP syntax error: ' . $syntax_error;
		}

		$is_valid = empty( $issues );

		return array(
			'valid'    => $is_valid,
			'issues'   => $issues,
			'warnings' => $warnings,
			'message'  => $is_valid ? 'Agent code is valid!' : 'Agent code has issues that need to be fixed.',
		);
	}

	/**
	 * Create agent files in library
	 */
	private function tool_create_agent_files( array $args ): array {
		$slug           = sanitize_file_name( $args['slug'] ?? '' );
		$agent_code     = $args['agent_code'] ?? '';
		$readme_content = $args['readme_content'] ?? '';
		$overwrite      = $args['overwrite'] ?? false;

		if ( empty( $slug ) || empty( $agent_code ) ) {
			return array( 'error' => 'Slug and agent_code are required' );
		}

		$library_path = $this->get_library_path();
		$agent_dir    = $library_path . $slug;

		// Check if exists
		if ( is_dir( $agent_dir ) && ! $overwrite ) {
			return array(
				'error' => "Agent directory '{$slug}' already exists. Set overwrite=true to replace.",
			);
		}

		// Create directory
		if ( ! wp_mkdir_p( $agent_dir ) ) {
			return array( 'error' => 'Failed to create agent directory' );
		}

		// Write agent.php
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$result = $wp_filesystem->put_contents( $agent_dir . '/agent.php', $agent_code, FS_CHMOD_FILE );
		if ( $result === false ) {
			return array( 'error' => 'Failed to write agent.php' );
		}

		// Write README if provided
		if ( ! empty( $readme_content ) ) {
			$wp_filesystem->put_contents( $agent_dir . '/README.md', $readme_content, FS_CHMOD_FILE );
		}

		return array(
			'success'   => true,
			'slug'      => $slug,
			'directory' => $agent_dir,
			'files'     => array(
				'agent.php' => strlen( $agent_code ) . ' bytes',
				'README.md' => ! empty( $readme_content ) ? strlen( $readme_content ) . ' bytes' : 'not created',
			),
			'next_step' => 'Activate the agent in Agentic → Agents to start using it.',
		);
	}

	/**
	 * List library agents
	 */
	private function tool_list_library_agents( array $args ): array {
		$include_code = $args['include_code_sample'] ?? false;
		$library_path = $this->get_library_path();

		if ( ! is_dir( $library_path ) ) {
			return array( 'error' => 'Library directory not found' );
		}

		$agents = array();
		$dirs   = @scandir( $library_path );

		foreach ( $dirs as $dir ) {
			if ( $dir === '.' || $dir === '..' ) {
				continue;
			}

			$agent_file = $library_path . $dir . '/agent.php';
			if ( ! file_exists( $agent_file ) ) {
				continue;
			}

			$agent_info = array(
				'slug'    => $dir,
				'file'    => $agent_file,
				'headers' => $this->parse_agent_headers( $agent_file ),
			);

			if ( $include_code ) {
				$content = file_get_contents( $agent_file );
				// Extract just the class definition line and tools count
				if ( preg_match( '/class\s+(\w+)\s+extends/', $content, $matches ) ) {
					$agent_info['class_name'] = $matches[1];
				}
				$agent_info['tool_count'] = substr_count( $content, "'type' => 'function'" );
				$agent_info['line_count'] = substr_count( $content, "\n" );
			}

			$agents[] = $agent_info;
		}

		return array(
			'agents' => $agents,
			'count'  => count( $agents ),
			'path'   => $library_path,
		);
	}

	/**
	 * Parse agent headers from file
	 */
	private function parse_agent_headers( string $file_path ): array {
		$content = file_get_contents( $file_path );

		$headers = array(
			'name'        => '',
			'version'     => '',
			'description' => '',
			'category'    => '',
			'icon'        => '',
		);

		$patterns = array(
			'name'        => '/Agent Name:\s*(.+)/i',
			'version'     => '/Version:\s*(.+)/i',
			'description' => '/Description:\s*(.+)/i',
			'category'    => '/Category:\s*(.+)/i',
			'icon'        => '/Icon:\s*(.+)/i',
		);

		foreach ( $patterns as $key => $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				$headers[ $key ] = trim( $matches[1] );
			}
		}

		return $headers;
	}

	/**
	 * Read agent source code
	 */
	private function tool_read_agent_source( array $args ): array {
		$agent_slug = $args['agent_slug'] ?? '';

		if ( empty( $agent_slug ) ) {
			return array( 'error' => 'Agent slug is required' );
		}

		$agent_file = $this->get_library_path() . $agent_slug . '/agent.php';

		if ( ! file_exists( $agent_file ) ) {
			return array( 'error' => "Agent '{$agent_slug}' not found in library" );
		}

		$content = file_get_contents( $agent_file );

		return array(
			'slug'       => $agent_slug,
			'file'       => $agent_file,
			'source'     => $content,
			'line_count' => substr_count( $content, "\n" ),
			'size'       => strlen( $content ),
		);
	}

	/**
	 * Get blank agent template
	 */
	private function tool_get_agent_template( array $args ): array {
		$minimal = $args['minimal'] ?? false;

		if ( $minimal ) {
			$template_file = __DIR__ . '/templates/minimal-agent.php.template';
			$template = file_exists( $template_file ) ? file_get_contents( $template_file ) : '';
		} else {
			// Full template with examples
			$template = file_get_contents( $this->get_library_path() . 'content-builder/agent.php' );
			// Sanitize for template use
			$template = preg_replace( '/Content Assistant/', '[AGENT_NAME]', $template );
			$template = preg_replace( '/content-builder/', '[SLUG]', $template );
		}

		return array(
			'template'     => $template,
			'type'         => $minimal ? 'minimal' : 'full',
			'placeholders' => array(
				'[AGENT_NAME]'  => 'Display name of your agent',
				'[SLUG]'        => 'kebab-case-slug',
				'[CLASS_NAME]'  => 'Agentic_Your_Agent_Name',
				'[DESCRIPTION]' => 'What your agent does (160 chars max)',
				'[CATEGORY]'    => 'Content, Admin, E-commerce, Frontend, Developer, or Marketing',
			),
		);
	}

	/**
	 * Generate tool schema
	 */
	private function tool_generate_tool_schema( array $args ): array {
		$tool_name        = $args['tool_name'] ?? '';
		$tool_description = $args['tool_description'] ?? '';
		$parameters       = $args['parameters'] ?? array();

		if ( empty( $tool_name ) || empty( $tool_description ) ) {
			return array( 'error' => 'tool_name and tool_description are required' );
		}

		$properties = array();
		$required   = array();

		foreach ( $parameters as $param ) {
			$prop = array(
				'type'        => $param['type'] ?? 'string',
				'description' => $param['description'] ?? '',
			);

			if ( isset( $param['enum'] ) ) {
				$prop['enum'] = $param['enum'];
			}

			$properties[ $param['name'] ] = $prop;

			if ( ! empty( $param['required'] ) ) {
				$required[] = $param['name'];
			}
		}

		$schema = array(
			'type'     => 'function',
			'function' => array(
				'name'        => $tool_name,
				'description' => $tool_description,
				'parameters'  => array(
					'type'       => 'object',
					'properties' => $properties,
					'required'   => $required,
				),
			),
		);

		// Also generate PHP code for get_tools()
		$php_code = $this->schema_to_php( $schema );

		return array(
			'schema'   => $schema,
			'php_code' => $php_code,
		);
	}

	/**
	 * Convert schema to PHP code
	 */
	private function schema_to_php( array $schema ): string {
		$name     = $schema['function']['name'];
		$desc     = $schema['function']['description'];
		$props    = $schema['function']['parameters']['properties'];
		$required = $schema['function']['parameters']['required'];

		$props_code = array();
		foreach ( $props as $key => $prop ) {
			$prop_lines = array(
				"                        '{$key}' => [",
				"                            'type' => '{$prop['type']}',",
				"                            'description' => '{$prop['description']}',",
			);
			if ( isset( $prop['enum'] ) ) {
				$enum         = "[ '" . implode( "', '", $prop['enum'] ) . "' ]";
				$prop_lines[] = "                            'enum' => {$enum},";
			}
			$prop_lines[] = '                        ],';
			$props_code[] = implode( "\n", $prop_lines );
		}

		$props_str    = implode( "\n", $props_code );
		$required_str = empty( $required ) ? '[]' : "[ '" . implode( "', '", $required ) . "' ]";

		return '[' . "\n" .
'    \'type\' => \'function\',' . "\n" .
'    \'function\' => [' . "\n" .
'        \'name\' => \'' . $name . '\',' . "\n" .
'        \'description\' => \'' . addslashes($desc) . '\',' . "\n" .
'        \'parameters\' => [' . "\n" .
'            \'type\' => \'object\',' . "\n" .
'            \'properties\' => [' . "\n" .
$props_str . "\n" .
'            ],' . "\n" .
'            \'required\' => ' . $required_str . ',' . "\n" .
'        ],' . "\n" .
'    ],' . "\n" .
'],';
	}

	/**
	 * Generate system prompt
	 */
	private function tool_generate_system_prompt( array $args ): array {
		$agent_name  = $args['agent_name'] ?? '';
		$purpose     = $args['purpose'] ?? '';
		$personality = $args['personality'] ?? 'helpful and professional';
		$constraints = $args['constraints'] ?? array();
		$tool_names  = $args['tool_names'] ?? array();

		if ( empty( $agent_name ) || empty( $purpose ) ) {
			return array( 'error' => 'agent_name and purpose are required' );
		}

		// Build expertise section
		$expertise = $purpose;

		// Build constraints section
		$constraints_section = '';
		if ( ! empty( $constraints ) ) {
			$constraints_list    = array_map( fn( $c ) => "- {$c}", $constraints );
			$constraints_section = "\n\nYou should NEVER:\n" . implode( "\n", $constraints_list );
		}

		// Build tools section
		$tools_section = '';
		if ( ! empty( $tool_names ) ) {
			$tools_list    = array_map( fn( $t ) => "- {$t}", $tool_names );
			$tools_section = "\n\nYou have these tools available:\n" . implode( "\n", $tools_list );
		}

		$prompt = 'You are the ' . $agent_name . ' agent for WordPress. You are an expert in:' . "\n\n" .
$expertise . "\n\n" .
'Your personality: ' . $personality . "\n\n" .
'When working:' . "\n" .
'1. Always confirm destructive actions before executing' . "\n" .
'2. Provide clear feedback on what you\'ve done' . "\n" .
'3. Suggest improvements when you notice issues' . "\n" .
'4. Follow WordPress best practices' . "\n" .
'5. Be concise but thorough in explanations' . $constraints_section . $tools_section;

		return array(
			'system_prompt' => $prompt,
			'agent_name'    => $agent_name,
			'char_count'    => strlen( $prompt ),
		);
	}

	/**
	 * Delete an agent
	 */
	private function tool_delete_agent( array $args ): array {
		$slug    = $args['slug'] ?? '';
		$confirm = $args['confirm'] ?? false;

		if ( empty( $slug ) ) {
			return array( 'error' => 'Agent slug is required' );
		}

		if ( ! $confirm ) {
			return array( 'error' => 'Deletion must be confirmed by setting confirm=true' );
		}

		// Don't allow deleting built-in agents
		$protected = array( 'content-builder', 'onboarding-agent', 'agent-builder' );
		if ( in_array( $slug, $protected, true ) ) {
			return array( 'error' => "Cannot delete protected agent: {$slug}" );
		}

		$agent_dir = $this->get_library_path() . $slug;

		if ( ! is_dir( $agent_dir ) ) {
			return array( 'error' => "Agent '{$slug}' not found" );
		}

		// Delete files and directory using WP_Filesystem
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		$files = glob( $agent_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$wp_filesystem->delete( $file );
			}
		}
		$subdirs = glob( $agent_dir . '/*', GLOB_ONLYDIR );
		foreach ( $subdirs as $subdir ) {
			// Simple recursive delete for subdirs
			$this->recursive_rmdir( $subdir );
		}
		$wp_filesystem->rmdir( $agent_dir );

		return array(
			'success' => true,
			'message' => "Agent '{$slug}' deleted",
			'slug'    => $slug,
		);
	}

	/**
	 * Recursive directory removal
	 */
	private function recursive_rmdir( string $dir ): void {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		$files = glob( $dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				$this->recursive_rmdir( $file );
			} else {
				$wp_filesystem->delete( $file );
			}
		}
		$wp_filesystem->rmdir( $dir );
	}

	/**
	 * Validate PHP syntax without using exec()
	 *
	 * Uses PHP's tokenizer to detect syntax errors safely.
	 * This is a basic validation - it won't catch all errors that php -l would,
	 * but it's safe for WordPress.org and catches most common syntax issues.
	 *
	 * @param string $code PHP code to validate.
	 * @return string|null Error message if syntax is invalid, null if valid.
	 */
	private function validate_php_syntax( string $code ): ?string {
		// Basic tokenizer check
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for syntax validation.
		set_error_handler(
			function ( $errno, $errstr ) use ( &$last_error ) {
				$last_error = $errstr;
			}
		);

		$last_error = null;
		$tokens     = @token_get_all( $code );
		
		restore_error_handler();

		if ( $last_error ) {
			return $last_error;
		}

		// Check for common syntax errors in the token stream
		$bracket_stack = array();
		$line_num      = 1;

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				list( $id, $text, $line ) = $token;
				$line_num = $line;

				// Check for parse errors (T_ERROR doesn't exist in all PHP versions)
				if ( defined( 'T_ERROR' ) && $id === T_ERROR ) {
					return sprintf( 'Parse error on line %d', $line );
				}
			} elseif ( is_string( $token ) ) {
				// Track brackets for balance checking
				if ( in_array( $token, array( '{', '[', '(' ), true ) ) {
					$bracket_stack[] = $token;
				} elseif ( in_array( $token, array( '}', ']', ')' ), true ) ) {
					if ( empty( $bracket_stack ) ) {
						return sprintf( 'Unmatched closing bracket "%s" on line %d', $token, $line_num );
					}
					$last = array_pop( $bracket_stack );
					$pairs = array( '{' => '}', '[' => ']', '(' => ')' );
					if ( $pairs[ $last ] !== $token ) {
						return sprintf( 'Mismatched brackets: expected "%s" but got "%s" on line %d', $pairs[ $last ], $token, $line_num );
					}
				}
			}
		}

		// Check for unclosed brackets
		if ( ! empty( $bracket_stack ) ) {
			return sprintf( 'Unclosed bracket "%s"', end( $bracket_stack ) );
		}

		// Check if code starts with <?php
		if ( strpos( trim( $code ), '<?php' ) !== 0 ) {
			return 'Code must start with <?php tag';
		}

		// Basic class/function syntax check
		if ( ! preg_match( '/class\s+\w+/', $code ) ) {
			return 'No valid class definition found';
		}

		return null; // Valid
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Agent_Builder() );
	}
);
