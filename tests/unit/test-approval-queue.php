<?php
/**
 * Unit Tests for Approval_Queue
 *
 * Tests enqueuing, approving, rejecting, and expiration of approval items.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Approval_Queue;

/**
 * Test case for Approval_Queue class.
 */
class Test_Approval_Queue extends TestCase {

	/**
	 * Approval_Queue instance.
	 *
	 * @var Approval_Queue
	 */
	private Approval_Queue $queue;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->queue = new Approval_Queue();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}agentic_approval_queue" );
	}

	// -------------------------------------------------------------------------
	// Adding items
	// -------------------------------------------------------------------------

	/**
	 * Test add returns an integer ID.
	 */
	public function test_add_returns_id() {
		$id = $this->queue->add(
			'developer-agent',
			'code_change',
			array( 'path' => 'style.css', 'content' => 'body { color: red; }' ),
			'User requested color change'
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test add stores all fields correctly.
	 */
	public function test_add_stores_all_fields() {
		$params = array( 'path' => 'index.php', 'content' => '<?php echo "hi";' );
		$id     = $this->queue->add(
			'code-generator',
			'create_file',
			$params,
			'Generated new file',
			14
		);

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'code-generator', $row['agent_id'] );
		$this->assertEquals( 'create_file', $row['action'] );
		$this->assertEquals( 'pending', $row['status'] );
		$this->assertEquals( 'Generated new file', $row['reasoning'] );
		$this->assertNotEmpty( $row['created_at'] );
		$this->assertNotEmpty( $row['expires_at'] );

		$stored_params = json_decode( $row['params'], true );
		$this->assertEquals( 'index.php', $stored_params['path'] );
	}

	/**
	 * Test add with default expiration (7 days).
	 */
	public function test_add_default_expiration() {
		$id = $this->queue->add( 'test-agent', 'action', array( 'key' => 'val' ) );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		$expires = strtotime( $row['expires_at'] );
		$created = strtotime( $row['created_at'] );

		// expires_at should be approximately 7 days after created_at.
		$diff_days = ( $expires - $created ) / 86400;
		$this->assertGreaterThanOrEqual( 6, $diff_days );
		$this->assertLessThanOrEqual( 8, $diff_days );
	}

	// -------------------------------------------------------------------------
	// Pending items
	// -------------------------------------------------------------------------

	/**
	 * Test get_pending returns pending items.
	 */
	public function test_get_pending() {
		$this->queue->add( 'agent-a', 'action1', array( 'data' => 1 ) );
		$this->queue->add( 'agent-b', 'action2', array( 'data' => 2 ) );

		$pending = $this->queue->get_pending();
		$this->assertCount( 2, $pending );

		// Params should be decoded.
		$this->assertIsArray( $pending[0]['params'] );
	}

	/**
	 * Test get_pending excludes approved/rejected items.
	 */
	public function test_get_pending_excludes_processed() {
		$id1 = $this->queue->add( 'agent-a', 'action1', array( 'data' => 1 ) );
		$id2 = $this->queue->add( 'agent-b', 'action2', array( 'data' => 2 ) );
		$this->queue->add( 'agent-c', 'action3', array( 'data' => 3 ) );

		// Set user so approve/reject can store approved_by.
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->queue->approve( $id1 );
		$this->queue->reject( $id2 );

		$pending = $this->queue->get_pending();
		$this->assertCount( 1, $pending );
		$this->assertEquals( 'agent-c', $pending[0]['agent_id'] );
	}

	/**
	 * Test get_pending_count.
	 */
	public function test_get_pending_count() {
		$this->assertEquals( 0, $this->queue->get_pending_count() );

		$this->queue->add( 'agent-a', 'action1', array() );
		$this->queue->add( 'agent-b', 'action2', array() );

		$this->assertEquals( 2, $this->queue->get_pending_count() );
	}

	/**
	 * Test get_pending returns newest first (DESC order).
	 */
	public function test_get_pending_order() {
		global $wpdb;

		// Insert with explicit timestamps to guarantee ordering.
		$wpdb->insert(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'agent_id'   => 'agent-first',
				'action'     => 'action1',
				'params'     => '{}',
				'status'     => 'pending',
				'created_at' => '2025-01-01 00:00:00',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'agentic_approval_queue',
			array(
				'agent_id'   => 'agent-second',
				'action'     => 'action2',
				'params'     => '{}',
				'status'     => 'pending',
				'created_at' => '2025-01-02 00:00:00',
			)
		);

		$pending = $this->queue->get_pending();
		$this->assertEquals( 'agent-second', $pending[0]['agent_id'] );
	}

	// -------------------------------------------------------------------------
	// Approval
	// -------------------------------------------------------------------------

	/**
	 * Test approve updates status.
	 */
	public function test_approve() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$id     = $this->queue->add( 'agent-a', 'action1', array( 'data' => 'test' ) );
		$result = $this->queue->approve( $id );

		$this->assertTrue( $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'approved', $row['status'] );
		$this->assertEquals( $user_id, (int) $row['approved_by'] );
		$this->assertNotEmpty( $row['approved_at'] );
	}

	/**
	 * Test approve reduces pending count.
	 */
	public function test_approve_reduces_pending() {
		$id = $this->queue->add( 'agent-a', 'action1', array() );
		$this->assertEquals( 1, $this->queue->get_pending_count() );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->queue->approve( $id );

		$this->assertEquals( 0, $this->queue->get_pending_count() );
	}

	// -------------------------------------------------------------------------
	// Rejection
	// -------------------------------------------------------------------------

	/**
	 * Test reject updates status.
	 */
	public function test_reject() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$id     = $this->queue->add( 'agent-a', 'action1', array( 'data' => 'test' ) );
		$result = $this->queue->reject( $id );

		$this->assertTrue( $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentic_approval_queue WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertEquals( 'rejected', $row['status'] );
		$this->assertEquals( $user_id, (int) $row['approved_by'] );
		$this->assertNotEmpty( $row['approved_at'] );
	}

	// -------------------------------------------------------------------------
	// Expiration cleanup
	// -------------------------------------------------------------------------

	/**
	 * Test cleanup_expired removes expired pending items.
	 */
	public function test_cleanup_expired() {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_approval_queue';

		// Add an item with past expiration.
		$id = $this->queue->add( 'agent-a', 'action1', array(), '', 1 );
		$wpdb->update(
			$table,
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ) ),
			array( 'id' => $id )
		);

		// Add a non-expired item.
		$this->queue->add( 'agent-b', 'action2', array() );

		$deleted = $this->queue->cleanup_expired();
		$this->assertGreaterThanOrEqual( 1, $deleted );

		$pending = $this->queue->get_pending();
		$this->assertCount( 1, $pending );
		$this->assertEquals( 'agent-b', $pending[0]['agent_id'] );
	}

	/**
	 * Test cleanup_expired does not remove non-expired items.
	 */
	public function test_cleanup_expired_preserves_valid() {
		$this->queue->add( 'agent-a', 'action1', array() );

		$deleted = $this->queue->cleanup_expired();
		$this->assertEquals( 0, $deleted );
		$this->assertEquals( 1, $this->queue->get_pending_count() );
	}

	/**
	 * Test cleanup_expired does not remove approved/rejected items.
	 */
	public function test_cleanup_expired_ignores_processed() {
		global $wpdb;
		$table = $wpdb->prefix . 'agentic_approval_queue';

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$id = $this->queue->add( 'agent-a', 'action1', array(), '', 1 );
		$this->queue->approve( $id );

		// Backdate the expires_at to the past.
		$wpdb->update(
			$table,
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ) ),
			array( 'id' => $id )
		);

		$deleted = $this->queue->cleanup_expired();
		$this->assertEquals( 0, $deleted );
	}

	// -------------------------------------------------------------------------
	// Admin notice
	// -------------------------------------------------------------------------

	/**
	 * Test pending_approval_notice outputs nothing when no pending items.
	 */
	public function test_no_notice_when_empty() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		ob_start();
		$this->queue->pending_approval_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test pending_approval_notice outputs notice when pending items exist.
	 */
	public function test_notice_when_pending() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->queue->add( 'agent-a', 'action1', array() );

		ob_start();
		$this->queue->pending_approval_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '1 pending approval', $output );
		$this->assertStringContainsString( 'Review now', $output );
	}

	/**
	 * Test pending_approval_notice requires manage_options capability.
	 */
	public function test_notice_requires_admin() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->queue->add( 'agent-a', 'action1', array() );

		ob_start();
		$this->queue->pending_approval_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}
}
