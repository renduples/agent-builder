<?php
/**
 * WordPress Function Mocks
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Mockery;

/**
 * Provides mocked WordPress functions for testing.
 */
class MockWPFunctions {

	/**
	 * Mock WordPress options functions.
	 */
	public static function mock_options() {
		$options = array();

		Mockery::mock( 'alias:get_option' )
			->shouldReceive( 'get_option' )
			->andReturnUsing(
				function ( $key, $default = false ) use ( &$options ) {
					return $options[ $key ] ?? $default;
				}
			);

		Mockery::mock( 'alias:update_option' )
			->shouldReceive( 'update_option' )
			->andReturnUsing(
				function ( $key, $value ) use ( &$options ) {
					$options[ $key ] = $value;
					return true;
				}
			);

		Mockery::mock( 'alias:delete_option' )
			->shouldReceive( 'delete_option' )
			->andReturnUsing(
				function ( $key ) use ( &$options ) {
					unset( $options[ $key ] );
					return true;
				}
			);
	}

	/**
	 * Mock WordPress transient functions.
	 */
	public static function mock_transients() {
		$transients = array();

		Mockery::mock( 'alias:get_transient' )
			->shouldReceive( 'get_transient' )
			->andReturnUsing(
				function ( $key ) use ( &$transients ) {
					return $transients[ $key ] ?? false;
				}
			);

		Mockery::mock( 'alias:set_transient' )
			->shouldReceive( 'set_transient' )
			->andReturnUsing(
				function ( $key, $value, $expiration ) use ( &$transients ) {
					$transients[ $key ] = $value;
					return true;
				}
			);

		Mockery::mock( 'alias:delete_transient' )
			->shouldReceive( 'delete_transient' )
			->andReturnUsing(
				function ( $key ) use ( &$transients ) {
					unset( $transients[ $key ] );
					return true;
				}
			);
	}

	/**
	 * Mock wp_remote_post function.
	 *
	 * @param array $response Response data.
	 * @return \Mockery\MockInterface
	 */
	public static function mock_remote_post( $response = null ) {
		if ( null === $response ) {
			$response = array(
				'body'     => '{"choices":[{"message":{"content":"Test response"}}]}',
				'response' => array( 'code' => 200 ),
			);
		}

		return Mockery::mock( 'alias:wp_remote_post' )
			->shouldReceive( 'wp_remote_post' )
			->andReturn( $response );
	}

	/**
	 * Mock wp_remote_get function.
	 *
	 * @param array $response Response data.
	 * @return \Mockery\MockInterface
	 */
	public static function mock_remote_get( $response = null ) {
		if ( null === $response ) {
			$response = array(
				'body'     => '{"data":[]}',
				'response' => array( 'code' => 200 ),
			);
		}

		return Mockery::mock( 'alias:wp_remote_get' )
			->shouldReceive( 'wp_remote_get' )
			->andReturn( $response );
	}

	/**
	 * Mock current_user_can function.
	 *
	 * @param bool $can_do Whether user has capability.
	 * @return \Mockery\MockInterface
	 */
	public static function mock_current_user_can( $can_do = true ) {
		return Mockery::mock( 'alias:current_user_can' )
			->shouldReceive( 'current_user_can' )
			->andReturn( $can_do );
	}

	/**
	 * Mock wp_verify_nonce function.
	 *
	 * @param bool $valid Whether nonce is valid.
	 * @return \Mockery\MockInterface
	 */
	public static function mock_verify_nonce( $valid = true ) {
		return Mockery::mock( 'alias:wp_verify_nonce' )
			->shouldReceive( 'wp_verify_nonce' )
			->andReturn( $valid ? 1 : false );
	}
}
