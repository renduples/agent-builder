<?php
/**
 * Tests for LLM_Client class
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\LLM_Client;

/**
 * Test case for LLM_Client.
 */
class Test_LLM_Client extends TestCase {

	/**
	 * Clean up options before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( 'agentic_llm_provider' );
		delete_option( 'agentic_llm_api_key' );
		delete_option( 'agentic_xai_api_key' );
		delete_option( 'agentic_model' );
	}

	/**
	 * Test LLM client instantiation.
	 */
	public function test_client_instantiation() {
		$client = new LLM_Client();
		$this->assertInstanceOf( LLM_Client::class, $client );
	}

	/**
	 * Test default provider is openai.
	 */
	public function test_default_provider() {
		$client = new LLM_Client();
		$this->assertEquals( 'openai', $client->get_provider() );
	}

	/**
	 * Test default model is gpt-4o.
	 */
	public function test_default_model() {
		$client = new LLM_Client();
		$this->assertEquals( 'gpt-4o', $client->get_model() );
	}

	/**
	 * Test provider reads from database option.
	 */
	public function test_provider_from_option() {
		update_option( 'agentic_llm_provider', 'anthropic' );
		$client = new LLM_Client();
		$this->assertEquals( 'anthropic', $client->get_provider() );
	}

	/**
	 * Test model reads from database option.
	 */
	public function test_model_from_option() {
		update_option( 'agentic_model', 'claude-3-5-sonnet-20241022' );
		$client = new LLM_Client();
		$this->assertEquals( 'claude-3-5-sonnet-20241022', $client->get_model() );
	}

	/**
	 * Test is_configured returns false when no API key.
	 */
	public function test_not_configured_without_api_key() {
		$client = new LLM_Client();
		$this->assertFalse( $client->is_configured() );
	}

	/**
	 * Test is_configured returns true when API key is set.
	 */
	public function test_configured_with_api_key() {
		update_option( 'agentic_llm_api_key', 'test-api-key-12345' );
		$client = new LLM_Client();
		$this->assertTrue( $client->is_configured() );
	}

	/**
	 * Test chat returns WP_Error when not configured.
	 */
	public function test_chat_returns_error_when_not_configured() {
		$client   = new LLM_Client();
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);
		$result   = $client->chat( $messages );

		$this->assertWPError( $result );
		$this->assertEquals( 'not_configured', $result->get_error_code() );
	}

	/**
	 * Test OpenAI endpoint.
	 */
	public function test_openai_endpoint() {
		$client   = new LLM_Client();
		$endpoint = $client->get_endpoint_for_provider( 'openai' );
		$this->assertEquals( 'https://api.openai.com/v1/chat/completions', $endpoint );
	}

	/**
	 * Test Anthropic endpoint.
	 */
	public function test_anthropic_endpoint() {
		$client   = new LLM_Client();
		$endpoint = $client->get_endpoint_for_provider( 'anthropic' );
		$this->assertEquals( 'https://api.anthropic.com/v1/messages', $endpoint );
	}

	/**
	 * Test xAI endpoint.
	 */
	public function test_xai_endpoint() {
		$client   = new LLM_Client();
		$endpoint = $client->get_endpoint_for_provider( 'xai' );
		$this->assertEquals( 'https://api.x.ai/v1/chat/completions', $endpoint );
	}

	/**
	 * Test Mistral endpoint.
	 */
	public function test_mistral_endpoint() {
		$client   = new LLM_Client();
		$endpoint = $client->get_endpoint_for_provider( 'mistral' );
		$this->assertEquals( 'https://api.mistral.ai/v1/chat/completions', $endpoint );
	}

	/**
	 * Test unknown provider returns empty endpoint.
	 */
	public function test_unknown_provider_endpoint() {
		$client   = new LLM_Client();
		$endpoint = $client->get_endpoint_for_provider( 'nonexistent' );
		$this->assertEmpty( $endpoint );
	}

	/**
	 * Test OpenAI headers use Bearer token.
	 */
	public function test_openai_headers() {
		$client  = new LLM_Client();
		$headers = $client->get_headers_for_provider( 'openai', 'sk-test123' );

		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Bearer sk-test123', $headers['Authorization'] );
		$this->assertEquals( 'application/json', $headers['Content-Type'] );
	}

	/**
	 * Test Anthropic headers use x-api-key.
	 */
	public function test_anthropic_headers() {
		$client  = new LLM_Client();
		$headers = $client->get_headers_for_provider( 'anthropic', 'test-key' );

		$this->assertArrayHasKey( 'x-api-key', $headers );
		$this->assertEquals( 'test-key', $headers['x-api-key'] );
		$this->assertArrayHasKey( 'anthropic-version', $headers );
		$this->assertEquals( '2023-06-01', $headers['anthropic-version'] );
	}

	/**
	 * Test xAI headers use Bearer token.
	 */
	public function test_xai_headers() {
		$client  = new LLM_Client();
		$headers = $client->get_headers_for_provider( 'xai', 'xai-test123' );

		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Bearer xai-test123', $headers['Authorization'] );
	}

	/**
	 * Test Google headers have no Authorization (key in URL).
	 */
	public function test_google_headers() {
		$client  = new LLM_Client();
		$headers = $client->get_headers_for_provider( 'google', 'google-key' );

		$this->assertArrayNotHasKey( 'Authorization', $headers );
		$this->assertEquals( 'application/json', $headers['Content-Type'] );
	}

	/**
	 * Test OpenAI request format.
	 */
	public function test_format_openai_request() {
		update_option( 'agentic_model', 'gpt-4o' );
		$client   = new LLM_Client();
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are helpful.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);

		$body = $client->format_request_for_provider( 'openai', $messages );

		$this->assertArrayHasKey( 'model', $body );
		$this->assertArrayHasKey( 'messages', $body );
		$this->assertArrayHasKey( 'max_tokens', $body );
		$this->assertArrayHasKey( 'temperature', $body );
		$this->assertEquals( 'gpt-4o', $body['model'] );
		$this->assertCount( 2, $body['messages'] );
	}

	/**
	 * Test Anthropic request format separates system messages.
	 */
	public function test_format_anthropic_request() {
		update_option( 'agentic_model', 'claude-3-5-sonnet-20241022' );
		$client   = new LLM_Client();
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are helpful.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);

		$body = $client->format_request_for_provider( 'anthropic', $messages );

		$this->assertArrayHasKey( 'model', $body );
		$this->assertArrayHasKey( 'system', $body );
		$this->assertEquals( 'You are helpful.', $body['system'] );
		// System message extracted, only user message remains.
		$this->assertCount( 1, $body['messages'] );
	}

	/**
	 * Test Google request format uses contents/parts structure.
	 */
	public function test_format_google_request() {
		$client   = new LLM_Client();
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);

		$body = $client->format_request_for_provider( 'google', $messages );

		$this->assertArrayHasKey( 'contents', $body );
		$this->assertCount( 1, $body['contents'] );
		$this->assertEquals( 'user', $body['contents'][0]['role'] );
		$this->assertEquals( 'Hello', $body['contents'][0]['parts'][0]['text'] );
	}

	/**
	 * Test get_usage with valid response.
	 */
	public function test_get_usage_valid() {
		$client   = new LLM_Client();
		$response = array(
			'usage' => array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
				'total_tokens'      => 30,
			),
		);

		$usage = $client->get_usage( $response );
		$this->assertEquals( 10, $usage['prompt_tokens'] );
		$this->assertEquals( 20, $usage['completion_tokens'] );
		$this->assertEquals( 30, $usage['total_tokens'] );
	}

	/**
	 * Test get_usage with missing usage data returns defaults.
	 */
	public function test_get_usage_missing() {
		$client = new LLM_Client();
		$usage  = $client->get_usage( array() );

		$this->assertEquals( 0, $usage['prompt_tokens'] );
		$this->assertEquals( 0, $usage['completion_tokens'] );
		$this->assertEquals( 0, $usage['total_tokens'] );
	}

	/**
	 * Test legacy xAI migration.
	 */
	public function test_legacy_xai_migration() {
		update_option( 'agentic_xai_api_key', 'xai-legacy-key' );

		$client = new LLM_Client();

		$this->assertTrue( $client->is_configured() );
		$this->assertEquals( 'xai', $client->get_provider() );
	}

	/**
	 * Test stream_chat returns not_implemented error.
	 */
	public function test_stream_chat_not_implemented() {
		$client = new LLM_Client();
		$result = $client->stream_chat(
			array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			function () {}
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'not_implemented', $result->get_error_code() );
	}

	/**
	 * Cleanup after each test.
	 */
	public function tearDown(): void {
		delete_option( 'agentic_llm_provider' );
		delete_option( 'agentic_llm_api_key' );
		delete_option( 'agentic_xai_api_key' );
		delete_option( 'agentic_model' );
		parent::tearDown();
	}
}
