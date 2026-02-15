<?php
/**
 * REST API endpoints
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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		// Chat endpoint.
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
					'image'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		// Get conversation history.
		register_rest_route(
			'agentic/v1',
			'/history/(?P<session_id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Get agent status.
		register_rest_route(
			'agentic/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// Test API key.
		register_rest_route(
			'agentic/v1',
			'/test-api',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_api_key' ),
				'permission_callback' => array( $this, 'check_admin' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'api_key'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get pending approvals (admin only).
		register_rest_route(
			'agentic/v1',
			'/approvals',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_approvals' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Handle approval action.
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

		// Approve or reject a user-space proposal.
		register_rest_route(
			'agentic/v1',
			'/proposals/(?P<id>[a-f0-9-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_proposal' ),
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
		$session_id = $request->get_param( 'session_id' ) ? $request->get_param( 'session_id' ) : wp_generate_uuid4();
		$history    = $request->get_param( 'history' ) ? $request->get_param( 'history' ) : array();
		$user_id    = get_current_user_id();

		// Validate optional image attachment (base64 data URL, max 5 MB).
		$image_data = null;
		$raw_image  = $request->get_param( 'image' );
		if ( $raw_image ) {
			if ( preg_match( '/^data:(image\/(jpeg|png|gif|webp));base64,/', $raw_image, $matches ) ) {
				$base64_part = substr( $raw_image, strlen( $matches[0] ) );
				// Rough size check: base64 is ~4/3 of original, so 5 MB encoded ≈ 6.7 MB base64 chars.
				if ( strlen( $base64_part ) <= 7 * 1024 * 1024 ) {
					// Save as a temporary upload so we have a public URL (required by some LLM providers).
					$temp_url = $this->save_temp_image( $base64_part, $matches[1] );
					if ( $temp_url ) {
						$image_data = array(
							'url'       => $temp_url,
							'data_url'  => $raw_image,
							'mime_type' => $matches[1],
							'base64'    => $base64_part,
						);
					}
				}
			}
		}

		// Validate and sanitize history messages.
		foreach ( $history as &$msg ) {
			// Ensure all messages have a content field (required by some LLM providers).
			if ( ! isset( $msg['content'] ) || null === $msg['content'] ) {
				$msg['content'] = '';
			}
		}
		unset( $msg );

		// Security check FIRST - fast, in-memory scan.
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

		// Get agent ID for caching.
		$agent_id = $request->get_param( 'agent_id' ) ? $request->get_param( 'agent_id' ) : 'default';

		// Check cache BEFORE calling LLM (saves tokens).
		if ( \Agentic\Response_Cache::should_cache( $message, $history ) ) {
			$cached = \Agentic\Response_Cache::get( $message, $agent_id, $user_id );
			if ( null !== $cached ) {
				// Add PII warning if applicable.
				if ( ! empty( $security_result['pii_warning'] ) ) {
					$cached['pii_warning'] = $security_result['pii_warning'];
				}
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		// Process with potential tool calls.
		$response     = null;
		$total_tokens = 0;
		$iterations   = 0;
		$tool_results = array();
		$usage        = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
		);

		$controller = new Agent_Controller();
		$response   = $controller->chat( $message, $history, $user_id, $session_id, $agent_id, $image_data );

		// Log errors to audit log.
		if ( ! empty( $response['error'] ) ) {
			$audit = new Audit_Log();
			$audit->log(
				$agent_id ? $agent_id : 'unknown',
				'chat_error',
				'error',
				array(
					'error_message' => $response['response'] ?? 'Unknown error',
					'user_message'  => substr( $message, 0, 200 ),
					'session_id'    => $session_id,
				)
			);
		}

		// Cache the response for future identical queries.
		if ( \Agentic\Response_Cache::should_cache( $message, $history ) ) {
			\Agentic\Response_Cache::set( $message, $agent_id, $response, $user_id );
		}

		// Add PII warning to response if detected (non-blocking).
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

		// History is stored client-side for now.
		// Could be enhanced to use transients or database storage.
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
	 * @param \WP_REST_Request $_request Request object (unused - no parameters needed).
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $_request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$llm = new LLM_Client();

		return new \WP_REST_Response(
			array(
				'version'      => AGENTIC_PLUGIN_VERSION,
				'configured'   => $llm->is_configured(),
				'provider'     => $llm->get_provider(),
				'model'        => $llm->get_model(),
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
	 * @param \WP_REST_Request $_request Request object (unused - no parameters needed).
	 * @return \WP_REST_Response
	 */
	public function get_approvals( \WP_REST_Request $_request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $approval ) {
			return new \WP_REST_Response( array( 'error' => 'Approval not found' ), 404 );
		}

		if ( 'pending' !== $approval['status'] ) {
				return new \WP_REST_Response( array( 'error' => 'Approval already processed' ), 400 );
		}

		$new_status = 'approve' === $action ? 'approved' : 'rejected';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$wpdb->update(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'status'      => $new_status,
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

			// If approved, execute the action.
		if ( 'approve' === $action ) {
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
	 * Handle a user-space proposal (approve or reject).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_proposal( \WP_REST_Request $request ): \WP_REST_Response {
		$proposal_id = sanitize_text_field( $request->get_param( 'id' ) );
		$action      = $request->get_param( 'action' );

		if ( 'approve' === $action ) {
			$result = Agent_Proposals::approve( $proposal_id );
		} else {
			$result = Agent_Proposals::reject( $proposal_id );
		}

		if ( ! empty( $result['error'] ) ) {
			return new \WP_REST_Response( $result, 400 );
		}

		return new \WP_REST_Response( $result, 200 );
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

		if ( 'code_change' === $approval['action'] && ! empty( $params['path'] ) ) {
			$repo_path      = Agent_Tools::get_allowed_repo_base();
			$target_subpath = ltrim( str_replace( '..', '', $params['path'] ), '/\\' );

			if ( ! Agent_Tools::is_allowed_subpath( $target_subpath ) ) {
				return;
			}

			$full_path = realpath( $repo_path . '/' . $target_subpath );

			if ( ! $full_path || ! str_starts_with( $full_path, trailingslashit( realpath( $repo_path ) ) ) ) {
				return;
			}

			if ( ! empty( $params['content'] ) ) {
				global $wp_filesystem;
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				WP_Filesystem();

				$dir = dirname( $full_path );
				if ( $wp_filesystem->is_writable( $dir ) ) {
					$wp_filesystem->put_contents( $full_path, $params['content'], FS_CHMOD_FILE );
					// Git commands intentionally removed for security.
					// Changes are written to disk but require manual commit via terminal.
				}
			}
		}
	}

	/**
	 * Test an API key with the LLM provider
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function test_api_key( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_text_field( $request->get_param( 'provider' ) );
		$api_key  = sanitize_text_field( $request->get_param( 'api_key' ) );

		if ( empty( $provider ) || empty( $api_key ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Provider and API key are required.',
				),
				400
			);
		}

		// Create a temporary LLM_Client with the test values.
		$llm = new LLM_Client();

		// Test by making a simple API call.
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello, please respond with OK.',
			),
		);

		// Temporarily override the provider and API key for testing.
		try {
			$response = wp_remote_post(
				$llm->get_endpoint_for_provider( $provider ),
				array(
					'timeout' => 15,
					'headers' => $llm->get_headers_for_provider( $provider, $api_key ),
					'body'    => wp_json_encode( $llm->format_request_for_provider( $provider, $messages ) ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Connection failed: ' . $response->get_error_message(),
					),
					400
				);
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body   = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 === $status || 201 === $status ) {
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => 'API key is valid and working!',
					),
					200
				);
			} else {
				$error_msg = $body['error']['message'] ?? $body['error'] ?? 'Unknown error';
				if ( is_array( $error_msg ) ) {
					$error_msg = wp_json_encode( $error_msg );
				}
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => 'API Error: ' . $error_msg,
						'status'  => $status,
					),
					$status
				);
			}
		} catch ( Exception $e ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Test failed: ' . $e->getMessage(),
				),
				400
			);
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

	/**
	 * Save a base64-encoded image as a temporary file in uploads and return its public URL.
	 *
	 * The file is placed in /uploads/agentic-tmp/ and prefixed with a unique ID.
	 * Temporary files older than 1 hour are cleaned up on each call.
	 *
	 * @param string $base64    Base64-encoded image data (no header/prefix).
	 * @param string $mime_type MIME type (e.g., 'image/png').
	 * @return string|false Public URL on success, false on failure.
	 */
	private function save_temp_image( string $base64, string $mime_type ): string|false {
		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		$ext     = $ext_map[ $mime_type ] ?? 'png';

		$upload_dir = wp_upload_dir();
		$tmp_dir    = $upload_dir['basedir'] . '/agentic-tmp';
		$tmp_url    = $upload_dir['baseurl'] . '/agentic-tmp';

		// Ensure directory exists.
		if ( ! is_dir( $tmp_dir ) ) {
			wp_mkdir_p( $tmp_dir );
			// Add an index.php to prevent directory listing.
			file_put_contents( $tmp_dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Cleanup files older than 1 hour.
		$this->cleanup_temp_images( $tmp_dir );

		$filename = 'agentic-' . wp_generate_uuid4() . '.' . $ext;
		$filepath = $tmp_dir . '/' . $filename;

		$decoded = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return false;
		}

		// Write using WP_Filesystem if available, fallback to file_put_contents.
		global $wp_filesystem;
		if ( function_exists( 'WP_Filesystem' ) ) {
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents( $filepath, $decoded, FS_CHMOD_FILE );
		} else {
			file_put_contents( $filepath, $decoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		if ( ! file_exists( $filepath ) ) {
			return false;
		}

		return $tmp_url . '/' . $filename;
	}

	/**
	 * Remove temp images older than 1 hour.
	 *
	 * @param string $dir Path to agentic-tmp directory.
	 * @return void
	 */
	private function cleanup_temp_images( string $dir ): void {
		$files = glob( $dir . '/agentic-*.{jpg,png,gif,webp}', GLOB_BRACE );
		if ( ! $files ) {
			return;
		}
		$cutoff = time() - HOUR_IN_SECONDS;
		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}
}
