<?php

declare(strict_types=1);
/**
 * Test case for NavigateAbility class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\NavigateAbility;
use WP_UnitTestCase;

/**
 * Test NavigateAbility functionality.
 */
class NavigateAbilityTest extends WP_UnitTestCase {

	/**
	 * Build a NavigateAbility instance for testing.
	 *
	 * @return NavigateAbility
	 */
	private function make_ability(): NavigateAbility {
		return new NavigateAbility(
			'sd-ai-agent/navigate',
			[
				'label'       => 'Navigate',
				'description' => 'Navigate the user to a URL within the WordPress site.',
			]
		);
	}

	// ── execute_callback — empty URL ──────────────────────────────────────

	/**
	 * execute_callback() returns WP_Error for empty URL.
	 */
	public function test_execute_returns_wp_error_for_empty_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_empty_url', $result->get_error_code() );
	}

	/**
	 * execute_callback() returns WP_Error when URL is missing.
	 */
	public function test_execute_returns_wp_error_for_missing_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_empty_url', $result->get_error_code() );
	}

	// ── execute_callback — external URL ──────────────────────────────────

	/**
	 * execute_callback() returns WP_Error for external URL.
	 */
	public function test_execute_returns_wp_error_for_external_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => 'https://example.com/external-page' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_url', $result->get_error_code() );
	}

	/**
	 * execute_callback() returns WP_Error for host-substring attack URL.
	 */
	public function test_execute_returns_wp_error_for_host_substring_attack(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Construct a URL that contains the site host as a substring but is a different domain.
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$evil_url  = 'https://' . $home_host . '.evil.tld/page';

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => $evil_url ] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_url', $result->get_error_code() );
	}

	// ── execute_callback — relative URL ──────────────────────────────────

	/**
	 * execute_callback() accepts relative URL starting with /.
	 */
	public function test_execute_accepts_relative_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => '/wp-admin/edit.php' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'navigate', $result['action'] );
		$this->assertStringContainsString( '/wp-admin/edit.php', $result['url'] );
	}

	/**
	 * execute_callback() converts relative URL to absolute using home_url().
	 */
	public function test_execute_converts_relative_url_to_absolute(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => '/some-page/' ] );

		$this->assertIsArray( $result );
		$this->assertStringStartsWith( home_url(), $result['url'] );
	}

	// ── execute_callback — full site URL ─────────────────────────────────

	/**
	 * execute_callback() accepts full URL within the site.
	 */
	public function test_execute_accepts_full_site_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$home_url = home_url();
		$ability  = $this->make_ability();
		$result   = $ability->run( [ 'url' => $home_url . '/some-page/' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'navigate', $result['action'] );
		$this->assertSame( $home_url . '/some-page/', $result['url'] );
	}

	// ── execute_callback — ThickBox/iframe URL ────────────────────────────

	/**
	 * execute_callback() returns WP_Error for ThickBox/iframe URL.
	 */
	public function test_execute_returns_wp_error_for_thickbox_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => home_url( '/wp-admin/media-upload.php?TB_iframe=true' ) ] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_iframe_url', $result->get_error_code() );
	}

	// ── execute_callback — result shape ──────────────────────────────────

	/**
	 * execute_callback() returns expected shape for valid URL.
	 */
	public function test_execute_returns_expected_shape(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => '/wp-admin/' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * execute_callback() message contains the URL.
	 */
	public function test_execute_message_contains_url(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$result  = $ability->run( [ 'url' => '/wp-admin/edit.php' ] );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'wp-admin', $result['message'] );
	}
}
