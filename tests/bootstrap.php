<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package Agent_Builder
 */

// Define test mode.
if ( ! defined( 'AGENTIC_TEST_MODE' ) ) {
	define( 'AGENTIC_TEST_MODE', true );
}

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Check if WordPress test suite is available.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found.\n";
	echo "Run: bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/agent-builder.php';
}

// Load the plugin.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Create plugin database tables for tests (activation hook doesn't fire in tests).
_agentic_create_test_tables();

function _agentic_create_test_tables() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Create tables via the plugin's own methods.
	if ( class_exists( 'Agentic\\Job_Manager' ) ) {
		\Agentic\Job_Manager::create_table();
	}
	if ( class_exists( 'Agentic\\Security_Log' ) ) {
		\Agentic\Security_Log::create_table();
	}

	// Create audit_log and approval_queue tables matching plugin's create_tables().
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_audit_log (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		agent_id varchar(64) NOT NULL,
		action varchar(128) NOT NULL,
		target_type varchar(64),
		target_id varchar(128),
		details longtext,
		reasoning text,
		tokens_used int unsigned DEFAULT 0,
		cost decimal(10,6) DEFAULT 0,
		user_id bigint(20) unsigned,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY agent_id (agent_id),
		KEY action (action),
		KEY created_at (created_at)
	) {$charset_collate};";
	dbDelta( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_approval_queue (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		agent_id varchar(64) NOT NULL,
		action varchar(128) NOT NULL,
		params longtext NOT NULL,
		reasoning text,
		status varchar(32) DEFAULT 'pending',
		approved_by bigint(20) unsigned,
		approved_at datetime,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		expires_at datetime,
		PRIMARY KEY (id),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";
	dbDelta( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_memory (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		memory_type varchar(50) NOT NULL,
		entity_id varchar(100) NOT NULL,
		memory_key varchar(255) NOT NULL,
		memory_value longtext NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		expires_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY memory_type_entity (memory_type, entity_id),
		KEY memory_key (memory_key)
	) {$charset_collate};";
	dbDelta( $sql );
}

// Load test helpers after WordPress is loaded.
require_once __DIR__ . '/helpers/TestCase.php';
require_once __DIR__ . '/helpers/MockWPFunctions.php';
require_once __DIR__ . '/helpers/TestDataFactory.php';
