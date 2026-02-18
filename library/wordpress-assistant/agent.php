<?php
/**
 * Agent Name: WordPress Assistant
 * Version: 1.1.0
 * Description: Your guide to WordPress and the AI ecosystem. Answers questions about the plugin and helps new users get started.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Starter
 * Tags: onboarding, documentation, getting-started, wordpress, guide
 * Capabilities: read
 * Icon: 🧭
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Assistant
 *
 * Your guide to WordPress and the AI ecosystem.
 * Read-only — does not execute code or make changes.
 */
if ( class_exists( 'Agentic_WordPress_Assistant' ) ) {
	return;
}
class Agentic_WordPress_Assistant extends \Agentic\Agent_Base {

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
		return 'wordpress-assistant';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'WordPress Assistant';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Your guide to WordPress and the AI ecosystem. Answers questions about the plugin and helps new users get started.';
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
		return '🧭';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'Starter';
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
		return "🧭 **WordPress Assistant**\n\n" .
				"Hi! I'm your guide to WordPress and the AI assistant ecosystem.\n\n" .
				"Here's what I can help you with:\n\n" .
				"- **Assistant Trainer** — Train your first AI assistant right now\n" .
				"- **Content Assistant** — Draft, edit, and optimise your posts and pages\n" .
				"- **Plugin Assistant** — Build your first custom plugin for WordPress\n" .
				"- **Theme Assistant** — Choose and customise your WordPress theme\n\n" .
				'Just type your question below or choose an option.';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'How do I create a new agent?',
			'What agents are available?',
			'Help me build a plugin',
			'How do I change my theme?',
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
		);
	}

	/**
	 * Execute agent-specific tools
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'get_agent_list' => $this->tool_get_agent_list(),
			default          => null,
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
		$registry->register( new Agentic_WordPress_Assistant() );
	}
);
