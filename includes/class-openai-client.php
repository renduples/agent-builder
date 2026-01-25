<?php
/**
 * xAI Grok API Client
 *
 * @package Agentic_Plugin
 */

declare(strict_types=1);

namespace Agentic\Core;

/**
 * xAI Grok API client for agent interactions
 *
 * Handles non-streaming chat completions via the xAI-compatible OpenAI API.
 */
class OpenAI_Client {

    /**
     * API endpoint (xAI uses OpenAI-compatible format)
     */
    private const API_ENDPOINT = 'https://api.x.ai/v1/chat/completions';

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
        $this->api_key = get_option( 'agentic_xai_api_key', '' );
        $this->model   = get_option( 'agentic_model', 'grok-3' );
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
    public function chat( array $messages, array $tools = [] ): array|\WP_Error {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'not_configured', 'OpenAI API key not configured.' );
        }

        $body = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        if ( ! empty( $tools ) ) {
            $body['tools']       = $tools;
            $body['tool_choice'] = 'auto';
        }

        $response = wp_remote_post( self::API_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            return new \WP_Error(
                'api_error',
                $body['error']['message'] ?? 'Unknown API error',
                [ 'status' => $status ]
            );
        }

        return $body;
    }

    /**
     * Process a streaming chat completion (for future use)
     *
     * @param array    $messages Conversation messages.
     * @param callable $callback Callback for each chunk.
     * @param array    $tools    Available tools.
     * @return bool|\WP_Error Success or error.
     */
    public function stream_chat( array $messages, callable $callback, array $tools = [] ): bool|\WP_Error {
        // Streaming implementation for future enhancement
        return new \WP_Error( 'not_implemented', 'Streaming not yet implemented.' );
    }

    /**
     * Get usage statistics from response
     *
     * @param array $response API response.
     * @return array Usage statistics.
     */
    public function get_usage( array $response ): array {
        return $response['usage'] ?? [
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
        ];
    }
}
