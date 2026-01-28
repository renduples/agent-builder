<?php
/**
 * Job Manager
 *
 * Handles async job queue for long-running agent tasks
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Job Manager class
 */
class Job_Manager {

	/**
	 * Table name
	 */
	private const TABLE_NAME = 'agentic_jobs';

	/**
	 * Job statuses
	 */
	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETED  = 'completed';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_CANCELLED  = 'cancelled';

	/**
	 * Initialize
	 */
	public static function init(): void {
		add_action( 'agentic_process_job', array( __CLASS__, 'process_job' ) );
		add_action( 'agentic_cleanup_jobs', array( __CLASS__, 'cleanup_old_jobs' ) );

		// Schedule hourly cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'agentic_cleanup_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'agentic_cleanup_jobs' );
		}
	}

	/**
	 * Get table name with prefix
	 *
	 * @return string
	 */
	private static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create jobs table
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id varchar(36) NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			agent_id varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			progress int(3) NOT NULL DEFAULT 0,
			message varchar(255) DEFAULT '',
			request_data longtext,
			response_data longtext,
			error_message text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY idx_user_created (user_id, created_at),
			KEY idx_status (status),
			KEY idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create a new job
	 *
	 * @param array $args Job arguments.
	 * @return string Job ID
	 */
	public static function create_job( array $args ): string {
		global $wpdb;

		$defaults = array(
			'user_id'      => get_current_user_id(),
			'agent_id'     => null,
			'request_data' => array(),
			'processor'    => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$job_id = wp_generate_uuid4();
		$now    = current_time( 'mysql' );

		// Store processor class in request_data
		if ( $args['processor'] ) {
			$args['request_data']['_processor'] = $args['processor'];
		}

		$wpdb->insert(
			self::get_table_name(),
			array(
				'id'           => $job_id,
				'user_id'      => $args['user_id'],
				'agent_id'     => $args['agent_id'],
				'status'       => self::STATUS_PENDING,
				'progress'     => 0,
				'message'      => '',
				'request_data' => wp_json_encode( $args['request_data'] ),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		// Schedule async processing
		wp_schedule_single_event( time(), 'agentic_process_job', array( $job_id ) );

		return $job_id;
	}

	/**
	 * Get job by ID
	 *
	 * @param string $job_id Job ID.
	 * @return object|null
	 */
	public static function get_job( string $job_id ): ?object {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $job_id ) );

		if ( ! $job ) {
			return null;
		}

		// Decode JSON fields
		$job->request_data = json_decode( $job->request_data, true );
		if ( $job->response_data ) {
			$job->response_data = json_decode( $job->response_data, true );
		}

		return $job;
	}

	/**
	 * Update job
	 *
	 * @param string $job_id Job ID.
	 * @param array  $data   Data to update.
	 * @return bool
	 */
	public static function update_job( string $job_id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		// Encode response_data if provided
		if ( isset( $data['response_data'] ) && is_array( $data['response_data'] ) ) {
			$data['response_data'] = wp_json_encode( $data['response_data'] );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			array( 'id' => $job_id ),
			null,
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Process a job
	 *
	 * @param string $job_id Job ID.
	 * @return void
	 */
	public static function process_job( string $job_id ): void {
		$job = self::get_job( $job_id );

		if ( ! $job ) {
			return;
		}

		// Check if already processing or completed
		if ( in_array( $job->status, array( self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_CANCELLED ), true ) ) {
			return;
		}

		// Update to processing
		self::update_job( $job_id, array( 'status' => self::STATUS_PROCESSING ) );

		try {
			// Get processor class from request data
			$processor_class = $job->request_data['_processor'] ?? null;

			if ( ! $processor_class || ! class_exists( $processor_class ) ) {
				throw new \Exception( 'Invalid or missing job processor' );
			}

			// Create processor instance
			$processor = new $processor_class();

			// Execute with progress callback
			$result = $processor->execute(
				$job->request_data,
				function ( $progress, $message ) use ( $job_id ) {
					self::update_job(
						$job_id,
						array(
							'progress' => $progress,
							'message'  => $message,
						)
					);
				}
			);

			// Mark as completed
			self::update_job(
				$job_id,
				array(
					'status'        => self::STATUS_COMPLETED,
					'progress'      => 100,
					'message'       => 'Completed',
					'response_data' => $result,
				)
			);

		} catch ( \Exception $e ) {
			// Mark as failed
			self::update_job(
				$job_id,
				array(
					'status'        => self::STATUS_FAILED,
					'error_message' => $e->getMessage(),
					'message'       => 'Failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Cancel a job
	 *
	 * @param string $job_id Job ID.
	 * @return bool
	 */
	public static function cancel_job( string $job_id ): bool {
		$job = self::get_job( $job_id );

		if ( ! $job || $job->status !== self::STATUS_PENDING ) {
			return false;
		}

		return self::update_job(
			$job_id,
			array(
				'status'  => self::STATUS_CANCELLED,
				'message' => 'Cancelled by user',
			)
		);
	}

	/**
	 * Get user's jobs
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Optional status filter.
	 * @param int    $limit   Limit.
	 * @return array
	 */
	public static function get_user_jobs( int $user_id, string $status = '', int $limit = 50 ): array {
		global $wpdb;

		$table = self::get_table_name();

		if ( $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d",
					$user_id,
					$status,
					$limit
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
					$user_id,
					$limit
				)
			);
		}

		// Decode JSON fields
		foreach ( $jobs as $job ) {
			$job->request_data = json_decode( $job->request_data, true );
			if ( $job->response_data ) {
				$job->response_data = json_decode( $job->response_data, true );
			}
		}

		return $jobs;
	}

	/**
	 * Clean up old completed/failed jobs
	 *
	 * @return int Number of deleted jobs
	 */
	public static function cleanup_old_jobs(): int {
		global $wpdb;

		$table = self::get_table_name();

		// Delete completed/failed jobs older than 24 hours
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			"DELETE FROM {$table} 
			WHERE status IN ('completed', 'failed', 'cancelled') 
			AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		return (int) $deleted;
	}

	/**
	 * Get job statistics
	 *
	 * @param int $user_id Optional user ID filter.
	 * @return array
	 */
	public static function get_stats( int $user_id = 0 ): array {
		global $wpdb;

		$table = self::get_table_name();

		$where = $user_id ? $wpdb->prepare( 'WHERE user_id = %d', $user_id ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
			FROM {$table} {$where}",
			ARRAY_A
		);

		return $stats ?: array(
			'total'      => 0,
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
		);
	}
}
