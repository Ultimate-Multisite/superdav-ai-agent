<?php
/**
 * Test case for NavigationAbilities class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\NavigationAbilities;
use WP_UnitTestCase;

/**
 * Test NavigationAbilities handler methods.
 */
class NavigationAbilitiesTest extends WP_UnitTestCase {

	// ─── navigate ─────────────────────────────────────────────────

	/**
	 * Test handle_navigate with relative URL.
	 */
	public function test_handle_navigate_relative_url() {
		$result = NavigationAbilities::handle_navigate( [
			'url' => '/wp-admin/edit.php',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertSame( 'navigate', $result['action'] );
		$this->assertStringContainsString( '/wp-admin/edit.php', $result['url'] );
	}

	/**
	 * Test handle_navigate with full site URL.
	 */
	public function test_handle_navigate_full_site_url() {
		$home_url = home_url();

		$result = NavigationAbilities::handle_navigate( [
			'url' => $home_url . '/some-page/',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'navigate', $result['action'] );
		$this->assertSame( $home_url . '/some-page/', $result['url'] );
	}

	/**
	 * Test handle_navigate with external URL returns WP_Error.
	 */
	public function test_handle_navigate_external_url() {
		$result = NavigationAbilities::handle_navigate( [
			'url' => 'https://example.com/external-page',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_invalid_url', $result->get_error_code() );
	}

	/**
	 * Test handle_navigate with empty URL returns WP_Error.
	 */
	public function test_handle_navigate_empty_url() {
		$result = NavigationAbilities::handle_navigate( [
			'url' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_empty_url', $result->get_error_code() );
	}

	/**
	 * Test handle_navigate with missing URL returns WP_Error.
	 */
	public function test_handle_navigate_missing_url() {
		$result = NavigationAbilities::handle_navigate( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_navigate blocks ThickBox/iframe URLs.
	 */
	public function test_handle_navigate_blocks_iframe_url() {
		$home_url = home_url();

		$result = NavigationAbilities::handle_navigate( [
			'url' => $home_url . '/wp-admin/media-upload.php?TB_iframe=true',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_iframe_url', $result->get_error_code() );
	}

	/**
	 * Test handle_navigate message contains the URL.
	 */
	public function test_handle_navigate_message_contains_url() {
		$result = NavigationAbilities::handle_navigate( [
			'url' => '/wp-admin/',
		] );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'wp-admin', $result['message'] );
	}

	/**
	 * Test handle_navigate with wp-admin relative URL.
	 */
	public function test_handle_navigate_wp_admin_relative() {
		$result = NavigationAbilities::handle_navigate( [
			'url' => '/wp-admin/',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'navigate', $result['action'] );
		$this->assertStringContainsString( 'wp-admin', $result['url'] );
	}

	// ─── get-page-html ────────────────────────────────────────────

	/**
	 * Test handle_get_page_html with valid selector.
	 */
	public function test_handle_get_page_html_valid_selector() {
		$result = NavigationAbilities::handle_get_page_html( [
			'selector' => '#main-content',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'selector', $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertSame( '#main-content', $result['selector'] );
		$this->assertSame( 'get_page_html', $result['action'] );
	}

	/**
	 * Test handle_get_page_html with empty selector returns WP_Error.
	 */
	public function test_handle_get_page_html_empty_selector() {
		$result = NavigationAbilities::handle_get_page_html( [
			'selector' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_empty_selector', $result->get_error_code() );
	}

	/**
	 * Test handle_get_page_html with missing selector returns WP_Error.
	 */
	public function test_handle_get_page_html_missing_selector() {
		$result = NavigationAbilities::handle_get_page_html( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_get_page_html default max_length is 5000.
	 */
	public function test_handle_get_page_html_default_max_length() {
		$result = NavigationAbilities::handle_get_page_html( [
			'selector' => 'body',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'max_length', $result );
		$this->assertSame( 5000, $result['max_length'] );
	}

	/**
	 * Test handle_get_page_html with custom max_length.
	 */
	public function test_handle_get_page_html_custom_max_length() {
		$result = NavigationAbilities::handle_get_page_html( [
			'selector'   => 'body',
			'max_length' => 1000,
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 1000, $result['max_length'] );
	}

	/**
	 * Test handle_get_page_html message contains selector.
	 */
	public function test_handle_get_page_html_message_contains_selector() {
		$result = NavigationAbilities::handle_get_page_html( [
			'selector' => '.entry-content',
		] );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( '.entry-content', $result['message'] );
	}
}
