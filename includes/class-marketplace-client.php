<?php
/**
 * Marketplace Client
 *
 * Handles communication with the marketplace API from client WordPress installations.
 * Provides one-click install functionality for agents.
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marketplace Client Class
 *
 * @since 0.2.0
 */
class Marketplace_Client {

	/**
	 * Marketplace API base URL
	 */
	private string $api_base;

	/**
	 * Cache duration in seconds
	 */
	private const CACHE_DURATION = 3600; // 1 hour

	/**
	 * Initialize the client
	 */
	public function __construct() {
		// Allow override for local development
		$this->api_base = defined( 'AGENTIC_MARKETPLACE_URL' )
			? AGENTIC_MARKETPLACE_URL
			: 'https://agentic-plugin.com';

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_agentic_browse_agents', array( $this, 'ajax_browse_agents' ) );
		add_action( 'wp_ajax_agentic_get_agent', array( $this, 'ajax_get_agent' ) );
		add_action( 'wp_ajax_agentic_install_agent', array( $this, 'ajax_install_agent' ) );
		add_action( 'wp_ajax_agentic_activate_agent', array( $this, 'ajax_activate_agent' ) );
		add_action( 'wp_ajax_agentic_deactivate_agent', array( $this, 'ajax_deactivate_agent' ) );
		add_action( 'wp_ajax_agentic_update_agent', array( $this, 'ajax_update_agent' ) );
		add_action( 'wp_ajax_agentic_rate_agent', array( $this, 'ajax_rate_agent' ) );

		// Schedule update checks
		add_action( 'init', array( $this, 'schedule_update_checks' ) );
		add_action( 'agentic_check_agent_updates', array( $this, 'check_for_updates' ) );
	}

	/**
	 * Schedule daily update checks
	 */
	public function schedule_update_checks(): void {
		if ( ! wp_next_scheduled( 'agentic_check_agent_updates' ) ) {
			wp_schedule_event( time(), 'daily', 'agentic_check_agent_updates' );
		}
	}

	/**
	 * Check for agent updates (runs daily via cron)
	 */
	public function check_for_updates(): void {
		$registry  = \Agentic_Agent_Registry::get_instance();
		$installed = $registry->get_installed_agents( true );
		$updates   = array();

		foreach ( $installed as $slug => $agent ) {
			// Skip bundled agents - they update with the plugin
			if ( ! empty( $agent['bundled'] ) ) {
				continue;
			}

			// Get stored license for this agent (if premium)
			$licenses    = get_option( 'agentic_licenses', array() );
			$license_key = $licenses[ $slug ]['license_key'] ?? '';

			// Check marketplace for latest version
			$params = array(
				'current_version' => $agent['version'] ?? '0.0.0',
				'site_url'        => home_url(),
			);

			// Add license key if agent is premium
			if ( ! empty( $license_key ) ) {
				$params['license_key'] = $license_key;
			}

			$response = $this->api_request( "agents/{$slug}/version", $params, 'GET' );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			// Check if update requires license renewal
			if ( isset( $response['error']['code'] ) && 'license_required' === $response['error']['code'] ) {
				$updates[ $slug ] = array(
					'current'         => $agent['version'] ?? '0.0.0',
					'latest'          => 'unknown',
					'license_expired' => true,
					'renew_url'       => $response['error']['renew_url'] ?? '',
					'name'            => $agent['name'] ?? $slug,
				);
				continue;
			}

			if ( ! isset( $response['data']['latest_version'] ) ) {
				continue;
			}

			$current_version = $agent['version'] ?? '0.0.0';
			$latest_version  = $response['data']['latest_version'];

			if ( version_compare( $latest_version, $current_version, '>' ) ) {
				$updates[ $slug ] = array(
					'current' => $current_version,
					'latest'  => $latest_version,
					'package' => $response['data']['download_url'] ?? '',
					'name'    => $agent['name'] ?? $slug,
				);
			}
		}

		// Store updates in transient (12 hours)
		set_transient( 'agentic_available_updates', $updates, 12 * HOUR_IN_SECONDS );

		do_action( 'agentic_updates_checked', $updates );
	}

	/**
	 * Get available updates
	 *
	 * @return array
	 */
	public function get_available_updates(): array {
		$updates = get_transient( 'agentic_available_updates' );

		if ( false === $updates ) {
			return array();
		}

		return $updates;
	}

	/**
	 * Add admin menu page
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'agentic-plugin',
			__( 'Add New Agent', 'agentic-plugin' ),
			__( 'Add New', 'agentic-plugin' ),
			'manage_options',
			'agentic-marketplace',
			array( $this, 'render_marketplace_page' )
		);

		add_submenu_page(
			'agentic-plugin',
			__( 'Agent Licenses', 'agentic-plugin' ),
			__( 'Licenses', 'agentic-plugin' ),
			'manage_options',
			'agentic-licenses',
			array( $this, 'render_licenses_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'agentic_page_agentic-marketplace' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'agentic-marketplace',
			AGENTIC_PLUGIN_URL . 'assets/css/marketplace.css',
			array(),
			AGENTIC_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'agentic-marketplace',
			AGENTIC_PLUGIN_URL . 'assets/js/marketplace.js',
			array( 'jquery', 'wp-util' ),
			AGENTIC_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'agentic-marketplace',
			'agenticMarketplace',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'agentic_marketplace' ),
				'apiBase'    => $this->api_base,
				'siteUrl'    => home_url(),
				'siteHash'   => md5( home_url() . wp_salt() ),
				'installed'  => $this->get_installed_agents(),
				'pricingUrl' => 'https://agentic-plugin.com/pricing/',
				'strings'    => array(
					'install'           => __( 'Install', 'agentic-plugin' ),
					'installing'        => __( 'Installing...', 'agentic-plugin' ),
					'installed'         => __( 'Installed', 'agentic-plugin' ),
					'activate'          => __( 'Activate', 'agentic-plugin' ),
					'activating'        => __( 'Activating...', 'agentic-plugin' ),
					'active'            => __( 'Active', 'agentic-plugin' ),
					'deactivate'        => __( 'Deactivate', 'agentic-plugin' ),
					'update'            => __( 'Update', 'agentic-plugin' ),
					'updating'          => __( 'Updating...', 'agentic-plugin' ),
					'purchase'          => __( 'Purchase', 'agentic-plugin' ),
					'enterLicense'      => __( 'Enter License Key', 'agentic-plugin' ),
					'error'             => __( 'An error occurred', 'agentic-plugin' ),
					'searchPlaceholder' => __( 'Search agents...', 'agentic-plugin' ),
					'noResults'         => __( 'No agents found', 'agentic-plugin' ),
					'viewDetails'       => __( 'View Details', 'agentic-plugin' ),
					'downloads'         => __( 'downloads', 'agentic-plugin' ),
					'lastUpdated'       => __( 'Last updated', 'agentic-plugin' ),
					'version'           => __( 'Version', 'agentic-plugin' ),
					'author'            => __( 'By', 'agentic-plugin' ),
					'requires'          => __( 'Requires', 'agentic-plugin' ),
					'testedUpTo'        => __( 'Tested up to', 'agentic-plugin' ),
					'free'              => __( 'Free', 'agentic-plugin' ),
				),
			)
		);
	}

	/**
	 * Get list of installed agents
	 */
	private function get_installed_agents(): array {
		$installed  = array();
		$agents_dir = WP_CONTENT_DIR . '/agents';

		if ( is_dir( $agents_dir ) ) {
			$dirs = glob( $agents_dir . '/*', GLOB_ONLYDIR );
			foreach ( $dirs as $dir ) {
				$agent_file = $dir . '/agent.php';
				if ( file_exists( $agent_file ) ) {
					$data                          = $this->get_agent_file_data( $agent_file );
					$installed[ basename( $dir ) ] = array(
						'version' => $data['Version'] ?? '1.0.0',
						'active'  => $this->is_agent_active( basename( $dir ) ),
					);
				}
			}
		}

		// Also check library agents
		$library_dir = AGENTIC_PLUGIN_DIR . 'library';
		if ( is_dir( $library_dir ) ) {
			$dirs = glob( $library_dir . '/*', GLOB_ONLYDIR );
			foreach ( $dirs as $dir ) {
				$agent_file = $dir . '/agent.php';
				if ( file_exists( $agent_file ) ) {
					$data                          = $this->get_agent_file_data( $agent_file );
					$installed[ basename( $dir ) ] = array(
						'version' => $data['Version'] ?? '1.0.0',
						'active'  => $this->is_agent_active( basename( $dir ) ),
						'bundled' => true,
					);
				}
			}
		}

		return $installed;
	}

	/**
	 * Get agent file header data
	 */
	private function get_agent_file_data( string $file ): array {
		$headers = array(
			'Name'        => 'Agent Name',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Category'    => 'Category',
			'Icon'        => 'Icon',
		);

		return get_file_data( $file, $headers );
	}

	/**
	 * Check if an agent is active
	 */
	private function is_agent_active( string $slug ): bool {
		$active_agents = get_option( 'agentic_active_agents', array() );
		return in_array( $slug, $active_agents, true );
	}

	/**
	 * Render marketplace page
	 */
	public function render_marketplace_page(): void {
		// Check if user has a valid license.
		$has_license = \Agentic\License_Manager::is_valid();

		if ( ! $has_license ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Agent Marketplace', 'agentic-plugin' ); ?></h1>
				
				<div class="notice notice-warning" style="padding: 20px; margin: 20px 0;">
					<h2 style="margin-top: 0;"><?php esc_html_e( 'License Required', 'agentic-plugin' ); ?></h2>
					<p><?php esc_html_e( 'A valid license is required to access the Agent Marketplace and download premium agents.', 'agentic-plugin' ); ?></p>
					<p>
						<a href="https://agentic-plugin.com/pricing" class="button button-primary" target="_blank">
							<?php esc_html_e( 'Purchase License ($10/year)', 'agentic-plugin' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-settings' ) ); ?>" class="button">
							<?php esc_html_e( 'Enter License Key', 'agentic-plugin' ); ?>
						</a>
					</p>
					<p style="margin: 0;">
						<strong><?php esc_html_e( 'What you get with a license:', 'agentic-plugin' ); ?></strong>
					</p>
					<ul style="margin-left: 20px;">
						<li><?php esc_html_e( 'Access to 100+ premium agents', 'agentic-plugin' ); ?></li>
						<li><?php esc_html_e( 'One-click agent installation', 'agentic-plugin' ); ?></li>
						<li><?php esc_html_e( 'Upload and sell your own agents', 'agentic-plugin' ); ?></li>
						<li><?php esc_html_e( 'Priority support', 'agentic-plugin' ); ?></li>
					</ul>
				</div>
			</div>
			<?php
			return;
		}

		?>
		<div class="wrap agentic-marketplace-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Add New Agent', 'agentic-plugin' ); ?></h1>

			<div class="agentic-marketplace-header">
				<div class="agentic-marketplace-tabs">
					<a href="#" class="agentic-tab active" data-tab="featured"><?php esc_html_e( 'Featured', 'agentic-plugin' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="popular"><?php esc_html_e( 'Popular', 'agentic-plugin' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="recent"><?php esc_html_e( 'Recently Updated', 'agentic-plugin' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="free"><?php esc_html_e( 'Free', 'agentic-plugin' ); ?></a>
				</div>

				<div class="agentic-marketplace-search">
					<input type="search" id="agentic-agent-search" placeholder="<?php esc_attr_e( 'Search agents...', 'agentic-plugin' ); ?>">
				</div>
			</div>

			<div class="agentic-marketplace-filters">
				<select id="agentic-category-filter">
					<option value=""><?php esc_html_e( 'All Categories', 'agentic-plugin' ); ?></option>
				</select>
			</div>

			<div class="agentic-marketplace-content">
				<div class="agentic-agents-grid" id="agentic-agents-grid">
					<div class="agentic-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading agents...', 'agentic-plugin' ); ?>
					</div>
				</div>

				<div class="agentic-marketplace-pagination" id="agentic-pagination"></div>
			</div>

			<!-- Agent Details Modal -->
			<div id="agentic-agent-modal" class="agentic-modal" style="display:none;">
				<div class="agentic-modal-overlay"></div>
				<div class="agentic-modal-content">
					<button class="agentic-modal-close">&times;</button>
					<div class="agentic-modal-body"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render licenses page
	 */
	public function render_licenses_page(): void {
		require_once AGENTIC_PLUGIN_DIR . 'admin/licenses.php';
	}

	/**
	 * AJAX: Browse agents
	 */
	public function ajax_browse_agents(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		// Require valid license for marketplace access.
		if ( ! \Agentic\License_Manager::is_valid() ) {
			wp_send_json_error(
				array(
					'message'    => 'A valid license is required to access the Agent Marketplace.',
					'code'       => 'license_required',
					'renew_url'  => 'https://agentic-plugin.com/pricing',
					'show_popup' => true,
				)
			);
		}

		$params = array(
			'page'      => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page'  => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 12,
			'search'    => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
			'category'  => isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '',
			'orderby'   => isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'date',
			'order'     => isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : 'DESC',
			'free_only' => isset( $_POST['free_only'] ) && $_POST['free_only'] === 'true',
		);

		$response = $this->api_request( 'agents', $params );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Get single agent
	 */
	public function ajax_get_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );
		// Require valid license for agent installation.
		if ( ! \Agentic\License_Manager::is_valid() ) {
			wp_send_json_error(
				array(
					'message' => 'A valid license is required to install agents from the marketplace.',
					'code'    => 'license_required',
				)
			);
		}
		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agentic-plugin' ) );
		}

		$response = $this->api_request( "agents/{$agent_id}" );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Install agent
	 */
	public function ajax_install_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'agentic-plugin' ) );
		}

		$agent_id    = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agentic-plugin' ) );
		}

		// Get agent details
		$agent = $this->api_request( "agents/{$agent_id}" );
		if ( is_wp_error( $agent ) ) {
			wp_send_json_error( $agent->get_error_message() );
		}

		// Check if premium and verify license
		if ( $agent['is_premium'] ) {
			if ( empty( $license_key ) ) {
				wp_send_json_error( __( 'License key required for premium agents', 'agentic-plugin' ) );
			}

			$verification = $this->api_request(
				'licenses/validate',
				array(
					'license_key' => $license_key,
					'agent_slug'  => $agent['slug'],
					'site_url'    => home_url(),
					'site_hash'   => hash_hmac( 'sha256', home_url(), AGENTIC_SALT ),
					'action'      => 'install',
				),
				'POST'
			);

			if ( is_wp_error( $verification ) ) {
				wp_send_json_error( $verification->get_error_message() );
			}

			// Handle error responses
			if ( isset( $verification['error'] ) ) {
				$error      = $verification['error'];
				$error_code = $error['code'] ?? 'unknown_error';
				$error_data = array(
					'message' => $error['message'] ?? __( 'License validation failed', 'agentic-plugin' ),
					'code'    => $error_code,
				);

				switch ( $error_code ) {
					case 'license_expired':
						// Check if still in grace period
						if ( ! empty( $error['allow_existing_usage'] ) ) {
							// Allow install but show warning
							$grace_warning = sprintf(
								/* translators: 1: expiration date, 2: grace period days, 3: renewal URL */
								__( 'License expired on %1$s. You have %2$d days to renew. <a href="%3$s" target="_blank">Renew now</a>', 'agentic-plugin' ),
								esc_html( $error['expired_at'] ?? 'unknown' ),
								absint( $error['grace_period_days'] ?? 7 ),
								esc_url( $error['renewal_url'] ?? '' )
							);
							set_transient( "agentic_license_warning_{$agent['slug']}", $grace_warning, DAY_IN_SECONDS );
							// Continue with download
							$download_url = $verification['data']['download_url'] ?? '';
							break;
						} else {
							$error_data['renewal_url'] = $error['renewal_url'] ?? '';
							wp_send_json_error( $error_data );
						}
						break;

					case 'activation_limit_reached':
						$error_data['activations'] = $error['activations'] ?? array();
						$error_data['upgrade_url'] = $error['upgrade_url'] ?? '';
						$error_data['manage_url']  = $error['manage_url'] ?? '';
						wp_send_json_error( $error_data );
						break;

					case 'agent_mismatch':
						$error_data['licensed_agent']  = $error['licensed_agent'] ?? '';
						$error_data['requested_agent'] = $error['requested_agent'] ?? '';
						wp_send_json_error( $error_data );
						break;

					case 'license_invalid':
						$error_data['purchase_url'] = $error['purchase_url'] ?? '';
						$error_data['support_url']  = $error['support_url'] ?? '';
						wp_send_json_error( $error_data );
						break;

					default:
						wp_send_json_error( $error_data );
						break;
				}
			}

			// Success - extract download URL from response data
			if ( isset( $verification['data']['download_url'] ) ) {
				$download_url = $verification['data']['download_url'];
			} else {
				wp_send_json_error( __( 'Invalid license validation response', 'agentic-plugin' ) );
			}

			// Store license with complete metadata
			$licenses                   = get_option( 'agentic_licenses', array() );
			$licenses[ $agent['slug'] ] = array(
				'license_key'      => $license_key,
				'status'           => $verification['data']['status'] ?? 'active',
				'expires_at'       => $verification['data']['expires_at'] ?? null,
				'activations_used' => $verification['data']['activations_used'] ?? 1,
				'activation_limit' => $verification['data']['activation_limit'] ?? 1,
				'customer_email'   => $verification['data']['customer_email'] ?? '',
				'validated_at'     => current_time( 'mysql' ),
				'site_hash'        => hash_hmac( 'sha256', home_url(), AGENTIC_SALT ),
			);
			update_option( 'agentic_licenses', $licenses );
		} else {
			// Track download
			$download = $this->api_request( "agents/{$agent_id}/download", array(), 'POST' );
			if ( is_wp_error( $download ) ) {
				wp_send_json_error( $download->get_error_message() );
			}
			$download_url = $download['download_url'];
		}

		// Download and install
		$result = $this->download_and_install_agent( $download_url, $agent['slug'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Agent installed successfully', 'agentic-plugin' ),
				'slug'    => $agent['slug'],
			)
		);
	}

	/**
	 * Download and install agent
	 */
	private function download_and_install_agent( string $download_url, string $slug ): bool|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Create agents directory if it doesn't exist
		$agents_dir = WP_CONTENT_DIR . '/agents';
		if ( ! is_dir( $agents_dir ) ) {
			wp_mkdir_p( $agents_dir );
		}

		$agent_dir = $agents_dir . '/' . $slug;

		// Download the file
		$temp_file = download_url( $download_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Extract to agents directory
		$result = unzip_file( $temp_file, $agent_dir );
		@unlink( $temp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check if agent.php exists in extracted content
		if ( ! file_exists( $agent_dir . '/agent.php' ) ) {
			// Maybe it's in a subdirectory
			$subdirs = glob( $agent_dir . '/*', GLOB_ONLYDIR );
			if ( ! empty( $subdirs ) && file_exists( $subdirs[0] . '/agent.php' ) ) {
				// Move contents up
				$this->move_directory_contents( $subdirs[0], $agent_dir );
				@rmdir( $subdirs[0] );
			} else {
				return new \WP_Error( 'invalid_agent', __( 'Invalid agent package: agent.php not found', 'agentic-plugin' ) );
			}
		}

		return true;
	}

	/**
	 * Move directory contents
	 */
	private function move_directory_contents( string $source, string $dest ): void {
		$files = scandir( $source );
		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}
			rename( $source . '/' . $file, $dest . '/' . $file );
		}
	}

	/**
	 * AJAX: Activate agent
	 */
	public function ajax_activate_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'agentic-plugin' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agentic-plugin' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		if ( ! in_array( $slug, $active_agents, true ) ) {
			$active_agents[] = $slug;
			update_option( 'agentic_active_agents', $active_agents );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Agent activated', 'agentic-plugin' ),
				'slug'    => $slug,
			)
		);
	}

	/**
	 * AJAX: Deactivate agent
	 */
	public function ajax_deactivate_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'agentic-plugin' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agentic-plugin' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		$active_agents = array_diff( $active_agents, array( $slug ) );
		update_option( 'agentic_active_agents', array_values( $active_agents ) );

		wp_send_json_success(
			array(
				'message' => __( 'Agent deactivated', 'agentic-plugin' ),
				'slug'    => $slug,
			)
		);
	}

	/**
	 * AJAX: Update agent
	 */
	public function ajax_update_agent(): void {
		// Same as install, but preserves settings
		$this->ajax_install_agent();
	}

	/**
	 * AJAX: Rate agent
	 */
	public function ajax_rate_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$rating   = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;

		if ( ! $agent_id || $rating < 1 || $rating > 5 ) {
			wp_send_json_error( __( 'Invalid rating', 'agentic-plugin' ) );
		}

		$response = $this->api_request(
			"agents/{$agent_id}/rate",
			array(
				'rating'    => $rating,
				'site_url'  => home_url(),
				'site_hash' => md5( home_url() . wp_salt() ),
			),
			'POST'
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Deactivate license when agent is deleted
	 *
	 * @param string $slug Agent slug.
	 */
	private function deactivate_agent_license( string $slug ): void {
		$licenses = get_option( 'agentic_licenses', array() );

		if ( empty( $licenses[ $slug ] ) ) {
			return; // No license to deactivate.
		}

		$license = $licenses[ $slug ];

		// Call API to deactivate
		$response = $this->api_request(
			'licenses/deactivate',
			array(
				'license_key' => $license['license_key'],
				'site_url'    => home_url(),
				'site_hash'   => hash_hmac( 'sha256', home_url(), AGENTIC_SALT ),
			),
			'POST'
		);

		// Remove from local storage whether API call succeeds or fails
		unset( $licenses[ $slug ] );
		update_option( 'agentic_licenses', $licenses );

		if ( is_wp_error( $response ) ) {
			// Log error but don't block deletion.
			error_log( 'Agentic: Failed to deactivate license for ' . $slug . ': ' . $response->get_error_message() );
		}
	}

	/**
	 * Check if agent license is valid (with grace period)
	 *
	 * @param string $slug Agent slug.
	 * @return bool
	 */
	public function is_agent_license_valid( string $slug ): bool {
		$licenses = get_option( 'agentic_licenses', array() );

		if ( empty( $licenses[ $slug ] ) ) {
			return false; // No license = not valid.
		}

		$license = $licenses[ $slug ];

		// Check status
		if ( 'active' !== $license['status'] ) {
			// Check if expired and within grace period
			if ( isset( $license['expires_at'] ) ) {
				$expires    = strtotime( $license['expires_at'] );
				$grace_days = 7; // From licensing strategy.
				$grace_end  = $expires + ( $grace_days * DAY_IN_SECONDS );

				if ( time() <= $grace_end ) {
					// Still in grace period.
					return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Get license info for an agent
	 *
	 * @param string $slug Agent slug.
	 * @return array|null
	 */
	public function get_agent_license( string $slug ): ?array {
		$licenses = get_option( 'agentic_licenses', array() );
		return $licenses[ $slug ] ?? null;
	}

	/**
	 * Make API request to marketplace
	 */
	private function api_request( string $endpoint, array $params = array(), string $method = 'GET' ): array|\WP_Error {
		$url = trailingslashit( $this->api_base ) . 'wp-json/agentic-marketplace/v1/' . $endpoint;

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( $method === 'GET' && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( $method === 'POST' ) {
			$args['method'] = 'POST';
			$args['body']   = $params;
		}

		// Check cache for GET requests
		if ( $method === 'GET' ) {
			$cache_key = 'agentic_api_' . md5( $url );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $data === null || ! is_array( $data ) ) {
			return new \WP_Error( 'api_error', __( 'Invalid API response format', 'agentic-plugin' ) );
		}

		if ( $code >= 400 ) {
			return new \WP_Error(
				'api_error',
				$data['message'] ?? __( 'API request failed', 'agentic-plugin' )
			);
		}

		// Cache successful GET requests
		if ( $method === 'GET' ) {
			set_transient( $cache_key, $data, self::CACHE_DURATION );
		}

		return $data;
	}
}
