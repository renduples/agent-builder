<?php
/**
 * License Manager
 *
 * Handles license validation, activation, and deactivation for premium features.
 *
 * @package Agentic_Plugin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * License Manager class
 */
class License_Manager {

	/**
	 * API base URL
	 */
	private const API_URL = 'https://agentic-plugin.com/wp-json/agentic-license/v1';

	/**
	 * License key option name
	 */
	private const OPTION_KEY = 'agentic_license_key';

	/**
	 * License cache transient key
	 */
	private const CACHE_KEY = 'agentic_license_cache';

	/**
	 * Cache duration (24 hours)
	 */
	private const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Grace period duration (7 days)
	 */
	private const GRACE_PERIOD = 7 * DAY_IN_SECONDS;

	/**
	 * Secret salt for hashing
	 * This will be retrieved from the server and should not be committed to the repo.
	 *
	 * @var string
	 */
	private static $hash_salt = '';

	/**
	 * Get stored license key
	 *
	 * @return string License key or empty string.
	 */
	public static function get_license_key(): string {
		return get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Save license key
	 *
	 * @param string $key License key to save.
	 * @return bool True on success, false on failure.
	 */
	public static function save_license_key( string $key ): bool {
		$key = self::sanitize_key( $key );
		return update_option( self::OPTION_KEY, $key );
	}

	/**
	 * Sanitize license key format
	 *
	 * @param string $key License key to sanitize.
	 * @return string Sanitized license key.
	 */
	public static function sanitize_key( string $key ): string {
		$key = strtoupper( trim( $key ) );
		$key = preg_replace( '/[^A-Z0-9-]/', '', $key );
		return $key;
	}

	/**
	 * Get or initialize hash salt
	 *
	 * @return string Hash salt.
	 */
	private static function get_hash_salt(): string {
		if ( ! empty( self::$hash_salt ) ) {
			return self::$hash_salt;
		}

		// Try to get from option (will be set via CLI or admin).
		$salt = get_option( 'agentic_license_hash_salt', '' );

		if ( empty( $salt ) ) {
			// Generate a temporary salt for testing (not production-ready).
			$salt = wp_generate_password( 64, false );
			// Don't save it automatically - must be set manually.
		}

		self::$hash_salt = $salt;
		return $salt;
	}

	/**
	 * Generate request signature
	 *
	 * @param string $license_key License key.
	 * @param string $site_url    Site URL.
	 * @return string SHA256 hash signature.
	 */
	private static function generate_signature( string $license_key, string $site_url ): string {
		return hash( 'sha256', $license_key . $site_url . self::get_hash_salt() );
	}

	/**
	 * Check if license is valid (with caching)
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid(): bool {
		$license_key = self::get_license_key();

		if ( empty( $license_key ) ) {
			return false;
		}

		// Check cache first.
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return true === $cached['valid'];
		}

		// Validate with API.
		$result = self::validate_with_api( $license_key );

		// Cache result.
		if ( null !== $result ) {
			set_transient( self::CACHE_KEY, $result, self::CACHE_DURATION );
			return true === $result['valid'];
		}

		// API unreachable - be lenient with grace period.
		return self::is_within_grace_period();
	}

	/**
	 * Check if we're within the grace period
	 *
	 * @return bool True if within grace period, false otherwise.
	 */
	private static function is_within_grace_period(): bool {
		$last_valid = get_option( 'agentic_license_last_valid_time', 0 );

		if ( empty( $last_valid ) ) {
			return false;
		}

		$time_since = time() - $last_valid;
		return $time_since < self::GRACE_PERIOD;
	}

	/**
	 * Validate license with API
	 *
	 * @param string $license_key License key to validate.
	 * @return array|null Validation result or null on failure.
	 */
	public static function validate_with_api( string $license_key ): ?array {
		$site_url = home_url();

		$response = wp_remote_post(
			self::API_URL . '/validate',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-License-Key' => $license_key,
					'X-Site-URL'    => $site_url,
					'X-Site-Hash'   => self::generate_signature( $license_key, $site_url ),
				),
				'body'    => wp_json_encode(
					array(
						'license_key'    => $license_key,
						'site_url'       => $site_url,
						'plugin_version' => defined( 'AGENTIC_PLUGIN_VERSION' ) ? AGENTIC_PLUGIN_VERSION : '1.0.0',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null; // API unreachable.
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['valid'] ) && true === $body['valid'] ) {
			update_option( 'agentic_license_last_valid_time', time() );
		}

		return $body;
	}

	/**
	 * Activate license for this site
	 *
	 * @param string $license_key License key to activate.
	 * @return array Result array with success status and message.
	 */
	public static function activate( string $license_key ): array {
		$license_key = self::sanitize_key( $license_key );
		$site_url    = home_url();

		$response = wp_remote_post(
			self::API_URL . '/activate',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-License-Key' => $license_key,
					'X-Site-URL'    => $site_url,
					'X-Site-Hash'   => self::generate_signature( $license_key, $site_url ),
				),
				'body'    => wp_json_encode(
					array(
						'license_key'    => $license_key,
						'site_url'       => $site_url,
						'site_name'      => get_bloginfo( 'name' ),
						'plugin_version' => defined( 'AGENTIC_PLUGIN_VERSION' ) ? AGENTIC_PLUGIN_VERSION : '1.0.0',
						'wp_version'     => get_bloginfo( 'version' ),
						'php_version'    => PHP_VERSION,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'connection_failed',
				'message' => 'Could not connect to license server. Please try again.',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['activated'] ) && true === $body['activated'] ) {
			self::save_license_key( $license_key );
			delete_transient( self::CACHE_KEY ); // Clear cache.
			update_option( 'agentic_license_last_valid_time', time() );

			return array(
				'success' => true,
				'message' => $body['message'] ?? 'License activated successfully!',
				'license' => $body['license'] ?? array(),
			);
		}

		return array(
			'success' => false,
			'error'   => $body['error'] ?? 'unknown_error',
			'message' => $body['message'] ?? 'License activation failed.',
		);
	}

	/**
	 * Deactivate license from this site
	 *
	 * @return array Result array with success status and message.
	 */
	public static function deactivate(): array {
		$license_key = self::get_license_key();
		$site_url    = home_url();

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => 'No license key found.',
			);
		}

		$response = wp_remote_post(
			self::API_URL . '/deactivate',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-License-Key' => $license_key,
					'X-Site-URL'    => $site_url,
					'X-Site-Hash'   => self::generate_signature( $license_key, $site_url ),
				),
				'body'    => wp_json_encode(
					array(
						'license_key' => $license_key,
						'site_url'    => $site_url,
					)
				),
			)
		);

		// Clear local data regardless of API response.
		delete_option( self::OPTION_KEY );
		delete_transient( self::CACHE_KEY );
		delete_option( 'agentic_license_last_valid_time' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => true, // Local deactivation succeeded.
				'message' => 'License removed locally. Server sync pending.',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'success' => true,
			'message' => $body['message'] ?? 'License deactivated.',
		);
	}

	/**
	 * Get license details for display
	 *
	 * @return array|null License information or null if not available.
	 */
	public static function get_license_info(): ?array {
		$license_key = self::get_license_key();

		if ( empty( $license_key ) ) {
			return null;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && isset( $cached['license'] ) ) {
			return $cached['license'];
		}

		$result = self::validate_with_api( $license_key );
		return $result['license'] ?? null;
	}

	/**
	 * Clear cached license data
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Check if a specific feature is available
	 *
	 * @param string $feature Feature name to check.
	 * @return bool True if feature is available, false otherwise.
	 */
	public static function has_feature( string $feature ): bool {
		if ( ! self::is_valid() ) {
			return false;
		}

		$info = self::get_license_info();

		if ( null === $info || ! isset( $info['features'] ) ) {
			return false;
		}

		return in_array( $feature, $info['features'], true );
	}
}
