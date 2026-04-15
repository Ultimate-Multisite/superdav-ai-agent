<?php

declare(strict_types=1);
/**
 * Test case for HookScanner.
 *
 * @package GratisAiAgent
 * @subpackage Tests\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\PluginBuilder;

use GratisAiAgent\PluginBuilder\HookScanner;
use WP_UnitTestCase;

/**
 * Tests for GratisAiAgent\PluginBuilder\HookScanner.
 *
 * Uses temporary directories and PHP fixture files to exercise scanner logic
 * without requiring real installed plugins or themes.
 */
class HookScannerTest extends WP_UnitTestCase {

	/**
	 * Temporary directory used as a fake plugin/theme root during tests.
	 *
	 * @var string
	 */
	private string $tmp_dir = '';

	/**
	 * Set up a clean temporary directory before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->tmp_dir = sys_get_temp_dir() . '/gratis_hook_scanner_test_' . uniqid( '' );
		mkdir( $this->tmp_dir, 0777, true );
	}

	/**
	 * Remove the temporary directory after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$this->rmdir_recursive( $this->tmp_dir );
	}

	// ─── scan_file ────────────────────────────────────────────────────────

	/**
	 * scan_file() returns an empty array for a file with no hook calls.
	 */
	public function test_scan_file_returns_empty_for_file_with_no_hooks(): void {
		$file = $this->tmp_dir . '/no-hooks.php';
		file_put_contents( $file, "<?php\n\$x = 1 + 1;\n" );

		$result = HookScanner::scan_file( $file );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * scan_file() returns hook records for do_action() calls.
	 */
	public function test_scan_file_detects_do_action(): void {
		$file = $this->tmp_dir . '/actions.php';
		file_put_contents(
			$file,
			"<?php\ndo_action( 'my_custom_action', \$data );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertSame( 'my_custom_action', $result[0]['name'] );
		$this->assertSame( 'action', $result[0]['type'] );
		$this->assertSame( 2, $result[0]['line'] );
	}

	/**
	 * scan_file() returns hook records for apply_filters() calls.
	 */
	public function test_scan_file_detects_apply_filters(): void {
		$file = $this->tmp_dir . '/filters.php';
		file_put_contents(
			$file,
			"<?php\n\$val = apply_filters( 'my_filter', \$val );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertSame( 'my_filter', $result[0]['name'] );
		$this->assertSame( 'filter', $result[0]['type'] );
	}

	/**
	 * scan_file() detects do_action_ref_array() as type 'action'.
	 */
	public function test_scan_file_detects_do_action_ref_array(): void {
		$file = $this->tmp_dir . '/ref-array.php';
		file_put_contents(
			$file,
			"<?php\ndo_action_ref_array( 'ref_action', [ &\$obj ] );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertSame( 'action', $result[0]['type'] );
		$this->assertSame( 'ref_action', $result[0]['name'] );
	}

	/**
	 * scan_file() detects apply_filters_ref_array() as type 'filter'.
	 */
	public function test_scan_file_detects_apply_filters_ref_array(): void {
		$file = $this->tmp_dir . '/ref-filter.php';
		file_put_contents(
			$file,
			"<?php\napply_filters_ref_array( 'ref_filter', [ &\$obj ] );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertSame( 'filter', $result[0]['type'] );
		$this->assertSame( 'ref_filter', $result[0]['name'] );
	}

	/**
	 * scan_file() does NOT capture add_action() or add_filter() calls.
	 */
	public function test_scan_file_skips_add_action_and_add_filter(): void {
		$file = $this->tmp_dir . '/registrations.php';
		file_put_contents(
			$file,
			"<?php\nadd_action( 'init', 'my_callback' );\nadd_filter( 'the_content', 'my_filter_cb' );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertEmpty( $result );
	}

	/**
	 * scan_file() skips dynamic/variable hook names.
	 */
	public function test_scan_file_skips_dynamic_hook_names(): void {
		$file = $this->tmp_dir . '/dynamic.php';
		file_put_contents(
			$file,
			"<?php\ndo_action( \$hook_name );\napply_filters( \$filter, \$val );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertEmpty( $result );
	}

	/**
	 * scan_file() includes a non-empty context string in each hook record.
	 */
	public function test_scan_file_includes_context(): void {
		$file = $this->tmp_dir . '/ctx.php';
		file_put_contents(
			$file,
			"<?php\n// line before\ndo_action( 'ctx_action' );\n// line after\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'context', $result[0] );
		$this->assertNotEmpty( $result[0]['context'] );
		// Context should contain the hook line itself.
		$this->assertStringContainsString( 'ctx_action', $result[0]['context'] );
	}

	/**
	 * scan_file() captures param_count for additional arguments.
	 */
	public function test_scan_file_captures_param_count(): void {
		$file = $this->tmp_dir . '/params.php';
		file_put_contents(
			$file,
			"<?php\napply_filters( 'my_filter', \$val, \$extra1, \$extra2 );\n"
		);

		$result = HookScanner::scan_file( $file );

		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'param_count', $result[0] );
		// Expects 3 commas after the hook name: one before $val, one before $extra1, one before $extra2.
		$this->assertSame( 3, $result[0]['param_count'] );
	}

	/**
	 * scan_file() returns an empty array for a non-existent file.
	 */
	public function test_scan_file_returns_empty_for_nonexistent_file(): void {
		$result = HookScanner::scan_file( $this->tmp_dir . '/does-not-exist.php' );
		$this->assertEmpty( $result );
	}

	// ─── scan_plugin ─────────────────────────────────────────────────────

	/**
	 * scan_plugin() returns WP_Error when the plugin directory does not exist.
	 */
	public function test_scan_plugin_returns_wp_error_for_missing_plugin(): void {
		$result = HookScanner::scan_plugin( 'definitely-not-a-real-plugin-slug-xyz' );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * scan_plugin() scans a fake plugin directory and returns full result envelope.
	 */
	public function test_scan_plugin_returns_full_result_envelope(): void {
		$slug       = 'gratis-test-hook-scanner-plugin-' . uniqid( '' );
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';

		if ( ! wp_mkdir_p( $plugin_dir ) ) {
			$this->markTestSkipped( 'Cannot create test plugin directory in WP_PLUGIN_DIR.' );
		}

		file_put_contents(
			$plugin_dir . 'plugin.php',
			"<?php\ndo_action( 'test_action_alpha' );\napply_filters( 'test_filter_beta', \$val );\n"
		);

		$result = HookScanner::scan_plugin( $slug, true );

		// Clean up before assertions so the test directory is removed even on failure.
		$this->rmdir_recursive( $plugin_dir );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'slug', $result );
		$this->assertArrayHasKey( 'type', $result );
		$this->assertArrayHasKey( 'hooks', $result );
		$this->assertArrayHasKey( 'total_hooks', $result );
		$this->assertArrayHasKey( 'total_actions', $result );
		$this->assertArrayHasKey( 'total_filters', $result );

		$this->assertSame( $slug, $result['slug'] );
		$this->assertSame( 'plugin', $result['type'] );
		$this->assertSame( 2, $result['total_hooks'] );
		$this->assertSame( 1, $result['total_actions'] );
		$this->assertSame( 1, $result['total_filters'] );
	}

	/**
	 * scan_plugin() skips vendor/ subdirectory.
	 */
	public function test_scan_plugin_skips_vendor_directory(): void {
		$slug       = 'gratis-test-vendor-skip-' . uniqid( '' );
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';

		if ( ! wp_mkdir_p( $plugin_dir . 'vendor/' ) ) {
			$this->markTestSkipped( 'Cannot create test plugin directory in WP_PLUGIN_DIR.' );
		}

		// Only put a hook in the vendor directory — it should be skipped.
		file_put_contents(
			$plugin_dir . 'vendor/lib.php',
			"<?php\ndo_action( 'vendor_action' );\n"
		);
		// Put a non-hook file in the root.
		file_put_contents( $plugin_dir . 'plugin.php', "<?php\n// empty\n" );

		$result = HookScanner::scan_plugin( $slug, true );
		$this->rmdir_recursive( $plugin_dir );

		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['total_hooks'] );
	}

	// ─── scan_theme ──────────────────────────────────────────────────────

	/**
	 * scan_theme() returns WP_Error when the theme directory does not exist.
	 */
	public function test_scan_theme_returns_wp_error_for_missing_theme(): void {
		$result = HookScanner::scan_theme( 'definitely-not-a-real-theme-slug-xyz' );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_theme_not_found', $result->get_error_code() );
	}

	/**
	 * scan_theme() scans a fake theme directory and returns full result envelope.
	 */
	public function test_scan_theme_returns_full_result_envelope(): void {
		$slug      = 'gratis-test-hook-scanner-theme-' . uniqid( '' );
		$theme_dir = trailingslashit( WP_CONTENT_DIR ) . 'themes/' . $slug . '/';

		if ( ! wp_mkdir_p( $theme_dir ) ) {
			$this->markTestSkipped( 'Cannot create test theme directory in WP_CONTENT_DIR/themes/.' );
		}

		file_put_contents(
			$theme_dir . 'functions.php',
			"<?php\ndo_action( 'theme_header_before' );\n"
		);

		$result = HookScanner::scan_theme( $slug, true );
		$this->rmdir_recursive( $theme_dir );

		$this->assertIsArray( $result );
		$this->assertSame( $slug, $result['slug'] );
		$this->assertSame( 'theme', $result['type'] );
		$this->assertSame( 1, $result['total_hooks'] );
		$this->assertSame( 1, $result['total_actions'] );
		$this->assertSame( 0, $result['total_filters'] );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────

	/**
	 * Recursively remove a directory and its contents.
	 *
	 * @param string $dir Directory path to remove.
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
}
