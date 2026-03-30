<?php

declare(strict_types=1);
/**
 * Test case for ModelBenchmarkPage class.
 *
 * @package GratisAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Admin;

use GratisAiAgent\Admin\ModelBenchmarkPage;
use WP_UnitTestCase;

/**
 * Test ModelBenchmarkPage functionality.
 */
class ModelBenchmarkPageTest extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected int $admin_id;

	/**
	 * Set up test user before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	// ─── Constants ────────────────────────────────────────────────────────────

	/**
	 * Test SLUG constant value.
	 */
	public function test_slug_constant(): void {
		$this->assertSame( 'gratis-ai-agent-benchmark', ModelBenchmarkPage::SLUG );
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	/**
	 * Test register() adds a management page under tools.php.
	 */
	public function test_register_adds_management_page(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		ModelBenchmarkPage::register();

		global $submenu;
		/** @var array<string, array<int, array<int, string>>> $submenu */
		$this->assertArrayHasKey( 'tools.php', $submenu );

		$slugs = array_column( $submenu['tools.php'], 2 );
		$this->assertContains( ModelBenchmarkPage::SLUG, $slugs );
	}

	/**
	 * Test register() hooks admin_enqueue_scripts when page is registered.
	 */
	public function test_register_hooks_enqueue_scripts(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		ModelBenchmarkPage::register();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', [ 'GratisAiAgent\Admin\ModelBenchmarkPage', 'enqueue_assets' ] )
		);
	}

	// ─── enqueue_assets ───────────────────────────────────────────────────────

	/**
	 * Test enqueue_assets() skips non-matching hook suffix.
	 */
	public function test_enqueue_assets_skips_wrong_hook(): void {
		wp_set_current_user( $this->admin_id );

		ModelBenchmarkPage::enqueue_assets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-benchmark-page', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips when asset file does not exist.
	 */
	public function test_enqueue_assets_skips_missing_asset_file(): void {
		wp_set_current_user( $this->admin_id );

		// Override build dir to a path that does not exist so file_exists() returns false.
		add_filter( 'gratis_ai_agent_build_dir', static fn() => '/nonexistent/path' );

		ModelBenchmarkPage::enqueue_assets( 'tools_page_' . ModelBenchmarkPage::SLUG );

		remove_all_filters( 'gratis_ai_agent_build_dir' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-benchmark-page', 'enqueued' ) );
	}

	// ─── render ───────────────────────────────────────────────────────────────

	/**
	 * Test render() outputs compatibility notice when wp_ai_client_prompt is unavailable.
	 */
	public function test_render_outputs_notice_when_ai_client_unavailable(): void {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available; cannot test unavailable path.' );
		}

		ob_start();
		ModelBenchmarkPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'AI Client SDK', $output );
	}

	/**
	 * Test render() outputs the benchmark wrap div when AI client is available.
	 */
	public function test_render_outputs_wrap_when_ai_client_available(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt not available in this test run.' );
		}

		ob_start();
		ModelBenchmarkPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gratis-ai-agent-benchmark-wrap', $output );
		$this->assertStringContainsString( 'gratis-ai-agent-benchmark-root', $output );
	}

	/**
	 * Test render() outputs the page heading.
	 */
	public function test_render_outputs_heading(): void {
		ob_start();
		ModelBenchmarkPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Model Benchmark', $output );
	}
}
