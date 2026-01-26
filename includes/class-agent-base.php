<?php
/**
 * Base Agent Class
 *
 * All agents extend this class to provide their identity, system prompt,
 * and available tools. The Agent Controller uses this to run conversations.
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 */

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for all Agentic agents
 *
 * Implementations must provide identifiers, descriptions, and optionally tools
 * to be used by the agent controller.
 */
abstract class Agent_Base {

	/**
	 * Get the agent's unique identifier (slug)
	 *
	 * @return string Agent ID (e.g., 'security-monitor', 'developer-agent')
	 */
	abstract public function get_id(): string;

	/**
	 * Get the agent's display name
	 *
	 * @return string Human-readable name
	 */
	abstract public function get_name(): string;

	/**
	 * Get the agent's description
	 *
	 * @return string Description of what the agent does
	 */
	abstract public function get_description(): string;

	/**
	 * Get the agent's system prompt
	 *
	 * This defines the agent's personality, expertise, and behavior.
	 *
	 * @return string System prompt for the LLM
	 */
	abstract public function get_system_prompt(): string;

	/**
	 * Get the agent's icon (emoji or dashicon)
	 *
	 * @return string Icon for display
	 */
	public function get_icon(): string {
		return '🤖';
	}

	/**
	 * Get the agent's category
	 *
	 * @return string Category (content, admin, ecommerce, frontend, developer)
	 */
	public function get_category(): string {
		return 'admin';
	}

	/**
	 * Get the agent's tool definitions
	 *
	 * Override this to provide agent-specific tools.
	 *
	 * @return array Tool definitions in OpenAI function format
	 */
	public function get_tools(): array {
		return array();
	}

	/**
	 * Execute a tool call
	 *
	 * Override this to handle agent-specific tool execution.
	 *
	 * @param string $tool_name Tool name.
	 * @param array  $arguments Tool arguments.
	 * @return array|null Result or null if tool not handled
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return null;
	}

	/**
	 * Get welcome message for chat interface
	 *
	 * @return string Welcome message shown when chat opens
	 */
	public function get_welcome_message(): string {
		return sprintf(
			"Hello! I'm %s. %s\n\nHow can I help you today?",
			$this->get_name(),
			$this->get_description()
		);
	}

	/**
	 * Get suggested prompts for the chat interface
	 *
	 * @return array Array of suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array();
	}

	/**
	 * Check if agent requires specific capabilities
	 *
	 * @return array Required WordPress capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'read' );
	}

	/**
	 * Check if current user can access this agent
	 *
	 * @return bool Whether user has access
	 */
	public function current_user_can_access(): bool {
		foreach ( $this->get_required_capabilities() as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get agent metadata for registration/display
	 *
	 * @return array Agent metadata
	 */
	public function get_metadata(): array {
		return array(
			'id'          => $this->get_id(),
			'name'        => $this->get_name(),
			'description' => $this->get_description(),
			'icon'        => $this->get_icon(),
			'category'    => $this->get_category(),
			'tools'       => array_map( fn( $t ) => $t['function']['name'] ?? $t['name'], $this->get_tools() ),
		);
	}
}
