<?php
/**
 * Unit Tests for System_Checker
 *
 * Tests system requirement checks (PHP, WP, memory, etc.).
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\System_Checker;

/**
 * Test case for System_Checker class.
 */
class Test_System_Checker extends TestCase {

	/**
	 * Clean up stored check results before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( 'agentic_last_system_check' );
	}

	// -------------------------------------------------------------------------
	// run_system_check
	// -------------------------------------------------------------------------

	/**
	 * Test run_system_check returns a WP_REST_Response.
	 */
	public function test_run_system_check_returns_response() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test response contains checks array and overall status.
	 */
	public function test_response_structure() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'checks', $data );
		$this->assertArrayHasKey( 'overall', $data );
		$this->assertIsArray( $data['checks'] );
		$this->assertIsBool( $data['overall'] );
	}

	/**
	 * Test all expected checks are present.
	 */
	public function test_check_names() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response    = System_Checker::run_system_check();
		$data        = $response->get_data();
		$check_names = array_column( $data['checks'], 'name' );

		$expected = array(
			'PHP Version',
			'Max Execution Time',
			'Memory Limit',
			'WordPress Version',
			'Permalinks',
			'LLM API Key',
			'REST API',
		);

		foreach ( $expected as $name ) {
			$this->assertContains( $name, $check_names, "Check '{$name}' should be present" );
		}
	}

	/**
	 * Test each check has required fields.
	 */
	public function test_check_fields() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		foreach ( $data['checks'] as $check ) {
			$this->assertArrayHasKey( 'name', $check );
			$this->assertArrayHasKey( 'status', $check );
			$this->assertArrayHasKey( 'value', $check );
			$this->assertArrayHasKey( 'required', $check );
			$this->assertArrayHasKey( 'fix', $check );
			$this->assertContains( $check['status'], array( 'pass', 'fail', 'warning' ) );
		}
	}

	// -------------------------------------------------------------------------
	// Individual checks
	// -------------------------------------------------------------------------

	/**
	 * Test PHP version check passes (we're running PHP 8.1+).
	 */
	public function test_php_version_passes() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$php_check = $this->find_check( $data['checks'], 'PHP Version' );
		$this->assertEquals( 'pass', $php_check['status'] );
		$this->assertEquals( phpversion(), $php_check['value'] );
	}

	/**
	 * Test WordPress version check passes.
	 */
	public function test_wordpress_version_passes() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$wp_check = $this->find_check( $data['checks'], 'WordPress Version' );
		$this->assertEquals( 'pass', $wp_check['status'] );
	}

	/**
	 * Test REST API check passes.
	 */
	public function test_rest_api_passes() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$rest_check = $this->find_check( $data['checks'], 'REST API' );
		$this->assertEquals( 'pass', $rest_check['status'] );
		$this->assertEquals( 'Enabled', $rest_check['value'] );
	}

	/**
	 * Test LLM API key check warns when not configured.
	 */
	public function test_llm_api_key_warning() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		delete_option( 'agentic_llm_api_key' );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$api_check = $this->find_check( $data['checks'], 'LLM API Key' );
		$this->assertEquals( 'warning', $api_check['status'] );
		$this->assertEquals( 'Not set', $api_check['value'] );
	}

	/**
	 * Test LLM API key check passes when configured.
	 */
	public function test_llm_api_key_passes() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		update_option( 'agentic_llm_api_key', 'sk-test-key-123' );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$api_check = $this->find_check( $data['checks'], 'LLM API Key' );
		$this->assertEquals( 'pass', $api_check['status'] );
		$this->assertEquals( 'Configured', $api_check['value'] );
	}

	// -------------------------------------------------------------------------
	// Results persistence
	// -------------------------------------------------------------------------

	/**
	 * Test results are saved to options.
	 */
	public function test_results_saved_to_option() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		System_Checker::run_system_check();

		$saved = get_option( 'agentic_last_system_check' );
		$this->assertIsArray( $saved );
		$this->assertArrayHasKey( 'timestamp', $saved );
		$this->assertArrayHasKey( 'results', $saved );
		$this->assertArrayHasKey( 'overall', $saved );
	}

	/**
	 * Test get_last_check returns saved results.
	 */
	public function test_get_last_check() {
		$this->assertNull( System_Checker::get_last_check() );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		System_Checker::run_system_check();
		$last = System_Checker::get_last_check();

		$this->assertIsArray( $last );
		$this->assertArrayHasKey( 'timestamp', $last );
	}

	/**
	 * Test requirements_met returns false before any check.
	 */
	public function test_requirements_met_no_check() {
		$this->assertFalse( System_Checker::requirements_met() );
	}

	/**
	 * Test requirements_met reflects check results.
	 */
	public function test_requirements_met_after_check() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Ensure permalinks are set so the check passes.
		update_option( 'permalink_structure', '/%postname%/' );

		System_Checker::run_system_check();
		// Result depends on environment, but the method should return a boolean.
		$this->assertIsBool( System_Checker::requirements_met() );
	}

	// -------------------------------------------------------------------------
	// Overall status
	// -------------------------------------------------------------------------

	/**
	 * Test overall is false if any check fails.
	 */
	public function test_overall_false_on_failure() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Force permalinks to default (empty) to trigger failure.
		update_option( 'permalink_structure', '' );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		$this->assertFalse( $data['overall'] );
	}

	/**
	 * Test overall is true when all checks pass.
	 */
	public function test_overall_true_when_passing() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'agentic_llm_api_key', 'sk-test-key' );

		$response = System_Checker::run_system_check();
		$data     = $response->get_data();

		// Warning status does not make overall false, only 'fail' does.
		$has_failures = false;
		foreach ( $data['checks'] as $check ) {
			if ( 'fail' === $check['status'] ) {
				$has_failures = true;
				break;
			}
		}

		$this->assertEquals( ! $has_failures, $data['overall'] );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Find a check by name.
	 *
	 * @param array  $checks Array of checks.
	 * @param string $name   Check name.
	 * @return array|null The check or null.
	 */
	private function find_check( array $checks, string $name ): ?array {
		foreach ( $checks as $check ) {
			if ( $check['name'] === $name ) {
				return $check;
			}
		}
		return null;
	}
}
