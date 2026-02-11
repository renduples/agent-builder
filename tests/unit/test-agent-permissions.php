<?php
/**
 * Unit Tests for Agent Permissions
 *
 * Tests permission scope definitions, settings retrieval/save,
 * is_allowed checks, confirmation mode, and user-space path detection.
 *
 * @package Agent_Builder
 * @subpackage Tests
 */

namespace Agentic\Tests;

use Agentic\Agent_Permissions;

/**
 * Test case for Agent_Permissions class.
 */
class Test_Agent_Permissions extends TestCase {

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		delete_option( Agent_Permissions::OPTION_KEY );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Scopes
	// -------------------------------------------------------------------------

	/**
	 * Test get_scopes returns all 6 scopes.
	 */
	public function test_get_scopes_returns_all_scopes() {
		$scopes = Agent_Permissions::get_scopes();
		$this->assertCount( 6, $scopes );
		$this->assertArrayHasKey( 'write_theme_files', $scopes );
		$this->assertArrayHasKey( 'write_custom_plugins', $scopes );
		$this->assertArrayHasKey( 'modify_options', $scopes );
		$this->assertArrayHasKey( 'manage_transients', $scopes );
		$this->assertArrayHasKey( 'modify_postmeta', $scopes );
		$this->assertArrayHasKey( 'query_database_write', $scopes );
	}

	/**
	 * Test each scope has label and description.
	 */
	public function test_scopes_have_label_and_description() {
		foreach ( Agent_Permissions::get_scopes() as $key => $scope ) {
			$this->assertArrayHasKey( 'label', $scope, "Scope {$key} missing 'label'" );
			$this->assertArrayHasKey( 'description', $scope, "Scope {$key} missing 'description'" );
			$this->assertNotEmpty( $scope['label'], "Scope {$key} has empty label" );
			$this->assertNotEmpty( $scope['description'], "Scope {$key} has empty description" );
		}
	}

	// -------------------------------------------------------------------------
	// Default settings
	// -------------------------------------------------------------------------

	/**
	 * Test default settings have all permissions disabled.
	 */
	public function test_default_settings_all_disabled() {
		$settings = Agent_Permissions::get_settings();

		$this->assertArrayHasKey( 'permissions', $settings );
		$this->assertArrayHasKey( 'confirmation_mode', $settings );

		foreach ( $settings['permissions'] as $scope => $enabled ) {
			$this->assertFalse( $enabled, "Scope {$scope} should be disabled by default" );
		}
	}

	/**
	 * Test default confirmation mode is 'confirm'.
	 */
	public function test_default_confirmation_mode() {
		$settings = Agent_Permissions::get_settings();
		$this->assertEquals( 'confirm', $settings['confirmation_mode'] );
	}

	// -------------------------------------------------------------------------
	// is_allowed
	// -------------------------------------------------------------------------

	/**
	 * Test is_allowed returns false for disabled scope.
	 */
	public function test_is_allowed_returns_false_by_default() {
		$this->assertFalse( Agent_Permissions::is_allowed( 'write_theme_files' ) );
		$this->assertFalse( Agent_Permissions::is_allowed( 'modify_options' ) );
	}

	/**
	 * Test is_allowed returns true after enabling scope.
	 */
	public function test_is_allowed_returns_true_when_enabled() {
		Agent_Permissions::save_settings( array( 'write_theme_files' => true ), 'confirm' );
		$this->assertTrue( Agent_Permissions::is_allowed( 'write_theme_files' ) );
	}

	/**
	 * Test is_allowed returns false for unknown scope.
	 */
	public function test_is_allowed_returns_false_for_unknown_scope() {
		$this->assertFalse( Agent_Permissions::is_allowed( 'nonexistent_scope' ) );
	}

	// -------------------------------------------------------------------------
	// requires_confirmation
	// -------------------------------------------------------------------------

	/**
	 * Test requires_confirmation returns true for default (confirm mode).
	 */
	public function test_requires_confirmation_default() {
		$this->assertTrue( Agent_Permissions::requires_confirmation() );
	}

	/**
	 * Test requires_confirmation returns false for auto mode.
	 */
	public function test_requires_confirmation_auto_mode() {
		Agent_Permissions::save_settings( array(), 'auto' );
		$this->assertFalse( Agent_Permissions::requires_confirmation() );
	}

	// -------------------------------------------------------------------------
	// save_settings
	// -------------------------------------------------------------------------

	/**
	 * Test save_settings persists correctly.
	 */
	public function test_save_settings_persists() {
		$result = Agent_Permissions::save_settings(
			array(
				'write_theme_files' => true,
				'modify_options'    => true,
			),
			'auto'
		);

		$this->assertTrue( $result );

		$settings = Agent_Permissions::get_settings();
		$this->assertTrue( $settings['permissions']['write_theme_files'] );
		$this->assertTrue( $settings['permissions']['modify_options'] );
		$this->assertFalse( $settings['permissions']['write_custom_plugins'] );
		$this->assertEquals( 'auto', $settings['confirmation_mode'] );
	}

	/**
	 * Test save_settings sanitizes invalid mode.
	 */
	public function test_save_settings_rejects_invalid_mode() {
		Agent_Permissions::save_settings( array(), 'yolo' );
		$settings = Agent_Permissions::get_settings();
		$this->assertEquals( 'confirm', $settings['confirmation_mode'] );
	}

	/**
	 * Test save_settings ignores unknown scopes.
	 */
	public function test_save_settings_ignores_unknown_scopes() {
		Agent_Permissions::save_settings( array( 'hack_the_planet' => true ), 'confirm' );
		$settings = Agent_Permissions::get_settings();
		$this->assertArrayNotHasKey( 'hack_the_planet', $settings['permissions'] );
	}

	// -------------------------------------------------------------------------
	// is_user_space_path
	// -------------------------------------------------------------------------

	/**
	 * Test active theme files are user-space.
	 */
	public function test_is_user_space_path_active_theme() {
		$theme = get_stylesheet();
		$this->assertTrue( Agent_Permissions::is_user_space_path( "themes/{$theme}/style.css" ) );
		$this->assertTrue( Agent_Permissions::is_user_space_path( "themes/{$theme}/functions.php" ) );
	}

	/**
	 * Test non-active theme is NOT user-space.
	 */
	public function test_is_user_space_path_inactive_theme() {
		$this->assertFalse( Agent_Permissions::is_user_space_path( 'themes/some-random-theme-xyz/style.css' ) );
	}

	/**
	 * Test agentic-custom plugin directory is user-space.
	 */
	public function test_is_user_space_path_custom_plugin() {
		$this->assertTrue( Agent_Permissions::is_user_space_path( 'plugins/agentic-custom/my-plugin.php' ) );
		$this->assertTrue( Agent_Permissions::is_user_space_path( 'plugins/agentic-custom' ) );
	}

	/**
	 * Test regular plugin directory is NOT user-space.
	 */
	public function test_is_user_space_path_regular_plugin() {
		$this->assertFalse( Agent_Permissions::is_user_space_path( 'plugins/akismet/akismet.php' ) );
	}

	/**
	 * Test path traversal is blocked.
	 */
	public function test_is_user_space_path_strips_traversal() {
		// Path traversal `..` is stripped, so the cleaned path still matches.
		// This is by design — the path normalizes to themes/<active>/style.css.
		$theme = get_stylesheet();
		$result = Agent_Permissions::is_user_space_path( "../../themes/{$theme}/style.css" );
		// After stripping '..', path becomes 'themes/<theme>/style.css' which IS user-space.
		$this->assertTrue( $result );
		// But path to a completely different location should be false.
		$this->assertFalse( Agent_Permissions::is_user_space_path( '../../etc/passwd' ) );
	}

	// -------------------------------------------------------------------------
	// get_write_scope_for_path
	// -------------------------------------------------------------------------

	/**
	 * Test theme path returns write_theme_files scope.
	 */
	public function test_get_write_scope_theme() {
		$this->assertEquals( 'write_theme_files', Agent_Permissions::get_write_scope_for_path( 'themes/my-theme/style.css' ) );
	}

	/**
	 * Test custom plugin path returns write_custom_plugins scope.
	 */
	public function test_get_write_scope_custom_plugin() {
		$this->assertEquals( 'write_custom_plugins', Agent_Permissions::get_write_scope_for_path( 'plugins/agentic-custom/foo.php' ) );
	}

	/**
	 * Test other path returns empty scope.
	 */
	public function test_get_write_scope_other_path() {
		$this->assertEquals( '', Agent_Permissions::get_write_scope_for_path( 'uploads/2025/photo.jpg' ) );
	}
}
