<?php
/**
 * Base Test Case Class
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use WP_UnitTestCase;

/**
 * Base test case for Agent Builder tests.
 */
class TestCase extends WP_UnitTestCase {

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->cleanup_test_data();
	}

	/**
	 * Teardown test environment.
	 */
	public function tearDown(): void {
		$this->cleanup_test_data();
		parent::tearDown();
	}

	/**
	 * Cleanup test data.
	 */
	protected function cleanup_test_data() {
		global $wpdb;

		// Clean up test options.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE 'agentic_test_%'"
		);

		// Clean up test jobs.
		$jobs_table = $wpdb->prefix . 'agentic_jobs';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$jobs_table}'" ) === $jobs_table ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$jobs_table} WHERE agent_id LIKE %s",
					'test_%'
				)
			);
		}

		// Clean up test audit logs.
		$audit_table = $wpdb->prefix . 'agentic_audit_log';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$audit_table}'" ) === $audit_table ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$audit_table} WHERE agent_id LIKE %s",
					'test_%'
				)
			);
		}

		// Clean up test security logs.
		$security_table = $wpdb->prefix . 'agentic_security_log';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$security_table}'" ) === $security_table ) {
			$wpdb->query(
				"DELETE FROM {$security_table} 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
			);
		}
	}

	/**
	 * Create a test agent file.
	 *
	 * @param string $agent_id Agent ID.
	 * @return string Agent file path.
	 */
	protected function create_test_agent( $agent_id ) {
		$agents_dir = WP_CONTENT_DIR . '/agents';
		if ( ! file_exists( $agents_dir ) ) {
			mkdir( $agents_dir, 0755, true );
		}

		$agent_dir = $agents_dir . '/' . $agent_id;
		if ( ! file_exists( $agent_dir ) ) {
			mkdir( $agent_dir, 0755, true );
		}

		// Use a unique class name per agent_id to avoid class redeclaration.
		$class_suffix = str_replace( '-', '_', ucwords( $agent_id, '-' ) );
		$agent_file   = $agent_dir . '/agent.php';
		$content      = <<<PHP
<?php
/**
 * Agent Name: Test Agent
 * Description: Test agent for PHPUnit
 * Version: 1.0.0
 * Author: Test Suite
 */

namespace Agentic;

class Test_Agent_{$class_suffix} extends Agent_Base {
	public function get_id(): string {
		return '{$agent_id}';
	}

	public function get_name(): string {
		return 'Test Agent';
	}

	public function get_description(): string {
		return 'Test agent for PHPUnit';
	}

	public function get_system_prompt(): string {
		return 'You are a test agent.';
	}
}
PHP;

		file_put_contents( $agent_file, $content );
		return $agent_file;
	}

	/**
	 * Delete a test agent.
	 *
	 * @param string $agent_id Agent ID.
	 */
	protected function delete_test_agent( $agent_id ) {
		$agent_dir = WP_CONTENT_DIR . '/agents/' . $agent_id;
		if ( file_exists( $agent_dir ) ) {
			$this->delete_directory( $agent_dir );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 */
	protected function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? $this->delete_directory( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	/**
	 * Assert that a string contains a substring.
	 *
	 * @param string $needle   Substring to find.
	 * @param string $haystack String to search.
	 * @param string $message  Optional message.
	 */
	protected function assertStringContains( $needle, $haystack, $message = '' ) {
		$this->assertStringContainsString( $needle, $haystack, $message );
	}
}
