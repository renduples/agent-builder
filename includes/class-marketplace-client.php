<?php
/**
 * Marketplace Client
 *
 * Handles communication with the marketplace API from client WordPress installations.
 * Provides one-click install functionality for agents.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.2.0
 *
 * php version 8.1
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
	 * Marketplace API base URL.
	 *
	 * @var string
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
		// Allow override for local development.
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
		add_action( 'wp_ajax_agentic_save_developer_api_key', array( $this, 'ajax_save_developer_api_key' ) );
		add_action( 'wp_ajax_agentic_disconnect_developer', array( $this, 'ajax_disconnect_developer' ) );

		// Schedule update checks.
		add_action( 'init', array( $this, 'schedule_update_checks' ) );
		add_action( 'agentic_check_agent_updates', array( $this, 'check_for_updates' ) );
		add_action( 'agentic_check_agent_updates', array( $this, 'revalidate_agent_licenses' ) );
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
			// Skip bundled agents - they update with the plugin.
			if ( ! empty( $agent['bundled'] ) ) {
				continue;
			}

			// Get stored license for this agent (if premium).
			$licenses    = get_option( 'agentic_licenses', array() );
			$license_key = $licenses[ $slug ]['license_key'] ?? '';

			// Check marketplace for latest version.
			$params = array(
				'current_version' => $agent['version'] ?? '0.0.0',
				'site_url'        => home_url(),
			);

			// Add license key if agent is premium.
			if ( ! empty( $license_key ) ) {
				$params['license_key'] = $license_key;
			}

			$response = $this->api_request( "agents/{$slug}/version", $params, 'GET' );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			// Check if update requires license renewal.
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

		// Store updates in transient (12 hours).
		set_transient( 'agentic_available_updates', $updates, 12 * HOUR_IN_SECONDS );

		do_action( 'agentic_updates_checked', $updates );
	}

	/**
	 * Revalidate agent licenses with the marketplace server.
	 *
	 * Runs daily via the agentic_check_agent_updates cron.
	 * Contacts the server for each stored license where validated_at is older than 72 hours.
	 * Uses fail-open design: if the server is unreachable, cached license data is preserved.
	 *
	 * @since 1.7.1
	 */
	public function revalidate_agent_licenses(): void {
		$licenses = get_option( 'agentic_licenses', array() );

		if ( empty( $licenses ) || ! is_array( $licenses ) ) {
			return;
		}

		$stale_threshold = 72 * HOUR_IN_SECONDS; // 72 hours.
		$now             = current_time( 'timestamp' );
		$updated         = false;

		foreach ( $licenses as $slug => $license_data ) {
			if ( empty( $license_data['license_key'] ) ) {
				continue;
			}

			// Skip if recently validated (within 72 hours).
			if ( ! empty( $license_data['validated_at'] ) ) {
				$validated_time = strtotime( $license_data['validated_at'] );
				if ( $validated_time && ( $now - $validated_time ) < $stale_threshold ) {
					continue;
				}
			}

			// Build validation request.
			$site_url  = home_url();
			$site_hash = defined( 'AGENTIC_SALT' )
				? hash_hmac( 'sha256', $site_url, AGENTIC_SALT )
				: ( $license_data['site_hash'] ?? '' );

			$params = array(
				'license_key' => $license_data['license_key'],
				'agent_slug'  => $slug,
				'site_url'    => $site_url,
				'site_hash'   => $site_hash,
				'action'      => 'check',
			);

			// Call the server validation endpoint directly (bypass GET cache).
			$url      = trailingslashit( $this->api_base ) . 'wp-json/agentic-marketplace/v1/licenses/validate';
			$response = wp_remote_post( $url, array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => $params,
			) );

			// Fail-open: if server is unreachable, keep existing cached data.
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $body ) ) {
				continue; // Invalid response, fail-open.
			}

			// Update license data from server response.
			if ( ! empty( $body['success'] ) && ! empty( $body['data'] ) ) {
				$licenses[ $slug ]['status']           = $body['data']['status'] ?? $license_data['status'];
				$licenses[ $slug ]['expires_at']       = $body['data']['expires_at'] ?? $license_data['expires_at'];
				$licenses[ $slug ]['activations_used'] = $body['data']['activations_used'] ?? $license_data['activations_used'];
				$licenses[ $slug ]['activation_limit'] = $body['data']['activation_limit'] ?? $license_data['activation_limit'];
				$licenses[ $slug ]['customer_email']   = $body['data']['customer_email'] ?? $license_data['customer_email'];
				$licenses[ $slug ]['validated_at']      = current_time( 'mysql' );
				$updated                               = true;
			} elseif ( isset( $body['error']['code'] ) ) {
				// License is invalid/revoked/expired — update status accordingly.
				$error_code = $body['error']['code'];

				if ( in_array( $error_code, array( 'license_invalid', 'license_refunded', 'license_suspended' ), true ) ) {
					$licenses[ $slug ]['status']       = 'revoked';
					$licenses[ $slug ]['validated_at'] = current_time( 'mysql' );
					$updated                           = true;
				} elseif ( 'license_expired' === $error_code ) {
					$licenses[ $slug ]['status']       = 'expired';
					$licenses[ $slug ]['expires_at']   = $body['error']['expired_at'] ?? $license_data['expires_at'];
					$licenses[ $slug ]['validated_at'] = current_time( 'mysql' );
					$updated                           = true;
				}
				// Rate limit or transient errors: fail-open, don't update.
			}
		}

		if ( $updated ) {
			update_option( 'agentic_licenses', $licenses );
		}
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
			'agent-builder',
			__( 'Developer Revenue', 'agent-builder' ),
			__( 'Revenue', 'agent-builder' ),
			'manage_options',
			'agentic-revenue',
			array( $this, 'render_revenue_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'agentic_page_agentic-marketplace' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'agentic-marketplace',
			AGENTIC_PLUGIN_URL . 'assets/css/marketplace.css',
			array(),
			(string) filemtime( AGENTIC_PLUGIN_DIR . 'assets/css/marketplace.css' )
		);

		wp_enqueue_script(
			'agentic-marketplace',
			AGENTIC_PLUGIN_URL . 'assets/js/marketplace.js',
			array( 'jquery', 'wp-util' ),
			(string) filemtime( AGENTIC_PLUGIN_DIR . 'assets/js/marketplace.js' ),
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
					'install'           => __( 'Install', 'agent-builder' ),
					'installing'        => __( 'Installing...', 'agent-builder' ),
					'installed'         => __( 'Installed', 'agent-builder' ),
					'activate'          => __( 'Activate', 'agent-builder' ),
					'activating'        => __( 'Activating...', 'agent-builder' ),
					'active'            => __( 'Active', 'agent-builder' ),
					'deactivate'        => __( 'Deactivate', 'agent-builder' ),
					'update'            => __( 'Update', 'agent-builder' ),
					'updating'          => __( 'Updating...', 'agent-builder' ),
					'purchase'          => __( 'Purchase', 'agent-builder' ),
					'enterLicense'      => __( 'Enter License Key', 'agent-builder' ),
					'error'             => __( 'An error occurred', 'agent-builder' ),
					'searchPlaceholder' => __( 'Search agents...', 'agent-builder' ),
					'noResults'         => __( 'No agents found', 'agent-builder' ),
					'viewDetails'       => __( 'View Details', 'agent-builder' ),
					'downloads'         => __( 'downloads', 'agent-builder' ),
					'lastUpdated'       => __( 'Last updated', 'agent-builder' ),
					'version'           => __( 'Version', 'agent-builder' ),
					'author'            => __( 'By', 'agent-builder' ),
					'requires'          => __( 'Requires', 'agent-builder' ),
					'testedUpTo'        => __( 'Tested up to', 'agent-builder' ),
					'free'              => __( 'Free', 'agent-builder' ),
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

		// Also check library agents.
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
	 *
	 * @param string $file Path to agent file.
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
	 *
	 * @param string $slug Agent slug to check.
	 */
	private function is_agent_active( string $slug ): bool {
		$active_agents = get_option( 'agentic_active_agents', array() );
		return in_array( $slug, $active_agents, true );
	}

	/**
	 * Render marketplace page
	 */
	public function render_marketplace_page(): void {
		?>
		<div class="wrap agentic-marketplace-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Add New Agent', 'agent-builder' ); ?></h1>

			<div class="agentic-marketplace-header">
				<div class="agentic-marketplace-tabs">
					<a href="#" class="agentic-tab active" data-tab="featured"><?php esc_html_e( 'Featured', 'agent-builder' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="popular"><?php esc_html_e( 'Popular', 'agent-builder' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="recent"><?php esc_html_e( 'Recently Updated', 'agent-builder' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="free"><?php esc_html_e( 'Free', 'agent-builder' ); ?></a>
				</div>

				<div class="agentic-marketplace-search">
					<input type="search" id="agentic-agent-search" placeholder="<?php esc_attr_e( 'Search agents...', 'agent-builder' ); ?>">
				</div>
			</div>

			<div class="agentic-marketplace-filters">
				<select id="agentic-category-filter">
					<option value=""><?php esc_html_e( 'All Categories', 'agent-builder' ); ?></option>
				</select>
			</div>

			<div class="agentic-marketplace-content">
				<div class="agentic-agents-grid" id="agentic-agents-grid">
					<div class="agentic-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading agents...', 'agent-builder' ); ?>
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
	 * Render revenue page
	 */
	public function render_revenue_page(): void {
		// Enqueue Chart.js for revenue charts (local copy for WordPress.org compliance).
		wp_enqueue_script(
			'chartjs',
			AGENTIC_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
		require_once AGENTIC_PLUGIN_DIR . 'admin/revenue.php';
	}

	/**
	 * AJAX: Save developer API key
	 */
	public function ajax_save_developer_api_key(): void {
		check_ajax_referer( 'agentic_save_api_key', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'API key is required' );
		}

		// Validate the API key with marketplace.
		$response = wp_remote_get(
			$this->api_base . '/wp-json/agentic-marketplace/v1/developer/stats',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Could not connect to marketplace' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			wp_send_json_error( 'Invalid API key' );
		}

		update_option( 'agentic_developer_api_key', $api_key );
		wp_send_json_success();
	}

	/**
	 * AJAX: Disconnect developer account
	 */
	public function ajax_disconnect_developer(): void {
		check_ajax_referer( 'agentic_disconnect', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		delete_option( 'agentic_developer_api_key' );
		wp_send_json_success();
	}

	/**
	 * AJAX: Browse agents
	 */
	public function ajax_browse_agents(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		$params = array(
			'page'      => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page'  => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 12,
			'search'    => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'category'  => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'orderby'   => isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'date',
			'order'     => isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'DESC',
			'free_only' => isset( $_POST['free_only'] ) && true === $_POST['free_only'],
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

		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agent-builder' ) );
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
			wp_send_json_error( __( 'Permission denied', 'agent-builder' ) );
		}

		$agent_id    = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agent-builder' ) );
		}

		// Get agent details.
		$agent = $this->api_request( "agents/{$agent_id}" );
		if ( is_wp_error( $agent ) ) {
			wp_send_json_error( $agent->get_error_message() );
		}

		// Check if premium and verify license.
		if ( $agent['is_premium'] ) {
			if ( empty( $license_key ) ) {
				wp_send_json_error( __( 'License key required for premium agents', 'agent-builder' ) );
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

			// Handle error responses.
			if ( isset( $verification['error'] ) ) {
				$error      = $verification['error'];
				$error_code = $error['code'] ?? 'unknown_error';
				$error_data = array(
					'message' => $error['message'] ?? __( 'License validation failed', 'agent-builder' ),
					'code'    => $error_code,
				);

				switch ( $error_code ) {
					case 'license_expired':
						// Check if still in grace period.
						if ( ! empty( $error['allow_existing_usage'] ) ) {
							// Allow install but show warning.
							$grace_warning = sprintf(
								/* translators: 1: expiration date, 2: grace period days, 3: renewal URL */
								__( 'License expired on %1$s. You have %2$d days to renew. <a href="%3$s" target="_blank">Renew now</a>', 'agent-builder' ),
								esc_html( $error['expired_at'] ?? 'unknown' ),
								absint( $error['grace_period_days'] ?? 7 ),
								esc_url( $error['renewal_url'] ?? '' )
							);
							set_transient( "agentic_license_warning_{$agent['slug']}", $grace_warning, DAY_IN_SECONDS );
							// Continue with download.
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

			// Success - extract download URL from response data.
			if ( isset( $verification['data']['download_url'] ) ) {
				$download_url = $verification['data']['download_url'];
			} else {
				wp_send_json_error( __( 'Invalid license validation response', 'agent-builder' ) );
			}

			// Store license with complete metadata.
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
			// Track download.
			$download = $this->api_request( "agents/{$agent_id}/download", array(), 'POST' );
			if ( is_wp_error( $download ) ) {
				wp_send_json_error( $download->get_error_message() );
			}
			$download_url = $download['download_url'];
		}

		// Download and install.
		$result = $this->download_and_install_agent( $download_url, $agent['slug'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Agent installed successfully', 'agent-builder' ),
				'slug'    => $agent['slug'],
			)
		);
	}

	/**
	 * Download and install agent
	 *
	 * @param string $download_url URL to download the agent from.
	 * @param string $slug         Agent slug for directory naming.
	 */
	private function download_and_install_agent( string $download_url, string $slug ): bool|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Create agents directory if it doesn't exist.
		$agents_dir = WP_CONTENT_DIR . '/agents';
		if ( ! is_dir( $agents_dir ) ) {
			wp_mkdir_p( $agents_dir );
		}

		$agent_dir = $agents_dir . '/' . $slug;

		// Download the file.
		$temp_file = download_url( $download_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Extract to agents directory.
		$result = unzip_file( $temp_file, $agent_dir );

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		$wp_filesystem->delete( $temp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check if agent.php exists in extracted content.
		if ( ! file_exists( $agent_dir . '/agent.php' ) ) {
			// Maybe it's in a subdirectory.
			$subdirs = glob( $agent_dir . '/*', GLOB_ONLYDIR );
			if ( ! empty( $subdirs ) && file_exists( $subdirs[0] . '/agent.php' ) ) {
				// Move contents up.
				$this->move_directory_contents( $subdirs[0], $agent_dir );
				$wp_filesystem->rmdir( $subdirs[0] );
			} else {
				return new \WP_Error( 'invalid_agent', __( 'Invalid agent package: agent.php not found', 'agent-builder' ) );
			}
		}

		return true;
	}

	/**
	 * Move directory contents
	 *
	 * @param string $source Source directory path.
	 * @param string $dest   Destination directory path.
	 */
	private function move_directory_contents( string $source, string $dest ): void {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$files = scandir( $source );
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$wp_filesystem->move( $source . '/' . $file, $dest . '/' . $file );
		}
	}

	/**
	 * AJAX: Activate agent
	 */
	public function ajax_activate_agent(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'agent-builder' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agent-builder' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		if ( ! in_array( $slug, $active_agents, true ) ) {
			$active_agents[] = $slug;
			update_option( 'agentic_active_agents', $active_agents );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Agent activated', 'agent-builder' ),
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
			wp_send_json_error( __( 'Permission denied', 'agent-builder' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agent-builder' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		$active_agents = array_diff( $active_agents, array( $slug ) );
		update_option( 'agentic_active_agents', array_values( $active_agents ) );

		wp_send_json_success(
			array(
				'message' => __( 'Agent deactivated', 'agent-builder' ),
				'slug'    => $slug,
			)
		);
	}

	/**
	 * AJAX: Update agent
	 */
	public function ajax_update_agent(): void {
		// Same as install, but preserves settings.
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
			wp_send_json_error( __( 'Invalid rating', 'agent-builder' ) );
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

		// Call API to deactivate.
		$response = $this->api_request(
			'licenses/deactivate',
			array(
				'license_key' => $license['license_key'],
				'site_url'    => home_url(),
				'site_hash'   => hash_hmac( 'sha256', home_url(), AGENTIC_SALT ),
			),
			'POST'
		);

		// Remove from local storage whether API call succeeds or fails.
		unset( $licenses[ $slug ] );
		update_option( 'agentic_licenses', $licenses );

		if ( is_wp_error( $response ) ) {
			// Log error but don't block deletion.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
				error_log( 'Agentic: Failed to deactivate license for ' . $slug . ': ' . $response->get_error_message() );
			}
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

		// Check status.
		if ( 'active' !== $license['status'] ) {
			// Check if expired and within grace period.
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
	 *
	 * @param string $endpoint API endpoint to call.
	 * @param array  $params   Request parameters.
	 * @param string $method   HTTP method (GET or POST).
	 */
	private function api_request( string $endpoint, array $params = array(), string $method = 'GET' ): array|\WP_Error {
		$url = trailingslashit( $this->api_base ) . 'wp-json/agentic-marketplace/v1/' . $endpoint;

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( 'POST' === $method ) {
			$args['method'] = 'POST';
			$args['body']   = $params;
		}

		// Check cache for GET requests.
		if ( 'GET' === $method ) {
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

		if ( null === $data || ! is_array( $data ) ) {
			return new \WP_Error( 'api_error', __( 'Invalid API response format', 'agent-builder' ) );
		}

		if ( $code >= 400 ) {
			return new \WP_Error(
				'api_error',
				$data['message'] ?? __( 'API request failed', 'agent-builder' )
			);
		}

		// Cache successful GET requests.
		if ( 'GET' === $method ) {
			set_transient( $cache_key, $data, self::CACHE_DURATION );
		}

		return $data;
	}
}
