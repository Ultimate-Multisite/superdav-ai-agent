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

	/** Admin-relative paths use the authoritative admin URL. */
	public function test_admin_path_uses_current_blog_url(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$result = $this->make_ability()->run( [ 'path' => 'plugins.php' ] );
		$this->assertIsArray( $result );
		$this->assertSame( get_admin_url( null, 'plugins.php' ), $result['url'] );
		$this->assertSame( 'navigate', $result['action'] );
	}

	/** Reject duplicated admin paths and ambiguous URL forms. */
	public function test_rejects_malformed_admin_urls(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( [ '/wp-admin/woo/wp-admin/plugins.php', '//evil.example/wp-admin/', '/wp-admin/../woo/wp-admin/plugins.php', '/wp-admin/%2e%2e/woo/wp-admin/plugins.php', '/unknown-blog/wp-admin/plugins.php', '/wp-admin/%252e%252e/woo/', '/woo%2fwp-admin/plugins.php' ] as $url ) {
			$this->assertWPError( $this->make_ability()->run( [ 'url' => $url ] ) );
		}
	}

	/** Cross-blog navigation returns a link without switching current context. */
	public function test_multisite_target_admin_link_preserves_origin(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite fixture required.' );
		}
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$blog_id = self::factory()->blog->create( [ 'path' => '/woo/' ] );
		add_user_to_blog( $blog_id, $user_id, 'administrator' );
		$origin = get_current_blog_id();
		$result = $this->make_ability()->run( [ 'blog_id' => $blog_id, 'path' => 'plugins.php' ] );
		$this->assertIsArray( $result );
		$this->assertSame( get_admin_url( $blog_id, 'plugins.php' ), $result['url'] );
		$this->assertSame( 'link', $result['action'] );
		$this->assertSame( $origin, get_current_blog_id() );
		$result = $this->make_ability()->run( [ 'url' => get_admin_url( $blog_id, 'plugins.php' ) ] );
		$this->assertSame( 'link', $result['action'] );
		$router = \SdAiAgent\Core\ClientAbilityRouter::from_raw( [ [ 'name' => 'sd-ai-agent-js/navigate-to' ] ] );
		$method = new \ReflectionMethod( $router, 'get_browser_navigation_args' );
		$this->assertNull( $method->invoke( $router, 'sd-ai-agent/ability-call', [ 'ability' => 'sd-ai-agent/navigate', 'arguments' => [ 'blog_id' => $blog_id, 'path' => 'plugins.php' ] ], [ 'sd-ai-agent-js/navigate-to' ] ) );
		switch_to_blog( $blog_id );
		try {
			$result = $this->make_ability()->run( [ 'url' => get_admin_url( $origin, 'plugins.php' ) ] );
			$this->assertSame( 'link', $result['action'] );
			$result = $this->make_ability()->run( [ 'path' => 'plugins.php' ] );
			$this->assertSame( get_admin_url( $blog_id, 'plugins.php' ), $result['url'] );
			$this->assertSame( 'navigate', $result['action'] );
		} finally {
			restore_current_blog();
		}
		remove_user_from_blog( $user_id, $blog_id );
		$this->assertWPError( $this->make_ability()->run( [ 'blog_id' => $blog_id, 'path' => 'plugins.php' ] ) );
	}

	/** Subdomain and port-bearing WordPress domains are distinct blogs too. */
	public function test_multisite_subdomain_and_port_links(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite fixture required.' );
		}
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		foreach ( [ [ 'domain' => 'woo.example.org', 'path' => '/' ], [ 'domain' => 'example.org:8080', 'path' => '/woo/' ] ] as $site ) {
			$blog_id = self::factory()->blog->create( $site );
			add_user_to_blog( $blog_id, $user_id, 'administrator' );
			$result = $this->make_ability()->run( [ 'blog_id' => $blog_id, 'path' => 'plugins.php' ] );
			$this->assertIsArray( $result );
			$this->assertSame( get_admin_url( $blog_id, 'plugins.php' ), $result['url'] );
			$this->assertSame( 'link', $result['action'] );
		}
	}
}
