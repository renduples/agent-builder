<?php
/**
 * Jobs REST API
 *
 * REST API endpoints for job management
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Jobs API class
 */
class Jobs_API {

	/**
	 * Initialize
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 */
	public static function register_routes(): void {
		register_rest_route(
			'agentic/v1',
			'/jobs',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_job' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			'agentic/v1',
			'/jobs/(?P<id>[a-f0-9\-]+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'get_job' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ __CLASS__, 'cancel_job' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			'agentic/v1',
			'/jobs/user/(?P<user_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_user_jobs' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Check permission
	 *
	 * @return bool
	 */
	public static function check_permission(): bool {
		return is_user_logged_in();
	}

	/**
	 * Create a new job
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_job( \WP_REST_Request $request ) {
		$job_id = Job_Manager::create_job( $request->get_params() );

		return new \WP_REST_Response(
			[
				'job_id' => $job_id,
				'status' => Job_Manager::STATUS_PENDING,
			],
			202
		);
	}

	/**
	 * Get job status
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_job( \WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = Job_Manager::get_job( $job_id );

		if ( ! $job ) {
			return new \WP_Error(
				'job_not_found',
				'Job not found',
				[ 'status' => 404 ]
			);
		}

		// Check ownership
		if ( $job->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'forbidden',
				'You do not have permission to access this job',
				[ 'status' => 403 ]
			);
		}

		return new \WP_REST_Response(
			[
				'id'            => $job->id,
				'status'        => $job->status,
				'progress'      => (int) $job->progress,
				'message'       => $job->message,
				'response_data' => $job->response_data,
				'error_message' => $job->error_message,
				'created_at'    => $job->created_at,
				'updated_at'    => $job->updated_at,
			],
			200
		);
	}

	/**
	 * Cancel a job
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function cancel_job( \WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = Job_Manager::get_job( $job_id );

		if ( ! $job ) {
			return new \WP_Error(
				'job_not_found',
				'Job not found',
				[ 'status' => 404 ]
			);
		}

		// Check ownership
		if ( $job->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'forbidden',
				'You do not have permission to cancel this job',
				[ 'status' => 403 ]
			);
		}

		$cancelled = Job_Manager::cancel_job( $job_id );

		if ( ! $cancelled ) {
			return new \WP_Error(
				'cannot_cancel',
				'Job cannot be cancelled (may already be processing or completed)',
				[ 'status' => 400 ]
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => 'Job cancelled',
			],
			200
		);
	}

	/**
	 * Get user's jobs
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_user_jobs( \WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'user_id' );

		// Check permission - users can only see their own jobs unless admin
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'forbidden',
				'You do not have permission to view these jobs',
				[ 'status' => 403 ]
			);
		}

		$status = $request->get_param( 'status' ) ?? '';
		$limit  = (int) ( $request->get_param( 'limit' ) ?? 50 );

		$jobs = Job_Manager::get_user_jobs( $user_id, $status, $limit );

		// Sanitize output - remove sensitive request data
		$jobs = array_map(
			function( $job ) {
				return [
					'id'         => $job->id,
					'agent_id'   => $job->agent_id,
					'status'     => $job->status,
					'progress'   => (int) $job->progress,
					'message'    => $job->message,
					'created_at' => $job->created_at,
					'updated_at' => $job->updated_at,
				];
			},
			$jobs
		);

		return new \WP_REST_Response(
			[
				'jobs'  => $jobs,
				'total' => count( $jobs ),
			],
			200
		);
	}
}
