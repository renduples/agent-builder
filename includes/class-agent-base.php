<?php
/**
 * Base Agent Class
 *
 * All agents extend this class to provide their identity, system prompt,
 * and available tools. The Agent Controller uses this to run conversations.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.1.0
 *
 * php version 8.1
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
	 * @return string Agent ID (e.g., 'content-builder', 'onboarding-assistant')
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
	 * Get the agent's version
	 *
	 * @return string Version number
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Get the agent's author
	 *
	 * @return string Author name
	 */
	public function get_author(): string {
		return 'Unknown';
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
	 * @param string $_tool_name Tool name (unused - base implementation returns null).
	 * @param array  $_arguments Tool arguments (unused - base implementation returns null).
	 * @return array|null Result or null if tool not handled
	 */
	public function execute_tool( string $_tool_name, array $_arguments ): ?array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
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

	/**
	 * Get event listeners for this agent
	 *
	 * Override this to react to WordPress actions as they happen.
	 * Each listener array must include:
	 *   - 'id'            (string) Unique listener identifier within this agent.
	 *   - 'hook'          (string) WordPress action hook name (e.g., 'save_post', 'wp_login').
	 *   - 'name'          (string) Human-readable listener name.
	 *   - 'callback'      (string) Method name on this agent to call.
	 *
	 * Optional:
	 *   - 'description'   (string) What this listener does.
	 *   - 'priority'      (int)    Hook priority (default 10).
	 *   - 'accepted_args' (int)    Number of arguments the callback accepts (default 1).
	 *   - 'prompt'        (string) If set, queues an async LLM task via wp_schedule_single_event
	 *                              instead of calling the callback synchronously. The hook
	 *                              arguments are JSON-serialized into the prompt context.
	 *                              Falls back to 'callback' if LLM is not configured.
	 *
	 * @return array[] Array of listener definitions.
	 */
	public function get_event_listeners(): array {
		return array();
	}

	/**
	 * Get scheduled tasks for this agent
	 *
	 * Override this to define recurring tasks the agent should perform.
	 * Each task array must include:
	 *   - 'id'       (string) Unique task identifier within this agent.
	 *   - 'name'     (string) Human-readable task name.
	 *   - 'callback' (string) Method name on this agent to call (fallback).
	 *   - 'schedule' (string) WP-Cron recurrence: 'hourly', 'twicedaily', 'daily', 'weekly'.
	 *
	 * Optional:
	 *   - 'description' (string) What this task does.
	 *   - 'prompt'      (string) If set, the task runs through the LLM autonomously
	 *                            using Agent_Controller::run_autonomous_task(). The agent
	 *                            will receive this prompt, use its tools, and produce a
	 *                            summary. Falls back to 'callback' if LLM is not configured.
	 *
	 * @return array[] Array of task definitions.
	 */
	public function get_scheduled_tasks(): array {
		return array();
	}

	/**
	 * Get the WP-Cron hook name for a scheduled task
	 *
	 * @param string $task_id Task identifier.
	 * @return string Hook name.
	 */
	public function get_cron_hook( string $task_id ): string {
		return 'agentic_task_' . $this->get_id() . '_' . $task_id;
	}

	/**
	 * Register all scheduled tasks for this agent
	 *
	 * Called when the agent is activated.
	 *
	 * @return void
	 */
	public function register_scheduled_tasks(): void {
		foreach ( $this->get_scheduled_tasks() as $task ) {
			$hook = $this->get_cron_hook( $task['id'] );

			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $task['schedule'], $hook );
			}
		}
	}

	/**
	 * Unregister all scheduled tasks for this agent
	 *
	 * Called when the agent is deactivated.
	 *
	 * @return void
	 */
	public function unregister_scheduled_tasks(): void {
		foreach ( $this->get_scheduled_tasks() as $task ) {
			$hook      = $this->get_cron_hook( $task['id'] );
			$timestamp = wp_next_scheduled( $hook );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
