<?php
/**
 * REST API endpoints
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Agentic\Core;

/**
 * REST API handler for agent interactions
 */
class REST_API {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Chat endpoint
		register_rest_route(
			'agentic/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
				'args'                => array(
					'message'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'session_id' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'agent_id'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
					'history'    => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		// Get conversation history
		register_rest_route(
			'agentic/v1',
			'/history/(?P<session_id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Get agent status
		register_rest_route(
			'agentic/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get pending approvals (admin only)
		register_rest_route(
			'agentic/v1',
			'/approvals',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_approvals' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Handle approval action
		register_rest_route(
			'agentic/v1',
			'/approvals/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_approval' ),
				'permission_callback' => array( $this, 'check_admin' ),
				'args'                => array(
					'action' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'approve', 'reject' ),
					),
				),
			)
		);
	}

	/**
	 * Handle chat request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' ) ?: wp_generate_uuid4();
		$history    = $request->get_param( 'history' ) ?: array();
		$user_id    = get_current_user_id();

		// Security check FIRST - fast, in-memory scan
		$security_result = \Agentic\Chat_Security::scan( $message, $user_id );

		if ( ! $security_result['pass'] ) {
			$status_code = ( $security_result['code'] ?? '' ) === 'rate_limited' ? 429 : 403;

			return new \WP_REST_Response(
				array(
					'error'    => true,
					'response' => $security_result['reason'],
					'code'     => $security_result['code'] ?? 'security_block',
				),
				$status_code
			);
		}

		// Get agent ID for caching
		$agent_id = $request->get_param( 'agent_id' ) ?: 'default';

		// Check cache BEFORE calling LLM (saves tokens)
		if ( \Agentic\Response_Cache::should_cache( $message, $history ) ) {
			$cached = \Agentic\Response_Cache::get( $message, $agent_id, $user_id );
			if ( $cached !== null ) {
				// Add PII warning if applicable
				if ( ! empty( $security_result['pii_warning'] ) ) {
					$cached['pii_warning'] = $security_result['pii_warning'];
				}
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		$controller = new Agent_Controller();
		$response   = $controller->chat( $message, $history, $user_id, $session_id, $agent_id );

		// Cache the response for future identical queries
		if ( \Agentic\Response_Cache::should_cache( $message, $history ) ) {
			\Agentic\Response_Cache::set( $message, $agent_id, $response, $user_id );
		}

		// Add PII warning to response if detected (non-blocking)
		if ( ! empty( $security_result['pii_warning'] ) ) {
			$response['pii_warning'] = $security_result['pii_warning'];
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Get conversation history
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_history( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $request->get_param( 'session_id' );

		// History is stored client-side for now
		// Could be enhanced to use transients or database storage
		return new \WP_REST_Response(
			array(
				'session_id' => $session_id,
				'history'    => array(),
				'message'    => 'History is stored client-side.',
			),
			200
		);
	}

	/**
	 * Get agent status
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$openai = new OpenAI_Client();

		return new \WP_REST_Response(
			array(
				'version'      => AGENTIC_CORE_VERSION,
				'configured'   => $openai->is_configured(),
				'mode'         => get_option( 'agentic_agent_mode', 'supervised' ),
				'capabilities' => array(
					'chat'         => true,
					'read_files'   => true,
					'search_code'  => true,
					'update_docs'  => true,
					'code_changes' => 'approval_required',
				),
			),
			200
		);
	}

	/**
	 * Get pending approvals
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_approvals( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$approvals = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50",
			ARRAY_A
		);

		foreach ( $approvals as &$approval ) {
			$approval['params'] = json_decode( $approval['params'], true );
		}

		return new \WP_REST_Response( array( 'approvals' => $approvals ), 200 );
	}

	/**
	 * Handle approval action
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_approval( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$id     = (int) $request->get_param( 'id' );
		$action = $request->get_param( 'action' );

		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $approval ) {
			return new \WP_REST_Response( array( 'error' => 'Approval not found' ), 404 );
		}

		if ( $approval['status'] !== 'pending' ) {
			return new \WP_REST_Response( array( 'error' => 'Approval already processed' ), 400 );
		}

		$new_status = $action === 'approve' ? 'approved' : 'rejected';

		$wpdb->update(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'status'      => $new_status,
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		// If approved, execute the action
		if ( $action === 'approve' ) {
			$this->execute_approved_action( $approval );
		}

		$audit = new Audit_Log();
		$audit->log( 'human', "approval_{$new_status}", 'approval', array( 'request_id' => $id ) );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'status'  => $new_status,
			),
			200
		);
	}

	/**
	 * Execute an approved action
	 *
	 * SECURITY: Git commit operations disabled. File changes are written but not auto-committed.
	 * Administrators should commit changes manually via a secure terminal.
	 *
	 * @param array $approval Approval record.
	 * @return void
	 */
	private function execute_approved_action( array $approval ): void {
		$params = json_decode( $approval['params'], true );

		if ( $approval['action'] === 'code_change' && ! empty( $params['path'] ) ) {
			$repo_path      = realpath( get_option( 'agentic_repo_path', ABSPATH ) );
			$target_subpath = ltrim( str_replace( '..', '', $params['path'] ), '/\\' );

			if ( ! $repo_path || ! is_dir( $repo_path ) ) {
				return;
			}

			$full_path = realpath( $repo_path . '/' . $target_subpath );

			if ( ! $full_path || ! str_starts_with( $full_path, trailingslashit( $repo_path ) ) ) {
				return;
			}

			if ( ! empty( $params['content'] ) && is_writable( dirname( $full_path ) ) ) {
				file_put_contents( $full_path, $params['content'] );
				// Git commands intentionally removed for security.
				// Changes are written to disk but require manual commit via terminal.
			}
		}
	}

	/**
	 * Check if user is logged in
	 *
	 * @return bool
	 */
	public function check_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Check if user is admin
	 *
	 * @return bool
	 */
	public function check_admin(): bool {
		return current_user_can( 'manage_options' );
	}
}
