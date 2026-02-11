<?php
/**
 * Agent Controller
 *
 * Handles conversations with any registered agent using their system prompt and tools.
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

declare(strict_types=1);

namespace Agentic;

/**
 * Main agent controller handling conversations and tool orchestration
 */
class Agent_Controller {

	/**
	 * LLM client
	 *
	 * @var LLM_Client
	 */
	private LLM_Client $llm;

	/**
	 * Core agent tools
	 *
	 * @var Agent_Tools
	 */
	private Agent_Tools $core_tools;

	/**
	 * Audit log
	 *
	 * @var Audit_Log
	 */
	private Audit_Log $audit;

	/**
	 * Current agent being used
	 *
	 * @var \Agentic\Agent_Base|null
	 */
	private ?\Agentic\Agent_Base $current_agent = null;

	/**
	 * Maximum tool iterations per request
	 */
	private const MAX_ITERATIONS = 10;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->llm        = new LLM_Client();
		$this->core_tools = new Agent_Tools();
		$this->audit      = new Audit_Log();
	}

	/**
	 * Set the current agent for conversation
	 *
	 * @param string $agent_id Agent identifier.
	 * @return bool Whether agent was set successfully.
	 */
	public function set_agent( string $agent_id ): bool {
		$registry = \Agentic_Agent_Registry::get_instance();
		$agent    = $registry->get_agent_instance( $agent_id );

		if ( ! $agent ) {
			return false;
		}

		if ( ! $agent->current_user_can_access() ) {
			return false;
		}

		$this->current_agent = $agent;
		return true;
	}

	/**
	 * Get the current agent
	 *
	 * @return \Agentic\Agent_Base|null Current agent or null.
	 */
	public function get_agent(): ?\Agentic\Agent_Base {
		return $this->current_agent;
	}

	/**
	 * Get tools for the current agent
	 *
	 * Combines agent-specific tools with core tools.
	 *
	 * @return array Tool definitions.
	 */
	private function get_tools_for_agent(): array {
		$tools = array();

		// Add agent-specific tools first.
		if ( $this->current_agent ) {
			$tools = $this->current_agent->get_tools();
		}

		// Merge core tools so the LLM knows about them.
		$core_tool_defs = $this->core_tools->get_tool_definitions();
		foreach ( $core_tool_defs as $core_tool ) {
			$tools[] = $core_tool;
		}

		return $tools;
	}

	/**
	 * Execute a tool call
	 *
	 * First checks if agent handles the tool, then falls back to core tools.
	 *
	 * @param string $tool_name Tool name.
	 * @param array  $arguments Tool arguments.
	 * @return array Tool result.
	 */
	private function execute_tool( string $tool_name, array $arguments ): array {
		$agent_id = $this->current_agent ? $this->current_agent->get_id() : 'unknown';

		$this->audit->log( $agent_id, 'tool_call', $tool_name, $arguments );

		// First, let the agent try to handle it.
		if ( $this->current_agent ) {
			$result = $this->current_agent->execute_tool( $tool_name, $arguments );

			if ( null !== $result ) {
				return $result;
			}
		}

		// Fall back to core tools.
		$result = $this->core_tools->execute( $tool_name, $arguments, $agent_id );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Process a chat message
	 *
	 * @param string $message    User message.
	 * @param array  $history    Conversation history.
	 * @param int    $user_id    User ID.
	 * @param string $session_id Session identifier.
	 * @param string $agent_id   Agent ID (optional, uses current agent if not set).
	 * @return array Response data.
	 */
	public function chat( string $message, array $history = array(), int $user_id = 0, string $session_id = '', string $agent_id = '' ): array {
		// Set agent if specified.
		if ( $agent_id && ( ! $this->current_agent || $this->current_agent->get_id() !== $agent_id ) ) {
			if ( ! $this->set_agent( $agent_id ) ) {
				return array(
					'response' => "Agent '{$agent_id}' is not available or you don't have access to it.",
					'error'    => true,
					'agent_id' => $agent_id,
				);
			}
		}

		if ( ! $this->current_agent ) {
			return array(
				'response' => 'No agent selected. Please select an agent to chat with.',
				'error'    => true,
				'agent_id' => '',
			);
		}

		if ( ! $this->llm->is_configured() ) {
			return array(
				'response' => 'The AI service is not configured. Please ask an administrator to set up the API key in Settings > Agentic.',
				'error'    => true,
				'agent_id' => $this->current_agent->get_id(),
			);
		}

		$current_agent_id = $this->current_agent->get_id();

		// Build messages array with agent's system prompt.
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $this->current_agent->get_system_prompt(),
			),
		);

		// Add history.
		foreach ( $history as $entry ) {
			$messages[] = array(
				'role'    => $entry['role'],
				'content' => $entry['content'],
			);
		}

		// Add current message.
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get tools for this agent.
		$tools = $this->get_tools_for_agent();

		// Log the conversation start.
		$this->audit->log(
			$current_agent_id,
			'chat_start',
			'conversation',
			array(
				'session_id' => $session_id,
				'user_id'    => $user_id,
				'message'    => substr( $message, 0, 200 ),
			)
		);

		// Process with potential tool calls.
		$response     = null;
		$total_tokens = 0;
		$iterations   = 0;
		$tool_results = array();
		$usage        = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
		);

		while ( $iterations < self::MAX_ITERATIONS ) {
			++$iterations;

			// Pass empty tools array if no tools defined.
			$result = $this->llm->chat( $messages, $tools ? $tools : null );

			if ( is_wp_error( $result ) ) {
				return array(
					'response' => 'Error communicating with AI: ' . $result->get_error_message(),
					'error'    => true,
					'agent_id' => $current_agent_id,
				);
			}

			$usage         = $this->llm->get_usage( $result );
			$total_tokens += $usage['total_tokens'];

			$choice = $result['choices'][0] ?? null;
			if ( ! $choice ) {
				return array(
					'response' => 'Invalid response from AI.',
					'error'    => true,
					'agent_id' => $current_agent_id,
				);
			}

			$assistant_message = $choice['message'];

			// Ensure the message has content (required by some providers).
			if ( ! isset( $assistant_message['content'] ) || null === $assistant_message['content'] ) {
				$assistant_message['content'] = '';
			}

			$messages[] = $assistant_message;

			// Check if we have tool calls.
			if ( ! empty( $assistant_message['tool_calls'] ) ) {
				foreach ( $assistant_message['tool_calls'] as $tool_call ) {
					$function_name = $tool_call['function']['name'];
					$arguments     = json_decode( $tool_call['function']['arguments'], true ) ?? array();

					// Execute the tool.
					$tool_result = $this->execute_tool( $function_name, $arguments );

					// Add tool result to messages.
					$messages[] = array(
						'role'         => 'tool',
						'tool_call_id' => $tool_call['id'],
						'content'      => wp_json_encode( $tool_result ),
					);

					$tool_results[] = array(
						'tool'   => $function_name,
						'result' => $tool_result,
					);
				}
			} else {
				// No more tool calls, we have our final response.
				$response = $assistant_message['content'];
				break;
			}
		}

		if ( null === $response ) {
			$response = 'I reached the maximum number of tool iterations. Please try a simpler request.';
		}

		// Estimate cost (Grok 3 pricing: $3/1M input, $15/1M output).
		$estimated_cost = ( $usage['prompt_tokens'] * 0.000003 ) + ( $usage['completion_tokens'] * 0.000015 );

		// Log completion.
		$this->audit->log(
			$current_agent_id,
			'chat_complete',
			'conversation',
			array(
				'session_id' => $session_id,
				'iterations' => $iterations,
				'tools_used' => array_column( $tool_results, 'tool' ),
				'prompt'     => substr( $message, 0, 500 ),
				'response'   => substr( $response, 0, 1000 ),
			),
			'',
			$total_tokens,
			$estimated_cost
		);

		$result = array(
			'response'    => $response,
			'agent_id'    => $current_agent_id,
			'agent_name'  => $this->current_agent->get_name(),
			'agent_icon'  => $this->current_agent->get_icon(),
			'session_id'  => $session_id,
			'tokens_used' => $total_tokens,
			'cost'        => round( $estimated_cost, 6 ),
			'tools_used'  => array_column( $tool_results, 'tool' ),
			'iterations'  => $iterations,
		);

		// Surface any pending proposal from tool results to the chat UI.
		foreach ( $tool_results as $tr ) {
			if ( ! empty( $tr['result']['pending_proposal'] ) ) {
				$result['pending_proposal'] = true;
				$result['proposal']         = array(
					'id'          => $tr['result']['proposal_id'],
					'description' => $tr['result']['description'],
					'diff'        => $tr['result']['diff'] ?? '',
					'tool'        => $tr['result']['tool'],
				);
				break; // One proposal per response.
			}
		}

		return $result;
	}

	/**
	 * Run an autonomous task through the LLM
	 *
	 * Used by scheduled tasks that define a `prompt` field. The agent runs
	 * through the full LLM loop (with tool calls) in autonomous mode,
	 * meaning no human user is present.
	 *
	 * Bypasses user access checks since this is system-initiated.
	 *
	 * @param \Agentic\Agent_Base $agent   Agent instance.
	 * @param string              $prompt  Task prompt describing what to do.
	 * @param string              $task_id Task identifier for logging.
	 * @return array|null Response data, or null if LLM is not configured.
	 */
	public function run_autonomous_task( \Agentic\Agent_Base $agent, string $prompt, string $task_id = '' ): ?array {
		if ( ! $this->llm->is_configured() ) {
			return null; // Caller will fall back to direct callback.
		}

		// Set agent directly — bypass capability check for system tasks.
		$this->current_agent = $agent;

		$agent_id = $agent->get_id();

		// Build autonomous system prompt.
		$autonomous_context = "\n\n[AUTONOMOUS MODE]\n"
			. "You are running autonomously as a scheduled task (task: {$task_id}). "
			. "There is no human user in this conversation.\n"
			. "Execute the requested task using your available tools, then provide "
			. "a concise summary of what you did and any findings.\n";

		$system_prompt = $agent->get_system_prompt() . $autonomous_context;

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$tools = $this->get_tools_for_agent();

		$session_id = 'autonomous_' . $task_id . '_' . gmdate( 'Ymd_His' );

		// Log autonomous start.
		$this->audit->log(
			$agent_id,
			'autonomous_chat_start',
			'scheduled_task',
			array(
				'task_id'    => $task_id,
				'session_id' => $session_id,
				'prompt'     => substr( $prompt, 0, 500 ),
			)
		);

		// Process with tool calls (same loop as chat()).
		$response     = null;
		$total_tokens = 0;
		$iterations   = 0;
		$tool_results = array();
		$usage        = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
		);

		while ( $iterations < self::MAX_ITERATIONS ) {
			++$iterations;

			$result = $this->llm->chat( $messages, $tools ? $tools : null );

			if ( is_wp_error( $result ) ) {
				$this->audit->log(
					$agent_id,
					'autonomous_chat_error',
					'scheduled_task',
					array(
						'task_id' => $task_id,
						'error'   => $result->get_error_message(),
					)
				);
				return null;
			}

			$usage         = $this->llm->get_usage( $result );
			$total_tokens += $usage['total_tokens'];

			$choice = $result['choices'][0] ?? null;
			if ( ! $choice ) {
				return null;
			}

			$assistant_message = $choice['message'];
			if ( ! isset( $assistant_message['content'] ) || null === $assistant_message['content'] ) {
				$assistant_message['content'] = '';
			}
			$messages[] = $assistant_message;

			if ( ! empty( $assistant_message['tool_calls'] ) ) {
				foreach ( $assistant_message['tool_calls'] as $tool_call ) {
					$function_name = $tool_call['function']['name'];
					$arguments     = json_decode( $tool_call['function']['arguments'], true ) ?? array();

					$tool_result = $this->execute_tool( $function_name, $arguments );

					$messages[] = array(
						'role'         => 'tool',
						'tool_call_id' => $tool_call['id'],
						'content'      => wp_json_encode( $tool_result ),
					);

					$tool_results[] = array(
						'tool'   => $function_name,
						'result' => $tool_result,
					);
				}
			} else {
				$response = $assistant_message['content'];
				break;
			}
		}

		if ( null === $response ) {
			$response = 'Reached maximum tool iterations for autonomous task.';
		}

		$estimated_cost = ( $usage['prompt_tokens'] * 0.000003 ) + ( $usage['completion_tokens'] * 0.000015 );

		// Log autonomous completion.
		$this->audit->log(
			$agent_id,
			'autonomous_chat_complete',
			'scheduled_task',
			array(
				'task_id'    => $task_id,
				'session_id' => $session_id,
				'iterations' => $iterations,
				'tools_used' => array_column( $tool_results, 'tool' ),
				'response'   => substr( $response, 0, 1000 ),
			),
			'',
			$total_tokens,
			$estimated_cost
		);

		return array(
			'response'    => $response,
			'agent_id'    => $agent_id,
			'task_id'     => $task_id,
			'mode'        => 'autonomous',
			'session_id'  => $session_id,
			'tokens_used' => $total_tokens,
			'cost'        => round( $estimated_cost, 6 ),
			'tools_used'  => array_column( $tool_results, 'tool' ),
			'iterations'  => $iterations,
		);
	}
}
