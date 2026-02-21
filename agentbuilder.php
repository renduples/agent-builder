<?php
/**
 * Main plugin file.
 *
 * Plugin Name:       Agent Builder
 * Plugin URI:        https://agentic-plugin.com
 * Description:       Build AI agents for WordPress using natural language descriptions.
 * Version:           1.9.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Agent Builder Team
 * Author URI:        https://profiles.wordpress.org/agenticplugin/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentbuilder
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
define( 'AGENT_BUILDER_VERSION', '1.8.1' );
define( 'AGENT_BUILDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENT_BUILDER_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENT_BUILDER_BASENAME', plugin_basename( __FILE__ ) );
define( 'AGENT_BUILDER_FILE', __FILE__ );

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
		// --- Hooks needed on every request (frontend, REST, cron) ---
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_adminbar_chat_overlay' ) );
		add_filter( 'the_content', array( $this, 'render_chat_interface' ) );

		// Cron schedules & handlers (must register on every load so WP-Cron can fire them).
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'agentic_agents_loaded', array( $this, 'bind_agent_cron_hooks' ) );
		add_action( 'agentic_agents_loaded', array( $this, 'bind_agent_event_listeners' ) );
		add_action( 'agentic_async_event', array( $this, 'handle_async_event' ), 10, 4 );
		add_action( 'agentic_cleanup_audit_log', array( $this, 'run_audit_cleanup' ) );
		add_action( 'agentic_agent_activated', array( $this, 'on_agent_activated_schedule' ), 10, 2 );
		add_action( 'agentic_agent_deactivated', array( $this, 'on_agent_deactivated_schedule' ), 10, 2 );

		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// --- Admin-only hooks (menus, settings, AJAX, admin bar, admin assets) ---
		if ( is_admin() ) {
			$this->init_admin_hooks();
		}
	}

	/**
	 * Register hooks that are only needed in the admin context.
	 *
	 * Keeps frontend requests lean by skipping menu registration,
	 * settings, AJAX handlers, and admin-only asset enqueues.
	 *
	 * @return void
	 */
	private function init_admin_hooks(): void {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_adminbar_chat_overlay' ) );

		// AJAX handlers (wp_ajax_ hooks only fire in admin context).
		add_action( 'wp_ajax_agentic_toggle_tool', array( $this, 'ajax_toggle_tool' ) );
		add_action( 'wp_ajax_agentic_run_task', array( $this, 'ajax_run_task' ) );
		add_action( 'wp_ajax_agentic_test_connection', array( $this, 'ajax_test_connection' ) );
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
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'agentbuilder' ),
			);
		}
		return $schedules;
	}

	/**
	 * Current database schema version.
	 *
	 * Bump this when adding migrations in maybe_upgrade_schema().
	 *
	 * @var string
	 */
	private const DB_SCHEMA_VERSION = '1.8.0';

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

		// Migrate old tool names to new prefixed names (one-time).
		$this->maybe_migrate_tool_names();

		// Disable database write tools by default (one-time).
		$this->maybe_disable_db_write_tools();

		// Run database schema upgrades if needed.
		$this->maybe_upgrade_schema();
	}

	/**
	 * Allowed values for the agentic_agent_mode option.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_AGENT_MODES = array( 'supervised', 'autonomous', 'restricted' );

	/**
	 * Admin initialization
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// Redirect to onboarding wizard on first activation.
		if ( get_option( 'agentic_activation_redirect' ) && ! get_option( 'agentic_onboarding_complete' ) ) {
			delete_option( 'agentic_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( admin_url( 'admin.php?page=agentic-setup' ) );
				exit;
			}
		}

		// Register settings.
		register_setting(
			'agentic_core_settings',
			'agentic_agent_mode',
			array(
				'type'              => 'string',
				'default'           => 'supervised',
				'sanitize_callback' => array( $this, 'sanitize_agent_mode' ),
			)
		);
	}

	/**
	 * Sanitize the agent mode setting to an allowed enum value.
	 *
	 * Falls back to 'supervised' if an invalid value is provided.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string Validated agent mode.
	 */
	public function sanitize_agent_mode( $value ): string {
		$value = sanitize_text_field( (string) $value );

		if ( in_array( $value, self::ALLOWED_AGENT_MODES, true ) ) {
			return $value;
		}

		return 'supervised';
	}

	/**
	 * AJAX handler for enabling/disabling individual tools
	 *
	 * Stores an array of disabled tool names in the agentic_disabled_tools option.
	 * When a tool is disabled it is removed from tool definitions sent to the LLM
	 * and execution is blocked in Agent_Tools::execute().
	 *
	 * @return void
	 */
	public function ajax_toggle_tool(): void {
		check_ajax_referer( 'agentic_toggle_tool' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'agentbuilder' ) );
		}

		$tool_name = sanitize_text_field( wp_unslash( $_POST['tool'] ?? '' ) );
		$enabled   = (bool) ( isset( $_POST['enabled'] ) ? rest_sanitize_boolean( wp_unslash( $_POST['enabled'] ) ) : true );

		if ( empty( $tool_name ) ) {
			wp_send_json_error( __( 'Missing tool name.', 'agentbuilder' ) );
		}

		$disabled_tools = get_option( 'agentic_disabled_tools', array() );
		if ( ! is_array( $disabled_tools ) ) {
			$disabled_tools = array();
		}

		if ( $enabled ) {
			$disabled_tools = array_values( array_diff( $disabled_tools, array( $tool_name ) ) );
		} elseif ( ! in_array( $tool_name, $disabled_tools, true ) ) {
				$disabled_tools[] = $tool_name;
		}

		update_option( 'agentic_disabled_tools', $disabled_tools );

		// Log the change.
		$audit = new Audit_Log();
		$audit->log(
			'system',
			$enabled ? 'tool_enabled' : 'tool_disabled',
			$tool_name,
			array( 'user_id' => get_current_user_id() )
		);

		wp_send_json_success(
			array(
				'tool'    => $tool_name,
				'enabled' => $enabled,
			)
		);
	}



	/**
	 * Migrate old tool names to new context-prefixed names.
	 *
	 * Runs once. Updates the agentic_disabled_tools option so that any
	 * previously disabled tools retain their state under the new names.
	 *
	 * @return void
	 */
	private function maybe_migrate_tool_names(): void {
		if ( get_option( 'agentic_tool_names_migrated' ) ) {
			return;
		}

		$old_to_new = array(
			'get_option' => 'db_get_option',
		);

		$disabled = get_option( 'agentic_disabled_tools', array() );
		if ( is_array( $disabled ) && ! empty( $disabled ) ) {
			$migrated = array();
			foreach ( $disabled as $tool ) {
				$migrated[] = $old_to_new[ $tool ] ?? $tool;
			}
			update_option( 'agentic_disabled_tools', array_unique( $migrated ) );
		}

		update_option( 'agentic_tool_names_migrated', '1' );
	}

	/**
	 * Run version-gated database schema upgrades.
	 *
	 * Compares the stored schema version against DB_SCHEMA_VERSION and
	 * runs any migrations that apply. Uses dbDelta for idempotent DDL.
	 *
	 * @return void
	 */
	private function maybe_upgrade_schema(): void {
		$current = get_option( 'agentic_db_schema_version', '0.0.0' );

		if ( version_compare( $current, self::DB_SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// --- v1.8.0: add composite indexes ---
		if ( version_compare( $current, '1.8.0', '<' ) ) {
			$this->migrate_180_indexes( $wpdb, $charset_collate );
		}

		update_option( 'agentic_db_schema_version', self::DB_SCHEMA_VERSION );
	}

	/**
	 * Migration v1.8.0: add composite indexes to core tables.
	 *
	 * Uses dbDelta which is idempotent — safe to run on tables that
	 * already have these indexes.
	 *
	 * @param \wpdb  $wpdb             WordPress database object.
	 * @param string $charset_collate  Character set and collation.
	 * @return void
	 */
	private function migrate_180_indexes( \wpdb $wpdb, string $charset_collate ): void {
		// Audit log: composite indexes for common queries.
		$sql_audit = "CREATE TABLE {$wpdb->prefix}agentic_audit_log (
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
			PRIMARY KEY  (id),
			KEY agent_id (agent_id),
			KEY action (action),
			KEY created_at (created_at),
			KEY idx_agent_created (agent_id, created_at),
			KEY idx_action_target (action, target_type),
			KEY idx_user_created (user_id, created_at)
		) {$charset_collate};";

		dbDelta( $sql_audit );

		// Approval queue: composite index for pending lookups.
		$sql_queue = "CREATE TABLE {$wpdb->prefix}agentic_approval_queue (
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
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY idx_status_created (status, created_at),
			KEY idx_expires (expires_at)
		) {$charset_collate};";

		dbDelta( $sql_queue );

		// Memory table: expiration index for TTL cleanup.
		$sql_memory = "CREATE TABLE {$wpdb->prefix}agentic_memory (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			memory_type varchar(50) NOT NULL,
			entity_id varchar(100) NOT NULL,
			memory_key varchar(255) NOT NULL,
			memory_value longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY memory_type_entity (memory_type, entity_id),
			KEY memory_key (memory_key),
			KEY idx_expires (expires_at)
		) {$charset_collate};";

		dbDelta( $sql_memory );
	}

	/**
	 * One-time migration: disable database write tools by default.
	 *
	 * New installs and existing installs both get write tools disabled
	 * until an administrator explicitly enables them via the Tools page.
	 *
	 * @return void
	 */
	private function maybe_disable_db_write_tools(): void {
		if ( get_option( 'agentic_db_write_tools_defaults_set' ) ) {
			return;
		}

		$write_tools = array(
			'db_update_option',
			'db_create_post',
			'db_update_post',
			'db_delete_post',
		);

		$disabled = get_option( 'agentic_disabled_tools', array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		$disabled = array_unique( array_merge( $disabled, $write_tools ) );
		update_option( 'agentic_disabled_tools', $disabled );
		update_option( 'agentic_db_write_tools_defaults_set', '1' );
	}

	/**
	 * AJAX handler: execute a scheduled task and return JSON results.
	 *
	 * @return void
	 */
	public function ajax_run_task(): void {
		check_ajax_referer( 'agentic_run_task' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'agentbuilder' ) ) );
		}

		$agent_id = sanitize_text_field( wp_unslash( $_POST['agent'] ?? '' ) );
		$task_id  = sanitize_text_field( wp_unslash( $_POST['task'] ?? '' ) );

		if ( empty( $agent_id ) || empty( $task_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing agent or task parameter.', 'agentbuilder' ) ) );
		}

		$registry = \Agentic_Agent_Registry::get_instance();
		$instance = $registry->get_agent_instance( $agent_id );

		if ( ! $instance ) {
			wp_send_json_error( array( 'message' => __( 'Agent not found or not active.', 'agentbuilder' ) ) );
		}

		$task_def = null;
		foreach ( $instance->get_scheduled_tasks() as $t ) {
			if ( $t['id'] === $task_id ) {
				$task_def = $t;
				break;
			}
		}

		if ( ! $task_def ) {
			wp_send_json_error( array( 'message' => __( 'Task not found on this agent.', 'agentbuilder' ) ) );
		}

		$start_time = microtime( true );
		$error_msg  = null;

		try {
			$this->execute_scheduled_task( $instance, $task_def );
		} catch ( \Throwable $e ) {
			$error_msg = $e->getMessage();
		}

		$duration = round( microtime( true ) - $start_time, 2 );

		// Fetch result from audit log.
		global $wpdb;
		$audit_table = $wpdb->prefix . 'agentic_audit_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time admin read.
		$result_json = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix.
				"SELECT details FROM {$audit_table} WHERE agent_id = %s AND action = 'scheduled_task_complete' AND target_type = %s ORDER BY id DESC LIMIT 1",
				$agent_id,
				$task_id
			)
		);

		$details = $result_json ? json_decode( $result_json, true ) : null;

		// Extract LLM response text.
		$response_text = '';
		if ( is_array( $details ) && ! empty( $details['result'] ) && is_string( $details['result'] ) ) {
			$decoded = json_decode( $details['result'], true );
			if ( is_array( $decoded ) && ! empty( $decoded['response'] ) ) {
				$response_text = $decoded['response'];
			} elseif ( str_starts_with( $details['result'], '{"response":"' ) ) {
				// Handle truncated JSON from older audit entries.
				$inner         = substr( $details['result'], 14 );
				$inner         = rtrim( $inner, '"}' );
				$response_text = str_replace(
					array( '\\n', '\\t', '\\/', '\\"', '\\\\' ),
					array( "\n", "\t", '/', '"', '\\' ),
					$inner
				);
			} else {
				$response_text = $details['result'];
			}
		}

		if ( $error_msg ) {
			wp_send_json_error(
				array(
					'message'  => $error_msg,
					'duration' => $duration,
				)
			);
		}

		wp_send_json_success(
			array(
				'duration'   => $duration,
				'duration_s' => $details['duration_s'] ?? null,
				'response'   => $response_text,
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
			__( 'Agent Builder', 'agentbuilder' ),
			__( 'Agent Builder', 'agentbuilder' ),
			'manage_options',
			'agentbuilder',
			array( $this, 'render_admin_page' ),
			'dashicons-superhero',
			30
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Dashboard', 'agentbuilder' ),
			__( 'Dashboard', 'agentbuilder' ),
			'manage_options',
			'agentbuilder',
			array( $this, 'render_admin_page' )
		);

		// Agent Chat.
		add_submenu_page(
			'agentbuilder',
			__( 'Agent Chat', 'agentbuilder' ),
			__( 'Agent Chat', 'agentbuilder' ),
			'read',
			'agentic-chat',
			array( $this, 'render_chat_page' )
		);

		// Agents menu (like Plugins menu).
		add_submenu_page(
			'agentbuilder',
			__( 'Installed Agents', 'agentbuilder' ),
			__( 'Installed Agents', 'agentbuilder' ),
			'manage_options',
			'agentic-agents',
			array( $this, 'render_agents_page' )
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Audit Log', 'agentbuilder' ),
			__( 'Audit Log', 'agentbuilder' ),
			'manage_options',
			'agentic-audit',
			array( $this, 'render_audit_log_page' )
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Agent Deployment', 'agentbuilder' ),
			__( 'Agent Deployment', 'agentbuilder' ),
			'manage_options',
			'agentic-deployment',
			array( $this, 'render_deployment_page' )
		);

		// Hidden pages — keep old slugs so bookmarks/links still work.
		add_submenu_page(
			null,
			__( 'Scheduled Tasks', 'agentbuilder' ),
			__( 'Scheduled Tasks', 'agentbuilder' ),
			'manage_options',
			'agentic-scheduled-tasks',
			array( $this, 'redirect_to_deployment_tab' )
		);

		add_submenu_page(
			null,
			__( 'Event Listeners', 'agentbuilder' ),
			__( 'Event Listeners', 'agentbuilder' ),
			'manage_options',
			'agentic-event-listeners',
			array( $this, 'redirect_to_deployment_tab' )
		);

		// Hidden page — Run Task (dedicated execution page).
		add_submenu_page(
			null,
			__( 'Run Task', 'agentbuilder' ),
			__( 'Run Task', 'agentbuilder' ),
			'manage_options',
			'agentic-run-task',
			array( $this, 'render_run_task_page' )
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Agent Tools', 'agentbuilder' ),
			__( 'Agent Tools', 'agentbuilder' ),
			'manage_options',
			'agentic-tools',
			array( $this, 'render_tools_page' )
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Approval Queue', 'agentbuilder' ),
			__( 'Approval Queue', 'agentbuilder' ),
			'manage_options',
			'agentic-approvals',
			array( $this, 'render_approvals_page' )
		);

		add_submenu_page(
			'agentbuilder',
			__( 'Settings', 'agentbuilder' ),
			__( 'Settings', 'agentbuilder' ),
			'manage_options',
			'agentic-settings',
			array( $this, 'render_settings_page' )
		);

		// Setup wizard — hidden from nav, accessible at admin.php?page=agentic-setup.
		add_submenu_page(
			null,
			__( 'Setup Wizard', 'agentbuilder' ),
			__( 'Setup Wizard', 'agentbuilder' ),
			'manage_options',
			'agentic-setup',
			array( $this, 'render_setup_page' )
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

		// — "AI Agents" quick-chat menu (opens overlay) —
		$registry     = \Agentic_Agent_Registry::get_instance();
		$active_slugs = $registry->get_active_agents();
		$all_agents   = $registry->get_installed_agents();

		if ( empty( $active_slugs ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'agentic-chat-bar',
				'title' => '<span class="ab-icon dashicons dashicons-format-chat" style="font-size: 18px; line-height: 1.3;"></span>' . __( 'AI Agents', 'agentbuilder' ),
				'href'  => '#',
				'meta'  => array(
					'title' => __( 'Chat with an AI Agent', 'agentbuilder' ),
				),
			)
		);

		// Sort so wordpress-assistant appears first (as "Helper").
		$sorted_slugs = $active_slugs;
		usort(
			$sorted_slugs,
			function ( $a, $b ) {
				if ( 'wordpress-assistant' === $a ) {
					return -1;
				}
				if ( 'wordpress-assistant' === $b ) {
					return 1;
				}
				return 0;
			}
		);

		foreach ( $sorted_slugs as $slug ) {
			if ( ! isset( $all_agents[ $slug ] ) ) {
				continue;
			}
			$agent_info = $all_agents[ $slug ];
			$icon       = $agent_info['icon'] ?? '🤖';

			// Show wordpress-assistant as "Helper" for a friendlier label.
			if ( 'wordpress-assistant' === $slug ) {
				$name = __( 'Helper', 'agentbuilder' );
			} else {
				$name = $agent_info['name'] ?? ucwords( str_replace( '-', ' ', $slug ) );
			}

			$wp_admin_bar->add_node(
				array(
					'id'     => 'agentic-chat-' . $slug,
					'parent' => 'agentic-chat-bar',
					'title'  => $icon . ' ' . esc_html( $name ),
					'href'   => '#agentic-chat-' . $slug,
					'meta'   => array(
						'class' => 'agentic-chat-trigger-bar',
					),
				)
			);
		}
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
					'name'          => __( 'Agent Audit Logs', 'agentbuilder' ),
					'singular_name' => __( 'Audit Log', 'agentbuilder' ),
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
					'name'          => __( 'Agent Approvals', 'agentbuilder' ),
					'singular_name' => __( 'Approval', 'agentbuilder' ),
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
		include_once AGENT_BUILDER_DIR . 'includes/class-llm-client.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-audit-log.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-agent-permissions.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-agent-proposals.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-agent-tools.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-abilities-bridge.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-agent-controller.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-rest-api.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-approval-queue.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-agentic-agent-registry.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-chat-security.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-response-cache.php';
		include_once AGENT_BUILDER_DIR . 'includes/class-shortcodes.php';

		// System requirements checker.
		include_once AGENT_BUILDER_DIR . 'includes/class-system-checker.php';

		// License client — handles revalidation, update gating, feature degradation.
		include_once AGENT_BUILDER_DIR . 'includes/class-license-client.php';
		License_Client::get_instance();

		// Initialize components.
		new REST_API();
		new Approval_Queue();
		new \Agentic\Shortcodes();

		// Bridge to WordPress Abilities API (WP 6.9+).
		if ( function_exists( 'wp_register_ability' ) ) {
			$abilities_bridge = new Abilities_Bridge( new Agent_Tools() );
			$abilities_bridge->register_hooks();
		}

		// Initialize Social Auth (for custom login/register with OAuth).

		// Load active agents (like WordPress loads active plugins).
		\Agentic_Agent_Registry::get_instance()->load_active_agents();
	}

	/**
	 * Render admin dashboard page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		include AGENT_BUILDER_DIR . 'admin/dashboard.php';
	}

	/**
	 * Render audit log page
	 *
	 * @return void
	 */
	public function render_audit_log_page(): void {
		include AGENT_BUILDER_DIR . 'admin/audit.php';
	}

	/**
	 * Render the unified Agent Deployment page (Shortcodes / Scheduled Tasks / Event Listeners).
	 *
	 * @return void
	 */
	public function render_deployment_page(): void {
		include AGENT_BUILDER_DIR . 'admin/deployment.php';
	}

	/**
	 * Render the dedicated Run Task page.
	 *
	 * @return void
	 */
	public function render_run_task_page(): void {
		include AGENT_BUILDER_DIR . 'admin/run-task.php';
	}

	/**
	 * Redirect legacy Scheduled Tasks / Event Listeners pages to the Deployment page.
	 *
	 * @return void
	 */
	public function redirect_to_deployment_tab(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect based on page slug.
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		$tab  = 'agentic-scheduled-tasks' === $page ? 'scheduled-tasks' : 'event-listeners';
		wp_safe_redirect( admin_url( 'admin.php?page=agentic-deployment&tab=' . $tab ) );
		exit;
	}

	/**
	 * Render agent tools page
	 *
	 * @return void
	 */
	public function render_tools_page(): void {
		include AGENT_BUILDER_DIR . 'admin/tools.php';
	}

	/**
	 * Bind cron hooks for all active agents' scheduled tasks
	 *
	 * Called on 'agentic_agents_loaded' action.
	 *
	 * @return void
	 */
	public function bind_agent_cron_hooks(): void {
		$registry  = \Agentic_Agent_Registry::get_instance();
		$instances = $registry->get_all_instances();

		foreach ( $instances as $agent ) {
			$tasks = $agent->get_scheduled_tasks();

			foreach ( $tasks as $task ) {
				$hook = $agent->get_cron_hook( $task['id'] );

				add_action(
					$hook,
					function () use ( $agent, $task ) {
						$this->execute_scheduled_task( $agent, $task );
					}
				);
			}
		}
	}

	/**
	 * Execute a scheduled task with outcome logging and optional LLM routing
	 *
	 * If the task defines a 'prompt' field and the LLM is configured, the task
	 * runs through Agent_Controller::run_autonomous_task() (full AI reasoning
	 * with tool calls). Otherwise it falls back to calling the agent's callback
	 * method directly.
	 *
	 * Every execution is wrapped with start/complete/error audit logging including
	 * duration timing, so admins can see exactly what happened and how long it took.
	 *
	 * @param \Agentic\Agent_Base $agent Agent instance.
	 * @param array               $task  Task definition from get_scheduled_tasks().
	 * @return void
	 */
	public function execute_scheduled_task( \Agentic\Agent_Base $agent, array $task ): void {
		$audit    = new \Agentic\Audit_Log();
		$start    = microtime( true );
		$agent_id = $agent->get_id();
		$mode     = ! empty( $task['prompt'] ) ? 'autonomous' : 'direct';

		// Log task start.
		$audit->log(
			$agent_id,
			'scheduled_task_start',
			$task['id'],
			array(
				'task_name' => $task['name'],
				'schedule'  => $task['schedule'],
				'mode'      => $mode,
			)
		);

		try {
			$result = null;

			// If task has a prompt, route through LLM for autonomous execution.
			if ( ! empty( $task['prompt'] ) ) {
				$controller = new \Agentic\Agent_Controller();
				$result     = $controller->run_autonomous_task( $agent, $task['prompt'], $task['id'] );
			}

			// Fallback to direct callback if no prompt or LLM not configured.
			if ( null === $result && method_exists( $agent, $task['callback'] ) ) {
				call_user_func( array( $agent, $task['callback'] ) );
				$result = array(
					'mode'   => 'direct',
					'status' => 'completed',
				);
			}

			$duration = round( microtime( true ) - $start, 3 );

			// Log task completion.
			$audit->log(
				$agent_id,
				'scheduled_task_complete',
				$task['id'],
				array(
					'task_name'  => $task['name'],
					'duration_s' => $duration,
					'mode'       => $mode,
					'result'     => is_array( $result ) ? substr( wp_json_encode( $result ), 0, 1000 ) : null,
				)
			);
		} catch ( \Throwable $e ) {
			$duration = round( microtime( true ) - $start, 3 );

			// Log task error.
			$audit->log(
				$agent_id,
				'scheduled_task_error',
				$task['id'],
				array(
					'task_name'  => $task['name'],
					'duration_s' => $duration,
					'error'      => $e->getMessage(),
					'file'       => $e->getFile() . ':' . $e->getLine(),
				)
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
				error_log(
					sprintf(
						'Agentic scheduled task error (%s/%s): %s',
						$agent_id,
						$task['id'],
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Bind WordPress action hooks for all active agents' event listeners
	 *
	 * Called on 'agentic_agents_loaded' action.
	 *
	 * @return void
	 */
	public function bind_agent_event_listeners(): void {
		$registry  = \Agentic_Agent_Registry::get_instance();
		$instances = $registry->get_all_instances();

		foreach ( $instances as $agent ) {
			$listeners = $agent->get_event_listeners();

			foreach ( $listeners as $listener ) {
				$priority      = $listener['priority'] ?? 10;
				$accepted_args = $listener['accepted_args'] ?? 1;

				add_action(
					$listener['hook'],
					function () use ( $agent, $listener ) {
						$args = func_get_args();
						$this->execute_event_listener( $agent, $listener, $args );
					},
					$priority,
					$accepted_args
				);
			}
		}
	}

	/**
	 * Execute an event listener with outcome logging
	 *
	 * For direct mode: calls the agent callback synchronously.
	 * For autonomous mode (prompt defined): queues an async LLM task via
	 * wp_schedule_single_event so it doesn't block the current request.
	 *
	 * @param \Agentic\Agent_Base $agent    Agent instance.
	 * @param array               $listener Listener definition.
	 * @param array               $args     WordPress hook arguments.
	 * @return void
	 */
	public function execute_event_listener( \Agentic\Agent_Base $agent, array $listener, array $args ): void {
		$audit    = new \Agentic\Audit_Log();
		$start    = microtime( true );
		$agent_id = $agent->get_id();
		$mode     = ! empty( $listener['prompt'] ) ? 'autonomous' : 'direct';

		// Log event trigger.
		$audit->log(
			$agent_id,
			'event_listener_triggered',
			$listener['id'],
			array(
				'listener_name' => $listener['name'],
				'hook'          => $listener['hook'],
				'mode'          => $mode,
				'args_summary'  => substr( wp_json_encode( $this->sanitize_hook_args( $args ) ), 0, 500 ),
			)
		);

		try {
			if ( ! empty( $listener['prompt'] ) ) {
				// Queue async LLM execution so we don't block the current request.
				wp_schedule_single_event(
					time(),
					'agentic_async_event',
					array(
						$agent_id,
						$listener['id'],
						$listener['prompt'],
						$this->sanitize_hook_args( $args ),
					)
				);
				return; // Actual execution happens in handle_async_event.
			}

			// Direct mode: call agent callback synchronously.
			if ( method_exists( $agent, $listener['callback'] ) ) {
				call_user_func_array( array( $agent, $listener['callback'] ), $args );
			}

			$duration = round( microtime( true ) - $start, 3 );

			$audit->log(
				$agent_id,
				'event_listener_complete',
				$listener['id'],
				array(
					'listener_name' => $listener['name'],
					'hook'          => $listener['hook'],
					'duration_s'    => $duration,
					'mode'          => 'direct',
				)
			);
		} catch ( \Throwable $e ) {
			$duration = round( microtime( true ) - $start, 3 );

			$audit->log(
				$agent_id,
				'event_listener_error',
				$listener['id'],
				array(
					'listener_name' => $listener['name'],
					'hook'          => $listener['hook'],
					'duration_s'    => $duration,
					'error'         => $e->getMessage(),
					'file'          => $e->getFile() . ':' . $e->getLine(),
				)
			);
		}
	}

	/**
	 * Handle async event processing via WP-Cron single event
	 *
	 * Runs the LLM with the event prompt and serialized hook arguments.
	 *
	 * @param string $agent_id    Agent ID.
	 * @param string $listener_id Listener ID.
	 * @param string $prompt      Base prompt.
	 * @param array  $hook_args   Sanitized hook arguments.
	 * @return void
	 */
	public function handle_async_event( string $agent_id, string $listener_id, string $prompt, array $hook_args ): void {
		$registry = \Agentic_Agent_Registry::get_instance();
		$agent    = $registry->get_agent_instance( $agent_id );
		$audit    = new \Agentic\Audit_Log();

		if ( ! $agent ) {
			$audit->log( $agent_id, 'event_listener_error', $listener_id, array( 'error' => 'Agent not found for async event' ) );
			return;
		}

		// Build context-enriched prompt.
		$context_json = wp_json_encode( $hook_args, JSON_PRETTY_PRINT );
		$full_prompt  = $prompt . "\n\n[EVENT CONTEXT]\n" . $context_json;

		$start = microtime( true );

		try {
			$controller = new \Agentic\Agent_Controller();
			$result     = $controller->run_autonomous_task( $agent, $full_prompt, 'event_' . $listener_id );

			// If LLM not configured, try direct fallback.
			if ( null === $result ) {
				$listeners = $agent->get_event_listeners();
				foreach ( $listeners as $listener ) {
					if ( $listener['id'] === $listener_id && method_exists( $agent, $listener['callback'] ) ) {
						call_user_func( array( $agent, $listener['callback'] ), ...$hook_args );
						$result = array(
							'mode'   => 'direct_fallback',
							'status' => 'completed',
						);
						break;
					}
				}
			}

			$duration = round( microtime( true ) - $start, 3 );

			$audit->log(
				$agent_id,
				'event_listener_complete',
				$listener_id,
				array(
					'duration_s' => $duration,
					'mode'       => 'autonomous',
					'result'     => is_array( $result ) ? substr( wp_json_encode( $result ), 0, 1000 ) : null,
				)
			);
		} catch ( \Throwable $e ) {
			$duration = round( microtime( true ) - $start, 3 );

			$audit->log(
				$agent_id,
				'event_listener_error',
				$listener_id,
				array(
					'duration_s' => $duration,
					'error'      => $e->getMessage(),
					'file'       => $e->getFile() . ':' . $e->getLine(),
				)
			);
		}
	}

	/**
	 * Sanitize hook arguments for safe serialization
	 *
	 * Converts WP objects to arrays, truncates large values, removes non-serializable data.
	 *
	 * @param array $args Raw hook arguments.
	 * @return array Sanitized arguments.
	 */
	private function sanitize_hook_args( array $args ): array {
		$sanitized = array();

		foreach ( $args as $key => $value ) {
			if ( $value instanceof \WP_Post ) {
				$sanitized[ $key ] = array(
					'_type'       => 'WP_Post',
					'ID'          => $value->ID,
					'post_title'  => $value->post_title,
					'post_type'   => $value->post_type,
					'post_status' => $value->post_status,
					'post_author' => $value->post_author,
				);
			} elseif ( $value instanceof \WP_Comment ) {
				$sanitized[ $key ] = array(
					'_type'           => 'WP_Comment',
					'comment_ID'      => $value->comment_ID,
					'comment_post_ID' => $value->comment_post_ID,
					'comment_author'  => $value->comment_author,
					'comment_content' => substr( $value->comment_content, 0, 500 ),
				);
			} elseif ( $value instanceof \WP_User ) {
				$sanitized[ $key ] = array(
					'_type'        => 'WP_User',
					'ID'           => $value->ID,
					'user_login'   => $value->user_login,
					'display_name' => $value->display_name,
					'roles'        => $value->roles,
				);
			} elseif ( is_object( $value ) ) {
				$sanitized[ $key ] = array(
					'_type' => get_class( $value ),
					'_note' => 'Object serialized to class name only',
				);
			} elseif ( is_string( $value ) && strlen( $value ) > 1000 ) {
				$sanitized[ $key ] = substr( $value, 0, 1000 ) . '... [truncated]';
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Register cron events when an agent is activated
	 *
	 * @param string     $slug  Agent slug.
	 * @param array|null $agent Agent data (unused, required by hook signature).
	 * @return void
	 */
	public function on_agent_activated_schedule( string $slug, $agent ): void {
		unset( $agent ); // Unused parameter required by hook signature.
		$registry = \Agentic_Agent_Registry::get_instance();
		$instance = $registry->get_agent_instance( $slug );

		if ( $instance ) {
			$instance->register_scheduled_tasks();
		}
	}

	/**
	 * Unregister cron events when an agent is deactivated
	 *
	 * @param string     $slug  Agent slug.
	 * @param array|null $agent Agent data (unused, required by hook signature).
	 * @return void
	 */
	public function on_agent_deactivated_schedule( string $slug, $agent ): void {
		unset( $agent ); // Unused parameter required by hook signature.
		$registry = \Agentic_Agent_Registry::get_instance();
		$instance = $registry->get_agent_instance( $slug );

		if ( $instance ) {
			$instance->unregister_scheduled_tasks();
		}
	}

	/**
	 * Run the daily audit log retention cleanup.
	 *
	 * Hooked to the 'agentic_cleanup_audit_log' cron event.
	 *
	 * @return void
	 */
	public function run_audit_cleanup(): void {
		$audit = new Audit_Log();
		$audit->cleanup_expired();
	}

	/**
	 * Render approvals page
	 *
	 * @return void
	 */
	public function render_approvals_page(): void {
		include AGENT_BUILDER_DIR . 'admin/approvals.php';
	}

	/**
	 * Render installed agents page
	 *
	 * @return void
	 */
	public function render_agents_page(): void {
		include AGENT_BUILDER_DIR . 'admin/agents.php';
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
			AGENT_BUILDER_URL . 'assets/css/chat.css',
			array(),
			(string) filemtime( AGENT_BUILDER_DIR . 'assets/css/chat.css' )
		);

		wp_enqueue_script(
			'agentic-chat',
			AGENT_BUILDER_URL . 'assets/js/chat.js',
			array(),
			(string) filemtime( AGENT_BUILDER_DIR . 'assets/js/chat.js' ),
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
		echo '<h1>' . esc_html__( 'Agent Chat', 'agentbuilder' ) . ' <span class="agentic-status" style="font-size: 14px; font-weight: normal; vertical-align: middle;"><span class="agentic-status-dot"></span>Online</span></h1>';
		include AGENT_BUILDER_DIR . 'templates/chat-interface.php';
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
			AGENT_BUILDER_URL . 'assets/js/settings.js',
			array(),
			(string) filemtime( AGENT_BUILDER_DIR . 'assets/js/settings.js' ),
			true
		);

		include AGENT_BUILDER_DIR . 'admin/settings.php';
	}

	/**
	 * Render setup wizard page
	 *
	 * @return void
	 */
	public function render_setup_page(): void {
		include AGENT_BUILDER_DIR . 'admin/setup.php';
	}

	/**
	 * AJAX: test AI provider connection during onboarding
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'agentic_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'agentbuilder' ) ) );
		}

		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
		$api_key  = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

		$allowed = array( 'xai', 'openai', 'google', 'anthropic', 'mistral', 'ollama' );
		if ( ! in_array( $provider, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid provider.', 'agentbuilder' ) ) );
		}

		// Build a minimal test request for each provider.
		$endpoints = array(
			'openai'    => array(
				'url'     => 'https://api.openai.com/v1/chat/completions',
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'gpt-4o-mini', 'messages' => array( array( 'role' => 'user', 'content' => 'Reply with: ready' ) ), 'max_tokens' => 5 ) ),
			),
			'anthropic' => array(
				'url'     => 'https://api.anthropic.com/v1/messages',
				'headers' => array( 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'claude-3-haiku-20240307', 'max_tokens' => 5, 'messages' => array( array( 'role' => 'user', 'content' => 'Reply with: ready' ) ) ) ),
			),
			'xai'       => array(
				'url'     => 'https://api.x.ai/v1/chat/completions',
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'grok-3', 'messages' => array( array( 'role' => 'user', 'content' => 'Reply with: ready' ) ), 'max_tokens' => 5 ) ),
			),
			'google'    => array(
				'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . rawurlencode( $api_key ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'contents' => array( array( 'parts' => array( array( 'text' => 'Reply with: ready' ) ) ) ) ) ),
			),
			'mistral'   => array(
				'url'     => 'https://api.mistral.ai/v1/chat/completions',
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'mistral-small-latest', 'messages' => array( array( 'role' => 'user', 'content' => 'Reply with: ready' ) ), 'max_tokens' => 5 ) ),
			),
			'ollama'    => array(
				'url'     => rtrim( $api_key, '/' ) . '/api/tags',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => null,
			),
		);

		$cfg      = $endpoints[ $provider ];
		$response = wp_remote_post(
			$cfg['url'],
			array(
				'headers' => $cfg['headers'],
				'body'    => $cfg['body'],
				'timeout' => 15,
				'method'  => 'ollama' === $provider ? 'GET' : 'POST',
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( array( 'message' => __( 'Connected successfully!', 'agentbuilder' ) ) );
		} else {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$msg  = $data['error']['message'] ?? $data['error'] ?? __( 'Connection failed (HTTP ' . $code . '). Check your API key.', 'agentbuilder' );
			wp_send_json_error( array( 'message' => $msg ) );
		}
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
				AGENT_BUILDER_URL . 'assets/css/chat.css',
				array(),
				(string) filemtime( AGENT_BUILDER_DIR . 'assets/css/chat.css' )
			);

			wp_enqueue_script(
				'agentic-chat',
				AGENT_BUILDER_URL . 'assets/js/chat.js',
				array(),
				(string) filemtime( AGENT_BUILDER_DIR . 'assets/js/chat.js' ),
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
	 * Enqueue assets for admin-bar chat overlay
	 *
	 * Loads on every page where the admin bar is visible so that clicking
	 * an "AI Agents" sub-menu item opens an overlay chat panel.
	 *
	 * @return void
	 */
	public function enqueue_adminbar_chat_overlay(): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'agentic-chat-overlay',
			AGENT_BUILDER_URL . 'assets/css/chat-overlay.css',
			array(),
			(string) filemtime( AGENT_BUILDER_DIR . 'assets/css/chat-overlay.css' )
		);

		wp_enqueue_script(
			'agentic-chat-overlay',
			AGENT_BUILDER_URL . 'assets/js/chat-overlay.js',
			array(),
			(string) filemtime( AGENT_BUILDER_DIR . 'assets/js/chat-overlay.js' ),
			true
		);

		// Build welcome messages and agent names map for active agents.
		$agentic_welcome_messages = array();
		$agentic_agent_names      = array();
		$agentic_registry         = \Agentic_Agent_Registry::get_instance();
		$agentic_registry->load_active_agents();

		$agentic_instances = $agentic_registry->get_all_instances();
		foreach ( $agentic_instances as $agentic_instance ) {
			$agentic_agent_names[ $agentic_instance->get_id() ] = $agentic_instance->get_name();
			$agentic_msg                                        = $agentic_instance->get_welcome_message();
			if ( $agentic_msg ) {
				$agentic_welcome_messages[ $agentic_instance->get_id() ] = $agentic_msg;
			}
		}

		wp_localize_script(
			'agentic-chat-overlay',
			'agenticChat',
			array(
				'restUrl'         => rest_url( 'agentic/v1/' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'userId'          => get_current_user_id(),
				'userName'        => wp_get_current_user()->display_name,
				'welcomeMessages' => $agentic_welcome_messages,
				'agentNames'      => $agentic_agent_names,
			)
		);
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
				include AGENT_BUILDER_DIR . 'templates/chat-interface.php';
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
		// Flag for onboarding redirect (handled in admin_init to avoid headers-sent issues).
		add_option( 'agentic_activation_redirect', true );

		// Set default options.
		add_option( 'agentic_agent_mode', 'supervised' );
		add_option( 'agentic_audit_enabled', true );
		add_option( 'agentic_llm_provider', 'openai' );
		add_option( 'agentic_llm_api_key', '' );
		add_option( 'agentic_model', 'gpt-4o' );

		// Activate all bundled library agents.
		$this->activate_bundled_agents();

		// Create database tables.
		$this->create_tables();

		// Schedule daily audit log cleanup.
		if ( ! wp_next_scheduled( 'agentic_cleanup_audit_log' ) ) {
			wp_schedule_event( time(), 'daily', 'agentic_cleanup_audit_log' );
		}

		// Create chat page if it doesn't exist.
		$chat_page = get_page_by_path( 'agent-chat' );
		if ( ! $chat_page ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => 'WordPress Assistant',
					'post_name'    => 'agent-chat',
					'post_status'  => 'publish',
					'post_content' => '<!-- Chat interface rendered by Agent Builder -->',
				)
			);
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Activate all bundled library agents on plugin activation.
	 *
	 * Scans the library directory and ensures every bundled agent
	 * is present in the agentic_active_agents option.
	 *
	 * @return void
	 */
	private function activate_bundled_agents(): void {
		$library_dir = plugin_dir_path( __FILE__ ) . 'library';

		if ( ! is_dir( $library_dir ) ) {
			return;
		}

		$folders = scandir( $library_dir );

		if ( ! is_array( $folders ) ) {
			return;
		}

		$bundled_slugs = array();

		foreach ( $folders as $folder ) {
			if ( '.' === $folder || '..' === $folder || 'README.md' === $folder ) {
				continue;
			}

			$agent_path = $library_dir . '/' . $folder;

			// Must be a directory with an agent.php file.
			if ( is_dir( $agent_path ) && file_exists( $agent_path . '/agent.php' ) ) {
				$bundled_slugs[] = $folder;
			}
		}

		if ( empty( $bundled_slugs ) ) {
			return;
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		$merged        = array_unique( array_merge( $active_agents, $bundled_slugs ) );

		update_option( 'agentic_active_agents', array_values( $merged ) );
	}

	/**
	 * Plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( 'agentic_cleanup_audit_log' );
		flush_rewrite_rules();
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
require_once AGENT_BUILDER_DIR . 'includes/class-job-manager.php';
require_once AGENT_BUILDER_DIR . 'includes/interface-job-processor.php';
require_once AGENT_BUILDER_DIR . 'includes/class-agent-builder-job-processor.php';
require_once AGENT_BUILDER_DIR . 'includes/class-jobs-api.php';
require_once AGENT_BUILDER_DIR . 'includes/class-security-log.php';

Job_Manager::init();
Jobs_API::init();

// Initialize plugin.
Plugin::get_instance();
