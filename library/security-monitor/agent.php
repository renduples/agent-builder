<?php
/**
 * Agent Name: Security Monitor
 * Version: 1.1.0
 * Description: Monitors your site for security issues, suspicious activity, and provides recommendations to harden your WordPress installation.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Admin
 * Tags: security, monitoring, hardening, vulnerabilities, malware, protection
 * Capabilities: manage_options
 * Icon: 🛡️
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security Monitor Agent
 *
 * A true AI agent specialized in WordPress security. Has its own personality,
 * system prompt, and security-focused tools.
 */
class Agentic_Security_Monitor extends \Agentic\Agent_Base {

	/**
	 * Load system prompt from template file
	 */
	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'security-monitor';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Security Monitor';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Monitors your site for security issues and provides hardening recommendations.';
	}

	/**
	 * Get system prompt
	 */
	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	/**
	 * Get agent icon
	 */
	public function get_icon(): string {
		return '🛡️';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'admin';
	}

	/**
	 * Get required capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	/**
	 * Get event listeners
	 */
	public function get_event_listeners(): array {
		return array(
			array(
				'id'            => 'failed_login',
				'hook'          => 'wp_login_failed',
				'name'          => 'Failed Login Monitor',
				'callback'      => 'on_failed_login',
				'description'   => 'Logs failed login attempts for security monitoring.',
				'accepted_args' => 2,
			),
			array(
				'id'            => 'user_registered',
				'hook'          => 'user_register',
				'name'          => 'New User Alert',
				'callback'      => 'on_user_registered',
				'description'   => 'Monitors new user registrations for suspicious activity.',
				'accepted_args' => 1,
			),
		);
	}

	/**
	 * Handle failed login event
	 *
	 * @param string    $username Username attempted.
	 * @param \WP_Error $error    WP_Error object.
	 * @return void
	 */
	public function on_failed_login( string $username, $error = null ): void {
		$audit = new \Agentic\Audit_Log();
		$audit->log(
			$this->get_id(),
			'failed_login_detected',
			'security',
			array(
				'username'  => $username,
				'ip'        => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ),
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Handle new user registration event
	 *
	 * @param int $user_id New user ID.
	 * @return void
	 */
	public function on_user_registered( int $user_id ): void {
		$user  = get_userdata( $user_id );
		$audit = new \Agentic\Audit_Log();
		$audit->log(
			$this->get_id(),
			'new_user_detected',
			'security',
			array(
				'user_id'    => $user_id,
				'user_login' => $user ? $user->user_login : 'unknown',
				'user_email' => $user ? $user->user_email : 'unknown',
				'roles'      => $user ? $user->roles : array(),
			)
		);
	}

	/**
	 * Get scheduled tasks
	 */
	public function get_scheduled_tasks(): array {
		return array(
			array(
				'id'          => 'daily_scan',
				'name'        => 'Daily Security Scan',
				'callback'    => 'run_daily_scan',
				'schedule'    => 'daily',
				'description' => 'Runs a comprehensive security scan and reports findings.',
				'prompt'      => 'Run a comprehensive security scan on this WordPress installation. '
					. 'Use your security_scan tool to check for vulnerabilities, then check file '
					. 'permissions with check_file_permissions, and review admin users with '
					. 'list_admin_users. Provide a summary of all findings and flag any critical '
					. 'issues that need immediate attention.',
			),
		);
	}

	/**
	 * Run the daily security scan (cron callback)
	 *
	 * @return void
	 */
	public function run_daily_scan(): void {
		$results = $this->tool_security_scan( array( 'check_type' => 'full' ) );

		$audit = new \Agentic\Audit_Log();
		$audit->log(
			$this->get_id(),
			'scheduled_scan_complete',
			'security',
			array(
				'score'        => $results['score'] ?? 0,
				'rating'       => $results['rating'] ?? 'Unknown',
				'issues_found' => $results['issues_found'] ?? 0,
				'issues'       => array_map(
					fn( $i ) => $i['issue'] ?? '',
					$results['issues'] ?? array()
				),
			)
		);
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "🛡️ **Security Monitor**\n\nI'm your WordPress security specialist. I can:\n\n" .
				"- **Scan your site** for vulnerabilities and misconfigurations\n" .
				"- **Check file permissions** for security issues\n" .
				"- **Review admin users** for suspicious accounts\n" .
				"- **Provide recommendations** to harden your installation\n\n" .
				'What would you like me to check?';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'Run a security scan on my site',
			'Check my file permissions',
			'Review my admin users',
			'How can I harden my WordPress installation?',
		);
	}

	/**
	 * Get agent-specific tools
	 */
	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'security_scan',
					'description' => 'Run a comprehensive security scan on the WordPress installation. Checks WordPress version, debug settings, admin username, SSL, file editing, and more.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'full_scan' => array(
								'type'        => 'boolean',
								'description' => 'Whether to run a full deep scan (takes longer but more thorough)',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_file_permissions',
					'description' => 'Check file and directory permissions for security issues. Reviews wp-config.php, .htaccess, and wp-content directory.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_admin_users',
					'description' => 'List all administrator users for security review. Shows registration date and last login.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
		);
	}

	/**
	 * Execute agent-specific tools
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'security_scan'          => $this->tool_security_scan( $arguments ),
			'check_file_permissions' => $this->tool_check_permissions(),
			'list_admin_users'       => $this->tool_list_admins(),
			default                  => null,
		};
	}

	/**
	 * Tool: Security scan
	 */
	private function tool_security_scan( array $args ): array {
		$issues = array();

		// Check WordPress version
		global $wp_version;
		$latest = '6.7'; // Would normally fetch from API

		if ( version_compare( $wp_version, $latest, '<' ) ) {
			$issues[] = array(
				'severity'       => 'medium',
				'issue'          => 'WordPress is not up to date',
				'current'        => $wp_version,
				'latest'         => $latest,
				'recommendation' => 'Update WordPress to the latest version',
			);
		}

		// Check for debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$issues[] = array(
				'severity'       => 'high',
				'issue'          => 'Debug display is enabled in production',
				'recommendation' => 'Set WP_DEBUG_DISPLAY to false in wp-config.php',
			);
		}

		// Check for default admin username
		$admin_user = get_user_by( 'login', 'admin' );
		if ( $admin_user ) {
			$issues[] = array(
				'severity'       => 'medium',
				'issue'          => 'Default "admin" username exists',
				'recommendation' => 'Create a new admin user with a unique username and delete the "admin" account',
			);
		}

		// Check SSL
		if ( ! is_ssl() && ! str_contains( home_url(), 'localhost' ) ) {
			$issues[] = array(
				'severity'       => 'high',
				'issue'          => 'Site is not using HTTPS',
				'recommendation' => 'Install an SSL certificate and force HTTPS',
			);
		}

		// Check file editing
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$issues[] = array(
				'severity'       => 'low',
				'issue'          => 'File editing is enabled in admin',
				'recommendation' => "Add define('DISALLOW_FILE_EDIT', true); to wp-config.php",
			);
		}

		// Check database prefix
		global $wpdb;
		if ( $wpdb->prefix === 'wp_' ) {
			$issues[] = array(
				'severity'       => 'low',
				'issue'          => 'Using default database prefix "wp_"',
				'recommendation' => 'Consider using a custom database prefix for new installations',
			);
		}

		// Calculate security score
		$score = 100;
		foreach ( $issues as $issue ) {
			$score -= match ( $issue['severity'] ) {
				'high'   => 20,
				'medium' => 10,
				'low'    => 5,
				default  => 5,
			};
		}

		return array(
			'scan_time'    => current_time( 'mysql' ),
			'issues_found' => count( $issues ),
			'issues'       => $issues,
			'score'        => max( 0, $score ),
			'rating'       => $score >= 80 ? 'Good' : ( $score >= 60 ? 'Fair' : 'Needs Attention' ),
		);
	}

	/**
	 * Tool: Check file permissions
	 */
	private function tool_check_permissions(): array {
		$checks = array();

		// Check wp-config.php
		$wp_config = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config ) ) {
			$perms                   = substr( sprintf( '%o', fileperms( $wp_config ) ), -4 );
			$secure                  = in_array( $perms, array( '0400', '0440', '0600', '0640', '0644' ), true );
			$checks['wp-config.php'] = array(
				'permissions'    => $perms,
				'secure'         => $secure,
				'recommendation' => $secure ? null : 'Set permissions to 0600 or 0640',
			);
		}

		// Check .htaccess
		$htaccess = ABSPATH . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			$perms               = substr( sprintf( '%o', fileperms( $htaccess ) ), -4 );
			$secure              = in_array( $perms, array( '0644', '0444' ), true );
			$checks['.htaccess'] = array(
				'permissions'    => $perms,
				'secure'         => $secure,
				'recommendation' => $secure ? null : 'Set permissions to 0644',
			);
		}

		// Check wp-content
		$wp_content            = WP_CONTENT_DIR;
		$perms                 = substr( sprintf( '%o', fileperms( $wp_content ) ), -4 );
		$secure                = in_array( $perms, array( '0755', '0750' ), true );
		$checks['wp-content/'] = array(
			'permissions'    => $perms,
			'secure'         => $secure,
			'recommendation' => $secure ? null : 'Set permissions to 0755',
		);

		// Check uploads directory
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && is_dir( $uploads['basedir'] ) ) {
			$perms              = substr( sprintf( '%o', fileperms( $uploads['basedir'] ) ), -4 );
			$secure             = in_array( $perms, array( '0755', '0750' ), true );
			$checks['uploads/'] = array(
				'permissions'    => $perms,
				'secure'         => $secure,
				'recommendation' => $secure ? null : 'Set permissions to 0755',
			);
		}

		$insecure_count = count( array_filter( $checks, fn( $c ) => ! $c['secure'] ) );

		return array(
			'checks'         => $checks,
			'total_checked'  => count( $checks ),
			'insecure_count' => $insecure_count,
			'status'         => $insecure_count === 0 ? 'All permissions are secure' : "{$insecure_count} file(s) need attention",
		);
	}

	/**
	 * Tool: List admin users
	 */
	private function tool_list_admins(): array {
		$admins = get_users( array( 'role' => 'administrator' ) );
		$result = array();

		foreach ( $admins as $admin ) {
			$last_login = get_user_meta( $admin->ID, 'last_login', true );
			$result[]   = array(
				'id'         => $admin->ID,
				'login'      => $admin->user_login,
				'email'      => $admin->user_email,
				'registered' => $admin->user_registered,
				'last_login' => $last_login ?: 'Never recorded',
				'flags'      => $this->get_user_flags( $admin ),
			);
		}

		return array(
			'admin_count'    => count( $result ),
			'admins'         => $result,
			'recommendation' => count( $result ) > 3
				? 'Consider reducing the number of admin users - each is a potential security risk'
				: null,
		);
	}

	/**
	 * Get security flags for a user
	 */
	private function get_user_flags( \WP_User $user ): array {
		$flags = array();

		if ( $user->user_login === 'admin' ) {
			$flags[] = 'Uses default "admin" username';
		}

		if ( strtotime( $user->user_registered ) < strtotime( '-2 years' ) ) {
			$flags[] = 'Account older than 2 years - verify still needed';
		}

		$last_login = get_user_meta( $user->ID, 'last_login', true );
		if ( $last_login && strtotime( $last_login ) < strtotime( '-6 months' ) ) {
			$flags[] = 'No login in 6+ months';
		}

		return $flags;
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Security_Monitor() );
	}
);

// Schedule security checks
add_action(
	'agentic_agent_security-monitor_activate',
	function () {
		if ( ! wp_next_scheduled( 'agentic_security_check' ) ) {
			wp_schedule_event( time(), 'daily', 'agentic_security_check' );
		}
	}
);

add_action(
	'agentic_agent_security-monitor_deactivate',
	function () {
		wp_clear_scheduled_hook( 'agentic_security_check' );
	}
);

add_action(
	'agentic_security_check',
	function () {
		$agent = new Agentic_Security_Monitor();
		$scan  = $agent->execute_tool( 'security_scan', array( 'full_scan' => true ) );

		if ( $scan['issues_found'] > 0 && class_exists( 'Agentic_Audit_Log' ) ) {
			Agentic_Audit_Log::get_instance()->log(
				'security-monitor',
				'security_scan',
				sprintf( 'Daily scan: %d issues found (score: %d)', $scan['issues_found'], $scan['score'] ),
				$scan
			);
		}
	}
);
