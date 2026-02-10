<?php
/**
 * Tests for Job_Manager class
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Job_Manager;

/**
 * Test case for Job_Manager.
 *
 * Note: Job_Manager uses all static methods.
 * create_job() accepts array with keys: user_id, agent_id, request_data, processor.
 * Returns a UUID string.
 */
class Test_Job_Manager extends TestCase {

	/**
	 * Ensure jobs table exists before tests.
	 */
	public function setUp(): void {
		parent::setUp();
		Job_Manager::create_table();
	}

	/**
	 * Test job creation returns UUID string.
	 */
	public function test_create_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'      => 1,
				'agent_id'     => 'test-agent',
				'request_data' => array( 'message' => 'test' ),
			)
		);

		$this->assertIsString( $job_id );
		$this->assertNotEmpty( $job_id );
		// UUID format: 8-4-4-4-12 hex characters.
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
			$job_id
		);
	}

	/**
	 * Test job retrieval.
	 */
	public function test_get_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'      => 1,
				'agent_id'     => 'test-agent',
				'request_data' => array( 'message' => 'test data' ),
			)
		);

		$job = Job_Manager::get_job( $job_id );

		$this->assertIsObject( $job );
		$this->assertEquals( $job_id, $job->id );
		$this->assertEquals( 'test-agent', $job->agent_id );
		$this->assertEquals( Job_Manager::STATUS_PENDING, $job->status );
	}

	/**
	 * Test get_job returns null for nonexistent job.
	 */
	public function test_get_nonexistent_job() {
		$job = Job_Manager::get_job( 'nonexistent-uuid' );
		$this->assertNull( $job );
	}

	/**
	 * Test job request_data is deserialized.
	 */
	public function test_job_request_data_deserialized() {
		$data   = array( 'prompt' => 'Write a poem', 'temperature' => 0.7 );
		$job_id = Job_Manager::create_job(
			array(
				'user_id'      => 1,
				'agent_id'     => 'test-agent',
				'request_data' => $data,
			)
		);

		$job = Job_Manager::get_job( $job_id );

		$this->assertIsArray( $job->request_data );
		$this->assertEquals( 'Write a poem', $job->request_data['prompt'] );
		$this->assertEquals( 0.7, $job->request_data['temperature'] );
	}

	/**
	 * Test update_job changes status.
	 */
	public function test_update_job_status() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		$result = Job_Manager::update_job( $job_id, array( 'status' => Job_Manager::STATUS_PROCESSING ) );
		$this->assertTrue( $result );

		// Clear cache by re-fetching.
		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( Job_Manager::STATUS_PROCESSING, $job->status );
	}

	/**
	 * Test update_job with progress and message.
	 */
	public function test_update_job_progress() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		Job_Manager::update_job(
			$job_id,
			array(
				'progress' => 50,
				'message'  => 'Half way done',
			)
		);

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( 50, (int) $job->progress );
		$this->assertEquals( 'Half way done', $job->message );
	}

	/**
	 * Test job completion.
	 */
	public function test_complete_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		$result_data = array( 'content' => 'Generated text' );
		Job_Manager::update_job(
			$job_id,
			array(
				'status'        => Job_Manager::STATUS_COMPLETED,
				'progress'      => 100,
				'response_data' => $result_data,
			)
		);

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( Job_Manager::STATUS_COMPLETED, $job->status );
		$this->assertEquals( 100, (int) $job->progress );
	}

	/**
	 * Test job failure.
	 */
	public function test_fail_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		Job_Manager::update_job(
			$job_id,
			array(
				'status'        => Job_Manager::STATUS_FAILED,
				'error_message' => 'API timeout',
				'message'       => 'Failed: API timeout',
			)
		);

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( Job_Manager::STATUS_FAILED, $job->status );
		$this->assertStringContainsString( 'API timeout', $job->message );
	}

	/**
	 * Test cancel_job only works on pending jobs.
	 */
	public function test_cancel_pending_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		$result = Job_Manager::cancel_job( $job_id );
		$this->assertTrue( $result );

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( Job_Manager::STATUS_CANCELLED, $job->status );
	}

	/**
	 * Test cancel_job fails for processing job.
	 */
	public function test_cannot_cancel_processing_job() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		Job_Manager::update_job( $job_id, array( 'status' => Job_Manager::STATUS_PROCESSING ) );
		wp_cache_flush();

		$result = Job_Manager::cancel_job( $job_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test cancel_job fails for nonexistent job.
	 */
	public function test_cannot_cancel_nonexistent_job() {
		$result = Job_Manager::cancel_job( 'nonexistent-uuid' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_user_jobs returns jobs for specific user.
	 */
	public function test_get_user_jobs() {
		$user_id = $this->factory->user->create();

		Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'agent-1' ) );
		Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'agent-2' ) );
		Job_Manager::create_job( array( 'user_id' => 999, 'agent_id' => 'agent-3' ) );

		wp_cache_flush();
		$jobs = Job_Manager::get_user_jobs( $user_id );

		$this->assertCount( 2, $jobs );
		foreach ( $jobs as $job ) {
			$this->assertEquals( $user_id, (int) $job->user_id );
		}
	}

	/**
	 * Test get_user_jobs with status filter.
	 */
	public function test_get_user_jobs_with_status_filter() {
		$user_id = $this->factory->user->create();

		$job1 = Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'agent-1' ) );
		$job2 = Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'agent-2' ) );

		Job_Manager::update_job( $job1, array( 'status' => Job_Manager::STATUS_COMPLETED ) );

		wp_cache_flush();
		$pending_jobs = Job_Manager::get_user_jobs( $user_id, Job_Manager::STATUS_PENDING );

		$this->assertCount( 1, $pending_jobs );
		$this->assertEquals( $job2, $pending_jobs[0]->id );
	}

	/**
	 * Test get_stats returns correct counts.
	 */
	public function test_get_stats() {
		$user_id = $this->factory->user->create();

		$job1 = Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'test' ) );
		$job2 = Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'test' ) );
		$job3 = Job_Manager::create_job( array( 'user_id' => $user_id, 'agent_id' => 'test' ) );

		Job_Manager::update_job( $job1, array( 'status' => Job_Manager::STATUS_COMPLETED ) );
		Job_Manager::update_job( $job2, array( 'status' => Job_Manager::STATUS_FAILED ) );

		wp_cache_flush();
		$stats = Job_Manager::get_stats( $user_id );

		$this->assertEquals( 3, (int) $stats['total'] );
		$this->assertEquals( 1, (int) $stats['completed'] );
		$this->assertEquals( 1, (int) $stats['failed'] );
		$this->assertEquals( 1, (int) $stats['pending'] );
	}

	/**
	 * Test get_stats returns zeros when no jobs.
	 */
	public function test_get_stats_empty() {
		wp_cache_flush();
		$stats = Job_Manager::get_stats( 999999 );

		$this->assertEquals( 0, (int) $stats['total'] );
	}

	/**
	 * Test cleanup_old_jobs removes old completed jobs.
	 */
	public function test_cleanup_old_jobs() {
		global $wpdb;

		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		// Mark as completed.
		Job_Manager::update_job( $job_id, array( 'status' => Job_Manager::STATUS_COMPLETED ) );

		// Backdate the updated_at to 25 hours ago.
		$table = $wpdb->prefix . 'agentic_jobs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'updated_at' => date( 'Y-m-d H:i:s', strtotime( '-49 hours' ) ) ),
			array( 'id' => $job_id )
		);

		wp_cache_flush();
		$deleted = Job_Manager::cleanup_old_jobs();
		$this->assertGreaterThan( 0, $deleted );

		$job = Job_Manager::get_job( $job_id );
		$this->assertNull( $job );
	}

	/**
	 * Test cleanup does not remove recent jobs.
	 */
	public function test_cleanup_preserves_recent_jobs() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'  => 1,
				'agent_id' => 'test-agent',
			)
		);

		Job_Manager::update_job( $job_id, array( 'status' => Job_Manager::STATUS_COMPLETED ) );

		wp_cache_flush();
		Job_Manager::cleanup_old_jobs();

		$job = Job_Manager::get_job( $job_id );
		$this->assertNotNull( $job, 'Recent completed job should not be cleaned up' );
	}

	/**
	 * Test job status constants are defined.
	 */
	public function test_status_constants() {
		$this->assertEquals( 'pending', Job_Manager::STATUS_PENDING );
		$this->assertEquals( 'processing', Job_Manager::STATUS_PROCESSING );
		$this->assertEquals( 'completed', Job_Manager::STATUS_COMPLETED );
		$this->assertEquals( 'failed', Job_Manager::STATUS_FAILED );
		$this->assertEquals( 'cancelled', Job_Manager::STATUS_CANCELLED );
	}

	/**
	 * Test processor is stored in request_data.
	 */
	public function test_processor_stored_in_request_data() {
		$job_id = Job_Manager::create_job(
			array(
				'user_id'      => 1,
				'agent_id'     => 'test-agent',
				'request_data' => array( 'prompt' => 'test' ),
				'processor'    => 'Agentic\\TestProcessor',
			)
		);

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );

		$this->assertArrayHasKey( '_processor', $job->request_data );
		$this->assertEquals( 'Agentic\\TestProcessor', $job->request_data['_processor'] );
	}

	/**
	 * Test default user_id from current user.
	 */
	public function test_default_user_id() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$job_id = Job_Manager::create_job(
			array( 'agent_id' => 'test-agent' )
		);

		wp_cache_flush();
		$job = Job_Manager::get_job( $job_id );
		$this->assertEquals( $user_id, (int) $job->user_id );
	}
}
