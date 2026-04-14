<?php

declare(strict_types=1);
/**
 * Test case for PluginBuilderAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\GeneratePluginAbility;
use GratisAiAgent\Abilities\PluginBuilderAbilities;
use GratisAiAgent\Abilities\SandboxActivatePluginAbility;
use GratisAiAgent\Abilities\SandboxTestPluginAbility;
use GratisAiAgent\Abilities\ScanPluginHooksAbility;
use GratisAiAgent\Abilities\ScanThemeHooksAbility;
use GratisAiAgent\Abilities\UpdatePluginSandboxedAbility;
use WP_UnitTestCase;

/**
 * Test PluginBuilderAbilities functionality.
 */
class PluginBuilderAbilitiesTest extends WP_UnitTestCase {

	// ── register ──────────────────────────────────────────────────────────

	/**
	 * register() hooks register_abilities to wp_abilities_api_init.
	 */
	public function test_register_hooks_register_abilities(): void {
		PluginBuilderAbilities::register();

		$this->assertNotFalse(
			has_action( 'wp_abilities_api_init', [ PluginBuilderAbilities::class, 'register_abilities' ] )
		);
	}

	// ── GeneratePluginAbility ─────────────────────────────────────────────

	/**
	 * GeneratePluginAbility returns WP_Error when description is empty.
	 */
	public function test_generate_plugin_returns_wp_error_for_empty_description(): void {
		$ability = new GeneratePluginAbility( 'gratis-ai-agent/generate-plugin' );

		$result = $ability->run( [ 'description' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_empty_description', $result->get_error_code() );
	}

	/**
	 * GeneratePluginAbility returns WP_Error when description is missing.
	 */
	public function test_generate_plugin_returns_wp_error_for_missing_description(): void {
		$ability = new GeneratePluginAbility( 'gratis-ai-agent/generate-plugin' );

		$result = $ability->run( [] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_empty_description', $result->get_error_code() );
	}

	// ── SandboxTestPluginAbility ───────────────────────────────────────────

	/**
	 * SandboxTestPluginAbility returns WP_Error when slug is empty.
	 */
	public function test_sandbox_test_returns_wp_error_for_empty_slug(): void {
		$ability = new SandboxTestPluginAbility( 'gratis-ai-agent/sandbox-test-plugin' );

		$result = $ability->run( [ 'slug' => '', 'plugin_file' => 'my-plugin.php' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * SandboxTestPluginAbility returns WP_Error when plugin_file is missing.
	 */
	public function test_sandbox_test_returns_wp_error_for_missing_plugin_file(): void {
		$ability = new SandboxTestPluginAbility( 'gratis-ai-agent/sandbox-test-plugin' );

		$result = $ability->run( [ 'slug' => 'my-plugin', 'plugin_file' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_plugin_file', $result->get_error_code() );
	}

	// ── SandboxActivatePluginAbility ───────────────────────────────────────

	/**
	 * SandboxActivatePluginAbility returns WP_Error when plugin_file is empty.
	 */
	public function test_sandbox_activate_returns_wp_error_for_empty_plugin_file(): void {
		$ability = new SandboxActivatePluginAbility( 'gratis-ai-agent/sandbox-activate-plugin' );

		$result = $ability->run( [ 'plugin_file' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_plugin_file', $result->get_error_code() );
	}

	/**
	 * SandboxActivatePluginAbility returns WP_Error when plugin_file is missing.
	 */
	public function test_sandbox_activate_returns_wp_error_for_missing_plugin_file(): void {
		$ability = new SandboxActivatePluginAbility( 'gratis-ai-agent/sandbox-activate-plugin' );

		$result = $ability->run( [] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_plugin_file', $result->get_error_code() );
	}

	// ── UpdatePluginSandboxedAbility ───────────────────────────────────────

	/**
	 * UpdatePluginSandboxedAbility returns WP_Error when slug is empty.
	 */
	public function test_update_plugin_sandboxed_returns_wp_error_for_empty_slug(): void {
		$ability = new UpdatePluginSandboxedAbility( 'gratis-ai-agent/update-plugin-sandboxed' );

		$result = $ability->run( [ 'slug' => '', 'files' => [ 'my-plugin.php' => '<?php' ], 'plugin_file' => 'my-plugin.php' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * UpdatePluginSandboxedAbility returns WP_Error when files is empty.
	 */
	public function test_update_plugin_sandboxed_returns_wp_error_for_empty_files(): void {
		$ability = new UpdatePluginSandboxedAbility( 'gratis-ai-agent/update-plugin-sandboxed' );

		$result = $ability->run( [ 'slug' => 'my-plugin', 'files' => [], 'plugin_file' => 'my-plugin.php' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_no_files', $result->get_error_code() );
	}

	/**
	 * UpdatePluginSandboxedAbility returns WP_Error when plugin_file is empty.
	 */
	public function test_update_plugin_sandboxed_returns_wp_error_for_empty_plugin_file(): void {
		$ability = new UpdatePluginSandboxedAbility( 'gratis-ai-agent/update-plugin-sandboxed' );

		$result = $ability->run( [ 'slug' => 'my-plugin', 'files' => [ 'my-plugin.php' => '<?php' ], 'plugin_file' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_plugin_file', $result->get_error_code() );
	}

	// ── ScanPluginHooksAbility ─────────────────────────────────────────────

	/**
	 * ScanPluginHooksAbility returns WP_Error when slug is empty.
	 */
	public function test_scan_plugin_hooks_returns_wp_error_for_empty_slug(): void {
		$ability = new ScanPluginHooksAbility( 'gratis-ai-agent/scan-plugin-hooks' );

		$result = $ability->run( [ 'slug' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * ScanPluginHooksAbility returns WP_Error when plugin does not exist.
	 */
	public function test_scan_plugin_hooks_returns_wp_error_for_nonexistent_plugin(): void {
		$ability = new ScanPluginHooksAbility( 'gratis-ai-agent/scan-plugin-hooks' );

		$result = $ability->run( [ 'slug' => 'nonexistent-plugin-xyz-99999' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	// ── ScanThemeHooksAbility ──────────────────────────────────────────────

	/**
	 * ScanThemeHooksAbility returns WP_Error when slug is empty.
	 */
	public function test_scan_theme_hooks_returns_wp_error_for_empty_slug(): void {
		$ability = new ScanThemeHooksAbility( 'gratis-ai-agent/scan-theme-hooks' );

		$result = $ability->run( [ 'slug' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * ScanThemeHooksAbility returns WP_Error when theme does not exist.
	 */
	public function test_scan_theme_hooks_returns_wp_error_for_nonexistent_theme(): void {
		$ability = new ScanThemeHooksAbility( 'gratis-ai-agent/scan-theme-hooks' );

		$result = $ability->run( [ 'slug' => 'nonexistent-theme-xyz-99999' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_theme_not_found', $result->get_error_code() );
	}
}
