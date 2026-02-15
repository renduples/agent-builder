<?php
/**
 * Unit Tests for Agent Tools
 *
 * Tests tool definitions, path sanitization, security constraints,
 * core tool execution (read_file, list_directory, search_code,
 * get_posts, get_comments, create_comment), and unknown tool handling.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Agent_Tools;

/**
 * Test case for Agent_Tools class.
 */
class Test_Agent_Tools extends TestCase {

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
		$this->tools = new Agent_Tools();
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Test get_allowed_repo_base returns wp-content.
	 */
	public function test_get_allowed_repo_base() {
		$base = Agent_Tools::get_allowed_repo_base();
		$this->assertEquals( WP_CONTENT_DIR, $base );
	}

	/**
	 * Test is_allowed_subpath accepts plugins/.
	 */
	public function test_is_allowed_subpath_plugins() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( 'plugins/my-plugin/file.php' ) );
	}

	/**
	 * Test is_allowed_subpath accepts themes/.
	 */
	public function test_is_allowed_subpath_themes() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( 'themes/my-theme/style.css' ) );
	}

	/**
	 * Test is_allowed_subpath accepts bare plugins.
	 */
	public function test_is_allowed_subpath_bare_plugins() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( 'plugins' ) );
	}

	/**
	 * Test is_allowed_subpath accepts bare themes.
	 */
	public function test_is_allowed_subpath_bare_themes() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( 'themes' ) );
	}

	/**
	 * Test is_allowed_subpath rejects uploads/.
	 */
	public function test_is_allowed_subpath_rejects_uploads() {
		$this->assertFalse( Agent_Tools::is_allowed_subpath( 'uploads/2025/01/secret.pdf' ) );
	}

	/**
	 * Test is_allowed_subpath rejects root paths.
	 */
	public function test_is_allowed_subpath_rejects_root() {
		$this->assertFalse( Agent_Tools::is_allowed_subpath( 'wp-config.php' ) );
	}

	/**
	 * Test is_allowed_subpath strips traversal.
	 */
	public function test_is_allowed_subpath_strips_traversal() {
		// After stripping "..", "../uploads" becomes "/uploads" → "uploads" which is not allowed.
		$this->assertFalse( Agent_Tools::is_allowed_subpath( '../uploads/secret.pdf' ) );
	}

	/**
	 * Test is_allowed_subpath with leading slash.
	 */
	public function test_is_allowed_subpath_leading_slash() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( '/plugins/my-plugin/file.php' ) );
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	/**
	 * Test get_tool_definitions returns array.
	 */
	public function test_get_tool_definitions_returns_array() {
		$tools = $this->tools->get_tool_definitions();
		$this->assertIsArray( $tools );
	}

	/**
	 * Test core tools are present.
	 */
	public function test_core_tools_present() {
		$tools = $this->tools->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$expected = array(
			'read_file',
			'list_directory',
			'search_code',
			'get_posts',
			'get_comments',
			'create_comment',
			'update_documentation',
			'request_code_change',
		);

		foreach ( $expected as $name ) {
			$this->assertContains( $name, $names, "Missing core tool: {$name}" );
		}
	}

	/**
	 * Test tool definitions have correct structure.
	 */
	public function test_tool_definition_structure() {
		$tools = $this->tools->get_tool_definitions();

		foreach ( $tools as $tool ) {
			$this->assertEquals( 'function', $tool['type'] );
			$this->assertArrayHasKey( 'function', $tool );
			$this->assertArrayHasKey( 'name', $tool['function'] );
			$this->assertArrayHasKey( 'description', $tool['function'] );
			$this->assertArrayHasKey( 'parameters', $tool['function'] );
		}
	}

	/**
	 * Test at least 8 core tools.
	 */
	public function test_minimum_tool_count() {
		$tools = $this->tools->get_tool_definitions();
		$this->assertGreaterThanOrEqual( 8, count( $tools ) );
	}

	// -------------------------------------------------------------------------
	// execute — unknown tool
	// -------------------------------------------------------------------------

	/**
	 * Test execute returns WP_Error for unknown tool.
	 */
	public function test_execute_unknown_tool() {
		$result = $this->tools->execute( 'nonexistent_tool', array() );
		$this->assertWPError( $result );
		$this->assertEquals( 'unknown_tool', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// execute — read_file
	// -------------------------------------------------------------------------

	/**
	 * Test read_file returns error for disallowed path.
	 */
	public function test_read_file_disallowed_path() {
		$result = $this->tools->execute( 'read_file', array( 'path' => 'uploads/secret.txt' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not allowed', $result['error'] );
	}

	/**
	 * Test read_file returns error for nonexistent file.
	 */
	public function test_read_file_nonexistent() {
		$result = $this->tools->execute( 'read_file', array( 'path' => 'plugins/no-such-file-xyz.php' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test read_file blocks path traversal.
	 */
	public function test_read_file_blocks_traversal() {
		$result = $this->tools->execute( 'read_file', array( 'path' => '../../wp-config.php' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test read_file succeeds for a real plugin file.
	 */
	public function test_read_file_success() {
		// agent-builder.php is inside plugins/.
		$result = $this->tools->execute(
			'read_file',
			array( 'path' => 'plugins/agent-builder/agent-builder.php' )
		);
		$this->assertIsArray( $result );
		// Should have 'content' key on success.
		if ( isset( $result['content'] ) ) {
			$this->assertStringContainsString( 'Agent Builder', $result['content'] );
		}
	}

	// -------------------------------------------------------------------------
	// execute — list_directory
	// -------------------------------------------------------------------------

	/**
	 * Test list_directory with empty path returns allowed roots.
	 */
	public function test_list_directory_root() {
		$result = $this->tools->execute( 'list_directory', array( 'path' => '' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );

		$names = array_column( $result['items'], 'name' );
		$this->assertContains( 'plugins', $names );
		$this->assertContains( 'themes', $names );
	}

	/**
	 * Test list_directory for plugins/.
	 */
	public function test_list_directory_plugins() {
		$result = $this->tools->execute( 'list_directory', array( 'path' => 'plugins' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertNotEmpty( $result['items'] );
	}

	/**
	 * Test list_directory rejects disallowed path.
	 */
	public function test_list_directory_disallowed() {
		$result = $this->tools->execute( 'list_directory', array( 'path' => 'uploads' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test list_directory returns file type info.
	 */
	public function test_list_directory_item_structure() {
		$result = $this->tools->execute( 'list_directory', array( 'path' => 'plugins' ) );

		if ( ! empty( $result['items'] ) ) {
			$item = $result['items'][0];
			$this->assertArrayHasKey( 'name', $item );
			$this->assertArrayHasKey( 'type', $item );
			$this->assertContains( $item['type'], array( 'file', 'directory' ) );
		}
	}

	// -------------------------------------------------------------------------
	// execute — search_code
	// -------------------------------------------------------------------------

	/**
	 * Test search_code returns results array.
	 */
	public function test_search_code_structure() {
		$result = $this->tools->execute(
			'search_code',
			array( 'pattern' => 'Agent_Tools' )
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'results', $result );
		$this->assertArrayHasKey( 'count', $result );
	}

	/**
	 * Test search_code with file_type filter.
	 */
	public function test_search_code_with_file_type() {
		$result = $this->tools->execute(
			'search_code',
			array(
				'pattern'   => 'class',
				'file_type' => 'php',
			)
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'results', $result );
	}

	/**
	 * Test search_code result items have expected fields.
	 */
	public function test_search_code_result_fields() {
		$result = $this->tools->execute(
			'search_code',
			array( 'pattern' => 'AGENTIC_PLUGIN_VERSION' )
		);

		$this->assertIsArray( $result );

		if ( ! empty( $result['results'] ) ) {
			$item = $result['results'][0];
			$this->assertArrayHasKey( 'file', $item );
			$this->assertArrayHasKey( 'line', $item );
			$this->assertArrayHasKey( 'match', $item );
		}
	}

	// -------------------------------------------------------------------------
	// execute — get_posts
	// -------------------------------------------------------------------------

	/**
	 * Test get_posts returns posts array.
	 */
	public function test_get_posts() {
		// Create a test post.
		$this->factory->post->create( array( 'post_title' => 'Agent Tools Test Post' ) );

		$result = $this->tools->execute( 'get_posts', array() );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
	}

	/**
	 * Test get_posts respects limit.
	 */
	public function test_get_posts_limit() {
		$this->factory->post->create_many( 5 );

		$result = $this->tools->execute( 'get_posts', array( 'limit' => 2 ) );
		$this->assertLessThanOrEqual( 2, count( $result['posts'] ) );
	}

	/**
	 * Test get_posts caps limit at 50.
	 */
	public function test_get_posts_max_limit() {
		// Request 100, should cap at 50 internally.
		$result = $this->tools->execute( 'get_posts', array( 'limit' => 100 ) );
		$this->assertIsArray( $result['posts'] );
	}

	/**
	 * Test get_posts response structure.
	 */
	public function test_get_posts_structure() {
		$this->factory->post->create( array( 'post_title' => 'Structure Test Post' ) );

		$result = $this->tools->execute( 'get_posts', array() );

		if ( ! empty( $result['posts'] ) ) {
			$post = $result['posts'][0];
			$this->assertArrayHasKey( 'id', $post );
			$this->assertArrayHasKey( 'title', $post );
			$this->assertArrayHasKey( 'slug', $post );
			$this->assertArrayHasKey( 'excerpt', $post );
			$this->assertArrayHasKey( 'date', $post );
			$this->assertArrayHasKey( 'url', $post );
		}
	}

	// -------------------------------------------------------------------------
	// execute — get_comments
	// -------------------------------------------------------------------------

	/**
	 * Test get_comments returns comments array.
	 */
	public function test_get_comments() {
		$post_id = $this->factory->post->create();
		$this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$result = $this->tools->execute( 'get_comments', array( 'post_id' => $post_id ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'comments', $result );
	}

	/**
	 * Test get_comments response structure.
	 */
	public function test_get_comments_structure() {
		$post_id = $this->factory->post->create();
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);

		$result = $this->tools->execute( 'get_comments', array( 'post_id' => $post_id ) );

		if ( ! empty( $result['comments'] ) ) {
			$comment = $result['comments'][0];
			$this->assertArrayHasKey( 'id', $comment );
			$this->assertArrayHasKey( 'post_id', $comment );
			$this->assertArrayHasKey( 'author', $comment );
			$this->assertArrayHasKey( 'content', $comment );
			$this->assertArrayHasKey( 'date', $comment );
		}
	}

	// -------------------------------------------------------------------------
	// execute — create_comment
	// -------------------------------------------------------------------------

	/**
	 * Test create_comment creates a comment.
	 */
	public function test_create_comment() {
		$post_id = $this->factory->post->create();

		$result = $this->tools->execute(
			'create_comment',
			array(
				'post_id' => $post_id,
				'content' => 'Agent generated comment',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'comment_id', $result );
		$this->assertEquals( $post_id, $result['post_id'] );
	}

	/**
	 * Test create_comment stores correct content.
	 */
	public function test_create_comment_content() {
		$post_id = $this->factory->post->create();

		$result = $this->tools->execute(
			'create_comment',
			array(
				'post_id' => $post_id,
				'content' => 'This is a test comment from the agent',
			)
		);

		$comment = get_comment( $result['comment_id'] );
		$this->assertEquals( 'This is a test comment from the agent', $comment->comment_content );
	}

	// -------------------------------------------------------------------------
	// execute — update_documentation
	// -------------------------------------------------------------------------

	/**
	 * Test update_documentation rejects non-doc files.
	 */
	public function test_update_documentation_rejects_php() {
		$result = $this->tools->execute(
			'update_documentation',
			array(
				'path'      => 'plugins/test/code.php',
				'content'   => '<?php echo "hack";',
				'reasoning' => 'test',
			)
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'documentation', $result['error'] );
	}

	/**
	 * Test update_documentation rejects disallowed path.
	 */
	public function test_update_documentation_rejects_disallowed() {
		$result = $this->tools->execute(
			'update_documentation',
			array(
				'path'      => 'uploads/readme.md',
				'content'   => 'content',
				'reasoning' => 'test',
			)
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — request_code_change
	// -------------------------------------------------------------------------

	/**
	 * Test request_code_change rejects disallowed path.
	 */
	public function test_request_code_change_rejects_disallowed() {
		$result = $this->tools->execute(
			'request_code_change',
			array(
				'path'      => 'uploads/hack.php',
				'content'   => '<?php',
				'reasoning' => 'test',
			)
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// Agent-registered tool handlers
	// -------------------------------------------------------------------------

	/**
	 * Test agent-registered tools are included in definitions.
	 */
	public function test_agent_registered_tools() {
		add_filter(
			'agentic_agent_tools',
			function ( $tools ) {
				$tools['custom_tool'] = array(
					'name'        => 'custom_tool',
					'description' => 'A custom agent tool',
					'handler'     => function ( $args ) {
						return array( 'custom_result' => true );
					},
				);
				return $tools;
			}
		);

		$tools = $this->tools->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$this->assertContains( 'custom_tool', $names );

		// Clean up.
		remove_all_filters( 'agentic_agent_tools' );
	}

	/**
	 * Test execute calls agent-registered handler.
	 */
	public function test_execute_agent_handler() {
		add_filter(
			'agentic_agent_tools',
			function ( $tools ) {
				$tools['test_handler'] = array(
					'name'        => 'test_handler',
					'description' => 'Test handler tool',
					'handler'     => function ( $args ) {
						return array(
							'handled' => true,
							'input'   => $args,
						);
					},
				);
				return $tools;
			}
		);

		// Force reload of tool definitions.
		$fresh_tools = new Agent_Tools();
		$result      = $fresh_tools->execute( 'test_handler', array( 'key' => 'value' ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['handled'] );
		$this->assertEquals( 'value', $result['input']['key'] );

		remove_all_filters( 'agentic_agent_tools' );
	}

	// -------------------------------------------------------------------------
	// Tool definitions — new tools present
	// -------------------------------------------------------------------------

	/**
	 * Test new core tools are present in definitions.
	 */
	public function test_new_core_tools_present() {
		$tools = $this->tools->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$expected_new = array(
			'query_database',
			'get_error_log',
			'get_site_health',
			'manage_wp_cron',
			'get_users',
			'get_option',
		);

		foreach ( $expected_new as $name ) {
			$this->assertContains( $name, $names, "Missing new core tool: {$name}" );
		}
	}

	/**
	 * Test total core tool count is now 15.
	 */
	public function test_tool_count_fifteen() {
		$tools = $this->tools->get_tool_definitions();
		$this->assertGreaterThanOrEqual( 15, count( $tools ) );
	}

	// -------------------------------------------------------------------------
	// execute — query_database
	// -------------------------------------------------------------------------

	/**
	 * Test query_database returns results for valid SELECT.
	 */
	public function test_query_database_select() {
		$this->factory->post->create( array( 'post_title' => 'DB Query Test' ) );

		$result = $this->tools->execute(
			'query_database',
			array( 'query' => "SELECT ID, post_title FROM {prefix}posts WHERE post_title = 'DB Query Test'" )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'results', $result );
		$this->assertArrayHasKey( 'row_count', $result );
		$this->assertGreaterThanOrEqual( 1, $result['row_count'] );
	}

	/**
	 * Test query_database rejects INSERT.
	 */
	public function test_query_database_rejects_insert() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => "INSERT INTO {prefix}posts (post_title) VALUES ('hacked')" )
		);
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'SELECT', $result['error'] );
	}

	/**
	 * Test query_database rejects UPDATE.
	 */
	public function test_query_database_rejects_update() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => "UPDATE {prefix}posts SET post_title = 'hacked'" )
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test query_database rejects DELETE.
	 */
	public function test_query_database_rejects_delete() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => 'DELETE FROM {prefix}posts' )
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test query_database rejects DROP.
	 */
	public function test_query_database_rejects_drop() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => 'DROP TABLE {prefix}posts' )
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test query_database rejects empty query.
	 */
	public function test_query_database_rejects_empty() {
		$result = $this->tools->execute( 'query_database', array( 'query' => '' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test query_database replaces prefix placeholder.
	 */
	public function test_query_database_replaces_prefix() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => 'SELECT COUNT(*) AS cnt FROM {prefix}options LIMIT 1' )
		);
		$this->assertArrayHasKey( 'results', $result );
		// The resolved query should not contain {prefix}.
		$this->assertStringNotContainsString( '{prefix}', $result['query'] );
	}

	/**
	 * Test query_database blocks SLEEP injection.
	 */
	public function test_query_database_blocks_sleep() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => 'SELECT SLEEP(10)' )
		);
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'disallowed', $result['error'] );
	}

	/**
	 * Test query_database blocks BENCHMARK.
	 */
	public function test_query_database_blocks_benchmark() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => "SELECT BENCHMARK(1000000, SHA1('test'))" )
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test query_database adds LIMIT if missing.
	 */
	public function test_query_database_adds_limit() {
		$result = $this->tools->execute(
			'query_database',
			array( 'query' => 'SELECT option_name FROM {prefix}options' )
		);
		$this->assertArrayHasKey( 'results', $result );
		$this->assertLessThanOrEqual( 100, $result['row_count'] );
	}

	// -------------------------------------------------------------------------
	// execute — get_error_log
	// -------------------------------------------------------------------------

	/**
	 * Test get_error_log returns structure.
	 */
	public function test_get_error_log_structure() {
		$result = $this->tools->execute( 'get_error_log', array() );
		$this->assertIsArray( $result );
		// Either has 'lines' (log exists) or 'error' (log not found).
		$this->assertTrue(
			isset( $result['lines'] ) || isset( $result['error'] ),
			'get_error_log should return lines or error'
		);
	}

	/**
	 * Test get_error_log respects line limit.
	 */
	public function test_get_error_log_line_limit() {
		$result = $this->tools->execute( 'get_error_log', array( 'lines' => 5 ) );
		$this->assertIsArray( $result );
		if ( isset( $result['lines'] ) ) {
			$this->assertLessThanOrEqual( 5, count( $result['lines'] ) );
		}
	}

	/**
	 * Test get_error_log caps at 200 lines.
	 */
	public function test_get_error_log_max_cap() {
		$result = $this->tools->execute( 'get_error_log', array( 'lines' => 999 ) );
		$this->assertIsArray( $result );
		if ( isset( $result['lines'] ) ) {
			$this->assertLessThanOrEqual( 200, count( $result['lines'] ) );
		}
	}

	// -------------------------------------------------------------------------
	// execute — get_site_health
	// -------------------------------------------------------------------------

	/**
	 * Test get_site_health returns all sections.
	 */
	public function test_get_site_health_sections() {
		$result = $this->tools->execute( 'get_site_health', array() );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'WordPress', $result );
		$this->assertArrayHasKey( 'php', $result );
		$this->assertArrayHasKey( 'memory', $result );
		$this->assertArrayHasKey( 'database', $result );
		$this->assertArrayHasKey( 'active_plugins', $result );
		$this->assertArrayHasKey( 'theme', $result );
		$this->assertArrayHasKey( 'debug', $result );
		$this->assertArrayHasKey( 'post_counts', $result );
	}

	/**
	 * Test get_site_health WordPress info.
	 */
	public function test_get_site_health_wp_info() {
		$result = $this->tools->execute( 'get_site_health', array() );
		$this->assertArrayHasKey( 'version', $result['wordpress'] );
		$this->assertArrayHasKey( 'site_url', $result['wordpress'] );
		$this->assertNotEmpty( $result['wordpress']['version'] );
	}

	/**
	 * Test get_site_health PHP info.
	 */
	public function test_get_site_health_php_info() {
		$result = $this->tools->execute( 'get_site_health', array() );
		$this->assertEquals( PHP_VERSION, $result['php']['version'] );
		$this->assertArrayHasKey( 'extensions', $result['php'] );
		$this->assertIsArray( $result['php']['extensions'] );
	}

	/**
	 * Test get_site_health memory info.
	 */
	public function test_get_site_health_memory() {
		$result = $this->tools->execute( 'get_site_health', array() );
		$this->assertArrayHasKey( 'current_usage', $result['memory'] );
		$this->assertArrayHasKey( 'peak_usage', $result['memory'] );
	}

	/**
	 * Test get_site_health database info.
	 */
	public function test_get_site_health_database() {
		$result = $this->tools->execute( 'get_site_health', array() );
		$this->assertArrayHasKey( 'server_version', $result['database'] );
		$this->assertArrayHasKey( 'prefix', $result['database'] );
		$this->assertArrayHasKey( 'tables', $result['database'] );
	}

	// -------------------------------------------------------------------------
	// execute — manage_wp_cron
	// -------------------------------------------------------------------------

	/**
	 * Test manage_wp_cron list returns events.
	 */
	public function test_manage_wp_cron_list() {
		$result = $this->tools->execute( 'manage_wp_cron', array( 'operation' => 'list' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'events', $result );
		$this->assertArrayHasKey( 'total_count', $result );
		$this->assertIsArray( $result['events'] );
	}

	/**
	 * Test manage_wp_cron list event structure.
	 */
	public function test_manage_wp_cron_event_structure() {
		// Schedule a test event.
		wp_schedule_single_event( time() + 3600, 'agentic_test_cron_event' );

		$result = $this->tools->execute( 'manage_wp_cron', array( 'operation' => 'list' ) );

		$found = false;
		foreach ( $result['events'] as $event ) {
			$this->assertArrayHasKey( 'hook', $event );
			$this->assertArrayHasKey( 'timestamp', $event );
			$this->assertArrayHasKey( 'next_run', $event );
			$this->assertArrayHasKey( 'schedule', $event );
			if ( 'agentic_test_cron_event' === $event['hook'] ) {
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Test cron event should be in list' );

		// Cleanup.
		wp_clear_scheduled_hook( 'agentic_test_cron_event' );
	}

	/**
	 * Test manage_wp_cron delete requires hook and timestamp.
	 */
	public function test_manage_wp_cron_delete_requires_params() {
		$result = $this->tools->execute( 'manage_wp_cron', array( 'operation' => 'delete' ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	/**
	 * Test manage_wp_cron delete removes an event.
	 */
	public function test_manage_wp_cron_delete_event() {
		$ts = time() + 7200;
		wp_schedule_single_event( $ts, 'agentic_test_delete_cron' );

		$result = $this->tools->execute(
			'manage_wp_cron',
			array(
				'operation' => 'delete',
				'hook'      => 'agentic_test_delete_cron',
				'timestamp' => $ts,
			)
		);

		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
		$this->assertFalse( wp_next_scheduled( 'agentic_test_delete_cron' ) );
	}

	/**
	 * Test manage_wp_cron unknown operation.
	 */
	public function test_manage_wp_cron_unknown_operation() {
		$result = $this->tools->execute( 'manage_wp_cron', array( 'operation' => 'restart' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — get_users
	// -------------------------------------------------------------------------

	/**
	 * Test get_users returns user list.
	 */
	public function test_get_users() {
		$result = $this->tools->execute( 'get_users', array() );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'total_found', $result );
		$this->assertArrayHasKey( 'available_roles', $result );
	}

	/**
	 * Test get_users returns user structure.
	 */
	public function test_get_users_structure() {
		$this->factory->user->create( array( 'role' => 'editor' ) );

		$result = $this->tools->execute( 'get_users', array() );

		if ( ! empty( $result['users'] ) ) {
			$user = $result['users'][0];
			$this->assertArrayHasKey( 'id', $user );
			$this->assertArrayHasKey( 'login', $user );
			$this->assertArrayHasKey( 'email', $user );
			$this->assertArrayHasKey( 'display_name', $user );
			$this->assertArrayHasKey( 'roles', $user );
			$this->assertArrayHasKey( 'registered', $user );
			$this->assertArrayHasKey( 'post_count', $user );
		}
	}

	/**
	 * Test get_users filters by role.
	 */
	public function test_get_users_filter_by_role() {
		$this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->factory->user->create( array( 'role' => 'editor' ) );

		$result = $this->tools->execute( 'get_users', array( 'role' => 'subscriber' ) );

		foreach ( $result['users'] as $user ) {
			$this->assertContains( 'subscriber', $user['roles'] );
		}
	}

	/**
	 * Test get_users respects limit.
	 */
	public function test_get_users_limit() {
		$this->factory->user->create_many( 5 );

		$result = $this->tools->execute( 'get_users', array( 'limit' => 2 ) );
		$this->assertLessThanOrEqual( 2, count( $result['users'] ) );
	}

	/**
	 * Test get_users search.
	 */
	public function test_get_users_search() {
		$this->factory->user->create(
			array(
				'user_login'   => 'searchable_user_xyz',
				'display_name' => 'Searchable XYZ',
			)
		);

		$result = $this->tools->execute( 'get_users', array( 'search' => 'searchable_user_xyz' ) );
		$this->assertGreaterThanOrEqual( 1, $result['total_found'] );
	}

	/**
	 * Test get_users includes available roles.
	 */
	public function test_get_users_available_roles() {
		$result = $this->tools->execute( 'get_users', array() );
		$this->assertArrayHasKey( 'administrator', $result['available_roles'] );
		$this->assertArrayHasKey( 'editor', $result['available_roles'] );
		$this->assertArrayHasKey( 'subscriber', $result['available_roles'] );
	}

	// -------------------------------------------------------------------------
	// execute — get_option
	// -------------------------------------------------------------------------

	/**
	 * Test get_option returns option value.
	 */
	public function test_get_option() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'blogname' ) );
		$this->assertIsArray( $result );
		$this->assertEquals( 'blogname', $result['name'] );
		$this->assertTrue( $result['exists'] );
		$this->assertArrayHasKey( 'value', $result );
	}

	/**
	 * Test get_option for nonexistent option.
	 */
	public function test_get_option_nonexistent() {
		$result = $this->tools->execute(
			'get_option',
			array( 'name' => 'agentic_nonexistent_option_xyz_999' )
		);
		$this->assertFalse( $result['exists'] );
	}

	/**
	 * Test get_option blocks sensitive options.
	 */
	public function test_get_option_blocks_password() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'db_password' ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'security', $result['error'] );
	}

	/**
	 * Test get_option blocks API keys.
	 */
	public function test_get_option_blocks_api_key() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'my_api_key' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_option blocks secret.
	 */
	public function test_get_option_blocks_secret() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'auth_secret_token' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_option blocks stripe keys.
	 */
	public function test_get_option_blocks_stripe() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'stripe_secret_key' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_option rejects empty name.
	 */
	public function test_get_option_empty_name() {
		$result = $this->tools->execute( 'get_option', array( 'name' => '' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_option returns array value.
	 */
	public function test_get_option_array_value() {
		$result = $this->tools->execute( 'get_option', array( 'name' => 'active_plugins' ) );
		$this->assertTrue( $result['exists'] );
		$this->assertEquals( 'array', $result['type'] );
	}

	/**
	 * Test get_option returns type info.
	 */
	public function test_get_option_type() {
		update_option( 'agentic_test_option_str', 'hello' );
		$result = $this->tools->execute( 'get_option', array( 'name' => 'agentic_test_option_str' ) );
		$this->assertEquals( 'string', $result['type'] );
		$this->assertEquals( 'hello', $result['value'] );
		delete_option( 'agentic_test_option_str' );
	}

	// -------------------------------------------------------------------------
	// User-space tool definitions
	// -------------------------------------------------------------------------

	/**
	 * Test write_file tool is defined.
	 */
	public function test_write_file_tool_defined() {
		$tools      = $this->tools->get_tool_definitions();
		$flat_names = array();
		foreach ( $tools as $tool ) {
			$flat_names[] = $tool['function']['name'] ?? $tool['name'] ?? '';
		}
		$this->assertContains( 'write_file', $flat_names );
	}

	/**
	 * Test modify_option tool is defined.
	 */
	public function test_modify_option_tool_defined() {
		$tools      = $this->tools->get_tool_definitions();
		$flat_names = array();
		foreach ( $tools as $tool ) {
			$flat_names[] = $tool['function']['name'] ?? $tool['name'] ?? '';
		}
		$this->assertContains( 'modify_option', $flat_names );
	}

	/**
	 * Test manage_transients tool is defined.
	 */
	public function test_manage_transients_tool_defined() {
		$tools      = $this->tools->get_tool_definitions();
		$flat_names = array();
		foreach ( $tools as $tool ) {
			$flat_names[] = $tool['function']['name'] ?? $tool['name'] ?? '';
		}
		$this->assertContains( 'manage_transients', $flat_names );
	}

	/**
	 * Test modify_postmeta tool is defined.
	 */
	public function test_modify_postmeta_tool_defined() {
		$tools      = $this->tools->get_tool_definitions();
		$flat_names = array();
		foreach ( $tools as $tool ) {
			$flat_names[] = $tool['function']['name'] ?? $tool['name'] ?? '';
		}
		$this->assertContains( 'modify_postmeta', $flat_names );
	}

	// -------------------------------------------------------------------------
	// User-space permission checks
	// -------------------------------------------------------------------------

	/**
	 * Test write_file with permission denied.
	 */
	public function test_write_file_permission_denied() {
		// All permissions are disabled by default.
		$result = $this->tools->execute(
			'write_file',
			array(
				'path'    => 'themes/' . get_stylesheet() . '/test.css',
				'content' => 'body { color: red; }',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'permission', strtolower( $result['error'] ) );
	}

	/**
	 * Test modify_option with permission denied.
	 */
	public function test_modify_option_permission_denied() {
		$result = $this->tools->execute(
			'modify_option',
			array(
				'name'      => 'agentic_test_opt',
				'value'     => 'test',
				'operation' => 'set',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test manage_transients with permission denied.
	 */
	public function test_manage_transients_permission_denied() {
		$result = $this->tools->execute(
			'manage_transients',
			array(
				'operation' => 'flush',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test modify_postmeta with permission denied.
	 */
	public function test_modify_postmeta_permission_denied() {
		$result = $this->tools->execute(
			'modify_postmeta',
			array(
				'post_id'    => 1,
				'meta_key'   => 'test_key',
				'meta_value' => 'test',
				'operation'  => 'set',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// User-space tool execution (with permissions enabled)
	// -------------------------------------------------------------------------

	/**
	 * Test modify_option succeeds in auto-approve mode with permission enabled.
	 */
	public function test_modify_option_auto_approve() {
		\Agentic\Agent_Permissions::save_settings( array( 'modify_options' => true ), 'auto' );

		$result = $this->tools->execute(
			'modify_option',
			array(
				'name'      => 'agentic_test_userspace_opt',
				'value'     => 'hello world',
				'operation' => 'set',
			),
			'test-agent'
		);

		$this->assertTrue( $result['success'] ?? false, 'modify_option should succeed: ' . wp_json_encode( $result ) );
		$this->assertEquals( 'hello world', get_option( 'agentic_test_userspace_opt' ) );

		// Cleanup.
		delete_option( 'agentic_test_userspace_opt' );
		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}

	/**
	 * Test modify_option creates proposal in confirm mode.
	 */
	public function test_modify_option_creates_proposal() {
		\Agentic\Agent_Permissions::save_settings( array( 'modify_options' => true ), 'confirm' );

		$result = $this->tools->execute(
			'modify_option',
			array(
				'name'      => 'agentic_test_prop',
				'value'     => 'test',
				'operation' => 'set',
			),
			'test-agent'
		);

		$this->assertTrue( $result['pending_proposal'] ?? false );
		$this->assertArrayHasKey( 'proposal_id', $result );
		$this->assertArrayHasKey( 'description', $result );

		// Cleanup.
		delete_transient( 'agentic_proposal_' . $result['proposal_id'] );
		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}

	/**
	 * Test modify_option blocks sensitive options.
	 */
	public function test_modify_option_blocks_sensitive() {
		\Agentic\Agent_Permissions::save_settings( array( 'modify_options' => true ), 'auto' );

		$result = $this->tools->execute(
			'modify_option',
			array(
				'name'      => 'siteurl',
				'value'     => 'http://evil.com',
				'operation' => 'set',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'sensitive', strtolower( $result['error'] ) );

		// Also test pattern-based blocking.
		$result2 = $this->tools->execute(
			'modify_option',
			array(
				'name'      => 'my_api_key_setting',
				'value'     => 'sk-1234',
				'operation' => 'set',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result2 );

		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}

	/**
	 * Test manage_transients list in auto mode.
	 */
	public function test_manage_transients_list() {
		\Agentic\Agent_Permissions::save_settings( array( 'manage_transients' => true ), 'auto' );
		set_transient( 'agentic_test_trans', 'value', 3600 );

		$result = $this->tools->execute(
			'manage_transients',
			array(
				'operation' => 'list',
				'search'    => 'agentic_test',
			),
			'test-agent'
		);

		// List operation returns transients array, not 'success' key.
		$this->assertArrayHasKey( 'transients', $result, 'manage_transients list should return transients: ' . wp_json_encode( $result ) );
		$this->assertGreaterThanOrEqual( 1, $result['count'] );

		// Cleanup.
		delete_transient( 'agentic_test_trans' );
		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}

	/**
	 * Test modify_postmeta set in auto mode.
	 */
	public function test_modify_postmeta_set() {
		\Agentic\Agent_Permissions::save_settings( array( 'modify_postmeta' => true ), 'auto' );

		$post_id = self::factory()->post->create();

		$result = $this->tools->execute(
			'modify_postmeta',
			array(
				'post_id'    => $post_id,
				'meta_key'   => 'agentic_test_meta',
				'meta_value' => 'test_value',
				'operation'  => 'set',
			),
			'test-agent'
		);

		$this->assertTrue( $result['success'] ?? false, 'modify_postmeta should succeed: ' . wp_json_encode( $result ) );
		$this->assertEquals( 'test_value', get_post_meta( $post_id, 'agentic_test_meta', true ) );

		// Cleanup.
		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}

	/**
	 * Test modify_postmeta with nonexistent post.
	 */
	public function test_modify_postmeta_nonexistent_post() {
		\Agentic\Agent_Permissions::save_settings( array( 'modify_postmeta' => true ), 'auto' );

		$result = $this->tools->execute(
			'modify_postmeta',
			array(
				'post_id'    => 999999,
				'meta_key'   => 'test_key',
				'meta_value' => 'test',
				'operation'  => 'set',
			),
			'test-agent'
		);

		$this->assertArrayHasKey( 'error', $result );

		delete_option( \Agentic\Agent_Permissions::OPTION_KEY );
	}
}
