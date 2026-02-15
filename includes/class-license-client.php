<?php
/**
 * License Client
 *
 * Handles plugin-level license validation, periodic revalidation,
 * update gating, and feature degradation. Industry-standard license
 * enforcement for commercial WordPress plugins.
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.7.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Client Class
 *
 * Responsibilities:
 * 1. Periodic revalidation — daily cron phones home, caches result for 72h
 * 2. Update gating — blocks plugin updates without a valid license
 * 3. Feature degradation — disables premium agents when license lapses
 * 4. Admin notices — warns about expiring/expired licenses
 *
 * Design principles:
 * - Fail-open: if server is unreachable, use cached last-known-good state
 * - Graceful: never break the site, just degrade premium features
 * - 7-day grace period after expiration
 *
 * @since 1.7.0
 */
class License_Client {

	/**
	 * Option key for the plugin license key.
	 */
	const OPTION_LICENSE_KEY = 'agentic_plugin_license_key';

	/**
	 * Option key for cached license data.
	 */
	const OPTION_LICENSE_DATA = 'agentic_plugin_license_data';

	/**
	 * Cron hook name for revalidation.
	 */
	const CRON_HOOK = 'agentic_license_revalidate';

	/**
	 * Cache duration in seconds (72 hours).
	 * If the server is unreachable, we trust the cached result for this long.
	 */
	const CACHE_TTL = 72 * HOUR_IN_SECONDS;

	/**
	 * Grace period in days after license expiration.
	 */
	const GRACE_PERIOD_DAYS = 7;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private string $api_base;

	/**
	 * Cached license status for the current request.
	 *
	 * @var array|null
	 */
	private ?array $cached_status = null;

	/**
	 * Singleton instance.
	 *
	 * @var License_Client|null
	 */
	private static ?License_Client $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return License_Client
	 */
	public static function get_instance(): License_Client {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers all hooks.
	 */
	private function __construct() {
		$this->api_base = defined( 'AGENTIC_API_BASE' )
			? AGENTIC_API_BASE
			: 'https://agentic-plugin.com';

		// Schedule daily revalidation cron.
		add_action( 'init', array( $this, 'schedule_revalidation' ) );
		add_action( self::CRON_HOOK, array( $this, 'revalidate_license' ) );

		// Update gating — intercept WordPress update checks.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'gate_plugin_updates' ) );
		add_filter( 'plugins_api', array( $this, 'gate_plugin_info' ), 10, 3 );

		// Admin notices for license status.
		add_action( 'admin_notices', array( $this, 'show_license_notices' ) );

		// AJAX handler for activating/deactivating license.
		add_action( 'wp_ajax_agentic_activate_plugin_license', array( $this, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_agentic_deactivate_plugin_license', array( $this, 'ajax_deactivate_license' ) );

		// Clean up on plugin deactivation.
		register_deactivation_hook( AGENTIC_PLUGIN_FILE, array( $this, 'on_deactivation' ) );
	}

	// =========================================================================
	// 1. LICENSE STATUS
	// =========================================================================

	/**
	 * Get the current license status.
	 *
	 * Returns cached data for the current request. Falls back to stored option.
	 * Never makes a remote call — use revalidate_license() for that.
	 *
	 * @return array {
	 *     @type string $status      'active', 'expired', 'grace_period', 'revoked', 'invalid', 'free'
	 *     @type string $license_key The license key (masked for display).
	 *     @type string $type        'personal' or 'agency'
	 *     @type string $expires_at  Expiration date.
	 *     @type int    $activations_used  Number of sites activated.
	 *     @type int    $activations_limit Maximum activations.
	 *     @type string $validated_at      Last successful validation timestamp.
	 *     @type bool   $is_valid    Whether the license grants premium access.
	 * }
	 */
	public function get_status(): array {
		if ( null !== $this->cached_status ) {
			return $this->cached_status;
		}

		$license_key = get_option( self::OPTION_LICENSE_KEY, '' );

		if ( empty( $license_key ) ) {
			$this->cached_status = $this->free_status();
			return $this->cached_status;
		}

		$stored = get_option( self::OPTION_LICENSE_DATA, array() );

		if ( empty( $stored ) || empty( $stored['status'] ) ) {
			// Have a key but no cached data — trigger async revalidation.
			$this->cached_status = array(
				'status'      => 'pending',
				'is_valid'    => false,
				'license_key' => $this->mask_key( $license_key ),
				'message'     => 'License validation pending.',
			);
			// Schedule immediate revalidation.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time(), self::CRON_HOOK );
			}
			return $this->cached_status;
		}

		// Check if cached data has expired (server unreachable for > CACHE_TTL).
		$validated_at  = strtotime( $stored['validated_at'] ?? '2000-01-01' );
		$cache_expired = ( time() - $validated_at ) > self::CACHE_TTL;

		if ( $cache_expired ) {
			// Cache is stale but we still have data.
			// Fail-open: use last known state but mark as stale.
			$stored['cache_stale'] = true;
		}

		// Re-evaluate status with grace period logic.
		$this->cached_status = $this->evaluate_status( $stored );
		return $this->cached_status;
	}

	/**
	 * Check if the license grants premium access.
	 *
	 * This is the single method that other code should call to gate features.
	 *
	 * @return bool
	 */
	public function is_premium(): bool {
		return $this->get_status()['is_valid'];
	}

	/**
	 * Get the license type.
	 *
	 * @return string 'personal', 'agency', or 'free'
	 */
	public function get_type(): string {
		return $this->get_status()['type'] ?? 'free';
	}

	// =========================================================================
	// 2. PERIODIC REVALIDATION (Cron)
	// =========================================================================

	/**
	 * Schedule daily revalidation if not already scheduled.
	 */
	public function schedule_revalidation(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Revalidate the license by calling the server.
	 *
	 * Runs via daily cron. Updates the cached license data.
	 * If server is unreachable, keeps existing cached data (fail-open).
	 */
	public function revalidate_license(): void {
		$license_key = get_option( self::OPTION_LICENSE_KEY, '' );

		if ( empty( $license_key ) ) {
			return; // No license to validate.
		}

		$site_url = home_url();

		$response = wp_remote_post(
			$this->api_base . '/wp-json/agentic-license/v1/validate',
			array(
				'timeout' => 15,
				'headers' => $this->get_site_headers(),
				'body'    => array(
					'license_key' => $license_key,
					'site_url'    => $site_url,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Server unreachable — keep existing cached data (fail-open).
			$this->log( 'Revalidation failed: ' . $response->get_error_message() );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			$this->log( 'Revalidation returned invalid JSON' );
			return;
		}

		// Build license data from server response.
		$license_data = array(
			'validated_at' => current_time( 'mysql' ),
			'server_code'  => $code,
		);

		if ( ! empty( $body['valid'] ) && isset( $body['license'] ) ) {
			// License is valid.
			$license_data['status']            = $body['license']['status'] ?? 'active';
			$license_data['type']              = $body['license']['type'] ?? 'personal';
			$license_data['expires_at']        = $body['license']['expires_at'] ?? '';
			$license_data['activations_used']  = $body['license']['activations_used'] ?? 0;
			$license_data['activations_limit'] = $body['license']['activations_limit'] ?? 1;
			$license_data['customer_email']    = $body['license']['customer_email'] ?? '';
		} else {
			// License is invalid/expired/revoked.
			$license_data['status']  = $body['error'] ?? 'invalid';
			$license_data['message'] = $body['message'] ?? 'License validation failed.';

			// Preserve type from previous data if available.
			$existing                   = get_option( self::OPTION_LICENSE_DATA, array() );
			$license_data['type']       = $existing['type'] ?? 'personal';
			$license_data['expires_at'] = $existing['expires_at'] ?? '';
		}

		update_option( self::OPTION_LICENSE_DATA, $license_data );

		// Reset in-memory cache.
		$this->cached_status = null;

		$this->log( 'Revalidation complete: status=' . $license_data['status'] );
	}

	// =========================================================================
	// 3. UPDATE GATING
	// =========================================================================

	/**
	 * Filter plugin update transient to block updates without valid license.
	 *
	 * Hooks into pre_set_site_transient_update_plugins.
	 * If our plugin has an update available but the license is invalid,
	 * we remove it from the update list.
	 *
	 * @param object $transient Update transient data.
	 * @return object Modified transient.
	 */
	public function gate_plugin_updates( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin_basename = AGENTIC_PLUGIN_BASENAME;

		// Only act if there's an update available for our plugin.
		if ( ! isset( $transient->response[ $plugin_basename ] ) ) {
			return $transient;
		}

		// If license is not premium, remove the update.
		if ( ! $this->is_premium() ) {
			// Move from response (has update) to no_update (no update shown).
			if ( isset( $transient->response[ $plugin_basename ] ) ) {
				$transient->no_update[ $plugin_basename ] = $transient->response[ $plugin_basename ];
				unset( $transient->response[ $plugin_basename ] );
			}
		}

		return $transient;
	}

	/**
	 * Filter plugin information API to inject license requirement message.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	public function gate_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// Only intercept requests for our plugin.
		if ( ! isset( $args->slug ) || 'agentbuilder' !== $args->slug ) {
			return $result;
		}

		// If no valid license, add a notice to the plugin info.
		if ( ! $this->is_premium() ) {
			if ( is_object( $result ) && isset( $result->sections ) ) {
				$result->sections['changelog'] = '<p><strong>A valid license is required to receive updates.</strong> '
					. '<a href="https://agentic-plugin.com/pricing/">Purchase or renew your license</a>.</p>'
					. ( $result->sections['changelog'] ?? '' );
			}
		}

		return $result;
	}

	// =========================================================================
	// 4. FEATURE DEGRADATION
	// =========================================================================

	/**
	 * Check if a premium (marketplace-installed) agent should be allowed to run.
	 *
	 * Called by the agent registry before loading user-space agents.
	 * Bundled agents always run. Only marketplace-installed premium agents
	 * are gated by the license.
	 *
	 * @param string $slug Agent slug.
	 * @return bool True if the agent should load.
	 */
	public function can_agent_run( string $slug ): bool {
		// Bundled agents always run — they ship with the plugin.
		$bundled_agents = $this->get_bundled_agent_slugs();
		if ( in_array( $slug, $bundled_agents, true ) ) {
			return true;
		}

		// Free agents always run.
		$agent_licenses = get_option( 'agentic_licenses', array() );
		if ( empty( $agent_licenses[ $slug ] ) ) {
			// No license stored = either free agent or pre-license install.
			// Allow it to run — we can't retroactively lock out free agents.
			return true;
		}

		// Premium agent — check plugin license AND agent license.
		if ( ! $this->is_premium() ) {
			return false;
		}

		// Check agent-specific license validity.
		$marketplace_client = new Marketplace_Client();
		return $marketplace_client->is_agent_license_valid( $slug );
	}

	/**
	 * Get list of bundled agent slugs.
	 *
	 * @return string[]
	 */
	private function get_bundled_agent_slugs(): array {
		$library_dir = AGENTIC_PLUGIN_DIR . 'library/';
		if ( ! is_dir( $library_dir ) ) {
			return array();
		}

		$slugs = array();
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$dirs = @scandir( $library_dir );
		if ( is_array( $dirs ) ) {
			foreach ( $dirs as $dir ) {
				if ( '.' !== $dir && '..' !== $dir && is_dir( $library_dir . $dir ) ) {
					$slugs[] = $dir;
				}
			}
		}
		return $slugs;
	}

	// =========================================================================
	// 5. ADMIN NOTICES
	// =========================================================================

	/**
	 * Show admin notices about license status.
	 */
	public function show_license_notices(): void {
		// Only show on our plugin pages and the dashboard.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$our_pages   = array( 'toplevel_page_agentbuilder', 'dashboard' );
		$is_our_page = in_array( $screen->id, $our_pages, true )
			|| str_starts_with( $screen->id, 'agentic_page_' )
			|| str_starts_with( $screen->id, 'agentbuilder_page_' );

		if ( ! $is_our_page ) {
			return;
		}

		$status = $this->get_status();

		switch ( $status['status'] ) {
			case 'grace_period':
				$days_left = $status['grace_days_left'] ?? 0;
				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: 1: days remaining, 2: renewal URL */
					esc_html__( 'Your Agent Builder license has expired. You have %1$d day(s) remaining in the grace period. Premium agents will be disabled after the grace period ends. %2$s', 'agentbuilder' ),
					(int) $days_left,
					'<a href="https://agentic-plugin.com/pricing/">' . esc_html__( 'Renew now', 'agentbuilder' ) . '</a>'
				);
				echo '</p></div>';
				break;

			case 'expired':
			case 'license_expired':
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: 1: renewal URL */
					esc_html__( 'Your Agent Builder license has expired. Premium marketplace agents are disabled and updates are blocked. %1$s', 'agentbuilder' ),
					'<a href="https://agentic-plugin.com/pricing/">' . esc_html__( 'Renew your license', 'agentbuilder' ) . '</a>'
				);
				echo '</p></div>';
				break;

			case 'revoked':
			case 'license_revoked':
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Your Agent Builder license has been revoked. Premium marketplace agents are disabled. Please contact support.', 'agentbuilder' );
				echo '</p></div>';
				break;

			case 'invalid':
			case 'invalid_key':
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Your Agent Builder license key is invalid. Please check your license key in Settings.', 'agentbuilder' );
				echo '</p></div>';
				break;
		}

		// Stale cache warning.
		if ( ! empty( $status['cache_stale'] ) && $this->is_premium() ) {
			echo '<div class="notice notice-info is-dismissible"><p>';
			echo esc_html__( 'Agent Builder has not been able to verify your license recently. Your premium features will continue working. If this persists, please check your server\'s outbound connectivity.', 'agentbuilder' );
			echo '</p></div>';
		}
	}

	// =========================================================================
	// 6. LICENSE ACTIVATION / DEACTIVATION (AJAX)
	// =========================================================================

	/**
	 * AJAX handler: activate a plugin license key.
	 */
	public function ajax_activate_license(): void {
		check_ajax_referer( 'agentic_license_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'agentbuilder' ) );
		}

		$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );

		if ( empty( $license_key ) ) {
			wp_send_json_error( __( 'Please enter a license key.', 'agentbuilder' ) );
		}

		// Validate with server and activate for this site.
		$response = wp_remote_post(
			$this->api_base . '/wp-json/agentic-license/v1/activate',
			array(
				'timeout' => 15,
				'headers' => $this->get_site_headers(),
				'body'    => array(
					'license_key'    => $license_key,
					'site_url'       => home_url(),
					'site_name'      => get_bloginfo( 'name' ),
					'plugin_version' => AGENTIC_PLUGIN_VERSION,
					'wp_version'     => get_bloginfo( 'version' ),
					'php_version'    => PHP_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s: error message */
					__( 'Could not connect to license server: %s', 'agentbuilder' ),
					$response->get_error_message()
				)
			);
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$http_code = wp_remote_retrieve_response_code( $response );

		if ( empty( $body['activated'] ) ) {
			$message = $body['message'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'License activation failed (HTTP %d).', 'agentbuilder' ),
				$http_code
			);
			wp_send_json_error( $message );
		}

		// Store the license key.
		update_option( self::OPTION_LICENSE_KEY, $license_key );

		// Store validated license data.
		$license_data = array(
			'status'            => $body['license']['status'] ?? 'active',
			'type'              => $body['license']['type'] ?? 'personal',
			'expires_at'        => $body['license']['expires_at'] ?? '',
			'activations_used'  => $body['license']['activations_used'] ?? 1,
			'activations_limit' => $body['license']['activations_limit'] ?? 1,
			'validated_at'      => current_time( 'mysql' ),
		);
		update_option( self::OPTION_LICENSE_DATA, $license_data );

		// Reset in-memory cache.
		$this->cached_status = null;

		wp_send_json_success(
			array(
				'message' => $body['message'] ?? __( 'License activated successfully!', 'agentbuilder' ),
				'status'  => $this->get_status(),
			)
		);
	}

	/**
	 * AJAX handler: deactivate the plugin license.
	 */
	public function ajax_deactivate_license(): void {
		check_ajax_referer( 'agentic_license_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'agentbuilder' ) );
		}

		$license_key = get_option( self::OPTION_LICENSE_KEY, '' );

		if ( ! empty( $license_key ) ) {
			// Tell server to deactivate this site.
			wp_remote_post(
				$this->api_base . '/wp-json/agentic-license/v1/deactivate',
				array(
					'timeout' => 10,
					'headers' => $this->get_site_headers(),
					'body'    => array(
						'license_key' => $license_key,
						'site_url'    => home_url(),
					),
				)
			);
		}

		// Clear local data regardless of server response.
		delete_option( self::OPTION_LICENSE_KEY );
		delete_option( self::OPTION_LICENSE_DATA );
		$this->cached_status = null;

		wp_send_json_success(
			array(
				'message' => __( 'License deactivated.', 'agentbuilder' ),
			)
		);
	}

	// =========================================================================
	// INTERNAL HELPERS
	// =========================================================================

	/**
	 * Get standard identifying headers sent with every API request.
	 *
	 * @return array Headers array for wp_remote_* calls.
	 */
	private function get_site_headers(): array {
		$site_url = home_url();
		$version  = defined( 'AGENTIC_PLUGIN_VERSION' ) ? AGENTIC_PLUGIN_VERSION : '0.0.0';

		return array(
			'X-Agentic-Site-URL'       => $site_url,
			'X-Agentic-Site-Name'      => get_bloginfo( 'name' ),
			'X-Agentic-Plugin-Version' => $version,
			'X-Agentic-WP-Version'     => get_bloginfo( 'version' ),
			'X-Agentic-PHP-Version'    => PHP_VERSION,
			'User-Agent'               => 'AgentBuilder/' . $version . '; ' . $site_url,
		);
	}

	/**
	 * Evaluate license status from stored data, including grace period logic.
	 *
	 * @param array $data Stored license data.
	 * @return array Evaluated status with is_valid flag.
	 */
	private function evaluate_status( array $data ): array {
		$license_key         = get_option( self::OPTION_LICENSE_KEY, '' );
		$data['license_key'] = $this->mask_key( $license_key );

		$status = $data['status'] ?? 'invalid';

		// Active license — straightforward.
		if ( 'active' === $status ) {
			// Double-check expiration date.
			if ( ! empty( $data['expires_at'] ) && strtotime( $data['expires_at'] ) < time() ) {
				// Server said active but it's past expiration — check grace period.
				$status = 'expired';
			} else {
				$data['is_valid'] = true;
				return $data;
			}
		}

		// Expired — check grace period.
		if ( in_array( $status, array( 'expired', 'license_expired' ), true ) ) {
			if ( ! empty( $data['expires_at'] ) ) {
				$expires   = strtotime( $data['expires_at'] );
				$grace_end = $expires + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );
				$days_left = max( 0, (int) ceil( ( $grace_end - time() ) / DAY_IN_SECONDS ) );

				if ( time() <= $grace_end ) {
					$data['status']          = 'grace_period';
					$data['grace_days_left'] = $days_left;
					$data['is_valid']        = true;
					return $data;
				}
			}

			$data['status']   = 'expired';
			$data['is_valid'] = false;
			return $data;
		}

		// Revoked — no access.
		if ( in_array( $status, array( 'revoked', 'license_revoked' ), true ) ) {
			$data['is_valid'] = false;
			return $data;
		}

		// Anything else (invalid, pending, etc.) — no access.
		$data['is_valid'] = false;
		return $data;
	}

	/**
	 * Return the status array for an unlicensed (free) installation.
	 *
	 * @return array
	 */
	private function free_status(): array {
		return array(
			'status'      => 'free',
			'is_valid'    => false,
			'license_key' => '',
			'type'        => 'free',
			'message'     => 'No license key entered.',
		);
	}

	/**
	 * Mask a license key for display (show first and last segments).
	 *
	 * @param string $key Full license key.
	 * @return string Masked key, e.g. "AGNT-****-****-****-JG2R"
	 */
	private function mask_key( string $key ): string {
		if ( strlen( $key ) < 10 ) {
			return '****';
		}

		$parts = explode( '-', $key );
		if ( count( $parts ) < 3 ) {
			return substr( $key, 0, 4 ) . '****' . substr( $key, -4 );
		}

		$first  = $parts[0];
		$last   = end( $parts );
		$middle = array_fill( 0, count( $parts ) - 2, '****' );

		return $first . '-' . implode( '-', $middle ) . '-' . $last;
	}

	/**
	 * Log a license-related message (debug only).
	 *
	 * @param string $message Log message.
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Agentic License: ' . $message );
		}
	}

	/**
	 * Clean up on plugin deactivation.
	 */
	public function on_deactivation(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
