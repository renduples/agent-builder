<?php
/**
 * Agent Name: Onboarding Agent
 * Version: 1.1.0
 * Description: Your guide to the Agent Builder ecosystem. Answers questions about the codebase, evaluates feature requests, and helps new developers get started.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Developer
 * Tags: development, documentation, onboarding, feature-requests, code-review
 * Capabilities: read
 * Icon: 💻
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onboarding Agent
 *
 * A true AI agent for developer onboarding and feature request evaluation.
 * This is a Q&A agent - it does NOT execute code or make changes.
 */
class Agentic_Onboarding_Agent extends \Agentic\Agent_Base {

	/**
	 * Load system prompt from template file
	 */
	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'onboarding-agent';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Onboarding Agent';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Your guide to the Agentic ecosystem. Answers questions and evaluates feature requests.';
	}

	/**
	 * Get system prompt
	 */
	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	/**
	 * Get agent icon
	 */
	public function get_icon(): string {
		return '💻';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'developer';
	}

	/**
	 * Get agent version
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Get agent author
	 */
	public function get_author(): string {
		return 'Agentic Community';
	}

	/**
	 * Get required capabilities - accessible to all logged-in users
	 */
	public function get_required_capabilities(): array {
		return array( 'read' );
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "Hi there! I'm the Onboarding Agent — here to help you get started.\n\n" .
				"Here's what I can help you with:\n\n" .
				"- **Agent Builder** — Build your first AI Agent right now\n" .
				"- **Content Builder** — Create pages and posts to get you started\n" .
				"- **Plugin Builder** — Build your first custom plugin for WordPress\n" .
				"- **Theme Builder** — Get help choosing and installing a Theme\n\n" .
				'Just type your question below or choose an option.';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'How do I create a new agent?',
			'Explain the agent architecture',
			'What files should I look at first?',
			'I have a feature idea for...',
		);
	}

	/**
	 * Get agent-specific tools - read-only exploration tools
	 */
	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_agent_list',
					'description' => 'Get a list of all installed agents and their status.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'evaluate_feature_request',
					'description' => 'Formally evaluate a feature request and record it for the team.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array(
								'type'        => 'string',
								'description' => 'Short title for the feature request',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'Detailed description of the feature',
							),
							'requester'   => array(
								'type'        => 'string',
								'description' => 'Name or identifier of who requested this',
							),
						),
						'required'   => array( 'title', 'description' ),
					),
				),
			),
		);
	}

	/**
	 * Execute agent-specific tools
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'get_agent_list'           => $this->tool_get_agent_list(),
			'evaluate_feature_request' => $this->tool_evaluate_feature( $arguments ),
			default                    => null,
		};
	}

	/**
	 * Tool: Get agent list
	 */
	private function tool_get_agent_list(): array {
		$agents = array();

		// Check library directory for agents
		$library_path = AGENT_BUILDER_DIR . 'library/';

		if ( is_dir( $library_path ) ) {
			$dirs = scandir( $library_path );
			foreach ( $dirs as $dir ) {
				if ( $dir === '.' || $dir === '..' ) {
					continue;
				}

				$agent_file = $library_path . $dir . '/agent.php';
				if ( file_exists( $agent_file ) ) {
					$header   = $this->parse_agent_header( $agent_file );
					$agents[] = array(
						'id'          => $dir,
						'name'        => $header['Agent Name'] ?? $dir,
						'description' => $header['Description'] ?? '',
						'category'    => $header['Category'] ?? 'unknown',
						'version'     => $header['Version'] ?? '0.0.0',
						'active'      => $this->is_agent_active( $dir ),
					);
				}
			}
		}

		return array(
			'agents'       => $agents,
			'total'        => count( $agents ),
			'active_count' => count( array_filter( $agents, fn( $a ) => $a['active'] ) ),
		);
	}

	/**
	 * Tool: Evaluate feature request
	 */
	private function tool_evaluate_feature( array $args ): array {
		$title       = sanitize_text_field( $args['title'] ?? '' );
		$description = sanitize_textarea_field( $args['description'] ?? '' );
		$requester   = sanitize_text_field( $args['requester'] ?? 'Anonymous' );

		if ( empty( $title ) || empty( $description ) ) {
			return array( 'error' => 'Title and description are required' );
		}

		// Create a feature request post
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => '[Feature Request] ' . $title,
				'post_content' => sprintf(
					"## Feature Request\n\n" .
					"**Requested by:** %s\n\n" .
					"**Date:** %s\n\n" .
					"## Description\n\n%s\n\n" .
					"## Agent Evaluation\n\n" .
					'_Pending evaluation by Onboarding Agent_',
					$requester,
					current_time( 'F j, Y' ),
					$description
				),
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}

		// Add meta for tracking
		update_post_meta( $post_id, '_feature_request', true );
		update_post_meta( $post_id, '_requester', $requester );
		update_post_meta( $post_id, '_status', 'pending_evaluation' );

		return array(
			'success'  => true,
			'post_id'  => $post_id,
			'title'    => $title,
			'message'  => 'Feature request recorded. I will now analyze it for feasibility and alignment.',
			'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);
	}

	/**
	 * Parse agent header from file
	 */
	private function parse_agent_header( string $file_path ): array {
		$content = file_get_contents( $file_path );
		$headers = array();

		if ( preg_match_all( '/^\s*\*\s*(Agent Name|Version|Description|Category|Author|Icon):\s*(.+)$/m', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headers[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}

		return $headers;
	}

	/**
	 * Check if agent is active
	 */
	private function is_agent_active( string $agent_id ): bool {
		$active_agents = get_option( 'agentic_active_agents', array() );
		return in_array( $agent_id, $active_agents, true );
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Onboarding_Agent() );
	}
);
