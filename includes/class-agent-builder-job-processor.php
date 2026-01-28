<?php
/**
 * Agent Builder Job Processor
 *
 * Processes agent building jobs asynchronously
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Agent Builder Job Processor
 */
class Agent_Builder_Job_Processor implements Job_Processor_Interface {

	/**
	 * Execute the agent building job
	 *
	 * @param array    $request_data      Job input data.
	 * @param callable $progress_callback Progress update callback.
	 * @return array Job result data.
	 * @throws \Exception If job execution fails.
	 */
	public function execute( array $request_data, callable $progress_callback ): array {
		$progress_callback( 5, 'Initializing agent builder...' );

		// Get the agent builder agent
		$registry = Agent_Registry::get_instance();
		$agent    = $registry->get_agent_instance( 'agent-builder' );

		if ( ! $agent ) {
			throw new \Exception( 'Agent Builder agent not found' );
		}

		$progress_callback( 10, 'Loading conversation history...' );

		// Build message array from history
		$messages = [];
		
		// Add system prompt
		$messages[] = [
			'role'    => 'system',
			'content' => $agent->get_system_prompt(),
		];

		// Add history if provided
		if ( ! empty( $request_data['history'] ) ) {
			foreach ( $request_data['history'] as $entry ) {
				if ( isset( $entry['role'], $entry['content'] ) ) {
					$messages[] = [
						'role'    => $entry['role'],
						'content' => $entry['content'],
					];
				}
			}
		}

		// Add current message
		$messages[] = [
			'role'    => 'user',
			'content' => $request_data['message'],
		];

		$progress_callback( 20, 'Analyzing request...' );

		// Create LLM client
		$llm = new LLM_Client();

		if ( ! $llm->is_configured() ) {
			throw new \Exception( 'LLM API is not configured. Please add your API key in settings.' );
		}

		// Get tools
		$tools = $agent->get_tools();

		$progress_callback( 30, 'Generating agent specification...' );

		// Make initial LLM call
		$llm_response = $llm->chat( $messages, $tools );

		if ( is_wp_error( $llm_response ) ) {
			throw new \Exception( 'LLM request failed: ' . $llm_response->get_error_message() );
		}

		// Extract message from response
		$choice = $llm_response['choices'][0]['message'] ?? [];

		$progress_callback( 50, 'Executing agent tools...' );

		// Handle tool calls iteratively
		$max_iterations = 5;
		$iteration      = 0;

		while ( isset( $choice['tool_calls'] ) && ! empty( $choice['tool_calls'] ) && $iteration < $max_iterations ) {
			$iteration++;

			$progress_percent = 50 + ( $iteration * 10 );
			$progress_callback( $progress_percent, "Processing tools (iteration {$iteration})..." );

			// Add assistant message with tool calls
			$messages[] = [
				'role'       => 'assistant',
				'content'    => $choice['content'] ?? '',
				'tool_calls' => $choice['tool_calls'],
			];

			// Execute each tool call
			foreach ( $choice['tool_calls'] as $tool_call ) {
				$tool_name = $tool_call['function']['name'];
				$tool_args = json_decode( $tool_call['function']['arguments'], true ) ?: [];

				// Execute tool through the agent
				$tool_result = $agent->execute_tool( $tool_name, $tool_args );

				// Add tool result
				$messages[] = [
					'role'         => 'tool',
					'tool_call_id' => $tool_call['id'],
					'content'      => is_array( $tool_result ) ? wp_json_encode( $tool_result ) : (string) $tool_result,
				];
			}

			// Get next LLM response
			$llm_response = $llm->chat( $messages, $tools );

			if ( is_wp_error( $llm_response ) ) {
				break;
			}

			// Update choice from new response
			$choice = $llm_response['choices'][0]['message'] ?? [];
		}

		$progress_callback( 95, 'Finalizing response...' );

		$response_content = $choice['content'] ?? 'No response generated.';

		$progress_callback( 100, 'Completed' );

		return [
			'response'   => $response_content,
			'agent_id'   => 'agent-builder',
			'iterations' => $iteration,
		];
	}
}
