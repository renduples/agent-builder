<?php
/**
 * Main plugin file.
 *
 * Plugin Name:       Agent Builder
 * Plugin URI:        https://agentic-plugin.com
 * Description:       Build AI agents without writing code. Describe the AI agent you want and let WordPress build it for you.
 * Version:           1.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Agent Builder Team
 * Author URI:        https://profiles.wordpress.org/agenticplugin/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agent-builder
 * Domain Path:       /languages
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AGENTIC_PLUGIN_VERSION', '1.2.0' );
define( 'AGENTIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENTIC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AGENTIC_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class
 *
 * @since 0.1.0
 */
final class Plugin {


	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'the_content', array( $this, 'render_chat_interface' ) );

		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin textdomain
	 *
	 * Since WordPress 4.6, translations are automatically loaded for plugins hosted on WordPress.org.
	 * This function is kept for backward compatibility but is no longer needed.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		// Translations are automatically loaded by WordPress for plugins on WordPress.org.
		// No action needed since WordPress 4.6+.
	}

	/**
	 * Initialize plugin
	 *
	 * @return void
	 */
	public function init(): void {
		// Register custom post types for audit logs.
		$this->register_post_types();

		// Load core components.
		$this->load_components();
	}

	/**
	 * Admin initialization
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// Register settings.
		register_setting(
			'agentic_core_settings',
			'agentic_agent_mode',
			array(
				'type'              => 'string',
				'default'           => 'supervised',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_menu_page(
			__( 'Agent Builder', 'agent-builder' ),
			__( 'Agent Builder', 'agent-builder' ),
			'manage_options',
			'agent-builder',
			array( $this, 'render_admin_page' ),
			'dashicons-superhero',
			30
		);

		add_submenu_page(
			'agent-builder',
			__( 'Dashboard', 'agent-builder' ),
			__( 'Dashboard', 'agent-builder' ),
			'manage_options',
			'agent-builder',
			array( $this, 'render_admin_page' )
		);

		// Agent Chat.
		add_submenu_page(
			'agent-builder',
			__( 'Agent Chat', 'agent-builder' ),
			__( 'Agent Chat', 'agent-builder' ),
			'read',
			'agentic-chat',
			array( $this, 'render_chat_page' )
		);

		// Agents menu (like Plugins menu).
		add_submenu_page(
			'agent-builder',
			__( 'Installed Agents', 'agent-builder' ),
			__( 'Installed Agents', 'agent-builder' ),
			'manage_options',
			'agentic-agents',
			array( $this, 'render_agents_page' )
		);

		add_submenu_page(
			'agent-builder',
			__( 'Agent Marketplace', 'agent-builder' ),
			__( 'Marketplace', 'agent-builder' ),
			'read',
			'agentic-agents-add',
			array( $this, 'render_agents_add_page' )
		);

		add_submenu_page(
			'agent-builder',
			__( 'Audit Log', 'agent-builder' ),
			__( 'Audit Log', 'agent-builder' ),
			'manage_options',
			'agentic-audit',
			array( $this, 'render_audit_log_page' )
		);

		add_submenu_page(
			'agent-builder',
			__( 'Code Proposals', 'agent-builder' ),
			__( 'Code Proposals', 'agent-builder' ),
			'manage_options',
			'agentic-approvals',
			array( $this, 'render_approvals_page' )
		);

		add_submenu_page(
			'agent-builder',
			__( 'Security Log', 'agent-builder' ),
			__( 'Security Log', 'agent-builder' ),
			'manage_options',
			'agentic-security-log',
			array( $this, 'render_security_log_page' )
		);

		add_submenu_page(
			'agent-builder',
			__( 'Settings', 'agent-builder' ),
			__( 'Settings', 'agent-builder' ),
			'manage_options',
			'agentic-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add Agentic menu to admin bar
	 *
	 * @param  \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add parent menu.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'agentic',
				'title' => '<span class="ab-icon dashicons dashicons-superhero" style="font-size: 18px; line-height: 1.3;"></span>' . __( 'Agents', 'agent-builder' ),
				'href'  => admin_url( 'admin.php?page=agentic-agents' ),
				'meta'  => array(
					'title' => __( 'Agent Builder', 'agent-builder' ),
				),
			)
		);

		// Add submenu items.
		$wp_admin_bar->add_node(
			array(
				'id'     => 'agentic-agents',
				'parent' => 'agentic',
				'title'  => __( 'Installed Agents', 'agent-builder' ),
				'href'   => admin_url( 'admin.php?page=agentic-agents' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'agentic-add-new',
				'parent' => 'agentic',
				'title'  => __( 'Add Agent', 'agent-builder' ),
				'href'   => admin_url( 'admin.php?page=agentic-agents-add' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'agentic-audit',
				'parent' => 'agentic',
				'title'  => __( 'Audit Log', 'agent-builder' ),
				'href'   => admin_url( 'admin.php?page=agentic-audit' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'agentic-settings',
				'parent' => 'agentic',
				'title'  => __( 'Settings', 'agent-builder' ),
				'href'   => admin_url( 'admin.php?page=agentic-settings' ),
			)
		);
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// Register System Checker routes.
		\Agentic\System_Checker::register_routes();
	}

	/**
	 * Register custom post types
	 *
	 * @return void
	 */
	private function register_post_types(): void {
		register_post_type(
			'agent_audit_log',
			array(
				'labels'       => array(
					'name'          => __( 'Agent Audit Logs', 'agent-builder' ),
					'singular_name' => __( 'Audit Log', 'agent-builder' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'supports'     => array( 'title', 'custom-fields' ),
				'capabilities' => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			'agent_approval',
			array(
				'labels'   => array(
					'name'          => __( 'Agent Approvals', 'agent-builder' ),
					'singular_name' => __( 'Approval', 'agent-builder' ),
				),
				'public'   => false,
				'show_ui'  => false,
				'supports' => array( 'title', 'custom-fields' ),
			)
		);
	}

	/**
	 * Load core components
	 *
	 * @return void
	 */
	private function load_components(): void {
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-llm-client.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-audit-log.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-agent-tools.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-agent-controller.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-rest-api.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-approval-queue.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-agentic-agent-registry.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-chat-security.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-response-cache.php';
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-shortcodes.php';

		// System requirements checker.
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-system-checker.php';

		// Marketplace components.
		include_once AGENTIC_PLUGIN_DIR . 'includes/class-marketplace-client.php';

		// Initialize components.
		new REST_API();
		new Approval_Queue();
		new \Agentic\Shortcodes();

		// Initialize Social Auth (for custom login/register with OAuth).

		// Initialize marketplace (on marketplace site only - controlled by constant).

		// Initialize marketplace client (for installing agents from marketplace).
		new Marketplace_Client();

		// Load active agents (like WordPress loads active plugins).
		\Agentic_Agent_Registry::get_instance()->load_active_agents();
	}

	/**
	 * Render admin dashboard page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/dashboard.php';
	}

	/**
	 * Render audit log page
	 *
	 * @return void
	 */
	public function render_audit_log_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/audit.php';
	}

	/**
	 * Render approvals page
	 *
	 * @return void
	 */
	public function render_approvals_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/approvals.php';
	}

	/**
	 * Render security log page
	 *
	 * @return void
	 */
	public function render_security_log_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/security-log.php';
	}

	/**
	 * Render installed agents page
	 *
	 * @return void
	 */
	public function render_agents_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/agents.php';
	}

	/**
	 * Render add new agent page
	 *
	 * @return void
	 */
	public function render_agents_add_page(): void {
		include AGENTIC_PLUGIN_DIR . 'admin/agents-add.php';
	}

	/**
	 * Render Agent Chat page
	 *
	 * @return void
	 */
	public function render_chat_page(): void {
		// Enqueue chat assets for admin.
		wp_enqueue_style(
			'agentic-chat',
			AGENTIC_PLUGIN_URL . 'assets/css/chat.css',
			array(),
			AGENTIC_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'agentic-chat',
			AGENTIC_PLUGIN_URL . 'assets/js/chat.js',
			array(),
			AGENTIC_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'agentic-chat',
			'agenticChat',
			array(
				'restUrl'  => rest_url( 'agentic/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userId'   => get_current_user_id(),
				'userName' => wp_get_current_user()->display_name,
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Agent Chat', 'agent-builder' ) . ' <span class="agentic-status" style="font-size: 14px; font-weight: normal; vertical-align: middle;"><span class="agentic-status-dot"></span>Online</span></h1>';
		include AGENTIC_PLUGIN_DIR . 'templates/chat-interface.php';
		echo '</div>';
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Enqueue settings page script.
		wp_enqueue_script(
			'agentic-settings',
			AGENTIC_PLUGIN_URL . 'assets/js/settings.js',
			array(),
			AGENTIC_PLUGIN_VERSION,
			true
		);

		include AGENTIC_PLUGIN_DIR . 'admin/settings.php';
	}

	/**
	 * Enqueue frontend assets for chat interface
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( is_page( 'agent-chat' ) && is_user_logged_in() ) {
			wp_enqueue_style(
				'agentic-chat',
				AGENTIC_PLUGIN_URL . 'assets/css/chat.css',
				array(),
				AGENTIC_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'agentic-chat',
				AGENTIC_PLUGIN_URL . 'assets/js/chat.js',
				array(),
				AGENTIC_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'agentic-chat',
				'agenticChat',
				array(
					'restUrl'  => rest_url( 'agentic/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'userId'   => get_current_user_id(),
					'userName' => wp_get_current_user()->display_name,
				)
			);
		}
	}

	/**
	 * Render chat interface on the agent-chat page
	 *
	 * @param  string $content Page content.
	 * @return string Modified content.
	 */
	public function render_chat_interface( string $content ): string {
		if ( is_page( 'agent-chat' ) ) {
			if ( is_user_logged_in() ) {
				ob_start();
				include AGENTIC_PLUGIN_DIR . 'templates/chat-interface.php';
				return ob_get_clean();
			} else {
				$login_url = home_url( '/login/' );
				return '<div class="agentic-login-required">
                    <div class="login-icon">🤖</div>
                    <h2>Chat with AI Agents</h2>
                    <p>Sign in to start chatting with powerful AI agents that can help you build, optimize, and manage your WordPress site.</p>
                    <div class="login-features">
                        <div class="feature"><span>✓</span> Access all installed agents</div>
                        <div class="feature"><span>✓</span> Save conversation history</div>
                        <div class="feature"><span>✓</span> Get personalized recommendations</div>
                    </div>
                    <a href="' . esc_url( $login_url ) . '" class="login-btn-primary">Sign In to Continue</a>
                    <p class="login-signup">Don\'t have an account? <a href="' . esc_url( $login_url ) . '">Sign up free</a></p>
                </div>';
			}
		}
		return $content;
	}

	/**
	 * Plugin activation
	 *
	 * @return void
	 */
	public function activate(): void {
		// Set default options.
		add_option( 'agentic_agent_mode', 'supervised' );
		add_option( 'agentic_audit_enabled', true );
		add_option( 'agentic_llm_provider', 'openai' );
		add_option( 'agentic_llm_api_key', '' );
		add_option( 'agentic_model', 'gpt-4o' );

		// Create database tables.
		$this->create_tables();

		// Create chat page if it doesn't exist.
		$chat_page = get_page_by_path( 'agent-chat' );
		if ( ! $chat_page ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => 'Developer Agent',
					'post_name'    => 'agent-chat',
					'post_status'  => 'publish',
					'post_content' => '<!-- Chat interface rendered by Agent Builder -->',
				)
			);
		}

		// Create marketplace pages if this is the marketplace site.
		if ( defined( 'AGENTIC_IS_MARKETPLACE' ) && AGENTIC_IS_MARKETPLACE ) {
			$submit_page     = get_page_by_path( 'submit-agent' );
			$dashboard_page  = get_page_by_path( 'developer-dashboard' );
			$guidelines_page = get_page_by_path( 'developer-guidelines' );

			// Submit Agent page.
			if ( ! $submit_page ) {
				wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_title'   => 'Submit Agent',
						'post_status'  => 'publish',
						'post_content' => '[agentic_submit_agent]',
					)
				);
			}

			// Developer Dashboard page.
			if ( ! $dashboard_page ) {
				wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_title'   => 'Developer Dashboard',
						'post_status'  => 'publish',
						'post_content' => '[agentic_developer_dashboard]',
					)
				);
			}

			// Developer Guidelines page.
			if ( ! $guidelines_page ) {
				wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_title'  => 'Developer Guidelines',
						'post_status' => 'publish',
					)
				);
			}
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Get developer guidelines page content.
	 *
	 * @return string
	 */
	public function get_developer_guidelines(): string {
		return '
<h2>Agent Builder Developer Guidelines</h2>
<p>Welcome to the Agent Builder developer community! Before submitting your agent, please review these guidelines to ensure a smooth review process.</p>

<h3>1. Code Quality Standards</h3>
<ul>
<li>Your agent must extend the <code>Agentic\Agent_Base</code> class</li>
<li>Follow WordPress coding standards</li>
<li>No obfuscated or minified PHP code</li>
<li>No external phone-home functionality without clear disclosure</li>
<li>Include proper documentation and inline comments</li>
</ul>

<h3>2. Security Requirements</h3>
<ul>
<li>Sanitize all inputs and escape all outputs</li>
<li>Use WordPress nonces for form submissions</li>
<li>Implement proper capability checks</li>
<li>No hardcoded API keys, passwords, or credentials</li>
<li>Follow WordPress security best practices</li>
</ul>

<h3>3. Licensing</h3>
<ul>
<li>Agents must be licensed under GPL-2.0-or-later, or a compatible open-source license</li>
<li>Include license information in the agent.php file header</li>
<li>Respect third-party licenses for any included libraries</li>
<li>Premium agents can charge for support/features but code must be GPL</li>
</ul>

<h3>4. Naming Conventions</h3>
<ul>
<li>Do not use trademarks you do not own (WordPress, OpenAI, etc.)</li>
<li>Agent slugs cannot be changed after approval</li>
<li>Choose a unique, descriptive name that reflects your agent&apos;s purpose</li>
<li>Avoid names that could be confused with official Agent Builder agents</li>
</ul>

<h3>5. Required Files</h3>
<ul>
<li><strong>agent.php</strong> - Main agent file in the root of your ZIP</li>
<li><strong>README.md</strong> - Documentation with usage instructions</li>
<li>Proper file headers with: Agent Name, Version, Description, Author, License</li>
</ul>

<h3>6. Review Process</h3>
<p>After submission, your agent will enter our review queue. We typically review submissions within <strong>14 business days</strong>. During review, we check for:</p>
<ul>
<li>Security vulnerabilities</li>
<li>Code quality and standards compliance</li>
<li>Proper extension of Agent_Base class</li>
<li>License compliance</li>
<li>Accurate description and functionality</li>
</ul>

<p>If issues are found, you will receive an email with details on what needs to be fixed. Once approved, your agent will be published to the marketplace.</p>

<h3>Ready to Submit?</h3>
';
	}

	/**
	 * Create custom database tables
	 *
	 * @return void
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Audit log table.
		$sql_audit = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_audit_log (
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
        ) $charset_collate;";

		// Approval queue table.
		$sql_queue = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_approval_queue (
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
        ) $charset_collate;";

		// Memory table.
		$sql_memory = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}agentic_memory (
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
        ) $charset_collate;";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_audit );
		dbDelta( $sql_queue );
		dbDelta( $sql_memory );

		// Create jobs table.
		Job_Manager::create_table();

		// Create security log table.
		Security_Log::create_table();
	}
}

// Initialize Job Manager.
require_once AGENTIC_PLUGIN_DIR . 'includes/class-job-manager.php';
require_once AGENTIC_PLUGIN_DIR . 'includes/interface-job-processor.php';
require_once AGENTIC_PLUGIN_DIR . 'includes/class-jobs-api.php';
require_once AGENTIC_PLUGIN_DIR . 'includes/class-security-log.php';

Job_Manager::init();
Jobs_API::init();

// Initialize plugin.
Plugin::get_instance();
