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

namespace Agentic\Core;

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
	}

	/**
	 * Add admin menu page
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'agentic-core',
			__( 'Add New Agent', 'agentic-core' ),
			__( 'Add New', 'agentic-core' ),
			'manage_options',
			'agentic-marketplace',
			array( $this, 'render_marketplace_page' )
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
			AGENTIC_CORE_PLUGIN_URL . 'assets/css/marketplace.css',
			array(),
			AGENTIC_CORE_VERSION
		);

		wp_enqueue_script(
			'agentic-marketplace',
			AGENTIC_CORE_PLUGIN_URL . 'assets/js/marketplace.js',
			array( 'jquery', 'wp-util' ),
			AGENTIC_CORE_VERSION,
			true
		);

		wp_localize_script(
			'agentic-marketplace',
			'agenticMarketplace',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'agentic_marketplace' ),
				'apiBase'   => $this->api_base,
				'siteUrl'   => home_url(),
				'siteHash'  => md5( home_url() . wp_salt() ),
				'installed' => $this->get_installed_agents(),
				'strings'   => array(
					'install'           => __( 'Install', 'agentic-core' ),
					'installing'        => __( 'Installing...', 'agentic-core' ),
					'installed'         => __( 'Installed', 'agentic-core' ),
					'activate'          => __( 'Activate', 'agentic-core' ),
					'activating'        => __( 'Activating...', 'agentic-core' ),
					'active'            => __( 'Active', 'agentic-core' ),
					'deactivate'        => __( 'Deactivate', 'agentic-core' ),
					'update'            => __( 'Update', 'agentic-core' ),
					'updating'          => __( 'Updating...', 'agentic-core' ),
					'purchase'          => __( 'Purchase', 'agentic-core' ),
					'enterLicense'      => __( 'Enter License Key', 'agentic-core' ),
					'error'             => __( 'An error occurred', 'agentic-core' ),
					'searchPlaceholder' => __( 'Search agents...', 'agentic-core' ),
					'noResults'         => __( 'No agents found', 'agentic-core' ),
					'viewDetails'       => __( 'View Details', 'agentic-core' ),
					'downloads'         => __( 'downloads', 'agentic-core' ),
					'lastUpdated'       => __( 'Last updated', 'agentic-core' ),
					'version'           => __( 'Version', 'agentic-core' ),
					'author'            => __( 'By', 'agentic-core' ),
					'requires'          => __( 'Requires', 'agentic-core' ),
					'testedUpTo'        => __( 'Tested up to', 'agentic-core' ),
					'free'              => __( 'Free', 'agentic-core' ),
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
		$library_dir = AGENTIC_CORE_PLUGIN_DIR . 'library';
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
		?>
		<div class="wrap agentic-marketplace-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Add New Agent', 'agentic-core' ); ?></h1>

			<div class="agentic-marketplace-header">
				<div class="agentic-marketplace-tabs">
					<a href="#" class="agentic-tab active" data-tab="featured"><?php esc_html_e( 'Featured', 'agentic-core' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="popular"><?php esc_html_e( 'Popular', 'agentic-core' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="recent"><?php esc_html_e( 'Recently Updated', 'agentic-core' ); ?></a>
					<a href="#" class="agentic-tab" data-tab="free"><?php esc_html_e( 'Free', 'agentic-core' ); ?></a>
				</div>

				<div class="agentic-marketplace-search">
					<input type="search" id="agentic-agent-search" placeholder="<?php esc_attr_e( 'Search agents...', 'agentic-core' ); ?>">
				</div>
			</div>

			<div class="agentic-marketplace-filters">
				<select id="agentic-category-filter">
					<option value=""><?php esc_html_e( 'All Categories', 'agentic-core' ); ?></option>
				</select>
			</div>

			<div class="agentic-marketplace-content">
				<div class="agentic-agents-grid" id="agentic-agents-grid">
					<div class="agentic-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading agents...', 'agentic-core' ); ?>
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
	 * AJAX: Browse agents
	 */
	public function ajax_browse_agents(): void {
		check_ajax_referer( 'agentic_marketplace', 'nonce' );

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

		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agentic-core' ) );
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
			wp_send_json_error( __( 'Permission denied', 'agentic-core' ) );
		}

		$agent_id    = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

		if ( ! $agent_id ) {
			wp_send_json_error( __( 'Invalid agent ID', 'agentic-core' ) );
		}

		// Get agent details
		$agent = $this->api_request( "agents/{$agent_id}" );
		if ( is_wp_error( $agent ) ) {
			wp_send_json_error( $agent->get_error_message() );
		}

		// Check if premium and verify license
		if ( $agent['is_premium'] ) {
			if ( empty( $license_key ) ) {
				wp_send_json_error( __( 'License key required for premium agents', 'agentic-core' ) );
			}

			$verification = $this->api_request(
				'verify-purchase',
				array(
					'license_key' => $license_key,
					'agent_id'    => $agent_id,
					'site_url'    => home_url(),
				),
				'POST'
			);

			if ( is_wp_error( $verification ) ) {
				wp_send_json_error( $verification->get_error_message() );
			}

			if ( ! $verification['valid'] ) {
				wp_send_json_error( __( 'Invalid license key', 'agentic-core' ) );
			}

			$download_url = $verification['download_url'];

			// Store license
			$licenses                   = get_option( 'agentic_licenses', array() );
			$licenses[ $agent['slug'] ] = array(
				'key'        => $license_key,
				'expires_at' => $verification['expires_at'],
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
				'message' => __( 'Agent installed successfully', 'agentic-core' ),
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
				return new \WP_Error( 'invalid_agent', __( 'Invalid agent package: agent.php not found', 'agentic-core' ) );
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
			wp_send_json_error( __( 'Permission denied', 'agentic-core' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agentic-core' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		if ( ! in_array( $slug, $active_agents, true ) ) {
			$active_agents[] = $slug;
			update_option( 'agentic_active_agents', $active_agents );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Agent activated', 'agentic-core' ),
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
			wp_send_json_error( __( 'Permission denied', 'agentic-core' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

		if ( ! $slug ) {
			wp_send_json_error( __( 'Invalid agent slug', 'agentic-core' ) );
		}

		$active_agents = get_option( 'agentic_active_agents', array() );
		$active_agents = array_diff( $active_agents, array( $slug ) );
		update_option( 'agentic_active_agents', array_values( $active_agents ) );

		wp_send_json_success(
			array(
				'message' => __( 'Agent deactivated', 'agentic-core' ),
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
			wp_send_json_error( __( 'Invalid rating', 'agentic-core' ) );
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
			return new \WP_Error( 'api_error', __( 'Invalid API response format', 'agentic-core' ) );
		}

		if ( $code >= 400 ) {
			return new \WP_Error(
				'api_error',
				$data['message'] ?? __( 'API request failed', 'agentic-core' )
			);
		}

		// Cache successful GET requests
		if ( $method === 'GET' ) {
			set_transient( $cache_key, $data, self::CACHE_DURATION );
		}

		return $data;
	}
}
