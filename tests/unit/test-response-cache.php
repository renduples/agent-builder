<?php
/**
 * Unit Tests for Response Cache
 *
 * Tests cache key generation, storage, retrieval, invalidation,
 * should_cache logic, and statistics.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Response_Cache;

/**
 * Test case for Response_Cache class.
 */
class Test_Response_Cache extends TestCase {

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear all cached responses before each test.
		Response_Cache::clear_all();
		// Ensure caching is enabled.
		update_option( 'agentic_response_cache_enabled', true );
	}

	// -------------------------------------------------------------------------
	// Cache key generation
	// -------------------------------------------------------------------------

	/**
	 * Test generate_key returns a string.
	 */
	public function test_generate_key_returns_string() {
		$key = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$this->assertIsString( $key );
	}

	/**
	 * Test generate_key includes prefix.
	 */
	public function test_generate_key_has_prefix() {
		$key = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$this->assertStringStartsWith( 'agentic_resp_', $key );
	}

	/**
	 * Test same message + agent produces same key.
	 */
	public function test_generate_key_deterministic() {
		$key1 = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$key2 = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test different agents produce different keys.
	 */
	public function test_generate_key_varies_by_agent() {
		$key1 = Response_Cache::generate_key( 'Hello world', 'agent-a' );
		$key2 = Response_Cache::generate_key( 'Hello world', 'agent-b' );
		$this->assertNotEquals( $key1, $key2 );
	}

	/**
	 * Test different messages produce different keys.
	 */
	public function test_generate_key_varies_by_message() {
		$key1 = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$key2 = Response_Cache::generate_key( 'Goodbye world', 'test-agent' );
		$this->assertNotEquals( $key1, $key2 );
	}

	/**
	 * Test key normalizes whitespace.
	 */
	public function test_generate_key_normalizes_whitespace() {
		$key1 = Response_Cache::generate_key( 'Hello   world', 'test-agent' );
		$key2 = Response_Cache::generate_key( 'Hello world', 'test-agent' );
		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test key normalizes case.
	 */
	public function test_generate_key_normalizes_case() {
		$key1 = Response_Cache::generate_key( 'Hello World', 'test-agent' );
		$key2 = Response_Cache::generate_key( 'hello world', 'test-agent' );
		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test key varies by user role bucket.
	 */
	public function test_generate_key_varies_by_role() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$sub   = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$key1 = Response_Cache::generate_key( 'Hello', 'test-agent', $admin );
		$key2 = Response_Cache::generate_key( 'Hello', 'test-agent', $sub );
		$this->assertNotEquals( $key1, $key2 );
	}

	/**
	 * Test guest user (0) gets guest bucket key.
	 */
	public function test_generate_key_guest_user() {
		$key = Response_Cache::generate_key( 'Hello world', 'test-agent', 0 );
		$this->assertIsString( $key );
	}

	// -------------------------------------------------------------------------
	// should_cache
	// -------------------------------------------------------------------------

	/**
	 * Test should_cache returns true for normal message.
	 */
	public function test_should_cache_normal_message() {
		$this->assertTrue( Response_Cache::should_cache( 'How do I create a WordPress plugin?' ) );
	}

	/**
	 * Test should_cache rejects short messages.
	 */
	public function test_should_cache_rejects_short() {
		$this->assertFalse( Response_Cache::should_cache( 'Hi' ) );
	}

	/**
	 * Test should_cache rejects messages with history.
	 */
	public function test_should_cache_rejects_with_history() {
		$history = array(
			array(
				'role'    => 'user',
				'content' => 'previous',
			),
		);
		$this->assertFalse( Response_Cache::should_cache( 'This is a follow-up message', $history ) );
	}

	/**
	 * Test should_cache rejects context-dependent phrases.
	 */
	public function test_should_cache_rejects_context_dependent() {
		$phrases = array(
			'What is on this page?',
			'Check my site for errors',
			'What happened today?',
			'Show me recent posts',
			'What is the current status?',
		);

		foreach ( $phrases as $phrase ) {
			$this->assertFalse(
				Response_Cache::should_cache( $phrase ),
				"Should reject: {$phrase}"
			);
		}
	}

	/**
	 * Test should_cache returns false when disabled.
	 */
	public function test_should_cache_disabled() {
		update_option( 'agentic_response_cache_enabled', false );
		$this->assertFalse( Response_Cache::should_cache( 'How do I create a plugin?' ) );
	}

	// -------------------------------------------------------------------------
	// is_enabled / get_ttl
	// -------------------------------------------------------------------------

	/**
	 * Test is_enabled returns true by default.
	 */
	public function test_is_enabled_default() {
		delete_option( 'agentic_response_cache_enabled' );
		$this->assertTrue( Response_Cache::is_enabled() );
	}

	/**
	 * Test is_enabled returns false when disabled.
	 */
	public function test_is_enabled_false() {
		update_option( 'agentic_response_cache_enabled', false );
		$this->assertFalse( Response_Cache::is_enabled() );
	}

	/**
	 * Test get_ttl returns default (1 hour).
	 */
	public function test_get_ttl_default() {
		delete_option( 'agentic_response_cache_ttl' );
		$this->assertEquals( HOUR_IN_SECONDS, Response_Cache::get_ttl() );
	}

	/**
	 * Test get_ttl clamps to minimum of 60 seconds.
	 */
	public function test_get_ttl_minimum() {
		update_option( 'agentic_response_cache_ttl', 10 );
		$this->assertEquals( 60, Response_Cache::get_ttl() );
	}

	/**
	 * Test get_ttl clamps to maximum of 24 hours.
	 */
	public function test_get_ttl_maximum() {
		update_option( 'agentic_response_cache_ttl', 999999 );
		$this->assertEquals( DAY_IN_SECONDS, Response_Cache::get_ttl() );
	}

	// -------------------------------------------------------------------------
	// set / get (storage & retrieval)
	// -------------------------------------------------------------------------

	/**
	 * Test set returns true on success.
	 */
	public function test_set_returns_true() {
		$result = Response_Cache::set(
			'How do I create a plugin?',
			'test-agent',
			array( 'response' => 'Here is how...' )
		);
		$this->assertTrue( $result );
	}

	/**
	 * Test get returns cached response.
	 */
	public function test_get_returns_cached() {
		$response = array( 'response' => 'Cached answer' );
		Response_Cache::set( 'What is WordPress?', 'test-agent', $response );

		$cached = Response_Cache::get( 'What is WordPress?', 'test-agent' );
		$this->assertNotNull( $cached );
		$this->assertEquals( 'Cached answer', $cached['response'] );
	}

	/**
	 * Test get marks response as cached.
	 */
	public function test_get_marks_cache_hit() {
		Response_Cache::set( 'Test question here', 'test-agent', array( 'response' => 'Answer' ) );
		$cached = Response_Cache::get( 'Test question here', 'test-agent' );

		$this->assertTrue( $cached['cached'] );
		$this->assertTrue( $cached['cache_hit'] );
	}

	/**
	 * Test get returns null for cache miss.
	 */
	public function test_get_returns_null_on_miss() {
		$this->assertNull( Response_Cache::get( 'Never asked before', 'test-agent' ) );
	}

	/**
	 * Test set stores cached_at timestamp.
	 */
	public function test_set_stores_timestamp() {
		$before = time();
		Response_Cache::set( 'Timestamp test question', 'test-agent', array( 'response' => 'Answer' ) );
		$cached = Response_Cache::get( 'Timestamp test question', 'test-agent' );

		$this->assertArrayHasKey( 'cached_at', $cached );
		$this->assertGreaterThanOrEqual( $before, $cached['cached_at'] );
	}

	/**
	 * Test set rejects error responses.
	 */
	public function test_set_rejects_error_responses() {
		$result = Response_Cache::set(
			'Error test question',
			'test-agent',
			array(
				'response' => 'fail',
				'error'    => true,
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * Test set rejects empty responses.
	 */
	public function test_set_rejects_empty_response() {
		$result = Response_Cache::set(
			'Empty test question',
			'test-agent',
			array( 'response' => '' )
		);
		$this->assertFalse( $result );
	}

	/**
	 * Test set rejects responses with tool calls.
	 */
	public function test_set_rejects_tool_responses() {
		$result = Response_Cache::set(
			'Tool test question here',
			'test-agent',
			array(
				'response'   => 'Answer',
				'tools_used' => array( 'read_file' ),
			)
		);
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Invalidation
	// -------------------------------------------------------------------------

	/**
	 * Test invalidate removes cached entry.
	 */
	public function test_invalidate() {
		Response_Cache::set( 'To be invalidated', 'test-agent', array( 'response' => 'Answer' ) );
		Response_Cache::invalidate( 'To be invalidated', 'test-agent' );

		$this->assertNull( Response_Cache::get( 'To be invalidated', 'test-agent' ) );
	}

	/**
	 * Test clear_all removes all cached entries.
	 */
	public function test_clear_all() {
		Response_Cache::set( 'Question one for clear', 'test-agent', array( 'response' => 'A1' ) );
		Response_Cache::set( 'Question two for clear', 'test-agent', array( 'response' => 'A2' ) );

		// Flush object cache so transients live in DB for clear_all to find.
		wp_cache_flush();
		$cleared = Response_Cache::clear_all();

		// Flush again so get() reads from DB (now empty).
		wp_cache_flush();

		$this->assertNull( Response_Cache::get( 'Question one for clear', 'test-agent' ) );
		$this->assertNull( Response_Cache::get( 'Question two for clear', 'test-agent' ) );
	}

	/**
	 * Test clear_all returns count of cleared entries.
	 */
	public function test_clear_all_returns_count() {
		Response_Cache::set( 'Count test one question', 'test-agent', array( 'response' => 'A1' ) );
		Response_Cache::set( 'Count test two question', 'test-agent', array( 'response' => 'A2' ) );

		// Flush WP object cache so transients live in DB.
		wp_cache_flush();

		$cleared = Response_Cache::clear_all();
		$this->assertGreaterThanOrEqual( 2, $cleared );
	}

	// -------------------------------------------------------------------------
	// Statistics
	// -------------------------------------------------------------------------

	/**
	 * Test get_stats returns expected keys.
	 */
	public function test_get_stats_keys() {
		$stats = Response_Cache::get_stats();
		$this->assertArrayHasKey( 'enabled', $stats );
		$this->assertArrayHasKey( 'ttl', $stats );
		$this->assertArrayHasKey( 'entry_count', $stats );
	}

	/**
	 * Test get_stats reflects enabled setting.
	 */
	public function test_get_stats_enabled() {
		$stats = Response_Cache::get_stats();
		$this->assertTrue( $stats['enabled'] );

		update_option( 'agentic_response_cache_enabled', false );
		$stats = Response_Cache::get_stats();
		$this->assertFalse( $stats['enabled'] );
	}

	/**
	 * Test get_stats counts entries.
	 */
	public function test_get_stats_entry_count() {
		// Flush so transients go to DB where get_stats counts them.
		Response_Cache::set( 'Stats test question one', 'test-agent', array( 'response' => 'A' ) );
		Response_Cache::set( 'Stats test question two', 'test-agent', array( 'response' => 'B' ) );
		wp_cache_flush();

		$stats = Response_Cache::get_stats();
		$this->assertGreaterThanOrEqual( 2, $stats['entry_count'] );
	}

	// -------------------------------------------------------------------------
	// Edge cases
	// -------------------------------------------------------------------------

	/**
	 * Test get returns null for corrupted cache (non-array).
	 */
	public function test_get_returns_null_for_corrupted_cache() {
		$key = Response_Cache::generate_key( 'Corrupted cache test', 'test-agent' );
		set_transient( $key, 'not-an-array', HOUR_IN_SECONDS );

		$this->assertNull( Response_Cache::get( 'Corrupted cache test', 'test-agent' ) );
	}

	/**
	 * Test get returns null for cache missing response key.
	 */
	public function test_get_returns_null_for_missing_response() {
		$key = Response_Cache::generate_key( 'Missing response test', 'test-agent' );
		set_transient( $key, array( 'foo' => 'bar' ), HOUR_IN_SECONDS );

		$this->assertNull( Response_Cache::get( 'Missing response test', 'test-agent' ) );
	}
}
