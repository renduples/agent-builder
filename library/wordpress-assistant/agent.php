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
			"Hi! I'm your guide to AI in WordPress. Ask me anything or pick a specialist below.\n\n" .
			"**Your bundled AI team:**\n" .
			"- ✍️ **Content Writer** — Create, edit, and publish posts and pages\n" .
			"- 🔍 **SEO Assistant** — Audit and fix titles, meta descriptions, and keywords\n" .
			"- 🔒 **Security Assistant** — Monitor logins, plugins, and suspicious activity\n" .
			"- 🩺 **Site Doctor** — Check database health, broken links, errors, and bloat\n" .
			"- 📡 **AI Radar** — Check your website's AI search engine visibillity and fix problems\n" .
			"- 🏗️ **Assistant Trainer** — Train a new AI assistant by giving it a job description\n" .
			'What can I help you with today?';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'What can Agent Builder do?',
			'Which assistant should I use for [task]?',
			'Can AI search engines find my website?',
			'How do I schedule an assistant to run automatically?',
		);
	}

	/**
	 * Get agent shortcuts — used by the setup wizard to render "load this agent" navigation pills.
	 * Each entry: ['icon' => string, 'label' => string, 'agent_id' => string]
	 */
	public function get_agent_shortcuts(): array {
		return array(
			array( 'icon' => '✍️', 'label' => 'Content Writer',   'agent_id' => 'content-writer' ),
			array( 'icon' => '🔍', 'label' => 'SEO Assistant',    'agent_id' => 'seo-assistant' ),
			array( 'icon' => '🔒', 'label' => 'Security Assistant', 'agent_id' => 'security-assistant' ),
			array( 'icon' => '🩺', 'label' => 'Site Doctor',      'agent_id' => 'site-doctor' ),
			array( 'icon' => '📡', 'label' => 'AI Radar',         'agent_id' => 'ai-radar' ),
			array( 'icon' => '🏗️', 'label' => 'Assistant Trainer', 'agent_id' => 'assistant-trainer' ),
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

// Register the agent.
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_WordPress_Assistant() );
	}
);
