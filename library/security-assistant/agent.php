<?php
/**
 * Agent Name: Security Assistant
 * Version: 1.0.0
 * Description: Monitors your site for security threats. Checks failed logins, outdated plugins, suspicious user accounts, and recently modified files.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Security
 * Tags: security, logins, plugins, vulnerabilities, monitoring
 * Capabilities: manage_options
 * Icon: 🔒
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security Assistant Agent
 *
 * Read-only security monitoring. Surfaces threats, explains risks,
 * and recommends remediation. Never modifies the site.
 */
class Agentic_Security_Assistant extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'security-assistant';
	}

	public function get_name(): string {
		return 'Security Assistant';
	}

	public function get_description(): string {
		return 'Monitors your site for security threats. Checks failed logins, outdated plugins, suspicious user accounts, and recently modified files.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '🔒';
	}

	public function get_category(): string {
		return 'Security';
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	public function get_welcome_message(): string {
		return "🔒 **Security Assistant**\n\n" .
			"I monitor your site for security threats and help you respond.\n\n" .
			"**What I check:**\n" .
			"- **Failed logins** — suspicious IPs and brute-force patterns\n" .
			"- **Outdated plugins** — the most common source of WordPress compromises\n" .
			"- **User accounts** — unexpected admin or editor accounts\n" .
			"- **File modifications** — recently changed PHP files that may indicate tampering\n" .
			"- **New registrations** — unusual spikes that may signal bot activity\n\n" .
			"Start with a full security scan or ask about a specific concern.";
	}

	public function get_suggested_prompts(): array {
		return array(
			'Run a full security scan',
			'Show me recent failed login attempts',
			'Are any plugins outdated?',
			'Show all administrator accounts',
		);
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	public function get_tools(): array {
		return array(

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_security_overview',
					'description' => 'Run a comprehensive security health check: failed logins in the last 24 hours, plugins with available updates, administrator user count, recent registrations in the last 24 hours, and a risk level summary with prioritised action items.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_failed_logins',
					'description' => 'Get recent failed login attempts from the Agent Builder security log. Returns attempts grouped by IP address. Falls back to transient cache if the security log table is not present.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'hours' => array(
								'type'        => 'integer',
								'description' => 'Look back this many hours. Defaults to 24.',
							),
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Max individual events to return (1–100). Defaults to 50.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_plugin_updates',
					'description' => 'List active plugins and whether they have updates available. Outdated plugins are the leading cause of WordPress site compromises.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'outdated_only' => array(
								'type'        => 'boolean',
								'description' => 'If true, return only plugins with available updates. Defaults to false.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_privileged_users',
					'description' => 'List all users with administrator or editor roles. Helps identify unexpected or unauthorised high-privilege accounts.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'role' => array(
								'type'        => 'string',
								'description' => '"administrator" (default), "editor", or "all_elevated" (both roles).',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_recent_registrations',
					'description' => 'Get recently registered user accounts. Useful for detecting automated spam registrations or unexpected new accounts.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'days'  => array(
								'type'        => 'integer',
								'description' => 'Look back this many days. Defaults to 7.',
							),
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Max results (1–50). Defaults to 20.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_file_modifications',
					'description' => 'Find PHP files that have been modified recently. Unexpected modifications to plugin, theme, or core files can indicate malicious code injection.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'hours'     => array(
								'type'        => 'integer',
								'description' => 'Look back this many hours. Defaults to 24.',
							),
							'directory' => array(
								'type'        => 'string',
								'description' => 'Subdirectory relative to ABSPATH to scan. Allowed: "wp-content" (default), "wp-includes", "wp-admin".',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Max files to return (1–50). Defaults to 20.',
							),
						),
					),
				),
			),

		);
	}

	// -------------------------------------------------------------------------
	// Tool dispatch
	// -------------------------------------------------------------------------

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'get_security_overview'    => $this->tool_get_security_overview(),
			'get_failed_logins'        => $this->tool_get_failed_logins( $arguments ),
			'check_plugin_updates'     => $this->tool_check_plugin_updates( $arguments ),
			'list_privileged_users'    => $this->tool_list_privileged_users( $arguments ),
			'get_recent_registrations' => $this->tool_get_recent_registrations( $arguments ),
			'check_file_modifications' => $this->tool_check_file_modifications( $arguments ),
			default                    => null,
		};
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private function tool_get_security_overview(): array {
		$failed   = $this->tool_get_failed_logins( array( 'hours' => 24, 'limit' => 100 ) );
		$plugins  = $this->tool_check_plugin_updates( array( 'outdated_only' => true ) );
		$admins   = $this->tool_list_privileged_users( array( 'role' => 'administrator' ) );
		$new_regs = $this->tool_get_recent_registrations( array( 'days' => 1, 'limit' => 20 ) );

		$risk_level = 'low';
		$risk_flags = array();

		$failed_count = $failed['total'] ?? 0;
		if ( $failed_count > 20 ) {
			$risk_level = 'high';
			$risk_flags[] = "{$failed_count} failed login attempts in the last 24 hours — possible brute-force attack.";
		} elseif ( $failed_count > 5 ) {
			$risk_level = 'medium';
			$risk_flags[] = "{$failed_count} failed login attempts in the last 24 hours.";
		}

		$outdated_count = $plugins['outdated_count'] ?? 0;
		if ( $outdated_count > 0 ) {
			if ( 'low' === $risk_level ) {
				$risk_level = 'medium';
			}
			$risk_flags[] = "{$outdated_count} plugin(s) have available updates — update immediately.";
		}

		$admin_count = count( $admins['users'] ?? array() );
		if ( $admin_count > 3 ) {
			$risk_flags[] = "{$admin_count} administrator accounts. Review for unexpected accounts.";
		}

		$reg_count = $new_regs['total'] ?? 0;
		if ( $reg_count > 5 ) {
			$risk_flags[] = "{$reg_count} new registrations in the last 24 hours — possible bot registration.";
		}

		return array(
			'risk_level'            => $risk_level,
			'risk_flags'            => $risk_flags,
			'failed_logins_24h'     => $failed_count,
			'outdated_plugins'      => $outdated_count,
			'admin_user_count'      => $admin_count,
			'new_registrations_24h' => $reg_count,
			'top_failed_login_ips'  => array_slice( $failed['by_ip'] ?? array(), 0, 5 ),
			'outdated_plugin_list'  => array_slice( $plugins['plugins'] ?? array(), 0, 5 ),
		);
	}

	private function tool_get_failed_logins( array $args ): array {
		global $wpdb;

		$hours = max( 1, (int) ( $args['hours'] ?? 24 ) );
		$limit = min( max( (int) ( $args['limit'] ?? 50 ), 1 ), 100 );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
		$table = $wpdb->prefix . 'agentic_security_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT event_type, ip_address, data, created_at
					FROM `{$table}`
					WHERE event_type = 'failed_login' AND created_at >= %s
					ORDER BY created_at DESC
					LIMIT %d",
					$since,
					$limit
				),
				ARRAY_A
			);

			$by_ip = array();
			foreach ( $rows as $row ) {
				$ip           = $row['ip_address'];
				$by_ip[ $ip ] = ( $by_ip[ $ip ] ?? 0 ) + 1;
			}
			arsort( $by_ip );

			$top_ips = array();
			foreach ( $by_ip as $ip => $count ) {
				$top_ips[] = array( 'ip' => $ip, 'attempts' => $count );
			}

			return array(
				'source' => 'agentic_security_log',
				'period' => "last {$hours} hours",
				'total'  => count( $rows ),
				'by_ip'  => $top_ips,
				'events' => array_slice(
					array_map(
						fn( $r ) => array(
							'ip'        => $r['ip_address'],
							'timestamp' => $r['created_at'],
							'data'      => json_decode( $r['data'], true ),
						),
						$rows
					),
					0,
					$limit
				),
			);
		}

		// Fallback: transient cache.
		$lockouts = get_transient( 'agentic_failed_login_ips' );
		return array(
			'source'   => 'transient_fallback',
			'note'     => 'Security log table not found. Activate Agent Builder security logging in Settings for full data.',
			'total'    => is_array( $lockouts ) ? count( $lockouts ) : 0,
			'lockouts' => is_array( $lockouts ) ? $lockouts : array(),
		);
	}

	private function tool_check_plugin_updates( array $args ): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$outdated_only  = (bool) ( $args['outdated_only'] ?? false );
		$update_plugins = get_site_transient( 'update_plugins' );
		$active_plugins = get_option( 'active_plugins', array() );
		$results        = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! file_exists( $plugin_path ) ) {
				continue;
			}

			$data        = get_plugin_data( $plugin_path, false, false );
			$has_update  = isset( $update_plugins->response[ $plugin_file ] );
			$new_version = $has_update ? ( $update_plugins->response[ $plugin_file ]->new_version ?? null ) : null;

			if ( $outdated_only && ! $has_update ) {
				continue;
			}

			$results[] = array(
				'file'        => $plugin_file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'new_version' => $new_version,
				'has_update'  => $has_update,
			);
		}

		return array(
			'total_active'   => count( $active_plugins ),
			'outdated_count' => count( array_filter( $results, fn( $p ) => $p['has_update'] ) ),
			'plugins'        => $results,
		);
	}

	private function tool_list_privileged_users( array $args ): array {
		$role_arg = $args['role'] ?? 'administrator';
		$roles    = 'all_elevated' === $role_arg ? array( 'administrator', 'editor' ) : array( $role_arg );

		$users = get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);

		return array(
			'roles' => $roles,
			'total' => count( $users ),
			'users' => array_map(
				fn( $u ) => array(
					'id'           => (int) $u->ID,
					'display_name' => $u->display_name,
					'user_login'   => $u->user_login,
					'user_email'   => $u->user_email,
					'roles'        => $u->roles,
					'registered'   => $u->user_registered,
				),
				$users
			),
		);
	}

	private function tool_get_recent_registrations( array $args ): array {
		$days  = max( 1, (int) ( $args['days'] ?? 7 ) );
		$limit = min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 50 );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$users = get_users(
			array(
				'date_query' => array(
					array(
						'after'  => $since,
						'column' => 'user_registered',
					),
				),
				'orderby'    => 'registered',
				'order'      => 'DESC',
				'number'     => $limit,
			)
		);

		return array(
			'period' => "last {$days} days",
			'total'  => count( $users ),
			'users'  => array_map(
				fn( $u ) => array(
					'id'         => (int) $u->ID,
					'login'      => $u->user_login,
					'email'      => $u->user_email,
					'roles'      => $u->roles,
					'registered' => $u->user_registered,
				),
				$users
			),
		);
	}

	private function tool_check_file_modifications( array $args ): array {
		$hours    = max( 1, (int) ( $args['hours'] ?? 24 ) );
		$dir_arg  = sanitize_text_field( $args['directory'] ?? 'wp-content' );
		$limit    = min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 50 );
		$since    = time() - ( $hours * HOUR_IN_SECONDS );

		$allowed = array( 'wp-content', 'wp-includes', 'wp-admin' );
		if ( ! in_array( $dir_arg, $allowed, true ) ) {
			$dir_arg = 'wp-content';
		}

		$scan_path = trailingslashit( ABSPATH ) . $dir_arg;
		if ( ! is_dir( $scan_path ) ) {
			return array( 'error' => "Directory not found: {$dir_arg}" );
		}

		$modified = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $scan_path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( $file->getMTime() >= $since ) {
				$modified[] = array(
					'path'     => str_replace( ABSPATH, '', $file->getPathname() ),
					'modified' => gmdate( 'Y-m-d H:i:s', $file->getMTime() ),
					'size_kb'  => round( $file->getSize() / 1024, 1 ),
				);
			}
			if ( count( $modified ) >= $limit ) {
				break;
			}
		}

		usort( $modified, fn( $a, $b ) => strtotime( $b['modified'] ) - strtotime( $a['modified'] ) );

		return array(
			'directory'          => $dir_arg,
			'period'             => "last {$hours} hours",
			'php_files_modified' => count( $modified ),
			'files'              => $modified,
			'assessment'         => count( $modified ) === 0
				? 'No PHP files modified in this period.'
				: 'Review any unexpected modifications, especially in wp-content/uploads or core directories.',
		);
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Security_Assistant() );
	}
);
