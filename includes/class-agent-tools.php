<?php
/**
 * Agent Tools - Core tool definitions and execution engine
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.1.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Collection of tools available to the AI agent
 */
class Agent_Tools {

	/**
	 * Agent tool handlers registered by installed agents
	 *
	 * @var array<string, callable>
	 */
	private array $agent_tool_handlers = array();

	/**
	 * Audit logger
	 *
	 * @var Audit_Log
	 */
	private Audit_Log $audit;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->audit = new Audit_Log();
	}

	/**
	 * Base path the agent can access (wp-content).
	 *
	 * @return string
	 */
	public static function get_allowed_repo_base(): string {
		return WP_CONTENT_DIR;
	}

	/**
	 * Check if a relative path stays inside plugins/, themes/, or uploads/.
	 *
	 * @param string $path Relative path.
	 * @return bool
	 */
	public static function is_allowed_subpath( string $path ): bool {
		$clean = ltrim( str_replace( '..', '', $path ), '/\\' );

		return (
			'plugins' === $clean ||
			'themes' === $clean ||
			'uploads' === $clean ||
			str_starts_with( $clean, 'plugins/' ) ||
			str_starts_with( $clean, 'themes/' ) ||
			str_starts_with( $clean, 'uploads/' )
		);
	}

	/**
	 * Determine the directory scope from a relative path.
	 *
	 * @param string $path Relative path (e.g. 'plugins/my-plugin/file.php').
	 * @return string Scope name: 'plugins', 'themes', 'uploads', or empty string.
	 */
	public static function get_path_scope( string $path ): string {
		$clean = ltrim( str_replace( '..', '', $path ), '/\\' );

		foreach ( array( 'plugins', 'themes', 'uploads' ) as $scope ) {
			if ( $scope === $clean || str_starts_with( $clean, $scope . '/' ) ) {
				return $scope;
			}
		}

		return '';
	}

	/**
	 * Check if a scoped operation is allowed.
	 *
	 * Uses the agentic_tool_scopes option which stores an associative array
	 * of 'scope:operation' => bool entries (e.g. 'plugins:read' => true).
	 * When a scope entry is missing it defaults to allowed (true).
	 *
	 * @param string $scope     Directory scope ('plugins', 'themes', 'uploads').
	 * @param string $operation Operation type ('read' or 'write').
	 * @return bool Whether the operation is permitted.
	 */
	public static function is_scope_allowed( string $scope, string $operation ): bool {
		$scopes = get_option( 'agentic_tool_scopes', array() );
		if ( ! is_array( $scopes ) ) {
			return true;
		}

		$key = $scope . ':' . $operation;
		return ! isset( $scopes[ $key ] ) || (bool) $scopes[ $key ];
	}

	/**
	 * Get tool definitions for OpenAI function calling
	 *
	 * Returns only enabled tools. Disabled tools (stored in agentic_disabled_tools
	 * option) are filtered out so the LLM never sees them.
	 *
	 * @return array Tool definitions.
	 */
	public function get_tool_definitions(): array {
		$all_tools      = $this->get_all_tool_definitions();
		$disabled_tools = get_option( 'agentic_disabled_tools', array() );

		if ( ! is_array( $disabled_tools ) || empty( $disabled_tools ) ) {
			return $all_tools;
		}

		return array_values(
			array_filter(
				$all_tools,
				function ( $tool ) use ( $disabled_tools ) {
					$name = $tool['function']['name'] ?? '';
					return ! in_array( $name, $disabled_tools, true );
				}
			)
		);
	}

	/**
	 * Get all tool definitions regardless of enabled/disabled status
	 *
	 * Used by the admin tools page to display every tool with its toggle state.
	 *
	 * @return array Tool definitions.
	 */
	public function get_all_tool_definitions(): array {
		// Core tools always available.
		$core_tools = array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_option',
					'description' => 'Read a WordPress option value from the options table. Sensitive options (passwords, secret keys, API keys) are blocked for security.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name' => array(
								'type'        => 'string',
								'description' => 'Option name to retrieve (e.g., blogname, siteurl, permalink_structure, active_plugins)',
							),
						),
						'required'   => array( 'name' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_posts',
					'description' => 'Query WordPress posts by type, status, or search term. Returns a summary list — use db_get_post for full content.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'description' => 'Post type to query (e.g., post, page). Default: post.',
							),
							'status'    => array(
								'type'        => 'string',
								'description' => 'Post status filter (publish, draft, pending, trash, any). Default: any.',
							),
							'search'    => array(
								'type'        => 'string',
								'description' => 'Search term to filter by title or content.',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Maximum posts to return (1-20). Default: 10.',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_post',
					'description' => 'Get a single WordPress post with full content, taxonomy terms, and edit link.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the post to retrieve.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_users',
					'description' => 'List WordPress users with role and registration info. Emails and passwords are never exposed.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'role'   => array(
								'type'        => 'string',
								'description' => 'Filter by role (administrator, editor, author, subscriber). Omit for all roles.',
							),
							'search' => array(
								'type'        => 'string',
								'description' => 'Search by username or display name.',
							),
							'limit'  => array(
								'type'        => 'integer',
								'description' => 'Maximum users to return (1-20). Default: 10.',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_terms',
					'description' => 'Get taxonomy terms (categories, tags, or custom taxonomies) with post counts.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'taxonomy'   => array(
								'type'        => 'string',
								'description' => 'Taxonomy name (e.g., category, post_tag, or a custom taxonomy slug).',
							),
							'hide_empty' => array(
								'type'        => 'boolean',
								'description' => 'Whether to hide terms with no posts. Default: true.',
							),
							'search'     => array(
								'type'        => 'string',
								'description' => 'Search terms by name.',
							),
							'limit'      => array(
								'type'        => 'integer',
								'description' => 'Maximum terms to return (1-50). Default: 20.',
							),
						),
						'required'   => array( 'taxonomy' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_post_meta',
					'description' => 'Read custom field values for a post. Sensitive meta keys (passwords, secrets, API keys) are blocked.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'  => array(
								'type'        => 'integer',
								'description' => 'The post ID to read meta from.',
							),
							'meta_key' => array(
								'type'        => 'string',
								'description' => 'Specific meta key to retrieve. Omit to return all non-sensitive meta.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_get_comments',
					'description' => 'Get WordPress comments, optionally filtered by post. Author emails are not exposed.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'Filter comments to a specific post. Omit for recent comments across all posts.',
							),
							'status'  => array(
								'type'        => 'string',
								'description' => 'Comment status (approve, hold, spam, trash, all). Default: approve.',
							),
							'limit'   => array(
								'type'        => 'integer',
								'description' => 'Maximum comments to return (1-50). Default: 10.',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_update_option',
					'description' => 'Update a WordPress option value. Sensitive options and critical site settings (siteurl, home, active_plugins) are blocked.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'  => array(
								'type'        => 'string',
								'description' => 'Option name to update.',
							),
							'value' => array(
								'type'        => 'string',
								'description' => 'New option value. For arrays or objects, pass a JSON string.',
							),
						),
						'required'   => array( 'name', 'value' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_create_post',
					'description' => 'Create a new WordPress post or page. Defaults to draft status for review before publishing.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'title'     => array(
								'type'        => 'string',
								'description' => 'Post title.',
							),
							'content'   => array(
								'type'        => 'string',
								'description' => 'Post content (HTML or plain text).',
							),
							'post_type' => array(
								'type'        => 'string',
								'description' => 'Post type (post, page). Default: post.',
							),
							'status'    => array(
								'type'        => 'string',
								'description' => 'Post status (draft, pending). Default: draft.',
							),
						),
						'required'   => array( 'title' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_update_post',
					'description' => 'Update an existing WordPress post. Only the fields you provide will be changed.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the post to update.',
							),
							'title'   => array(
								'type'        => 'string',
								'description' => 'New post title.',
							),
							'content' => array(
								'type'        => 'string',
								'description' => 'New post content.',
							),
							'excerpt' => array(
								'type'        => 'string',
								'description' => 'New post excerpt.',
							),
							'status'  => array(
								'type'        => 'string',
								'description' => 'New post status (draft, pending, publish).',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'db_delete_post',
					'description' => 'Move a WordPress post to the trash. Does not permanently delete — use the WordPress admin to empty trash.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the post to trash.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
		);

		// Get tools from activated agents via filter.
		$agent_tools = apply_filters( 'agentic_agent_tools', array() );

		// Convert agent tools to OpenAI function format and merge.
		foreach ( $agent_tools as $tool_name => $tool ) {
			$core_tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'],
					'description' => $tool['description'],
					'parameters'  => $tool['parameters'] ?? array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);

			// Store handler for later execution.
			$this->agent_tool_handlers[ $tool['name'] ] = $tool['handler'] ?? null;
		}

		return $core_tools;
	}

	/**
	 * Execute a tool call
	 *
	 * @param string $name      Tool name.
	 * @param array  $arguments Tool arguments.
	 * @param string $agent_id  Agent identifier.
	 * @return array|\WP_Error Tool result.
	 */
	public function execute( string $name, array $arguments, string $agent_id = 'onboarding_agent' ): array|\WP_Error {
		// Block disabled tools before any execution.
		$disabled_tools = get_option( 'agentic_disabled_tools', array() );
		if ( is_array( $disabled_tools ) && in_array( $name, $disabled_tools, true ) ) {
			$this->audit->log(
				$agent_id,
				'tool_blocked',
				$name,
				array( 'reason' => 'Tool is disabled by administrator' )
			);
			return new \WP_Error(
				'tool_disabled',
				sprintf(
					/* translators: %s: tool name */
					__( 'The tool "%s" has been disabled by an administrator and cannot be used.', 'agentbuilder' ),
					$name
				)
			);
		}

		$this->audit->log( $agent_id, 'tool_call', $name, $arguments );

		// Check for agent-registered tool handlers first.
		// Make sure handlers are loaded by calling get_tool_definitions.
		if ( empty( $this->agent_tool_handlers ) ) {
			$this->get_tool_definitions();
		}

		if ( isset( $this->agent_tool_handlers[ $name ] ) && is_callable( $this->agent_tool_handlers[ $name ] ) ) {
			return call_user_func( $this->agent_tool_handlers[ $name ], $arguments );
		}

		// Core tools.
		switch ( $name ) {
			case 'db_get_option':
				return $this->get_option( $arguments['name'] ?? '' );

			case 'db_get_posts':
				return $this->get_posts( $arguments );

			case 'db_get_post':
				return $this->get_post( (int) ( $arguments['post_id'] ?? 0 ) );

			case 'db_get_users':
				return $this->get_users( $arguments );

			case 'db_get_terms':
				return $this->get_terms( $arguments );

			case 'db_get_post_meta':
				return $this->get_post_meta_data( $arguments );

			case 'db_get_comments':
				return $this->get_comments( $arguments );

			case 'db_update_option':
				return $this->update_option( $arguments['name'] ?? '', $arguments['value'] ?? '' );

			case 'db_create_post':
				return $this->create_post( $arguments );

			case 'db_update_post':
				return $this->update_post( $arguments );

			case 'db_delete_post':
				return $this->delete_post( (int) ( $arguments['post_id'] ?? 0 ) );

			default:
				return new \WP_Error( 'unknown_tool', "Unknown tool: {$name}" );
		}
	}

	/**
	 * Execute a user-space tool (stub — write tools removed in 1.8.0).
	 *
	 * Retained as public method for backward compatibility with Agent_Proposals.
	 *
	 * @param string $tool_name      Tool name.
	 * @param array  $arguments      Tool arguments.
	 * @param string $agent_id       Agent ID.
	 * @param bool   $skip_confirm   Skip confirmation.
	 * @return array Error result.
	 */
	public function execute_user_space( string $tool_name, array $arguments, string $agent_id = 'unknown', bool $skip_confirm = false ): array {
		unset( $arguments, $agent_id, $skip_confirm ); // Unused.
		return array( 'error' => "User-space tool \"{$tool_name}\" is no longer available." );
	}

	// -------------------------------------------------------------------------
	// Core tool implementations
	// -------------------------------------------------------------------------

	/**
	 * Check whether a name matches any sensitive pattern.
	 *
	 * Used by get_option, update_option, and get_post_meta_data to block
	 * access to options/meta containing passwords, keys, or secrets.
	 *
	 * @param string $name The name to check.
	 * @return bool True if the name is sensitive.
	 */
	private function is_sensitive_name( string $name ): bool {
		$sensitive_patterns = array(
			'password',
			'secret',
			'api_key',
			'apikey',
			'auth_key',
			'auth_salt',
			'logged_in_key',
			'logged_in_salt',
			'nonce_key',
			'nonce_salt',
			'secure_auth_key',
			'secure_auth_salt',
			'stripe',
			'paypal',
		);

		$lower_name = strtolower( $name );
		foreach ( $sensitive_patterns as $pattern ) {
			if ( false !== strpos( $lower_name, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Read a WordPress option value.
	 *
	 * @param string $name Option name.
	 * @return array Option data or error.
	 */
	private function get_option( string $name ): array {
		if ( empty( $name ) ) {
			return array( 'error' => 'Option name is required.' );
		}

		if ( $this->is_sensitive_name( $name ) ) {
			return array(
				'name'  => $name,
				'error' => 'This option is blocked for security reasons. Sensitive options containing passwords, keys, or secrets cannot be read.',
			);
		}

		$value = get_option( $name, null );

		if ( null === $value ) {
			return array(
				'name'   => $name,
				'exists' => false,
				'error'  => 'Option not found.',
			);
		}

		return array(
			'name'   => $name,
			'exists' => true,
			'value'  => $value,
			'type'   => gettype( $value ),
		);
	}

	/**
	 * Query WordPress posts.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array List of matching posts.
	 */
	private function get_posts( array $arguments ): array {
		$limit = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 20 );

		$query_args = array(
			'post_type'      => sanitize_key( $arguments['post_type'] ?? 'post' ),
			'post_status'    => sanitize_key( $arguments['status'] ?? 'any' ),
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $arguments['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $arguments['search'] );
		}

		$query = new \WP_Query( $query_args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'date'     => $post->post_date,
				'modified' => $post->post_modified,
				'type'     => $post->post_type,
				'author'   => get_the_author_meta( 'display_name', $post->post_author ),
			);
		}

		return array(
			'posts'       => $posts,
			'total_found' => $query->found_posts,
			'returned'    => count( $posts ),
		);
	}

	/**
	 * Get a single WordPress post with full content.
	 *
	 * @param int $post_id Post ID.
	 * @return array Post data or error.
	 */
	private function get_post( int $post_id ): array {
		if ( $post_id < 1 ) {
			return array( 'error' => 'A valid post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found." );
		}

		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$terms_data = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$terms_data[ $taxonomy ] = $terms;
			}
		}

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'status'    => $post->post_status,
			'type'      => $post->post_type,
			'date'      => $post->post_date,
			'modified'  => $post->post_modified,
			'author'    => get_the_author_meta( 'display_name', $post->post_author ),
			'terms'     => $terms_data,
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * List WordPress users (no emails or passwords exposed).
	 *
	 * @param array $arguments Tool arguments.
	 * @return array List of users.
	 */
	private function get_users( array $arguments ): array {
		$limit = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 20 );

		$query_args = array(
			'number' => $limit,
			'fields' => array( 'ID', 'user_login', 'display_name', 'user_registered' ),
		);

		if ( ! empty( $arguments['role'] ) ) {
			$query_args['role'] = sanitize_key( $arguments['role'] );
		}

		if ( ! empty( $arguments['search'] ) ) {
			$query_args['search']         = '*' . sanitize_text_field( $arguments['search'] ) . '*';
			$query_args['search_columns'] = array( 'user_login', 'display_name' );
		}

		$user_query = new \WP_User_Query( $query_args );
		$users      = array();

		foreach ( $user_query->get_results() as $user ) {
			$user_data = (array) $user;
			$roles     = get_userdata( $user_data['ID'] );

			$users[] = array(
				'id'           => $user_data['ID'],
				'username'     => $user_data['user_login'],
				'display_name' => $user_data['display_name'],
				'roles'        => $roles ? $roles->roles : array(),
				'registered'   => $user_data['user_registered'],
			);
		}

		return array(
			'users'       => $users,
			'total_found' => $user_query->get_total(),
			'returned'    => count( $users ),
		);
	}

	/**
	 * Get taxonomy terms.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array List of terms or error.
	 */
	private function get_terms( array $arguments ): array {
		$taxonomy = sanitize_key( $arguments['taxonomy'] ?? '' );

		if ( empty( $taxonomy ) ) {
			return array( 'error' => 'Taxonomy is required.' );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'error' => "Taxonomy \"{$taxonomy}\" does not exist." );
		}

		$limit = min( max( (int) ( $arguments['limit'] ?? 20 ), 1 ), 50 );

		$term_args = array(
			'taxonomy'   => $taxonomy,
			'number'     => $limit,
			'hide_empty' => (bool) ( $arguments['hide_empty'] ?? true ),
		);

		if ( ! empty( $arguments['search'] ) ) {
			$term_args['search'] = sanitize_text_field( $arguments['search'] );
		}

		$terms = get_terms( $term_args );

		if ( is_wp_error( $terms ) ) {
			return array( 'error' => $terms->get_error_message() );
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'count'  => $term->count,
				'parent' => $term->parent,
			);
		}

		return array(
			'taxonomy' => $taxonomy,
			'terms'    => $result,
			'returned' => count( $result ),
		);
	}

	/**
	 * Read post meta data, blocking sensitive keys.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Meta data or error.
	 */
	private function get_post_meta_data( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return array( 'error' => 'A valid post_id is required.' );
		}

		if ( ! get_post( $post_id ) ) {
			return array( 'error' => "Post {$post_id} not found." );
		}

		// Single key requested.
		if ( ! empty( $arguments['meta_key'] ) ) {
			$key = sanitize_text_field( $arguments['meta_key'] );

			if ( $this->is_sensitive_name( $key ) ) {
				return array( 'error' => "Meta key \"{$key}\" is blocked for security reasons." );
			}

			$value = get_post_meta( $post_id, $key, true );

			return array(
				'post_id'  => $post_id,
				'meta_key' => $key,
				'value'    => $value,
				'exists'   => metadata_exists( 'post', $post_id, $key ),
			);
		}

		// All meta — filter out sensitive keys and internal WP keys.
		$all_meta  = get_post_meta( $post_id );
		$safe_meta = array();

		foreach ( $all_meta as $key => $values ) {
			if ( $this->is_sensitive_name( $key ) ) {
				continue;
			}
			// Keep it simple: first value for each key.
			$safe_meta[ $key ] = maybe_unserialize( $values[0] );
		}

		return array(
			'post_id' => $post_id,
			'meta'    => $safe_meta,
		);
	}

	/**
	 * Get WordPress comments.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array List of comments.
	 */
	private function get_comments( array $arguments ): array {
		$limit = min( max( (int) ( $arguments['limit'] ?? 10 ), 1 ), 50 );

		$comment_args = array(
			'number'  => $limit,
			'status'  => sanitize_key( $arguments['status'] ?? 'approve' ),
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		if ( ! empty( $arguments['post_id'] ) ) {
			$comment_args['post_id'] = (int) $arguments['post_id'];
		}

		$comments = get_comments( $comment_args );

		$result = array();
		foreach ( $comments as $comment ) {
			$result[] = array(
				'id'          => (int) $comment->comment_ID,
				'post_id'     => (int) $comment->comment_post_ID,
				'author_name' => $comment->comment_author,
				'content'     => $comment->comment_content,
				'date'        => $comment->comment_date,
				'status'      => wp_get_comment_status( $comment ),
				'parent'      => (int) $comment->comment_parent,
			);
		}

		return array(
			'comments' => $result,
			'returned' => count( $result ),
		);
	}

	/**
	 * Update a WordPress option value.
	 *
	 * Blocks sensitive options and critical site settings that should
	 * only be changed through the WordPress admin.
	 *
	 * @param string $name  Option name.
	 * @param string $value New value.
	 * @return array Result.
	 */
	private function update_option( string $name, string $value ): array {
		if ( empty( $name ) ) {
			return array( 'error' => 'Option name is required.' );
		}

		if ( $this->is_sensitive_name( $name ) ) {
			return array(
				'name'  => $name,
				'error' => 'This option is blocked for security reasons.',
			);
		}

		// Protected options that should never be changed via agents.
		$protected_options = array(
			'siteurl',
			'home',
			'admin_email',
			'users_can_register',
			'default_role',
			'active_plugins',
			'template',
			'stylesheet',
			'db_version',
			'initial_db_version',
			'wp_user_roles',
			'agentic_disabled_tools',
			'agentic_tool_scopes',
			'agentic_active_agents',
		);

		if ( in_array( $name, $protected_options, true ) ) {
			return array(
				'name'  => $name,
				'error' => 'This option is protected and cannot be changed by agents.',
			);
		}

		// Attempt JSON decode for complex values.
		$decoded = json_decode( $value, true );
		if ( null !== $decoded && json_last_error() === JSON_ERROR_NONE ) {
			$value = $decoded;
		}

		$updated = update_option( $name, $value );

		return array(
			'name'    => $name,
			'updated' => $updated,
		);
	}

	/**
	 * Create a new WordPress post (draft or pending only).
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Created post data or error.
	 */
	private function create_post( array $arguments ): array {
		if ( empty( $arguments['title'] ) ) {
			return array( 'error' => 'Title is required.' );
		}

		// Only allow safe statuses — never publish directly.
		$allowed_statuses = array( 'draft', 'pending' );
		$status           = sanitize_key( $arguments['status'] ?? 'draft' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'draft';
		}

		$post_data = array(
			'post_title'   => sanitize_text_field( $arguments['title'] ),
			'post_content' => wp_kses_post( $arguments['content'] ?? '' ),
			'post_type'    => sanitize_key( $arguments['post_type'] ?? 'post' ),
			'post_status'  => $status,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}

		return array(
			'post_id'   => $post_id,
			'title'     => $post_data['post_title'],
			'status'    => $post_data['post_status'],
			'type'      => $post_data['post_type'],
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Update an existing WordPress post.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Updated post data or error.
	 */
	private function update_post( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return array( 'error' => 'A valid post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found." );
		}

		$post_data = array( 'ID' => $post_id );

		if ( isset( $arguments['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $arguments['title'] );
		}
		if ( isset( $arguments['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $arguments['content'] );
		}
		if ( isset( $arguments['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $arguments['excerpt'] );
		}
		if ( isset( $arguments['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $arguments['status'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		$updated_post = get_post( $post_id );

		return array(
			'post_id'   => $post_id,
			'title'     => $updated_post->post_title,
			'status'    => $updated_post->post_status,
			'modified'  => $updated_post->post_modified,
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Move a WordPress post to the trash.
	 *
	 * @param int $post_id Post ID.
	 * @return array Result or error.
	 */
	private function delete_post( int $post_id ): array {
		if ( $post_id < 1 ) {
			return array( 'error' => 'A valid post_id is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found." );
		}

		// Always trash, never permanently delete.
		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return array( 'error' => "Failed to trash post {$post_id}." );
		}

		return array(
			'post_id' => $post_id,
			'title'   => $post->post_title,
			'trashed' => true,
		);
	}
}
