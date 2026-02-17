<?php
/**
 * Unit Tests for Agent Tools
 *
 * Tests tool definitions, path validation helpers, core db_get_option
 * tool execution, agent handler integration, and unknown tool handling.
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
	 * Test is_allowed_subpath accepts uploads/.
	 */
	public function test_is_allowed_subpath_accepts_uploads() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( 'uploads/2025/01/secret.pdf' ) );
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
		// After stripping "..", "../uploads" becomes "/uploads" → "uploads" which is allowed.
		$this->assertTrue( Agent_Tools::is_allowed_subpath( '../uploads/secret.pdf' ) );
	}

	/**
	 * Test is_allowed_subpath with leading slash.
	 */
	public function test_is_allowed_subpath_leading_slash() {
		$this->assertTrue( Agent_Tools::is_allowed_subpath( '/plugins/my-plugin/file.php' ) );
	}

	/**
	 * Test get_path_scope returns plugins.
	 */
	public function test_get_path_scope_plugins() {
		$this->assertEquals( 'plugins', Agent_Tools::get_path_scope( 'plugins/my-plugin/file.php' ) );
	}

	/**
	 * Test get_path_scope returns themes.
	 */
	public function test_get_path_scope_themes() {
		$this->assertEquals( 'themes', Agent_Tools::get_path_scope( 'themes/flavor/style.css' ) );
	}

	/**
	 * Test get_path_scope returns uploads.
	 */
	public function test_get_path_scope_uploads() {
		$this->assertEquals( 'uploads', Agent_Tools::get_path_scope( 'uploads/2025/01/photo.jpg' ) );
	}

	/**
	 * Test get_path_scope returns empty for unknown.
	 */
	public function test_get_path_scope_unknown() {
		$this->assertEquals( '', Agent_Tools::get_path_scope( 'wp-config.php' ) );
	}

	/**
	 * Test is_scope_allowed defaults to true when no option set.
	 */
	public function test_is_scope_allowed_default() {
		delete_option( 'agentic_tool_scopes' );
		$this->assertTrue( Agent_Tools::is_scope_allowed( 'plugins', 'read' ) );
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
	 * Test db_get_option core tool is present.
	 */
	public function test_core_tool_present() {
		$tools = $this->tools->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$this->assertContains( 'db_get_option', $names, 'Missing core tool: db_get_option' );
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
	 * Test at least 1 core tool is defined.
	 */
	public function test_minimum_tool_count() {
		$tools = $this->tools->get_tool_definitions();
		$this->assertGreaterThanOrEqual( 1, count( $tools ) );
	}

	/**
	 * Test disabled tools are filtered out.
	 */
	public function test_disabled_tools_filtered() {
		update_option( 'agentic_disabled_tools', array( 'db_get_option' ) );

		$fresh = new Agent_Tools();
		$tools = $fresh->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$this->assertNotContains( 'db_get_option', $names );

		delete_option( 'agentic_disabled_tools' );
	}

	/**
	 * Test disabled tool execution is blocked.
	 */
	public function test_disabled_tool_blocked() {
		update_option( 'agentic_disabled_tools', array( 'db_get_option' ) );

		$fresh  = new Agent_Tools();
		$result = $fresh->execute( 'db_get_option', array( 'name' => 'blogname' ) );

		$this->assertWPError( $result );
		$this->assertEquals( 'tool_disabled', $result->get_error_code() );

		delete_option( 'agentic_disabled_tools' );
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
	// Agent-registered tool handlers
	// -------------------------------------------------------------------------

	/**
	 * Test agent tools appear in definitions via filter.
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
	// execute — db_get_option
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_option reads an option.
	 */
	public function test_get_option() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'blogname' ) );
		$this->assertIsArray( $result );
		$this->assertEquals( 'blogname', $result['name'] );
		$this->assertTrue( $result['exists'] );
		$this->assertArrayHasKey( 'value', $result );
	}

	/**
	 * Test db_get_option for nonexistent option.
	 */
	public function test_get_option_nonexistent() {
		$result = $this->tools->execute(
			'db_get_option',
			array( 'name' => 'agentic_nonexistent_option_xyz_999' )
		);
		$this->assertFalse( $result['exists'] );
	}

	/**
	 * Test db_get_option blocks sensitive options.
	 */
	public function test_get_option_blocks_password() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'db_password' ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'security', $result['error'] );
	}

	/**
	 * Test db_get_option blocks API keys.
	 */
	public function test_get_option_blocks_api_key() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'my_api_key' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test db_get_option blocks secret.
	 */
	public function test_get_option_blocks_secret() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'auth_secret_token' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test db_get_option blocks stripe keys.
	 */
	public function test_get_option_blocks_stripe() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'stripe_secret_key' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test db_get_option rejects empty name.
	 */
	public function test_get_option_empty_name() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => '' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test db_get_option returns array value.
	 */
	public function test_get_option_array_value() {
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'active_plugins' ) );
		$this->assertTrue( $result['exists'] );
		$this->assertEquals( 'array', $result['type'] );
	}

	/**
	 * Test db_get_option returns type info.
	 */
	public function test_get_option_type() {
		update_option( 'agentic_test_option_str', 'hello' );
		$result = $this->tools->execute( 'db_get_option', array( 'name' => 'agentic_test_option_str' ) );
		$this->assertEquals( 'string', $result['type'] );
		$this->assertEquals( 'hello', $result['value'] );
		delete_option( 'agentic_test_option_str' );
	}

	// -------------------------------------------------------------------------
	// execute_user_space — stub
	// -------------------------------------------------------------------------

	/**
	 * Test execute_user_space returns error (write tools removed).
	 */
	public function test_execute_user_space_returns_error() {
		$result = $this->tools->execute_user_space( 'plugin_write_file', array(), 'test-agent' );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'no longer available', $result['error'] );
	}

	// -------------------------------------------------------------------------
	// New database tool definitions
	// -------------------------------------------------------------------------

	/**
	 * Test all 11 core database tools are defined.
	 */
	public function test_all_database_tools_present() {
		// Ensure no tools are disabled so we see all definitions.
		delete_option( 'agentic_disabled_tools' );
		$fresh = new Agent_Tools();
		$tools = $fresh->get_tool_definitions();
		$names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$expected = array(
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

		foreach ( $expected as $tool_name ) {
			$this->assertContains( $tool_name, $names, "Missing core tool: {$tool_name}" );
		}
	}

	// -------------------------------------------------------------------------
	// execute — db_get_posts
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_posts returns posts.
	 */
	public function test_get_posts() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Test Post' ) );

		$result = $this->tools->execute( 'db_get_posts', array( 'post_type' => 'post' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total_found', $result );
		$this->assertGreaterThanOrEqual( 1, count( $result['posts'] ) );

		// Verify post structure.
		$found = false;
		foreach ( $result['posts'] as $post ) {
			if ( $post['id'] === $post_id ) {
				$found = true;
				$this->assertEquals( 'Test Post', $post['title'] );
				$this->assertArrayHasKey( 'status', $post );
				$this->assertArrayHasKey( 'date', $post );
			}
		}
		$this->assertTrue( $found, 'Created post not found in results' );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_get_posts respects limit.
	 */
	public function test_get_posts_limit() {
		$ids = self::factory()->post->create_many( 5 );

		$result = $this->tools->execute( 'db_get_posts', array( 'limit' => 2 ) );
		$this->assertCount( 2, $result['posts'] );

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	// -------------------------------------------------------------------------
	// execute — db_get_post
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_post returns full post data.
	 */
	public function test_get_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Full Post',
				'post_content' => 'Full content here.',
			)
		);

		$result = $this->tools->execute( 'db_get_post', array( 'post_id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertEquals( $post_id, $result['id'] );
		$this->assertEquals( 'Full Post', $result['title'] );
		$this->assertEquals( 'Full content here.', $result['content'] );
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertArrayHasKey( 'edit_link', $result );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_get_post returns error for missing post.
	 */
	public function test_get_post_not_found() {
		$result = $this->tools->execute( 'db_get_post', array( 'post_id' => 999999 ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test db_get_post rejects invalid ID.
	 */
	public function test_get_post_invalid_id() {
		$result = $this->tools->execute( 'db_get_post', array( 'post_id' => 0 ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — db_get_users
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_users returns users without emails.
	 */
	public function test_get_users_no_emails() {
		$result = $this->tools->execute( 'db_get_users', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'users', $result );
		$this->assertGreaterThanOrEqual( 1, count( $result['users'] ) );

		// Verify no email or password fields.
		foreach ( $result['users'] as $user ) {
			$this->assertArrayNotHasKey( 'email', $user );
			$this->assertArrayNotHasKey( 'user_email', $user );
			$this->assertArrayNotHasKey( 'user_pass', $user );
			$this->assertArrayHasKey( 'display_name', $user );
			$this->assertArrayHasKey( 'roles', $user );
		}
	}

	// -------------------------------------------------------------------------
	// execute — db_get_terms
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_terms returns categories.
	 */
	public function test_get_terms() {
		$result = $this->tools->execute( 'db_get_terms', array( 'taxonomy' => 'category' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertEquals( 'category', $result['taxonomy'] );
	}

	/**
	 * Test db_get_terms errors on invalid taxonomy.
	 */
	public function test_get_terms_invalid_taxonomy() {
		$result = $this->tools->execute( 'db_get_terms', array( 'taxonomy' => 'nonexistent_tax' ) );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'does not exist', $result['error'] );
	}

	/**
	 * Test db_get_terms requires taxonomy.
	 */
	public function test_get_terms_empty_taxonomy() {
		$result = $this->tools->execute( 'db_get_terms', array() );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — db_get_post_meta
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_post_meta returns meta.
	 */
	public function test_get_post_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'test_meta_key', 'test_value' );

		$result = $this->tools->execute(
			'db_get_post_meta',
			array(
				'post_id'  => $post_id,
				'meta_key' => 'test_meta_key',
			)
		);

		$this->assertEquals( 'test_value', $result['value'] );
		$this->assertTrue( $result['exists'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_get_post_meta returns all meta.
	 */
	public function test_get_post_meta_all() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'custom_field', 'hello' );

		$result = $this->tools->execute( 'db_get_post_meta', array( 'post_id' => $post_id ) );

		$this->assertArrayHasKey( 'meta', $result );
		$this->assertArrayHasKey( 'custom_field', $result['meta'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_get_post_meta blocks sensitive keys.
	 */
	public function test_get_post_meta_blocks_sensitive() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'stripe_customer_id', 'cus_123' );

		$result = $this->tools->execute(
			'db_get_post_meta',
			array(
				'post_id'  => $post_id,
				'meta_key' => 'stripe_customer_id',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'blocked', $result['error'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_get_post_meta filters sensitive keys from all meta.
	 */
	public function test_get_post_meta_filters_sensitive_from_all() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'safe_key', 'safe_value' );
		update_post_meta( $post_id, 'stripe_secret', 'sk_test_123' );

		$result = $this->tools->execute( 'db_get_post_meta', array( 'post_id' => $post_id ) );

		$this->assertArrayHasKey( 'safe_key', $result['meta'] );
		$this->assertArrayNotHasKey( 'stripe_secret', $result['meta'] );

		wp_delete_post( $post_id, true );
	}

	// -------------------------------------------------------------------------
	// execute — db_get_comments
	// -------------------------------------------------------------------------

	/**
	 * Test db_get_comments returns comments without emails.
	 */
	public function test_get_comments() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => 'Test comment',
				'comment_author_email' => 'test@example.com',
			)
		);

		$result = $this->tools->execute( 'db_get_comments', array( 'post_id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'comments', $result );
		$this->assertGreaterThanOrEqual( 1, count( $result['comments'] ) );

		// Verify no email exposed.
		foreach ( $result['comments'] as $comment ) {
			$this->assertArrayNotHasKey( 'author_email', $comment );
			$this->assertArrayNotHasKey( 'comment_author_email', $comment );
			$this->assertArrayHasKey( 'author_name', $comment );
			$this->assertArrayHasKey( 'content', $comment );
		}

		wp_delete_comment( $comment_id, true );
		wp_delete_post( $post_id, true );
	}

	// -------------------------------------------------------------------------
	// execute — db_update_option
	// -------------------------------------------------------------------------

	/**
	 * Test db_update_option updates an option.
	 */
	public function test_update_option() {
		// Ensure write tools are enabled for this test.
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		update_option( 'agentic_test_writable', 'old_value' );

		$result = $this->tools->execute(
			'db_update_option',
			array(
				'name'  => 'agentic_test_writable',
				'value' => 'new_value',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['updated'] );
		$this->assertEquals( 'new_value', get_option( 'agentic_test_writable' ) );

		delete_option( 'agentic_test_writable' );
	}

	/**
	 * Test db_update_option blocks sensitive options.
	 */
	public function test_update_option_blocks_sensitive() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute(
			'db_update_option',
			array(
				'name'  => 'stripe_secret_key',
				'value' => 'sk_test_new',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'security', $result['error'] );
	}

	/**
	 * Test db_update_option blocks protected options.
	 */
	public function test_update_option_blocks_protected() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$protected = array( 'siteurl', 'home', 'admin_email', 'active_plugins', 'agentic_disabled_tools' );

		foreach ( $protected as $opt ) {
			$result = $this->tools->execute(
				'db_update_option',
				array(
					'name'  => $opt,
					'value' => 'hacked',
				)
			);
			$this->assertArrayHasKey( 'error', $result, "Expected {$opt} to be blocked" );
			$this->assertStringContainsString( 'protected', $result['error'] );
		}
	}

	/**
	 * Test db_update_option rejects empty name.
	 */
	public function test_update_option_empty_name() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute( 'db_update_option', array( 'name' => '', 'value' => 'x' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — db_create_post
	// -------------------------------------------------------------------------

	/**
	 * Test db_create_post creates a draft.
	 */
	public function test_create_post() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute(
			'db_create_post',
			array(
				'title'   => 'Agent Created Post',
				'content' => 'This is test content.',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertEquals( 'draft', $result['status'] );
		$this->assertEquals( 'Agent Created Post', $result['title'] );

		wp_delete_post( $result['post_id'], true );
	}

	/**
	 * Test db_create_post forces safe status (publish → draft).
	 */
	public function test_create_post_rejects_publish() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute(
			'db_create_post',
			array(
				'title'  => 'Publish Attempt',
				'status' => 'publish',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'draft', $result['status'] );

		wp_delete_post( $result['post_id'], true );
	}

	/**
	 * Test db_create_post allows pending status.
	 */
	public function test_create_post_pending() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute(
			'db_create_post',
			array(
				'title'  => 'Pending Post',
				'status' => 'pending',
			)
		);

		$this->assertEquals( 'pending', $result['status'] );

		wp_delete_post( $result['post_id'], true );
	}

	/**
	 * Test db_create_post requires title.
	 */
	public function test_create_post_requires_title() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute( 'db_create_post', array( 'content' => 'No title' ) );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — db_update_post
	// -------------------------------------------------------------------------

	/**
	 * Test db_update_post updates title and content.
	 */
	public function test_update_post() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$post_id = self::factory()->post->create( array( 'post_title' => 'Original' ) );

		$result = $this->tools->execute(
			'db_update_post',
			array(
				'post_id' => $post_id,
				'title'   => 'Updated Title',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $post_id, $result['post_id'] );
		$this->assertEquals( 'Updated Title', $result['title'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_update_post errors on missing post.
	 */
	public function test_update_post_not_found() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute(
			'db_update_post',
			array(
				'post_id' => 999999,
				'title'   => 'Fail',
			)
		);
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// execute — db_delete_post
	// -------------------------------------------------------------------------

	/**
	 * Test db_delete_post trashes a post.
	 */
	public function test_delete_post_trashes() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$post_id = self::factory()->post->create( array( 'post_title' => 'To Trash' ) );

		$result = $this->tools->execute( 'db_delete_post', array( 'post_id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['trashed'] );
		$this->assertEquals( 'trash', get_post_status( $post_id ) );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test db_delete_post errors on missing post.
	 */
	public function test_delete_post_not_found() {
		delete_option( 'agentic_disabled_tools' );
		$this->tools = new Agent_Tools();

		$result = $this->tools->execute( 'db_delete_post', array( 'post_id' => 999999 ) );
		$this->assertArrayHasKey( 'error', $result );
	}
}
