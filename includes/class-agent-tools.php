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
						'properties' => array(),
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
					'display_name' => 'Developer Agent',
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
				'comment_author'       => 'Developer Agent',
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
		$commit_message = "feat(agent): {$reasoning}\n\nProposed by Developer Agent\nFile: {$path}";
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
