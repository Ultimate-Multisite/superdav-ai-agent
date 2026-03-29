<?php
/**
 * Test case for SiteBuilderAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\SiteBuilderAbilities;
use WP_UnitTestCase;

/**
 * Test SiteBuilderAbilities handler methods.
 */
class SiteBuilderAbilitiesTest extends WP_UnitTestCase {

	// ─── check_fresh_install ──────────────────────────────────────

	/**
	 * Test check_fresh_install returns expected structure.
	 */
	public function test_check_fresh_install_returns_structure() {
		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'is_fresh', $result );
		$this->assertArrayHasKey( 'post_count', $result );
		$this->assertArrayHasKey( 'page_count', $result );
		$this->assertArrayHasKey( 'has_custom_menu', $result );
		$this->assertArrayHasKey( 'site_title', $result );
		$this->assertArrayHasKey( 'has_default_title', $result );
	}

	/**
	 * Test check_fresh_install is_fresh is boolean.
	 */
	public function test_check_fresh_install_is_fresh_is_bool() {
		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertIsBool( $result['is_fresh'] );
	}

	/**
	 * Test check_fresh_install post_count and page_count are integers.
	 */
	public function test_check_fresh_install_counts_are_ints() {
		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertIsInt( $result['post_count'] );
		$this->assertIsInt( $result['page_count'] );
		$this->assertGreaterThanOrEqual( 0, $result['post_count'] );
		$this->assertGreaterThanOrEqual( 0, $result['page_count'] );
	}

	/**
	 * Test check_fresh_install with published posts marks site as not fresh.
	 */
	public function test_check_fresh_install_not_fresh_with_posts() {
		// Create multiple published posts to exceed the "fresh" threshold.
		$this->factory->post->create_many( 3, [ 'post_status' => 'publish' ] );

		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertFalse( $result['is_fresh'] );
		$this->assertGreaterThanOrEqual( 3, $result['post_count'] );
	}

	/**
	 * Test check_fresh_install has_custom_menu is boolean.
	 */
	public function test_check_fresh_install_has_custom_menu_is_bool() {
		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertIsBool( $result['has_custom_menu'] );
	}

	/**
	 * Test check_fresh_install site_title is a string.
	 */
	public function test_check_fresh_install_site_title_is_string() {
		$result = SiteBuilderAbilities::check_fresh_install();

		$this->assertIsString( $result['site_title'] );
	}

	// ─── handle_detect_fresh_install ─────────────────────────────

	/**
	 * Test handle_detect_fresh_install returns expected structure.
	 */
	public function test_handle_detect_fresh_install_returns_structure() {
		$result = SiteBuilderAbilities::handle_detect_fresh_install();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'is_fresh', $result );
		$this->assertArrayHasKey( 'post_count', $result );
		$this->assertArrayHasKey( 'page_count', $result );
		$this->assertArrayHasKey( 'has_custom_menu', $result );
		$this->assertArrayHasKey( 'site_title', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test handle_detect_fresh_install message is a non-empty string.
	 */
	public function test_handle_detect_fresh_install_message_is_string() {
		$result = SiteBuilderAbilities::handle_detect_fresh_install();

		$this->assertIsString( $result['message'] );
		$this->assertNotEmpty( $result['message'] );
	}

	// ─── handle_set_site_builder_mode ────────────────────────────

	/**
	 * Test handle_set_site_builder_mode enable returns success structure.
	 */
	public function test_handle_set_site_builder_mode_enable() {
		$result = SiteBuilderAbilities::handle_set_site_builder_mode( [ 'enabled' => true ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'site_builder_mode', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['site_builder_mode'] );
	}

	/**
	 * Test handle_set_site_builder_mode disable returns success structure.
	 */
	public function test_handle_set_site_builder_mode_disable() {
		$result = SiteBuilderAbilities::handle_set_site_builder_mode( [ 'enabled' => false ] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['site_builder_mode'] );
	}

	/**
	 * Test handle_set_site_builder_mode message is a non-empty string.
	 */
	public function test_handle_set_site_builder_mode_message_is_string() {
		$result = SiteBuilderAbilities::handle_set_site_builder_mode( [ 'enabled' => true ] );

		$this->assertIsString( $result['message'] );
		$this->assertNotEmpty( $result['message'] );
	}

	// ─── handle_get_site_builder_status ──────────────────────────

	/**
	 * Test handle_get_site_builder_status returns expected structure.
	 */
	public function test_handle_get_site_builder_status_returns_structure() {
		$result = SiteBuilderAbilities::handle_get_site_builder_status();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'site_builder_mode', $result );
		$this->assertArrayHasKey( 'onboarding_complete', $result );
		$this->assertArrayHasKey( 'site_title', $result );
		$this->assertArrayHasKey( 'site_url', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test handle_get_site_builder_status site_builder_mode is boolean.
	 */
	public function test_handle_get_site_builder_status_mode_is_bool() {
		$result = SiteBuilderAbilities::handle_get_site_builder_status();

		$this->assertIsBool( $result['site_builder_mode'] );
		$this->assertIsBool( $result['onboarding_complete'] );
	}

	// ─── handle_complete_site_builder ────────────────────────────

	/**
	 * Test handle_complete_site_builder returns success structure.
	 */
	public function test_handle_complete_site_builder_returns_structure() {
		$result = SiteBuilderAbilities::handle_complete_site_builder();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test handle_complete_site_builder disables site builder mode.
	 */
	public function test_handle_complete_site_builder_disables_mode() {
		// First enable site builder mode.
		SiteBuilderAbilities::handle_set_site_builder_mode( [ 'enabled' => true ] );

		// Then complete it.
		SiteBuilderAbilities::handle_complete_site_builder();

		// Status should now show mode disabled and onboarding complete.
		$status = SiteBuilderAbilities::handle_get_site_builder_status();
		$this->assertFalse( $status['site_builder_mode'] );
		$this->assertTrue( $status['onboarding_complete'] );
	}
}
