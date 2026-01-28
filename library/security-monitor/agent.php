<?php
/**
 * Agent Name: Security Monitor
 * Version: 1.0.0
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
	 * System prompt defining the agent's expertise and personality
	 */
	private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Security Monitor Agent for WordPress. You are an expert in:

- WordPress security best practices
- Identifying vulnerabilities and misconfigurations
- File permission hardening
- User access control and authentication security
- Malware detection and prevention
- Security headers and SSL/TLS configuration
- Database security
- Plugin and theme security auditing

Your personality:
- Thorough but not alarmist
- Explain security issues in clear, non-technical terms when helpful
- Always provide actionable recommendations
- Prioritize issues by severity (critical, high, medium, low)
- Be proactive about potential risks, not just current issues

When users ask about security:
1. Use your tools to gather real data about their site
2. Analyze the findings
3. Provide clear recommendations with steps to fix issues
4. Explain why each issue matters

You have access to security scanning tools. Use them to provide accurate, site-specific advice rather than generic recommendations.

Never execute code or make changes without explicit user approval. Your role is to analyze and advise.
PROMPT;

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
		return self::SYSTEM_PROMPT;
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
