<?php
/**
 * LLM Client for multiple AI providers.
 *
 * @package Agentic_Plugin
 */

declare(strict_types=1);

namespace Agentic\Core;

/**
 * LLM client supporting OpenAI, Anthropic, xAI, Google, and Mistral.
 *
 * Handles chat completions across multiple AI providers.
 */
class LLM_Client {

	/**
	 * API endpoints for different providers
	 */
	private const ENDPOINTS = array(
		'openai'    => 'https://api.openai.com/v1/chat/completions',
		'anthropic' => 'https://api.anthropic.com/v1/messages',
		'xai'       => 'https://api.x.ai/v1/chat/completions',
		'google'    => 'https://generativelanguage.googleapis.com/v1beta/models/',
		'mistral'   => 'https://api.mistral.ai/v1/chat/completions',
	);

	/**
	 * LLM provider
	 *
	 * @var string
	 */
	private string $provider;

	/**
	 * API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->provider = get_option( 'agentic_llm_provider', 'openai' );
		$this->api_key  = get_option( 'agentic_llm_api_key', '' );
		$this->model    = get_option( 'agentic_model', 'gpt-4o' );

		// Backward compatibility: migrate legacy xAI options.
		$legacy_key = get_option( 'agentic_xai_api_key', '' );
		if ( empty( $this->api_key ) && ! empty( $legacy_key ) ) {
			$this->api_key  = $legacy_key;
			$this->provider = 'xai';
			update_option( 'agentic_llm_api_key', $legacy_key );
			update_option( 'agentic_llm_provider', 'xai' );
		}
	}

	/**
	 * Get current provider
	 *
	 * @return string
	 */
	public function get_provider(): string {
		return $this->provider;
	}

	/**
	 * Get current model
	 *
	 * @return string
	 */
	public function get_model(): string {
		return $this->model;
	}

	/**
	 * Check if the client is configured
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}

	/**
	 * Send a chat completion request
	 *
	 * @param array $messages Conversation messages.
	 * @param array $tools    Available tools.
	 * @return array|\WP_Error Response or error.
	 */
	public function chat( array $messages, array $tools = array() ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'not_configured', 'LLM API key not configured.' );
		}

		// Get endpoint and headers for the provider.
		$endpoint = $this->get_endpoint();
		$headers  = $this->get_headers();
		$body     = $this->format_request( $messages, $tools );

		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			return new \WP_Error(
				'api_error',
				$data['error']['message'] ?? 'Unknown API error',
				array( 'status' => $status )
			);
		}

		return $data;
	}

	/**
	 * Process a streaming chat completion (for future use)
	 *
	 * @param array    $messages Conversation messages.
	 * @param callable $callback Callback for each chunk.
	 * @param array    $tools    Available tools.
	 * @return bool|\WP_Error Success or error.
	 */
	public function stream_chat( array $messages, callable $callback, array $tools = array() ): bool|\WP_Error {
		// Streaming implementation for future enhancement.
		unset( $messages, $callback, $tools );
		return new \WP_Error( 'not_implemented', 'Streaming not yet implemented.' );
	}

	/**
	 * Get usage statistics from response
	 *
	 * @param array $response API response.
	 * @return array Usage statistics.
	 */
	public function get_usage( array $response ): array {
		return $response['usage'] ?? array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
		);
	}

	/**
	 * Get API endpoint for a specific provider (public version for testing)
	 *
	 * @param string $provider Provider name.
	 * @return string Endpoint URL.
	 */
	public function get_endpoint_for_provider( string $provider ): string {
		if ( ! isset( self::ENDPOINTS[ $provider ] ) ) {
			return '';
		}

		$endpoint = self::ENDPOINTS[ $provider ];

		// Google uses model-specific endpoints.
		if ( 'google' === $provider ) {
			$endpoint .= 'gemini-2.0-flash-exp:generateContent?key=' . get_option( 'agentic_llm_api_key', '' );
		}

		return $endpoint;
	}

	/**
	 * Get headers for a specific provider (public version for testing)
	 *
	 * @param string $provider Provider name.
	 * @param string $api_key  API key to use.
	 * @return array Headers.
	 */
	public function get_headers_for_provider( string $provider, string $api_key ): array {
		$headers = array( 'Content-Type' => 'application/json' );

		switch ( $provider ) {
			case 'anthropic':
				$headers['x-api-key']         = $api_key;
				$headers['anthropic-version'] = '2023-06-01';
				break;
			case 'google':
				// API key in URL for Google.
				break;
			default:
				// OpenAI, xAI, Mistral use Bearer token.
				$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		return $headers;
	}

	/**
	 * Format request body for a specific provider (public version for testing)
	 *
	 * @param string $provider Provider name.
	 * @param array  $messages Conversation messages.
	 * @return array Formatted request body.
	 */
	public function format_request_for_provider( string $provider, array $messages ): array {
		$body = array();

		switch ( $provider ) {
			case 'anthropic':
				// Anthropic uses different format.
				$system = '';
				$msgs   = array();
				foreach ( $messages as $msg ) {
					if ( 'system' === $msg['role'] ) {
						$system = $msg['content'];
					} else {
						$msgs[] = $msg;
					}
				}
				$body['model']      = get_option( 'agentic_model', 'claude-3-5-sonnet-20241022' );
				$body['messages']   = $msgs;
				$body['max_tokens'] = 4096;
				if ( ! empty( $system ) ) {
					$body['system'] = $system;
				}
				break;

			case 'google':
				// Google uses different format.
				$contents = array();
				foreach ( $messages as $msg ) {
					$contents[] = array(
						'role'  => 'user' === $msg['role'] ? 'user' : 'model',
						'parts' => array( array( 'text' => $msg['content'] ) ),
					);
				}
				$body['contents'] = $contents;
				break;

			default:
				// OpenAI, xAI, Mistral use standard format.
				$body['model']       = get_option( 'agentic_model', 'gpt-4o' );
				$body['messages']    = $messages;
				$body['max_tokens']  = 4096;
				$body['temperature'] = 0.7;
		}

		return $body;
	}

	/**
	 * Get API endpoint for the current provider
	 *
	 * @return string|\WP_Error Endpoint URL or error.
	 */
	private function get_endpoint(): string|\WP_Error {
		if ( ! isset( self::ENDPOINTS[ $this->provider ] ) ) {
			return new \WP_Error( 'invalid_provider', 'Invalid LLM provider.' );
		}

		$endpoint = self::ENDPOINTS[ $this->provider ];

		// Google uses model-specific endpoints.
		if ( 'google' === $this->provider ) {
			$endpoint .= $this->model . ':generateContent?key=' . $this->api_key;
		}

		return $endpoint;
	}

	/**
	 * Get headers for the current provider
	 *
	 * @return array Headers.
	 */
	private function get_headers(): array {
		$headers = array( 'Content-Type' => 'application/json' );

		switch ( $this->provider ) {
			case 'anthropic':
				$headers['x-api-key']         = $this->api_key;
				$headers['anthropic-version'] = '2023-06-01';
				break;
			case 'google':
				// API key in URL for Google.
				break;
			default:
				// OpenAI, xAI, Mistral use Bearer token.
				$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

	/**
	 * Format request body for the current provider
	 *
	 * @param array $messages Conversation messages.
	 * @param array $tools    Available tools.
	 * @return array Formatted request body.
	 */
	private function format_request( array $messages, array $tools ): array {
		$body = array();

		switch ( $this->provider ) {
			case 'anthropic':
				// Anthropic uses different format.
				$system = '';
				$msgs   = array();
				foreach ( $messages as $msg ) {
					if ( 'system' === $msg['role'] ) {
						$system = $msg['content'];
					} else {
						$msgs[] = $msg;
					}
				}
				$body['model']      = $this->model;
				$body['messages']   = $msgs;
				$body['max_tokens'] = 4096;
				if ( ! empty( $system ) ) {
					$body['system'] = $system;
				}
				break;

			case 'google':
				// Google uses different format.
				$contents = array();
				foreach ( $messages as $msg ) {
					$contents[] = array(
						'role'  => 'user' === $msg['role'] ? 'user' : 'model',
						'parts' => array( array( 'text' => $msg['content'] ) ),
					);
				}
				$body['contents'] = $contents;
				break;

			default:
				// OpenAI, xAI, Mistral use same format.
				$body['model']    = $this->model;
				$body['messages'] = $messages;
				if ( ! empty( $tools ) ) {
					$body['tools']       = $tools;
					$body['tool_choice'] = 'auto';
				}
		}

		return $body;
	}
}
