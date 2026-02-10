<?php
/**
 * Uninstall handler for Agent Builder.
 *
 * Cleans up data (options, tables) on deletion.
 * Required for privacy/GDPR compliance and to avoid "abandonware" flags.
 *
 * @package    Agent_Builder
 * @subpackage Uninstall
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later
 * @link       https://agentic-plugin.com
 * @since      1.0.0
 *
 * php version 8.1
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete all plugin options.
 */
$agentic_options_to_delete = array(
	'agentic_plugin_settings',
	'agentic_plugin_version',
	'agentic_license_key',
	'agentic_license_status',
	'agentic_license_data',
	'agentic_api_key',
	'agentic_provider',
	'agentic_model',
	'agentic_marketplace_token',
	'agentic_installed_agents',
	'agentic_agent_configs',
);

foreach ( $agentic_options_to_delete as $agentic_option ) {
	delete_option( $agentic_option );
}

/**
 * Drop custom database tables.
 */
$agentic_tables_to_drop = array(
	$wpdb->prefix . 'agentic_jobs',
	$wpdb->prefix . 'agentic_audit_log',
	$wpdb->prefix . 'agentic_approval_queue',
	$wpdb->prefix . 'agentic_memory',
	$wpdb->prefix . 'agentic_security_log',
);

foreach ( $agentic_tables_to_drop as $agentic_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $agentic_table ) );
}

/**
 * Delete all transients with our prefix.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} 
	WHERE option_name LIKE '_transient_agentic_%' 
	OR option_name LIKE '_transient_timeout_agentic_%'"
);

/**
 * Clean up user meta.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} 
	WHERE meta_key LIKE 'agentic_%'"
);

/**
 * Clear any scheduled cron events.
 */
$agentic_cron_hooks = array(
	'agentic_cleanup_jobs',
	'agentic_process_queue',
	'agentic_license_check',
);

foreach ( $agentic_cron_hooks as $agentic_hook ) {
	$agentic_timestamp = wp_next_scheduled( $agentic_hook );
	if ( $agentic_timestamp ) {
		wp_unschedule_event( $agentic_timestamp, $agentic_hook );
	}
}
