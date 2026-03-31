<?php

declare(strict_types=1);
/**
 * Test case for FreshInstallDetector class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\FreshInstallDetector;
use WP_UnitTestCase;

/**
 * Test FreshInstallDetector functionality.
 */
class FreshInstallDetectorTest extends WP_UnitTestCase {

	/**
	 * Clear the detection cache before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( FreshInstallDetector::TRANSIENT_KEY );
	}

	/**
	 * Clear the detection cache after each test.
	 */
	public function tear_down(): void {
		delete_transient( FreshInstallDetector::TRANSIENT_KEY );
		parent::tear_down();
	}

	// ── constants ─────────────────────────────────────────────────────────────

	/**
	 * TRANSIENT_KEY constant is defined.
	 */
	public function test_transient_key_constant_is_defined(): void {
		$this->assertSame( 'gratis_ai_agent_fresh_install', FreshInstallDetector::TRANSIENT_KEY );
	}

	/**
	 * CACHE_TTL constant is a positive integer.
	 */
	public function test_cache_ttl_is_positive(): void {
		$this->assertGreaterThan( 0, FreshInstallDetector::CACHE_TTL );
	}

	/**
	 * DEFAULT_POST_TITLES includes the expected WordPress defaults.
	 */
	public function test_default_post_titles_includes_hello_world(): void {
		$this->assertContains( 'Hello world!', FreshInstallDetector::DEFAULT_POST_TITLES );
		$this->assertContains( 'Sample Page', FreshInstallDetector::DEFAULT_POST_TITLES );
		$this->assertContains( 'Privacy Policy', FreshInstallDetector::DEFAULT_POST_TITLES );
	}

	// ── register ─────────────────────────────────────────────────────────────

	/**
	 * register() hooks clearCache to the expected WordPress actions.
	 */
	public function test_register_adds_expected_hooks(): void {
		FreshInstallDetector::register();

		$this->assertNotFalse( has_action( 'transition_post_status', [ FreshInstallDetector::class, 'clearCache' ] ) );
		$this->assertNotFalse( has_action( 'delete_post', [ FreshInstallDetector::class, 'clearCache' ] ) );
		$this->assertNotFalse( has_action( 'switch_theme', [ FreshInstallDetector::class, 'clearCache' ] ) );
	}

	// ── clearCache ────────────────────────────────────────────────────────────

	/**
	 * clearCache() removes the transient.
	 */
	public function test_clear_cache_removes_transient(): void {
		set_transient( FreshInstallDetector::TRANSIENT_KEY, '1', 300 );

		FreshInstallDetector::clearCache();

		$this->assertFalse( get_transient( FreshInstallDetector::TRANSIENT_KEY ) );
	}

	/**
	 * clearCache() accepts variadic arguments (hook compatibility).
	 */
	public function test_clear_cache_accepts_variadic_args(): void {
		set_transient( FreshInstallDetector::TRANSIENT_KEY, '1', 300 );

		// Should not throw even with extra arguments.
		FreshInstallDetector::clearCache( 'publish', 'draft', new \WP_Post( new \stdClass() ) );

		$this->assertFalse( get_transient( FreshInstallDetector::TRANSIENT_KEY ) );
	}

	// ── isFreshInstall ────────────────────────────────────────────────────────

	/**
	 * isFreshInstall() returns a boolean.
	 */
	public function test_is_fresh_install_returns_bool(): void {
		$result = FreshInstallDetector::isFreshInstall();
		$this->assertIsBool( $result );
	}

	/**
	 * isFreshInstall() caches the result in a transient.
	 */
	public function test_is_fresh_install_caches_result(): void {
		// First call — no cache.
		FreshInstallDetector::isFreshInstall();

		$cached = get_transient( FreshInstallDetector::TRANSIENT_KEY );
		$this->assertNotFalse( $cached );
		$this->assertContains( $cached, [ '0', '1' ] );
	}

	/**
	 * isFreshInstall() returns cached value without re-evaluating.
	 */
	public function test_is_fresh_install_uses_cached_value(): void {
		// Seed the cache with a known value.
		set_transient( FreshInstallDetector::TRANSIENT_KEY, '1', 300 );

		$result = FreshInstallDetector::isFreshInstall();
		$this->assertTrue( $result );
	}

	/**
	 * isFreshInstall() returns false when cached value is '0'.
	 */
	public function test_is_fresh_install_returns_false_from_cache(): void {
		set_transient( FreshInstallDetector::TRANSIENT_KEY, '0', 300 );

		$result = FreshInstallDetector::isFreshInstall();
		$this->assertFalse( $result );
	}

	/**
	 * isFreshInstall() returns false when a real post exists.
	 */
	public function test_is_fresh_install_returns_false_when_real_post_exists(): void {
		self::factory()->post->create(
			[
				'post_title'  => 'My Real Blog Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$result = FreshInstallDetector::isFreshInstall();
		$this->assertFalse( $result );
	}

	/**
	 * isFreshInstall() returns false when a real page exists.
	 */
	public function test_is_fresh_install_returns_false_when_real_page_exists(): void {
		self::factory()->post->create(
			[
				'post_title'  => 'My Custom Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		$result = FreshInstallDetector::isFreshInstall();
		$this->assertFalse( $result );
	}

	/**
	 * isFreshInstall() does not count default WordPress posts as real content.
	 */
	public function test_is_fresh_install_ignores_default_post_titles(): void {
		// Create posts with default titles — these should not count as real content.
		self::factory()->post->create(
			[
				'post_title'  => 'Hello world!',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
		self::factory()->post->create(
			[
				'post_title'  => 'Sample Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		// Default-titled posts/pages should not count as real content.
		$status = FreshInstallDetector::getStatus();
		$this->assertFalse( $status['has_real_posts'] );
		$this->assertFalse( $status['has_real_pages'] );
	}

	// ── getStatus ─────────────────────────────────────────────────────────────

	/**
	 * getStatus() returns the expected array shape.
	 */
	public function test_get_status_returns_expected_shape(): void {
		$status = FreshInstallDetector::getStatus();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'is_fresh_install', $status );
		$this->assertArrayHasKey( 'has_real_posts', $status );
		$this->assertArrayHasKey( 'has_real_pages', $status );
		$this->assertArrayHasKey( 'is_default_theme', $status );
		$this->assertArrayHasKey( 'active_theme', $status );
	}

	/**
	 * getStatus() returns boolean values for detection flags.
	 */
	public function test_get_status_returns_booleans_for_flags(): void {
		$status = FreshInstallDetector::getStatus();

		$this->assertIsBool( $status['is_fresh_install'] );
		$this->assertIsBool( $status['has_real_posts'] );
		$this->assertIsBool( $status['has_real_pages'] );
		$this->assertIsBool( $status['is_default_theme'] );
	}

	/**
	 * getStatus() returns a non-empty string for active_theme.
	 */
	public function test_get_status_returns_string_for_active_theme(): void {
		$status = FreshInstallDetector::getStatus();

		$this->assertIsString( $status['active_theme'] );
		$this->assertNotEmpty( $status['active_theme'] );
	}

	/**
	 * getStatus() reports has_real_posts = true when a real post exists.
	 */
	public function test_get_status_reports_real_posts_when_present(): void {
		self::factory()->post->create(
			[
				'post_title'  => 'A Real Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$status = FreshInstallDetector::getStatus();

		$this->assertTrue( $status['has_real_posts'] );
		$this->assertFalse( $status['is_fresh_install'] );
	}

	/**
	 * getStatus() is_fresh_install is consistent with isFreshInstall().
	 */
	public function test_get_status_is_fresh_install_matches_is_fresh_install(): void {
		// Clear cache so both calls evaluate fresh.
		delete_transient( FreshInstallDetector::TRANSIENT_KEY );

		$status   = FreshInstallDetector::getStatus();
		$expected = $status['is_fresh_install'];

		// Clear cache again so isFreshInstall() re-evaluates.
		delete_transient( FreshInstallDetector::TRANSIENT_KEY );

		$this->assertSame( $expected, FreshInstallDetector::isFreshInstall() );
	}
}
