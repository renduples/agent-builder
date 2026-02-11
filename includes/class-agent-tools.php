<?php
/**
 * Agent Tools - Git and WordPress operations
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
	 * Repository path
	 *
	 * @var string
	 */
	private string $repo_path;

	/**
	 * Allowed root directories inside wp-content
	 *
	 * @var array<int, string>
	 */
	private array $allowed_roots = array();

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
		$this->repo_path     = self::get_allowed_repo_base();
		$this->allowed_roots = array(
			trailingslashit( WP_CONTENT_DIR . '/plugins' ),
			trailingslashit( WP_CONTENT_DIR . '/themes' ),
		);
		$this->audit         = new Audit_Log();
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
	 * Check if a relative path stays inside plugins/ or themes/.
	 *
	 * @param string $path Relative path.
	 * @return bool
	 */
	public static function is_allowed_subpath( string $path ): bool {
		$clean = ltrim( str_replace( '..', '', $path ), '/\\' );

		return (
			'plugins' === $clean ||
			'themes' === $clean ||
			str_starts_with( $clean, 'plugins/' ) ||
			str_starts_with( $clean, 'themes/' )
		);
	}

	/**
	 * Get tool definitions for OpenAI function calling
	 *
	 * @return array Tool definitions.
	 */
	public function get_tool_definitions(): array {
		// Core tools always available.
		$core_tools = array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'read_file',
					'description' => 'Read the contents of a file from the repository',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'path' => array(
								'type'        => 'string',
								'description' => 'Relative path to the file from repository root',
							),
						),
						'required'   => array( 'path' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_directory',
					'description' => 'List contents of a directory in the repository',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'path' => array(
								'type'        => 'string',
								'description' => 'Relative path to the directory from repository root',
							),
						),
						'required'   => array( 'path' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'search_code',
					'description' => 'Search for a pattern in repository files',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'pattern'   => array(
								'type'        => 'string',
								'description' => 'Search pattern (regex supported)',
							),
							'file_type' => array(
								'type'        => 'string',
								'description' => 'File extension to search (e.g., php, js, md)',
							),
						),
						'required'   => array( 'pattern' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_posts',
					'description' => 'Get WordPress posts or pages with optional filters',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'description' => 'Post type (post, page, etc.)',
							),
							'category'  => array(
								'type'        => 'string',
								'description' => 'Category slug to filter by',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Maximum number of results',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_comments',
					'description' => 'Get comments, optionally for a specific post',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'Post ID to get comments for',
							),
							'limit'   => array(
								'type'        => 'integer',
								'description' => 'Maximum number of results',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_comment',
					'description' => 'Create a new comment on a post (agent response to discussion)',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'Post ID to comment on',
							),
							'content' => array(
								'type'        => 'string',
								'description' => 'Comment content (Markdown supported)',
							),
						),
						'required'   => array( 'post_id', 'content' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_documentation',
					'description' => 'Update a markdown documentation file. This action is autonomous for docs.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'path'      => array(
								'type'        => 'string',
								'description' => 'Relative path to the markdown file',
							),
							'content'   => array(
								'type'        => 'string',
								'description' => 'New content for the file',
							),
							'reasoning' => array(
								'type'        => 'string',
								'description' => 'Explanation of why this change is being made',
							),
						),
						'required'   => array( 'path', 'content', 'reasoning' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'request_code_change',
					'description' => 'Propose a code change by creating a git branch. The change will be committed to a new branch for human review via pull request.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'path'      => array(
								'type'        => 'string',
								'description' => 'Relative path to the code file',
							),
							'content'   => array(
								'type'        => 'string',
								'description' => 'The complete new content for the file',
							),
							'reasoning' => array(
								'type'        => 'string',
								'description' => 'Explanation of why this change is needed (becomes commit message)',
							),
						),
						'required'   => array( 'path', 'content', 'reasoning' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'manage_schedules',
					'description' => 'View and manage scheduled tasks for agents. Can list all schedules, pause a task, or resume a paused task.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'operation' => array(
								'type'        => 'string',
								'enum'        => array( 'list', 'pause', 'resume' ),
								'description' => 'Operation to perform: list all schedules, pause a task, or resume a paused task',
							),
							'agent_id'  => array(
								'type'        => 'string',
								'description' => 'Agent ID (required for pause/resume)',
							),
							'task_id'   => array(
								'type'        => 'string',
								'description' => 'Task ID (required for pause/resume)',
							),
						),
						'required'   => array( 'operation' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'query_database',
					'description' => 'Execute a read-only SQL SELECT query against the WordPress database. Only SELECT statements are allowed. Use $wpdb->prefix for table names (e.g., wp_posts). Results are limited to 100 rows.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'query' => array(
								'type'        => 'string',
								'description' => 'SQL SELECT query to execute. Use {prefix} as placeholder for the WordPress table prefix (e.g., "SELECT * FROM {prefix}posts LIMIT 10").',
							),
						),
						'required'   => array( 'query' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_error_log',
					'description' => 'Read the WordPress debug.log file (last N lines). Requires WP_DEBUG_LOG to be enabled. Useful for diagnosing PHP errors, warnings, and notices.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'lines' => array(
								'type'        => 'integer',
								'description' => 'Number of lines to read from the end of the log file (default: 50, max: 200)',
							),
							'filter' => array(
								'type'        => 'string',
								'description' => 'Optional text filter — only return lines containing this string (case-insensitive)',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_site_health',
					'description' => 'Get comprehensive WordPress site health information including PHP version, WordPress version, memory usage, active plugins, database size, and server environment details.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'manage_wp_cron',
					'description' => 'List, view, or delete WordPress cron events. Covers all WP-Cron scheduled events, not just agent tasks.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'operation' => array(
								'type'        => 'string',
								'enum'        => array( 'list', 'delete' ),
								'description' => 'Operation: list all cron events, or delete a specific event',
							),
							'hook'      => array(
								'type'        => 'string',
								'description' => 'Hook name (required for delete operation)',
							),
							'timestamp' => array(
								'type'        => 'integer',
								'description' => 'Unix timestamp of the event to delete (required for delete)',
							),
						),
						'required'   => array( 'operation' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_users',
					'description' => 'List WordPress users with their roles, registration dates, and post counts. Can filter by role.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'role'   => array(
								'type'        => 'string',
								'description' => 'Filter by user role (e.g., administrator, editor, subscriber)',
							),
							'search' => array(
								'type'        => 'string',
								'description' => 'Search users by login, email, or display name',
							),
							'limit'  => array(
								'type'        => 'integer',
								'description' => 'Maximum number of users to return (default: 20, max: 50)',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_option',
					'description' => 'Read a WordPress option value from the options table. Sensitive options (passwords, secret keys, API keys) are redacted for security.',
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
	public function execute( string $name, array $arguments, string $agent_id = 'developer_agent' ): array|\WP_Error {
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
			case 'read_file':
				return $this->read_file( $arguments['path'] );

			case 'list_directory':
				return $this->list_directory( $arguments['path'] ?? '' );

			case 'search_code':
				return $this->search_code( $arguments['pattern'], $arguments['file_type'] ?? null );

			case 'get_posts':
				return $this->get_posts( $arguments );

			case 'get_comments':
				return $this->get_comments( $arguments );

			case 'create_comment':
				return $this->create_comment( $arguments['post_id'], $arguments['content'] );

			case 'update_documentation':
				return $this->update_documentation( $arguments['path'], $arguments['content'], $arguments['reasoning'] );

			case 'request_code_change':
				return $this->request_code_change( $arguments['path'], $arguments['content'], $arguments['reasoning'] );

			case 'manage_schedules':
				return $this->manage_schedules( $arguments );

			case 'query_database':
				return $this->query_database( $arguments['query'] ?? '' );

			case 'get_error_log':
				return $this->get_error_log( $arguments );

			case 'get_site_health':
				return $this->get_site_health();

			case 'manage_wp_cron':
				return $this->manage_wp_cron( $arguments );

			case 'get_users':
				return $this->get_users( $arguments );

			case 'get_option':
				return $this->get_option( $arguments['name'] ?? '' );

			default:
				return new \WP_Error( 'unknown_tool', "Unknown tool: {$name}" );
		}
	}

	/**
	 * Read a file from the repository
	 *
	 * @param string $path Relative path.
	 * @return array File content or error.
	 */
	private function read_file( string $path ): array {
		// Security: prevent path traversal.
		$path = $this->sanitize_path( $path );

		if ( ! self::is_allowed_subpath( $path ) ) {
			return array(
				'error' => 'Path not allowed. Only plugins/ or themes/ are accessible.',
			);
		}
		$full_path = $this->repo_path . '/' . $path;

		if ( ! file_exists( $full_path ) ) {
			return array(
				'error' => 'File not found',
				'path'  => $path,
			);
		}

		if ( ! is_readable( $full_path ) ) {
			return array(
				'error' => 'File not readable',
				'path'  => $path,
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local files.
		$content = file_get_contents( $full_path );
		$size    = strlen( $content );

		if ( $size > 100000 ) {
			$content = substr( $content, 0, 100000 ) . "\n\n[Content truncated - file too large]";
		}

		return array(
			'path'    => $path,
			'content' => $content,
			'size'    => $size,
		);
	}

	/**
	 * List directory contents
	 *
	 * @param string $path Relative path.
	 * @return array Directory listing.
	 */
	private function list_directory( string $path ): array {
		$path = $this->sanitize_path( $path );

		// If no path provided, list allowed roots.
		if ( '' === $path ) {
			$items = array();
			foreach ( $this->allowed_roots as $root ) {
				if ( is_dir( $root ) ) {
					$items[] = array(
						'name' => basename( rtrim( $root, '/\\' ) ),
						'type' => 'directory',
						'size' => null,
					);
				}
			}

			return array(
				'path'  => '',
				'items' => $items,
			);
		}

		if ( ! self::is_allowed_subpath( $path ) ) {
			return array(
				'error' => 'Path not allowed. Only plugins/ or themes/ are accessible.',
				'path'  => $path,
			);
		}

		$full_path = $this->repo_path . '/' . $path;

		if ( ! is_dir( $full_path ) ) {
			return array(
				'error' => 'Directory not found',
				'path'  => $path,
			);
		}

		$items = scandir( $full_path );
		$items = array_diff( $items, array( '.', '..' ) );

		$result = array();
		foreach ( $items as $item ) {
			$item_path = $full_path . '/' . $item;
			$result[]  = array(
				'name' => $item,
				'type' => is_dir( $item_path ) ? 'directory' : 'file',
				'size' => is_file( $item_path ) ? filesize( $item_path ) : null,
			);
		}

		return array(
			'path'  => $path,
			'items' => $result,
		);
	}

	/**
	 * Search for a pattern in repository files
	 *
	 * @param string      $pattern   Search pattern.
	 * @param string|null $file_type File extension.
	 * @return array Search results.
	 */
	private function search_code( string $pattern, ?string $file_type = null ): array {
		$results = array();

		$paths = array_filter( $this->allowed_roots, 'is_dir' );
		$count = 0;

		foreach ( $paths as $path ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path )
			);

			foreach ( $iterator as $file ) {
				if ( $count >= 50 ) {
					break 2;
				}

				if ( $file->isFile() && ( ! $file_type || $file->getExtension() === $file_type ) ) {
					// Skip vendor/node_modules.
					if ( strpos( $file->getPathname(), 'vendor/' ) !== false || strpos( $file->getPathname(), 'node_modules/' ) !== false ) {
						continue;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local files.
					$content = file_get_contents( $file->getPathname() );
					if ( preg_match( "/{$pattern}/i", $content, $matches, PREG_OFFSET_CAPTURE ) ) {
						$relative_path = str_replace( trailingslashit( $this->repo_path ), '', $file->getPathname() );
						$line_number   = substr_count( substr( $content, 0, $matches[0][1] ), "\n" ) + 1;

						$results[] = array(
							'file'  => $relative_path,
							'line'  => $line_number,
							'match' => $matches[0][0],
						);
						++$count;
					}
				}
			}
		}

		return array(
			'pattern' => $pattern,
			'results' => $results,
			'count'   => count( $results ),
		);
	}

	/**
	 * Get WordPress posts
	 *
	 * @param array $args Query arguments.
	 * @return array Posts.
	 */
	private function get_posts( array $args ): array {
		$query_args = array(
			'post_type'      => $args['post_type'] ?? 'post',
			'posts_per_page' => min( $args['limit'] ?? 20, 50 ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['category_name'] = $args['category'];
		}

		$posts  = get_posts( $query_args );
		$result = array();

		foreach ( $posts as $post ) {
			$result[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'slug'    => $post->post_name,
				'excerpt' => wp_trim_words( $post->post_content, 30 ),
				'date'    => $post->post_date,
				'author'  => get_the_author_meta( 'display_name', $post->post_author ),
				'url'     => get_permalink( $post->ID ),
			);
		}

		return array( 'posts' => $result );
	}

	/**
	 * Get comments
	 *
	 * @param array $args Query arguments.
	 * @return array Comments.
	 */
	private function get_comments( array $args ): array {
		$query_args = array(
			'number' => min( $args['limit'] ?? 20, 50 ),
			'status' => 'approve',
		);

		if ( ! empty( $args['post_id'] ) ) {
			$query_args['post_id'] = (int) $args['post_id'];
		}

		$comments = get_comments( $query_args );
		$result   = array();

		foreach ( $comments as $comment ) {
			$result[] = array(
				'id'      => $comment->comment_ID,
				'post_id' => $comment->comment_post_ID,
				'author'  => $comment->comment_author,
				'content' => $comment->comment_content,
				'date'    => $comment->comment_date,
				'parent'  => $comment->comment_parent,
			);
		}

		return array( 'comments' => $result );
	}

	/**
	 * Create a comment (agent response)
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Comment content.
	 * @return array Result.
	 */
	private function create_comment( int $post_id, string $content ): array {
		$agent_user = get_user_by( 'login', 'developer-agent' );

		if ( ! $agent_user ) {
			// Create agent user if it doesn't exist.
			$user_id = wp_insert_user(
				array(
					'user_login'   => 'developer-agent',
					'user_pass'    => wp_generate_password( 32 ),
					'user_email'   => 'agent@agentic.test',
					'display_name' => 'Onboarding Agent',
					'role'         => 'author',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				return array( 'error' => $user_id->get_error_message() );
			}

			$agent_user = get_user_by( 'id', $user_id );
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Onboarding Agent',
				'comment_author_email' => $agent_user->user_email,
				'comment_content'      => $content,
				'comment_type'         => 'comment',
				'user_id'              => $agent_user->ID,
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			return array( 'error' => 'Failed to create comment' );
		}

		$this->audit->log(
			'developer_agent',
			'create_comment',
			'comment',
			array(
				'comment_id' => $comment_id,
				'post_id'    => $post_id,
			)
		);

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'post_id'    => $post_id,
			'url'        => get_comment_link( $comment_id ),
		);
	}

	/**
	 * Update a documentation file
	 *
	 * @param string $path      File path.
	 * @param string $content   New content.
	 * @param string $reasoning Explanation for change.
	 * @return array Result.
	 */
	private function update_documentation( string $path, string $content, string $reasoning ): array {
		// Only allow markdown files.
		if ( ! preg_match( '/\.(md|txt|rst)$/i', $path ) ) {
			return array( 'error' => 'Only documentation files (.md, .txt, .rst) can be updated autonomously' );
		}

		$path = $this->sanitize_path( $path );

		if ( ! self::is_allowed_subpath( $path ) ) {
			return array( 'error' => 'Path not allowed. Only plugins/ or themes/ are accessible.' );
		}

		$full_path = $this->repo_path . '/' . $path;

		// Backup existing content.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local files.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$result = $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE );

		if ( false === $result ) {
			return array( 'error' => 'Failed to write file' );
		}

		// Commit the change directly to current branch (docs are autonomous).
		$commit_message = "docs: Update {$path}\\n\\nReasoning: {$reasoning}";
		$this->git_exec( 'git add ' . escapeshellarg( $path ) );
		$this->git_exec( 'git commit -m ' . escapeshellarg( $commit_message ) );

		$this->audit->log(
			'developer_agent',
			'update_documentation',
			'file',
			array(
				'path'      => $path,
				'reasoning' => $reasoning,
				'backup'    => $backup ? md5( $backup ) : null,
			)
		);

		return array(
			'success'   => true,
			'path'      => $path,
			'reasoning' => $reasoning,
		);
	}

	/**
	 * Request a code change via git branch
	 *
	 * Creates a new branch, commits the change, and returns info for PR creation.
	 *
	 * @param string $path      File path.
	 * @param string $content   New file content.
	 * @param string $reasoning Explanation (becomes commit message).
	 * @return array Result.
	 */
	private function request_code_change( string $path, string $content, string $reasoning ): array {
		$path = $this->sanitize_path( $path );

		if ( ! self::is_allowed_subpath( $path ) ) {
			return array( 'error' => 'Path not allowed. Only plugins/ or themes/ are accessible.' );
		}
		$full_path = $this->repo_path . '/' . $path;

		// Generate branch name.
		$timestamp   = gmdate( 'Ymd-His' );
		$path_slug   = preg_replace( '/[^a-z0-9]+/', '-', strtolower( basename( $path, '.' . pathinfo( $path, PATHINFO_EXTENSION ) ) ) );
		$branch_name = "agent/{$path_slug}-{$timestamp}";

		// Get current branch to return to later.
		$current_branch = $this->git_exec( 'git rev-parse --abbrev-ref HEAD' );
		if ( ! $current_branch ) {
			return array( 'error' => 'Failed to get current git branch' );
		}
		$current_branch = trim( $current_branch );

		// Create and checkout new branch.
		$result = $this->git_exec( 'git checkout -b ' . escapeshellarg( $branch_name ) );
		if ( false === $result ) {
			return array( 'error' => 'Failed to create git branch: ' . $branch_name );
		}

		// Write the file using WP_Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$dir = dirname( $full_path );
		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->mkdir( $dir, FS_CHMOD_DIR );
		}

		if ( ! $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE ) ) {
			// Checkout back to original branch.
			$this->git_exec( 'git checkout ' . escapeshellarg( $current_branch ) );
			$this->git_exec( 'git branch -D ' . escapeshellarg( $branch_name ) );
			return array( 'error' => 'Failed to write file: ' . $path );
		}

		// Stage and commit.
		$commit_message = "feat(agent): {$reasoning}\n\nProposed by Onboarding Agent\nFile: {$path}";
		$this->git_exec( 'git add ' . escapeshellarg( $path ) );
		$commit_result = $this->git_exec( 'git commit -m ' . escapeshellarg( $commit_message ) );

		if ( false === $commit_result ) {
			// Checkout back and clean up.
			$this->git_exec( 'git checkout ' . escapeshellarg( $current_branch ) );
			$this->git_exec( 'git branch -D ' . escapeshellarg( $branch_name ) );
			return array( 'error' => 'Failed to commit changes' );
		}

		// Switch back to original branch.
		$this->git_exec( 'git checkout ' . escapeshellarg( $current_branch ) );

		// Log the action.
		$this->audit->log(
			'developer_agent',
			'request_code_change',
			'git_branch',
			array(
				'branch'    => $branch_name,
				'path'      => $path,
				'reasoning' => $reasoning,
			)
		);

		// Build response with instructions.
		$remote_url    = trim( $this->git_exec( 'git remote get-url origin' ) ?? '' );
		$github_pr_url = '';
		if ( preg_match( '#github\.com[:/]([^/]+/[^/\.]+)#', $remote_url, $matches ) ) {
			$repo          = $matches[1];
			$github_pr_url = "https://github.com/{$repo}/compare/{$current_branch}...{$branch_name}?expand=1";
		}

		return array(
			'success'        => true,
			'branch'         => $branch_name,
			'base_branch'    => $current_branch,
			'path'           => $path,
			'message'        => "Code change committed to branch '{$branch_name}'. Please review and merge.",
			'review_command' => "git diff {$current_branch}...{$branch_name}",
			'merge_command'  => "git checkout {$current_branch} && git merge {$branch_name}",
			'pr_url'         => $github_pr_url ? $github_pr_url : null,
		);
	}

	/**
	 * Execute a git command
	 *
	 * SECURITY: Git commands are disabled to prevent command execution vulnerabilities.
	 * This method now returns false to disable git operations.
	 *
	 * @param string $command Git command.
	 * @return string|false Output or false on failure.
	 */
	/**
	 * Manage agent scheduled tasks
	 *
	 * @param array $args Arguments: operation, agent_id, task_id.
	 * @return array Result.
	 */
	private function manage_schedules( array $args ): array {
		$operation = $args['operation'] ?? 'list';
		$registry  = \Agentic_Agent_Registry::get_instance();

		switch ( $operation ) {
			case 'list':
				$instances = $registry->get_all_instances();
				$all_tasks = array();

				foreach ( $instances as $agent ) {
					$tasks = $agent->get_scheduled_tasks();
					foreach ( $tasks as $task ) {
						$hook     = $agent->get_cron_hook( $task['id'] );
						$next_run = wp_next_scheduled( $hook );

						$all_tasks[] = array(
							'agent_id'   => $agent->get_id(),
							'agent_name' => $agent->get_name(),
							'task_id'    => $task['id'],
							'task_name'  => $task['name'],
							'schedule'   => $task['schedule'],
							'active'     => false !== $next_run,
							'next_run'   => $next_run ? wp_date( 'Y-m-d H:i:s', $next_run ) : null,
							'mode'       => ! empty( $task['prompt'] ) ? 'autonomous' : 'direct',
						);
					}
				}

				return array(
					'schedules'   => $all_tasks,
					'total_tasks' => count( $all_tasks ),
				);

			case 'pause':
				$agent_id = $args['agent_id'] ?? '';
				$task_id  = $args['task_id'] ?? '';
				$instance = $registry->get_agent_instance( $agent_id );

				if ( ! $instance ) {
					return array( 'error' => 'Agent not found: ' . $agent_id );
				}

				$tasks = $instance->get_scheduled_tasks();
				foreach ( $tasks as $task ) {
					if ( $task['id'] === $task_id ) {
						$hook      = $instance->get_cron_hook( $task_id );
						$timestamp = wp_next_scheduled( $hook );

						if ( $timestamp ) {
							wp_unschedule_event( $timestamp, $hook );
							$this->audit->log( $agent_id, 'schedule_paused', $task_id );
							return array(
								'success' => true,
								'message' => sprintf( "Paused task '%s' for agent '%s'", $task['name'], $instance->get_name() ),
							);
						}
						return array( 'error' => 'Task is not currently scheduled' );
					}
				}
				return array( 'error' => 'Task not found: ' . $task_id );

			case 'resume':
				$agent_id = $args['agent_id'] ?? '';
				$task_id  = $args['task_id'] ?? '';
				$instance = $registry->get_agent_instance( $agent_id );

				if ( ! $instance ) {
					return array( 'error' => 'Agent not found: ' . $agent_id );
				}

				$tasks = $instance->get_scheduled_tasks();
				foreach ( $tasks as $task ) {
					if ( $task['id'] === $task_id ) {
						$hook = $instance->get_cron_hook( $task_id );

						if ( ! wp_next_scheduled( $hook ) ) {
							wp_schedule_event( time(), $task['schedule'], $hook );
							$this->audit->log( $agent_id, 'schedule_resumed', $task_id );
							return array(
								'success' => true,
								'message' => sprintf( "Resumed task '%s' for agent '%s'", $task['name'], $instance->get_name() ),
							);
						}
						return array( 'error' => 'Task is already scheduled' );
					}
				}
				return array( 'error' => 'Task not found: ' . $task_id );

			default:
				return array( 'error' => 'Unknown operation: ' . $operation . '. Use list, pause, or resume.' );
		}
	}

	/**
	 * Execute a read-only database query
	 *
	 * Only SELECT statements are allowed. Results capped at 100 rows.
	 *
	 * @param string $query SQL query with {prefix} placeholder.
	 * @return array Query results or error.
	 */
	private function query_database( string $query ): array {
		global $wpdb;

		if ( empty( $query ) ) {
			return array( 'error' => 'Query cannot be empty.' );
		}

		// Replace {prefix} placeholder with actual table prefix.
		$query = str_replace( '{prefix}', $wpdb->prefix, $query );

		// Security: only allow SELECT queries.
		$normalized = trim( preg_replace( '/\s+/', ' ', $query ) );
		$first_word = strtoupper( strtok( $normalized, ' ' ) );

		if ( 'SELECT' !== $first_word ) {
			return array( 'error' => 'Only SELECT queries are allowed. Use WordPress functions for data modification.' );
		}

		// Block dangerous patterns even in SELECT context.
		$dangerous = array( 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE', 'BENCHMARK(', 'SLEEP(' );
		$upper     = strtoupper( $normalized );
		foreach ( $dangerous as $pattern ) {
			if ( false !== strpos( $upper, $pattern ) ) {
				return array( 'error' => 'Query contains a disallowed pattern: ' . $pattern );
			}
		}

		// Enforce a LIMIT if none is present.
		if ( false === stripos( $normalized, ' LIMIT ' ) ) {
			$query .= ' LIMIT 100';
		}

		// Execute the query.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Read-only, validated SELECT query.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( null === $results ) {
			return array(
				'error'      => 'Query execution failed.',
				'db_error'   => $wpdb->last_error,
				'last_query' => $wpdb->last_query,
			);
		}

		return array(
			'results'   => $results,
			'row_count' => count( $results ),
			'query'     => $wpdb->last_query,
		);
	}

	/**
	 * Read the WordPress debug.log file
	 *
	 * @param array $args Arguments: lines (int), filter (string).
	 * @return array Log contents or error.
	 */
	private function get_error_log( array $args ): array {
		$max_lines = min( (int) ( $args['lines'] ?? 50 ), 200 );
		$filter    = $args['filter'] ?? '';

		// Find the log file.
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			$log_file = WP_DEBUG_LOG;
		}

		if ( ! file_exists( $log_file ) ) {
			return array(
				'error'    => 'Debug log file not found. Ensure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php.',
				'expected' => $log_file,
			);
		}

		if ( ! is_readable( $log_file ) ) {
			return array( 'error' => 'Debug log file is not readable.' );
		}

		$file_size = filesize( $log_file );

		// Read the last portion of the file efficiently.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local log file.
		$content = '';
		// Read up to 256 KB from end.
		$read_bytes = min( $file_size, 262144 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading local log file.
		$fp = fopen( $log_file, 'r' );
		if ( $fp ) {
			if ( $file_size > $read_bytes ) {
				fseek( $fp, -$read_bytes, SEEK_END );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reading local log file.
			$content = fread( $fp, $read_bytes );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading local log file.
			fclose( $fp );
		}

		$lines = explode( "\n", $content );

		// If we seeked into middle of file, drop the first (partial) line.
		if ( $file_size > $read_bytes ) {
			array_shift( $lines );
		}

		// Apply filter if provided.
		if ( $filter ) {
			$lines = array_filter(
				$lines,
				function ( $line ) use ( $filter ) {
					return false !== stripos( $line, $filter );
				}
			);
			$lines = array_values( $lines );
		}

		// Get last N lines.
		$lines = array_slice( $lines, -$max_lines );

		return array(
			'lines'     => $lines,
			'count'     => count( $lines ),
			'file_size' => $file_size,
			'file'      => $log_file,
			'filter'    => $filter ?: null,
		);
	}

	/**
	 * Get comprehensive site health information
	 *
	 * @return array Site health data.
	 */
	private function get_site_health(): array {
		global $wpdb;

		// WordPress info.
		$wp_info = array(
			'version'   => get_bloginfo( 'version' ),
			'site_url'  => get_site_url(),
			'home_url'  => get_home_url(),
			'multisite' => is_multisite(),
			'language'  => get_locale(),
		);

		// PHP info.
		$php_info = array(
			'version'         => PHP_VERSION,
			'memory_limit'    => ini_get( 'memory_limit' ),
			'max_exec_time'   => ini_get( 'max_execution_time' ),
			'upload_max_size' => ini_get( 'upload_max_filesize' ),
			'post_max_size'   => ini_get( 'post_max_size' ),
			'sapi'            => PHP_SAPI,
			'extensions'      => get_loaded_extensions(),
		);

		// Memory usage.
		$memory = array(
			'current_usage'    => size_format( memory_get_usage() ),
			'peak_usage'       => size_format( memory_get_peak_usage() ),
			'wp_memory_limit'  => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'not set',
			'wp_max_memory'    => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'not set',
		);

		// Database info.
		$db_info = array(
			'server_version' => $wpdb->db_version(),
			'prefix'         => $wpdb->prefix,
			'charset'        => $wpdb->charset,
			'collate'        => $wpdb->collate,
		);

		// Table sizes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
				 FROM information_schema.TABLES
				 WHERE table_schema = %s
				 ORDER BY (data_length + index_length) DESC
				 LIMIT 20",
				DB_NAME
			),
			ARRAY_A
		);
		$db_info['tables'] = $tables ?: array();

		// Active plugins.
		$active_plugins = get_option( 'active_plugins', array() );
		$plugins_info   = array();
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( $active_plugins as $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( file_exists( $plugin_path ) ) {
				$data           = get_plugin_data( $plugin_path, false, false );
				$plugins_info[] = array(
					'name'    => $data['Name'],
					'version' => $data['Version'],
					'file'    => $plugin_file,
				);
			}
		}

		// Active theme.
		$theme      = wp_get_theme();
		$theme_info = array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'template'    => $theme->get_template(),
			'stylesheet'  => $theme->get_stylesheet(),
			'parent'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
		);

		// Cron info.
		$cron_events = _get_cron_array();
		$cron_count  = 0;
		if ( $cron_events ) {
			foreach ( $cron_events as $hooks ) {
				$cron_count += count( $hooks );
			}
		}

		// Debug settings.
		$debug = array(
			'WP_DEBUG'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'SCRIPT_DEBUG'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
		);

		// Post counts.
		$post_counts = array();
		$post_types  = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$post_counts[ $pt ] = array(
				'publish' => (int) $counts->publish,
				'draft'   => (int) $counts->draft,
				'total'   => array_sum( (array) $counts ),
			);
		}

		return array(
			'wordpress'      => $wp_info,
			'php'            => $php_info,
			'memory'         => $memory,
			'database'       => $db_info,
			'active_plugins' => $plugins_info,
			'theme'          => $theme_info,
			'cron_events'    => $cron_count,
			'debug'          => $debug,
			'post_counts'    => $post_counts,
		);
	}

	/**
	 * Manage WordPress cron events
	 *
	 * @param array $args Arguments: operation, hook, timestamp.
	 * @return array Result.
	 */
	private function manage_wp_cron( array $args ): array {
		$operation = $args['operation'] ?? 'list';

		switch ( $operation ) {
			case 'list':
				$cron_array = _get_cron_array();
				$events     = array();

				if ( $cron_array ) {
					foreach ( $cron_array as $timestamp => $hooks ) {
						foreach ( $hooks as $hook => $schedules ) {
							foreach ( $schedules as $key => $data ) {
								$events[] = array(
									'hook'      => $hook,
									'timestamp' => $timestamp,
									'next_run'  => wp_date( 'Y-m-d H:i:s', $timestamp ),
									'schedule'  => $data['schedule'] ?: 'single',
									'interval'  => $data['interval'] ?? null,
									'args'      => $data['args'],
								);
							}
						}
					}

					// Sort by next run time.
					usort( $events, fn( $a, $b ) => $a['timestamp'] <=> $b['timestamp'] );
				}

				return array(
					'events'      => $events,
					'total_count' => count( $events ),
				);

			case 'delete':
				$hook      = $args['hook'] ?? '';
				$timestamp = (int) ( $args['timestamp'] ?? 0 );

				if ( empty( $hook ) || empty( $timestamp ) ) {
					return array( 'error' => 'Both hook and timestamp are required for delete operation.' );
				}

				// Find the event to get its args.
				$cron_array = _get_cron_array();
				if ( isset( $cron_array[ $timestamp ][ $hook ] ) ) {
					foreach ( $cron_array[ $timestamp ][ $hook ] as $key => $data ) {
						wp_unschedule_event( $timestamp, $hook, $data['args'] );
					}
					return array(
						'success' => true,
						'message' => sprintf( 'Deleted cron event "%s" scheduled for %s', $hook, wp_date( 'Y-m-d H:i:s', $timestamp ) ),
					);
				}

				return array( 'error' => sprintf( 'Cron event not found: hook=%s, timestamp=%d', $hook, $timestamp ) );

			default:
				return array( 'error' => 'Unknown operation: ' . $operation . '. Use list or delete.' );
		}
	}

	/**
	 * Get WordPress users
	 *
	 * @param array $args Arguments: role, search, limit.
	 * @return array User list.
	 */
	private function get_users( array $args ): array {
		$query_args = array(
			'number'  => min( (int) ( $args['limit'] ?? 20 ), 50 ),
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( ! empty( $args['role'] ) ) {
			$query_args['role'] = sanitize_text_field( $args['role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search']         = '*' . sanitize_text_field( $args['search'] ) . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$user_query = new \WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$result     = array();

		foreach ( $users as $user ) {
			$result[] = array(
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
				'registered'   => $user->user_registered,
				'post_count'   => count_user_posts( $user->ID ),
			);
		}

		// Available roles.
		$wp_roles       = wp_roles();
		$available_roles = array();
		foreach ( $wp_roles->role_names as $slug => $name ) {
			$available_roles[ $slug ] = $name;
		}

		return array(
			'users'           => $result,
			'total_found'     => $user_query->get_total(),
			'available_roles' => $available_roles,
		);
	}

	/**
	 * Get a WordPress option value
	 *
	 * Sensitive options are redacted.
	 *
	 * @param string $name Option name.
	 * @return array Option value or error.
	 */
	private function get_option( string $name ): array {
		if ( empty( $name ) ) {
			return array( 'error' => 'Option name is required.' );
		}

		// Block sensitive options.
		$sensitive_patterns = array(
			'password', 'secret', 'api_key', 'apikey', 'auth_key', 'auth_salt',
			'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt',
			'secure_auth_key', 'secure_auth_salt', 'stripe', 'paypal',
		);

		$lower_name = strtolower( $name );
		foreach ( $sensitive_patterns as $pattern ) {
			if ( false !== strpos( $lower_name, $pattern ) ) {
				return array(
					'name'  => $name,
					'error' => 'This option is blocked for security reasons. Sensitive options containing passwords, keys, or secrets cannot be read.',
				);
			}
		}

		$value = get_option( $name, null );

		if ( null === $value ) {
			return array(
				'name'   => $name,
				'exists' => false,
				'error'  => 'Option not found.',
			);
		}

		// Serialize complex values for display.
		$display_value = $value;
		if ( is_array( $value ) || is_object( $value ) ) {
			$display_value = $value; // Will be JSON-encoded by the caller.
		}

		return array(
			'name'   => $name,
			'exists' => true,
			'value'  => $display_value,
			'type'   => gettype( $value ),
		);
	}

	/**
	 * Execute a git command (DISABLED for security)
	 *
	 * Git commands are disabled to prevent remote code execution.
	 * Changes are written to disk but require manual git commits via terminal.
	 *
	 * @param string $_command Git command to execute (unused - git execution disabled).
	 * @return false Always returns false; git execution is disabled.
	 */
	private function git_exec( string $_command ): string|false { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Git command execution is intentionally disabled for security.
		// Future implementation should use a safe git library or background job.
		return false;
	}

	/**
	 * Sanitize file path to prevent traversal
	 *
	 * @param string $path Input path.
	 * @return string Sanitized path.
	 */
	/**
	 * Sanitize and validate file path to prevent traversal attacks
	 *
	 * @param string $path Path to sanitize.
	 * @return string Sanitized path.
	 */
	private function sanitize_path( string $path ): string {
		$path = str_replace( '..', '', $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}
}
