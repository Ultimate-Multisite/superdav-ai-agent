<?php

declare(strict_types=1);
/**
 * Unit tests for PluginSandbox — three-layer plugin safety system.
 *
 * Coverage:
 *   - layer1_syntax_check(): non-existent dir, empty dir, valid PHP, broken PHP
 *   - layer2_isolated_include(): missing main file guard
 *   - layer3_activate(): previous-fatal transient guard
 *   - run_all(): array shape, short-circuit on Layer 1 failure, Layer 2 error path
 *   - auto_deactivate_fatal_plugins(): smoke test (no active fatal transients)
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\PluginBuilder;

use GratisAiAgent\PluginBuilder\PluginSandbox;
use WP_UnitTestCase;

/**
 * Tests for PluginSandbox.
 *
 * @group plugin-builder
 * @group plugin-sandbox
 */
class PluginSandboxTest extends WP_UnitTestCase {

	/**
	 * Temp directories created during a test run — removed in tearDown.
	 *
	 * @var string[]
	 */
	private array $temp_dirs = [];

	// ─── Helpers ────────────────────────────────────────────────────

	/**
	 * Create a fresh, isolated temp directory and track it for cleanup.
	 */
	private function make_temp_dir(): string {
		$dir = sys_get_temp_dir() . '/gratis_sandbox_test_' . uniqid( '', true );
		mkdir( $dir, 0755, true );
		$this->temp_dirs[] = $dir;
		return $dir;
	}

	/**
	 * Recursively delete a directory tree.
	 */
	private function rmdir_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getRealPath() );
			} else {
				unlink( $item->getRealPath() );
			}
		}
		rmdir( $dir );
	}

	// ─── Lifecycle ──────────────────────────────────────────────────

	/**
	 * Clean up all temp directories after each test.
	 */
	public function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rmdir_recursive( $dir );
		}
		$this->temp_dirs = [];
		parent::tearDown();
	}

	// ─── layer1_syntax_check ────────────────────────────────────────

	/**
	 * A path that does not exist should return WP_Error.
	 */
	public function test_layer1_returns_wp_error_for_nonexistent_dir(): void {
		$result = PluginSandbox::layer1_syntax_check( '/nonexistent/gratis-test-path-' . uniqid() );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_sandbox_dir_not_found', $result->get_error_code() );
	}

	/**
	 * An existing directory with no PHP files should return true (nothing to check).
	 */
	public function test_layer1_returns_true_for_empty_dir(): void {
		$dir    = $this->make_temp_dir();
		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertTrue( $result );
	}

	/**
	 * A directory containing only syntactically valid PHP should return true.
	 */
	public function test_layer1_returns_true_for_valid_php(): void {
		$dir = $this->make_temp_dir();
		file_put_contents( $dir . '/plugin.php', "<?php\ndeclare(strict_types=1);\necho 'hello';\n" );

		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertTrue( $result );
	}

	/**
	 * A directory containing a PHP file with a syntax error should return WP_Error.
	 */
	public function test_layer1_returns_wp_error_for_syntax_error(): void {
		$dir = $this->make_temp_dir();
		// Definite syntax error: assignment with no right-hand side.
		file_put_contents( $dir . '/broken.php', "<?php\n\$x = ;\n" );

		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_syntax_error', $result->get_error_code() );
		$this->assertStringContainsString( 'broken.php', $result->get_error_message() );
	}

	/**
	 * Only one broken file in a multi-file directory is sufficient to trigger the error.
	 */
	public function test_layer1_fails_when_one_of_multiple_files_is_broken(): void {
		$dir = $this->make_temp_dir();
		file_put_contents( $dir . '/valid.php', "<?php\nfunction foo() { return 1; }\n" );
		file_put_contents( $dir . '/broken.php', "<?php\nclass {\n" );

		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_syntax_error', $result->get_error_code() );
	}

	/**
	 * Non-PHP files in the directory are ignored by the syntax check.
	 */
	public function test_layer1_ignores_non_php_files(): void {
		$dir = $this->make_temp_dir();
		// Plain text that would be invalid PHP if parsed — should be ignored.
		file_put_contents( $dir . '/readme.txt', "This is not PHP.\n\$x = ;\n" );
		file_put_contents( $dir . '/style.css', "body { color: red; }" );

		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertTrue( $result );
	}

	/**
	 * Nested PHP files in subdirectories are also checked.
	 */
	public function test_layer1_checks_subdirectories_recursively(): void {
		$dir    = $this->make_temp_dir();
		$subdir = $dir . '/admin';
		mkdir( $subdir, 0755, true );
		file_put_contents( $dir . '/plugin.php', "<?php\necho 'main';\n" );
		file_put_contents( $subdir . '/broken.php', "<?php\n}\n" );

		$result = PluginSandbox::layer1_syntax_check( $dir );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_syntax_error', $result->get_error_code() );
	}

	// ─── layer2_isolated_include ────────────────────────────────────

	/**
	 * When the main plugin file does not exist, layer2 returns WP_Error.
	 */
	public function test_layer2_returns_wp_error_for_missing_main_file(): void {
		$dir    = $this->make_temp_dir();
		$result = PluginSandbox::layer2_isolated_include( $dir, 'nonexistent-plugin.php' );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_sandbox_file_not_found', $result->get_error_code() );
		$this->assertStringContainsString( 'nonexistent-plugin.php', $result->get_error_message() );
	}

	/**
	 * layer2 strips a leading slash from the plugin_file parameter.
	 */
	public function test_layer2_normalises_leading_slash_in_plugin_file(): void {
		$dir    = $this->make_temp_dir();
		// File does NOT exist — we just need the normalisation branch to reach the
		// "file_not_found" guard, confirming the path was resolved correctly.
		$result = PluginSandbox::layer2_isolated_include( $dir, '/missing.php' );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_sandbox_file_not_found', $result->get_error_code() );
	}

	// ─── layer3_activate ────────────────────────────────────────────

	/**
	 * When the fatal transient is set from a prior activation attempt, layer3
	 * returns WP_Error and clears the transient.
	 */
	public function test_layer3_returns_wp_error_when_previous_fatal_transient_set(): void {
		$plugin_file   = 'gratis-test-plugin/gratis-test-plugin.php';
		$transient_key = PluginSandbox::FATAL_TRANSIENT_PREFIX . md5( $plugin_file );

		set_transient( $transient_key, 1, 60 );

		$result = PluginSandbox::layer3_activate( $plugin_file );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_previous_fatal', $result->get_error_code() );
		$this->assertStringContainsString( $plugin_file, $result->get_error_message() );
		// Transient must be cleared after being consumed.
		$this->assertFalse( get_transient( $transient_key ), 'Fatal transient should be deleted after being read.' );
	}

	// ─── run_all ────────────────────────────────────────────────────

	/**
	 * run_all() returns an array (not WP_Error) and its shape is correct.
	 */
	public function test_run_all_returns_correct_array_shape(): void {
		$result = PluginSandbox::run_all( '/nonexistent/path', 'plugin.php' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'layer1_passed', $result );
		$this->assertArrayHasKey( 'layer2_passed', $result );
		$this->assertArrayHasKey( 'layer3_passed', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'passed', $result );
	}

	/**
	 * run_all() short-circuits when Layer 1 fails: layer2_passed stays false.
	 */
	public function test_run_all_short_circuits_on_layer1_failure(): void {
		$result = PluginSandbox::run_all( '/nonexistent/path-' . uniqid(), 'plugin.php' );

		$this->assertFalse( $result['layer1_passed'] );
		$this->assertFalse( $result['layer2_passed'] );
		$this->assertFalse( $result['passed'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Layer 1', $result['errors'][0] );
	}

	/**
	 * When Layer 1 passes but the main file is missing, run_all() fails at Layer 2.
	 */
	public function test_run_all_fails_at_layer2_when_main_file_missing(): void {
		$dir = $this->make_temp_dir();
		// Add a valid PHP helper file so Layer 1 passes.
		file_put_contents( $dir . '/helper.php', "<?php\nfunction gratis_helper() { return true; }\n" );

		$result = PluginSandbox::run_all( $dir, 'missing-main.php' );

		$this->assertTrue( $result['layer1_passed'] );
		$this->assertFalse( $result['layer2_passed'] );
		$this->assertFalse( $result['passed'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Layer 2', $result['errors'][0] );
	}

	/**
	 * When Layer 1 fails with a syntax error, the error message is surfaced in run_all.
	 */
	public function test_run_all_surfaces_layer1_error_message(): void {
		$dir = $this->make_temp_dir();
		file_put_contents( $dir . '/bad.php', "<?php\n\$y = ;\n" );

		$result = PluginSandbox::run_all( $dir, 'bad.php' );

		$this->assertStringContainsString( 'Layer 1', $result['errors'][0] );
	}

	// ─── auto_deactivate_fatal_plugins ──────────────────────────────

	/**
	 * With no fatal transients in the database the method should complete without error.
	 */
	public function test_auto_deactivate_runs_without_error_when_no_fatal_transients(): void {
		// Smoke test: no fatal transients set, no active plugins with matching transients.
		PluginSandbox::auto_deactivate_fatal_plugins();

		// If we reach here, no exception or fatal was thrown.
		$this->assertTrue( true );
	}
}
