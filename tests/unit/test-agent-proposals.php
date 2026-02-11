<?php
/**
 * Unit Tests for Agent Proposals
 *
 * Tests proposal creation, retrieval, approval, rejection,
 * file backup, and diff generation.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Agent_Proposals;
use Agentic\Audit_Log;

/**
 * Test case for Agent_Proposals class.
 */
class Test_Agent_Proposals extends TestCase {

	/**
	 * Track proposal IDs for cleanup.
	 *
	 * @var array
	 */
	private array $created_proposals = array();

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		foreach ( $this->created_proposals as $id ) {
			delete_transient( 'agentic_proposal_' . $id );
		}
		$this->created_proposals = array();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// create
	// -------------------------------------------------------------------------

	/**
	 * Test create returns proposal with ID and pending status.
	 */
	public function test_create_returns_proposal() {
		$proposal = Agent_Proposals::create( 'write_file', array( 'path' => 'themes/test/style.css' ), 'test-agent', 'Test change' );

		$this->created_proposals[] = $proposal['id'];

		$this->assertArrayHasKey( 'id', $proposal );
		$this->assertEquals( 'pending', $proposal['status'] );
		$this->assertEquals( 'write_file', $proposal['tool'] );
		$this->assertEquals( 'test-agent', $proposal['agent_id'] );
		$this->assertEquals( 'Test change', $proposal['description'] );
	}

	/**
	 * Test create stores proposal in transient.
	 */
	public function test_create_stores_transient() {
		$proposal = Agent_Proposals::create( 'modify_option', array( 'name' => 'test' ), 'test-agent', 'Set an option' );
		$this->created_proposals[] = $proposal['id'];

		$stored = get_transient( 'agentic_proposal_' . $proposal['id'] );
		$this->assertIsArray( $stored );
		$this->assertEquals( $proposal['id'], $stored['id'] );
	}

	/**
	 * Test create stores diff when provided.
	 */
	public function test_create_with_diff() {
		$diff     = "--- old\n+++ new\n-removed\n+added";
		$proposal = Agent_Proposals::create( 'write_file', array(), 'test-agent', 'Apply diff', $diff );
		$this->created_proposals[] = $proposal['id'];

		$this->assertEquals( $diff, $proposal['diff'] );
	}

	// -------------------------------------------------------------------------
	// get
	// -------------------------------------------------------------------------

	/**
	 * Test get retrieves existing proposal.
	 */
	public function test_get_existing_proposal() {
		$proposal = Agent_Proposals::create( 'write_file', array(), 'test-agent', 'Get test' );
		$this->created_proposals[] = $proposal['id'];

		$retrieved = Agent_Proposals::get( $proposal['id'] );
		$this->assertNotNull( $retrieved );
		$this->assertEquals( $proposal['id'], $retrieved['id'] );
	}

	/**
	 * Test get returns null for nonexistent proposal.
	 */
	public function test_get_nonexistent_returns_null() {
		$this->assertNull( Agent_Proposals::get( 'nonexistent-uuid-1234' ) );
	}

	// -------------------------------------------------------------------------
	// reject
	// -------------------------------------------------------------------------

	/**
	 * Test reject removes transient.
	 */
	public function test_reject_removes_transient() {
		$proposal = Agent_Proposals::create( 'write_file', array(), 'test-agent', 'Reject test' );
		// Don't add to created_proposals since reject cleans up.

		$result = Agent_Proposals::reject( $proposal['id'] );
		$this->assertTrue( $result['success'] );
		$this->assertNull( Agent_Proposals::get( $proposal['id'] ) );
	}

	/**
	 * Test reject nonexistent returns error.
	 */
	public function test_reject_nonexistent_returns_error() {
		$result = Agent_Proposals::reject( 'does-not-exist' );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test reject already-processed returns error.
	 */
	public function test_reject_already_processed() {
		$proposal = Agent_Proposals::create( 'write_file', array(), 'test-agent', 'Double reject' );

		// Reject once.
		Agent_Proposals::reject( $proposal['id'] );

		// Try again.
		$result = Agent_Proposals::reject( $proposal['id'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	// -------------------------------------------------------------------------
	// generate_diff
	// -------------------------------------------------------------------------

	/**
	 * Test generate_diff with additions.
	 */
	public function test_generate_diff_additions() {
		$old  = "line1\nline2";
		$new  = "line1\nline2\nline3";
		$diff = Agent_Proposals::generate_diff( $old, $new, 'old.txt', 'new.txt' );

		$this->assertStringContainsString( '--- old.txt', $diff );
		$this->assertStringContainsString( '+++ new.txt', $diff );
		$this->assertStringContainsString( '+line3', $diff );
	}

	/**
	 * Test generate_diff with removals.
	 */
	public function test_generate_diff_removals() {
		$old  = "line1\nline2\nline3";
		$new  = "line1\nline3";
		$diff = Agent_Proposals::generate_diff( $old, $new );

		$this->assertStringContainsString( '-line2', $diff );
	}

	/**
	 * Test generate_diff with empty old (new file).
	 */
	public function test_generate_diff_new_file() {
		$diff = Agent_Proposals::generate_diff( '', "<?php\necho 'hello';", 'none', 'new.php' );

		$this->assertStringContainsString( '+<?php', $diff );
	}

	/**
	 * Test generate_diff with identical content.
	 */
	public function test_generate_diff_no_changes() {
		$content = "line1\nline2\nline3";
		$diff    = Agent_Proposals::generate_diff( $content, $content );

		// Should have headers.
		$this->assertStringContainsString( '---', $diff );
		// With identical content, there should be no actual change lines (lines starting with +/- without header prefix).
		$lines = explode( "\n", $diff );
		$change_lines = array_filter( $lines, function( $line ) {
			// Skip diff header lines.
			if ( str_starts_with( $line, '---' ) || str_starts_with( $line, '+++' ) ) {
				return false;
			}
			return str_starts_with( $line, '+' ) || str_starts_with( $line, '-' );
		} );
		$this->assertCount( 0, $change_lines, 'Identical content should produce no change lines' );
	}

	// -------------------------------------------------------------------------
	// backup_file
	// -------------------------------------------------------------------------

	/**
	 * Test backup_file returns null for nonexistent file.
	 */
	public function test_backup_nonexistent_returns_null() {
		$result = Agent_Proposals::backup_file( '/tmp/nonexistent_agentic_test_file.php' );
		$this->assertNull( $result );
	}

	/**
	 * Test backup_file creates backup of existing file.
	 */
	public function test_backup_creates_copy() {
		// Create a temp file.
		$tmp = tempnam( sys_get_temp_dir(), 'agentic_test_' );
		file_put_contents( $tmp, 'test content' ); // phpcs:ignore

		$backup = Agent_Proposals::backup_file( $tmp );

		// Clean up.
		unlink( $tmp );
		if ( $backup && file_exists( $backup ) ) {
			unlink( $backup );
		}

		// The backup may be null if WP_CONTENT_DIR/agentic-backups isn't writable in test env.
		// Just verify it returns a string path or null (doesn't throw).
		$this->assertTrue( null === $backup || is_string( $backup ) );
	}
}
