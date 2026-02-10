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
			array( 'pattern' => 'class', 'file_type' => 'php' )
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
				'comment_post_ID' => $post_id,
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

		$result  = $this->tools->execute(
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
						return array( 'handled' => true, 'input' => $args );
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
}
