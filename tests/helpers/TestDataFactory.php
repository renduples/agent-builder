<?php
/**
 * Test Data Factory
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

/**
 * Factory for generating test data.
 */
class TestDataFactory {

	/**
	 * Create test LLM configuration.
	 *
	 * @param array $overrides Optional configuration overrides.
	 * @return array
	 */
	public static function llm_config( $overrides = array() ) {
		$defaults = array(
			'provider' => 'openai',
			'api_key'  => 'test-api-key-12345',
			'model'    => 'gpt-4o',
			'endpoint' => 'https://api.openai.com/v1/chat/completions',
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test chat message.
	 *
	 * @param array $overrides Optional message overrides.
	 * @return array
	 */
	public static function chat_message( $overrides = array() ) {
		$defaults = array(
			'role'    => 'user',
			'content' => 'Test message content',
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test agent metadata.
	 *
	 * @param array $overrides Optional metadata overrides.
	 * @return array
	 */
	public static function agent_metadata( $overrides = array() ) {
		$defaults = array(
			'id'          => 'test-agent',
			'name'        => 'Test Agent',
			'description' => 'Test agent description',
			'version'     => '1.0.0',
			'author'      => 'Test Suite',
			'tools'       => array(),
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test job data.
	 *
	 * @param array $overrides Optional job overrides.
	 * @return array
	 */
	public static function job_data( $overrides = array() ) {
		$defaults = array(
			'agent_id'   => 'test-agent',
			'action'     => 'test_action',
			'status'     => 'pending',
			'priority'   => 5,
			'created_by' => 1,
			'data'       => array( 'test' => 'data' ),
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test audit log entry.
	 *
	 * @param array $overrides Optional log overrides.
	 * @return array
	 */
	public static function audit_log( $overrides = array() ) {
		$defaults = array(
			'agent_name' => 'test-agent',
			'action'     => 'test_action',
			'user_id'    => 1,
			'status'     => 'success',
			'details'    => 'Test action performed',
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test security event.
	 *
	 * @param array $overrides Optional event overrides.
	 * @return array
	 */
	public static function security_event( $overrides = array() ) {
		$defaults = array(
			'event_type' => 'blocked',
			'ip_address' => '127.0.0.1',
			'user_id'    => null,
			'agent_id'   => 'test-agent',
			'message'    => 'Test security event',
			'pattern'    => 'test_pattern',
			'severity'   => 'medium',
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test API response.
	 *
	 * @param array $overrides Optional response overrides.
	 * @return array
	 */
	public static function api_response( $overrides = array() ) {
		$defaults = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => 'Test AI response',
						'role'    => 'assistant',
					),
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
				'total_tokens'      => 30,
			),
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Create test approval queue item.
	 *
	 * @param array $overrides Optional item overrides.
	 * @return array
	 */
	public static function approval_item( $overrides = array() ) {
		$defaults = array(
			'agent_id'   => 'test-agent',
			'action'     => 'test_action',
			'data'       => array( 'test' => 'data' ),
			'status'     => 'pending',
			'created_by' => 1,
		);

		return array_merge( $defaults, $overrides );
	}
}
