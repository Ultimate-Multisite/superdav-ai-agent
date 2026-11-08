<?php

declare(strict_types=1);
/**
 * Test case for PluginInstaller class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\PluginBuilder;

use SdAiAgent\PluginBuilder\PluginInstaller;
use WP_UnitTestCase;

/**
 * Test PluginInstaller methods.
 */
class PluginInstallerTest extends WP_UnitTestCase {

	/**
	 * Test slug used across test cases.
	 *
	 * @var string
	 */
	private string $test_slug = 'sd-test-plugin-phpunit';

	/**
	 * Full path to the test plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Set up: ensure the test plugin directory does not exist before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		add_filter( 'file_mod_allowed', '__return_true', PHP_INT_MAX );
		add_filter( 'sd_ai_agent_allow_shared_code_modifications', '__return_true', PHP_INT_MAX );
		$this->plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->test_slug . '/';
		$this->cleanup_plugin_dir();
	}

	/**
	 * Tear down: remove test plugin directory and any DB records.
	 */
	public function tearDown(): void {
		$this->cleanup_plugin_dir();
		$this->cleanup_db_records();
		remove_filter( 'file_mod_allowed', '__return_true', PHP_INT_MAX );
		remove_filter( 'sd_ai_agent_allow_shared_code_modifications', '__return_true', PHP_INT_MAX );
		parent::tearDown();
	}

	// ─── validate_plugin_path ────────────────────────────────────────────────

	/**
	 * Valid relative paths should be returned normalised.
	 */
	public function test_validate_plugin_path_valid_returns_normalised(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, 'includes/class-main.php' );
		$this->assertIsString( $result );
		$this->assertSame( 'includes/class-main.php', $result );
	}

	/**
	 * Leading slashes should be stripped.
	 */
	public function test_validate_plugin_path_strips_leading_slash(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, '/includes/main.php' );
		$this->assertIsString( $result );
		$this->assertSame( 'includes/main.php', $result );
	}

	/**
	 * Redundant slug prefix should be stripped.
	 */
	public function test_validate_plugin_path_strips_slug_prefix(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, $this->test_slug . '/main.php' );
		$this->assertIsString( $result );
		$this->assertSame( 'main.php', $result );
	}

	/**
	 * Empty path should return WP_Error.
	 */
	public function test_validate_plugin_path_rejects_empty(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, '' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_empty_path', $result->get_error_code() );
	}

	/**
	 * Null bytes in path should return WP_Error.
	 */
	public function test_validate_plugin_path_rejects_null_bytes(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, "file\0.php" );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_path', $result->get_error_code() );
	}

	/**
	 * Directory traversal sequences should return WP_Error.
	 */
	public function test_validate_plugin_path_rejects_traversal(): void {
		$result = PluginInstaller::validate_plugin_path( $this->test_slug, '../other-plugin/evil.php' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	// ─── install_plugin ──────────────────────────────────────────────────────

	/**
	 * install_plugin() should reject invalid slugs.
	 */
	public function test_install_plugin_rejects_invalid_slug(): void {
		$result = PluginInstaller::install_plugin(
			'Invalid Slug!',
			'<?php /* test */',
			'Test',
			[]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * install_plugin() should reject slugs with uppercase letters.
	 */
	public function test_install_plugin_rejects_uppercase_slug(): void {
		$result = PluginInstaller::install_plugin(
			'MyPlugin',
			'<?php /* test */',
			'Test',
			[]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * install_plugin() writes a file and creates a DB record.
	 */
	public function test_install_plugin_writes_file_and_db_record(): void {
		$content = '<?php /* Plugin Name: Test Plugin */';
		$result  = PluginInstaller::install_plugin(
			$this->test_slug,
			$content,
			'A test plugin',
			[ 'step' => 'write main file' ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'plugin_dir', $result );
		$this->assertArrayHasKey( 'plugin_file', $result );
		$this->assertGreaterThan( 0, $result['id'] );

		// File should exist on disk.
		$expected_file = $this->plugin_dir . $this->test_slug . '.php';
		$this->assertFileExists( $expected_file );
		$this->assertStringContainsString( 'Plugin Name: Test Plugin', file_get_contents( $expected_file ) );

		// plugin_file should be slug/slug.php.
		$this->assertSame( $this->test_slug . '/' . $this->test_slug . '.php', $result['plugin_file'] );
	}

	/**
	 * install_plugin() must not replace an untracked installed plugin.
	 */
	public function test_install_plugin_rejects_untracked_existing_slug_without_mutation(): void {
		global $wpdb;
		wp_mkdir_p( $this->plugin_dir );
		$plugin_file = $this->plugin_dir . $this->test_slug . '.php';
		$original    = '<?php /* Plugin Name: Existing Untracked Plugin */';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Synthetic installed-plugin fixture.
		file_put_contents( $plugin_file, $original );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		activate_plugin( $this->test_slug . '/' . $this->test_slug . '.php' );
		$hash_before = hash_file( 'sha256', $plugin_file );

		$result = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* Plugin Name: Replacement */',
			'Replacement attempt',
			[]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_slug_exists', $result->get_error_code() );
		$this->assertSame( $hash_before, hash_file( 'sha256', $plugin_file ) );
		$this->assertTrue( is_plugin_active( $this->test_slug . '/' . $this->test_slug . '.php' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion against the generated plugin table.
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ) ) );
	}

	/**
	 * install_plugin() must not follow an existing main-file symbolic link.
	 */
	public function test_install_plugin_rejects_leaf_symlink_without_mutation(): void {
		global $wpdb;
		wp_mkdir_p( $this->plugin_dir );
		$sentinel = trailingslashit( sys_get_temp_dir() ) . $this->test_slug . '-install-sentinel.php';
		$original = '<?php /* outside install sentinel */';
		file_put_contents( $sentinel, $original );
		$this->assertTrue( symlink( $sentinel, $this->plugin_dir . $this->test_slug . '.php' ) );

		try {
			$result = PluginInstaller::install_plugin(
				$this->test_slug,
				'<?php /* replacement */',
				'Symlink collision test',
				[]
			);

			$this->assertWPError( $result );
			$this->assertSame( 'sd_ai_agent_plugin_slug_exists', $result->get_error_code() );
			$this->assertSame( $original, file_get_contents( $sentinel ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion against the generated plugin table.
			$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ) ) );
		} finally {
			unlink( $sentinel );
		}
	}

	/**
	 * install_plugin() must not replace a tracked generated plugin.
	 */
	public function test_install_plugin_rejects_tracked_existing_slug_without_mutation(): void {
		global $wpdb;
		$original = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* Plugin Name: Existing Tracked Plugin */',
			'Original',
			[]
		);
		$this->assertIsArray( $original );
		$plugin_file = $this->plugin_dir . $this->test_slug . '.php';
		$hash_before = hash_file( 'sha256', $plugin_file );

		$result = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* Plugin Name: Replacement */',
			'Replacement attempt',
			[]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_slug_exists', $result->get_error_code() );
		$this->assertSame( $hash_before, hash_file( 'sha256', $plugin_file ) );
		$this->assertFalse( is_plugin_active( $this->test_slug . '/' . $this->test_slug . '.php' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion against the generated plugin table.
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ) ) );
	}

	/**
	 * install_plugin() should reject PHP with syntax errors.
	 */
	public function test_install_plugin_rejects_invalid_php_syntax(): void {
		$broken_php = '<?php $x = ;'; // Missing value after =
		$result     = PluginInstaller::install_plugin(
			$this->test_slug,
			$broken_php,
			'A broken plugin',
			[ 'step' => 'write main file' ]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_php_syntax_error', $result->get_error_code() );
		$this->assertStringContainsString( 'syntax error', $result->get_error_message() );

		// File should NOT exist on disk.
		$expected_file = $this->plugin_dir . $this->test_slug . '.php';
		$this->assertFileDoesNotExist( $expected_file );
	}

	// ─── install_complex_plugin ──────────────────────────────────────────────

	/**
	 * install_complex_plugin() should reject invalid slugs.
	 */
	public function test_install_complex_plugin_rejects_invalid_slug(): void {
		$result = PluginInstaller::install_complex_plugin(
			'Bad Slug',
			[ 'main.php' => '<?php' ],
			'Test',
			[]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * install_complex_plugin() should reject empty file map.
	 */
	public function test_install_complex_plugin_rejects_empty_files(): void {
		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[],
			'Test',
			[]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_no_files', $result->get_error_code() );
	}

	/**
	 * install_complex_plugin() should reject traversal paths.
	 */
	public function test_install_complex_plugin_rejects_traversal_paths(): void {
		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[ '../evil.php' => '<?php evil();' ],
			'Test',
			[]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * install_complex_plugin() writes multiple files and records in the DB.
	 */
	public function test_install_complex_plugin_writes_multiple_files(): void {
		$files = [
			$this->test_slug . '.php' => '<?php /* Plugin Name: Complex Test */',
			'includes/class-loader.php' => '<?php class Loader {}',
		];

		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			$files,
			'A complex test plugin',
			[ 'step' => 'multi-file' ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertGreaterThan( 0, $result['id'] );

		// Both files should exist.
		$this->assertFileExists( $this->plugin_dir . $this->test_slug . '.php' );
		$this->assertFileExists( $this->plugin_dir . 'includes/class-loader.php' );
	}

	/**
	 * install_complex_plugin() should reject PHP with syntax errors.
	 */
	public function test_install_complex_plugin_rejects_invalid_php_syntax(): void {
		global $wpdb;
		$files = [
			$this->test_slug . '.php' => '<?php /* Plugin Name: Complex Test */',
			'includes/broken.php' => '<?php $x = ;', // Missing value after =
		];

		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			$files,
			'A broken complex plugin',
			[ 'step' => 'multi-file' ]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_php_syntax_error', $result->get_error_code() );
		$this->assertStringContainsString( 'syntax error', $result->get_error_message() );

		$this->assertDirectoryDoesNotExist( $this->plugin_dir );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion against the generated plugin table.
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ) ) );
	}

	/**
	 * A later install write failure removes only the newly claimed plugin directory.
	 */
	public function test_install_complex_plugin_rolls_back_after_later_write_failure(): void {
		global $wpdb;
		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[
				$this->test_slug . '.php' => '<?php /* Plugin Name: Atomic Install */',
				'blocked'                  => 'This file prevents a child directory from being created.',
				'blocked/later.php'        => '<?php /* valid but unwritable destination */',
			],
			'Atomic install test',
			[]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_mkdir_failed', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( $this->plugin_dir );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion against the generated plugin table.
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ) ) );
	}

	/**
	 * A failed metadata insert removes every file from the fresh installation.
	 */
	public function test_install_complex_plugin_rolls_back_after_db_insert_failure(): void {
		global $wpdb;
		$original_wpdb = $wpdb;
		$wpdb          = new class( $original_wpdb ) {
			private \wpdb $delegate;
			public string $prefix;
			public int $insert_id = 0;
			public string $last_error = 'Simulated generated plugin insert failure.';

			public function __construct( \wpdb $delegate ) {
				$this->delegate = $delegate;
				$this->prefix   = $delegate->prefix;
			}

			public function __call( string $name, array $arguments ): mixed {
				return $this->delegate->{$name}( ...$arguments );
			}

			public function __get( string $name ): mixed {
				return $this->delegate->{$name};
			}

			public function insert(): false {
				return false;
			}
		};

		try {
			$result = PluginInstaller::install_complex_plugin(
				$this->test_slug,
				[
					$this->test_slug . '.php' => '<?php /* Plugin Name: Failed DB Install */',
					'includes/valid.php'       => '<?php /* valid */',
				],
				'Database failure test',
				[]
			);
		} finally {
			$wpdb = $original_wpdb;
		}

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_db_insert_failed', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( $this->plugin_dir );
	}

	/**
	 * install_complex_plugin() strips redundant slug prefix from file paths.
	 */
	public function test_install_complex_plugin_normalises_slug_prefix(): void {
		$files = [
			$this->test_slug . '/' . $this->test_slug . '.php' => '<?php /* Plugin Name: Normalised */',
		];

		$result = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			$files,
			'Normalise prefix test',
			[]
		);

		$this->assertIsArray( $result );
		// File should be at the normalised path (slug prefix stripped).
		$this->assertFileExists( $this->plugin_dir . $this->test_slug . '.php' );
	}

	/**
	 * install() must not create slug/slug as a directory/file when the main path misses .php.
	 */
	public function test_install_rejects_e2e_counter_main_file_path_without_php_extension(): void {
		$slug       = 'sd-e2e-counter-widget';
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';
		$files      = [
			$slug . '/' . $slug => '<?php /* Plugin Name: SD E2E Counter Widget */',
		];
		$this->remove_directory( $plugin_dir );

		$result = PluginInstaller::install(
			$slug,
			$files,
			'Create a shortcode counter plugin with admin Tools page and inline JS.',
			'{}',
			$slug . '/' . $slug
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_plugin_file_path', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( $plugin_dir . $slug );
		$this->remove_directory( $plugin_dir );
	}

	/**
	 * install() must reject a main entrypoint that is absent from the file set.
	 */
	public function test_install_rejects_missing_main_plugin_file_before_writing(): void {
		$result = PluginInstaller::install(
			$this->test_slug,
			[ 'includes/helper.php' => '<?php /* helper */' ],
			'Missing entrypoint test',
			'{}',
			$this->test_slug . '/missing-main.php'
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_file_missing', $result->get_error_code() );
		$this->assertDirectoryDoesNotExist( $this->plugin_dir );
	}

	// ─── update_plugin_files ─────────────────────────────────────────────────

	/**
	 * update_plugin_files() should return error when plugin directory does not exist.
	 */
	public function test_update_plugin_files_missing_dir_returns_error(): void {
		$result = PluginInstaller::update_plugin_files(
			'non-existent-plugin-phpunit',
			[ 'main.php' => '<?php /* updated */' ]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * update_plugin_files() should reject empty files map.
	 */
	public function test_update_plugin_files_empty_files_returns_error(): void {
		$result = PluginInstaller::update_plugin_files( $this->test_slug, [] );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_no_files', $result->get_error_code() );
	}

	/**
	 * update_plugin_files() updates an existing file on disk.
	 */
	public function test_update_plugin_files_updates_existing_file(): void {
		// First install the plugin.
		$install = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* original */',
			'Update test',
			[]
		);
		$this->assertIsArray( $install );

		// Now update the file.
		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[ $this->test_slug . '.php' => '<?php /* updated */' ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertNotEmpty( $result['updated'] );

		$file_path = $this->plugin_dir . $this->test_slug . '.php';
		$this->assertFileExists( $file_path );
		$this->assertStringContainsString( 'updated', file_get_contents( $file_path ) );
	}

	/**
	 * update_plugin_files() must reject a leaf link before staging or metadata changes.
	 */
	public function test_update_plugin_files_rejects_leaf_symlink_without_mutation(): void {
		global $wpdb;
		$install = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* original */',
			'Leaf link update test',
			[]
		);
		$this->assertIsArray( $install );
		$plugin_file    = $this->plugin_dir . $this->test_slug . '.php';
		$sentinel       = trailingslashit( sys_get_temp_dir() ) . $this->test_slug . '-update-sentinel.php';
		$original       = '<?php /* outside update sentinel */';
		$metadata_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A );
		unlink( $plugin_file );
		file_put_contents( $sentinel, $original );
		$this->assertTrue( symlink( $sentinel, $plugin_file ) );

		try {
			$result = PluginInstaller::update_plugin_files(
				$this->test_slug,
				[ $this->test_slug . '.php' => '<?php /* changed */' ]
			);

			$this->assertWPError( $result );
			$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
			$this->assertSame( $original, file_get_contents( $sentinel ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion that metadata is byte-for-byte unchanged.
			$this->assertSame( $metadata_before, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A ) );
		} finally {
			unlink( $sentinel );
		}
	}

	/**
	 * update_plugin_files() must reject dangling leaf links.
	 */
	public function test_update_plugin_files_rejects_dangling_leaf_symlink(): void {
		$install = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* original */',
			'Dangling link update test',
			[]
		);
		$this->assertIsArray( $install );
		$plugin_file = $this->plugin_dir . $this->test_slug . '.php';
		unlink( $plugin_file );
		$this->assertTrue( symlink( $this->plugin_dir . 'missing-target.php', $plugin_file ) );

		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[ $this->test_slug . '.php' => '<?php /* changed */' ]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
		$this->assertTrue( is_link( $plugin_file ) );
	}

	/**
	 * Updates preserve a generated plugin's recorded non-canonical entrypoint.
	 */
	public function test_update_plugin_files_uses_recorded_main_plugin_file(): void {
		$install = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[
				'main-entry.php' => '<?php /* Plugin Name: Alternate Entrypoint */',
				'first.txt'      => 'ORIGINAL',
			],
			'Alternate entrypoint test',
			[]
		);
		$this->assertIsArray( $install );
		$this->assertSame( $this->test_slug . '/main-entry.php', $install['plugin_file'] );

		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[ 'first.txt' => 'CHANGED' ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'CHANGED', file_get_contents( $this->plugin_dir . 'first.txt' ) );
		$this->assertFileExists( $this->plugin_dir . 'main-entry.php' );
	}

	/**
	 * Invalid later PHP content must not persist an earlier ordered update.
	 */
	public function test_update_plugin_files_preflights_all_content_before_mutation(): void {
		global $wpdb;
		$install = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[
				$this->test_slug . '.php' => '<?php /* Plugin Name: Atomic Update */',
				'first.txt'                => 'ORIGINAL',
				'second.php'               => '<?php /* original */',
			],
			'Original metadata',
			[ 'step' => 'original' ]
		);
		$this->assertIsArray( $install );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test snapshot of generated plugin metadata.
		$metadata_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A );

		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[
				'first.txt'  => 'CHANGED',
				'second.php' => '<?php $broken = ;',
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_php_syntax_error', $result->get_error_code() );
		$this->assertSame( 'ORIGINAL', file_get_contents( $this->plugin_dir . 'first.txt' ) );
		$this->assertSame( '<?php /* original */', file_get_contents( $this->plugin_dir . 'second.php' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion that metadata is byte-for-byte unchanged.
		$this->assertSame( $metadata_before, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A ) );
	}

	/**
	 * A later staging write failure leaves live files and metadata unchanged.
	 */
	public function test_update_plugin_files_rolls_back_after_later_write_failure(): void {
		global $wpdb;
		$install = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[
				$this->test_slug . '.php' => '<?php /* Plugin Name: Atomic Update */',
				'first.txt'                => 'ORIGINAL',
				'blocked'                  => 'This remains a file.',
			],
			'Original metadata',
			[]
		);
		$this->assertIsArray( $install );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test snapshot of generated plugin metadata.
		$metadata_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A );

		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[
				'first.txt'         => 'CHANGED',
				'blocked/later.php' => '<?php /* valid but unwritable staging destination */',
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_staging_write_failed', $result->get_error_code() );
		$this->assertSame( 'ORIGINAL', file_get_contents( $this->plugin_dir . 'first.txt' ) );
		$this->assertSame( 'This remains a file.', file_get_contents( $this->plugin_dir . 'blocked' ) );
		$this->assertFileDoesNotExist( $this->plugin_dir . 'blocked/later.php' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion that metadata is byte-for-byte unchanged.
		$this->assertSame( $metadata_before, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A ) );
	}

	/**
	 * A metadata update failure restores every original file and database field.
	 */
	public function test_update_plugin_files_rolls_back_after_db_update_failure(): void {
		global $wpdb;
		$install = PluginInstaller::install_complex_plugin(
			$this->test_slug,
			[
				$this->test_slug . '.php' => '<?php /* Plugin Name: Atomic DB Update */',
				'first.txt'                => 'ORIGINAL',
				'second.php'               => '<?php /* original */',
			],
			'Original metadata',
			[ 'step' => 'original' ]
		);
		$this->assertIsArray( $install );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test snapshot of generated plugin metadata.
		$metadata_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A );

		$original_wpdb = $wpdb;
		$wpdb          = new class( $original_wpdb ) {
			private \wpdb $delegate;
			public string $prefix;
			public string $last_error = 'Simulated generated plugin update failure.';

			public function __construct( \wpdb $delegate ) {
				$this->delegate = $delegate;
				$this->prefix   = $delegate->prefix;
			}

			public function __call( string $name, array $arguments ): mixed {
				return $this->delegate->{$name}( ...$arguments );
			}

			public function __get( string $name ): mixed {
				return $this->delegate->{$name};
			}

			public function update(): false {
				return false;
			}
		};

		try {
			$result = PluginInstaller::update_plugin_files(
				$this->test_slug,
				[
					'first.txt'  => 'CHANGED',
					'second.php' => '<?php /* changed */',
				]
			);
		} finally {
			$wpdb = $original_wpdb;
		}

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_db_update_failed', $result->get_error_code() );
		$this->assertSame( 'ORIGINAL', file_get_contents( $this->plugin_dir . 'first.txt' ) );
		$this->assertSame( '<?php /* original */', file_get_contents( $this->plugin_dir . 'second.php' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Test assertion that metadata is byte-for-byte unchanged.
		$this->assertSame( $metadata_before, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PluginInstaller::table_name() . ' WHERE slug = %s', $this->test_slug ), ARRAY_A ) );
	}

	/**
	 * update_plugin_files() should reject traversal paths.
	 */
	public function test_update_plugin_files_rejects_traversal(): void {
		// Create the plugin dir so we get past the is_dir check.
		wp_mkdir_p( $this->plugin_dir );

		$result = PluginInstaller::update_plugin_files(
			$this->test_slug,
			[ '../other-plugin/evil.php' => '<?php evil();' ]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	// ─── delete_generated_plugin ─────────────────────────────────────────────

	/**
	 * delete_generated_plugin() should return error when no DB record exists.
	 */
	public function test_delete_generated_plugin_missing_record_returns_error(): void {
		$result = PluginInstaller::delete_generated_plugin( 'non-existent-plugin-phpunit' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * delete_generated_plugin() removes disk files and DB record.
	 */
	public function test_delete_generated_plugin_removes_files_and_record(): void {
		// Install a plugin first.
		$install = PluginInstaller::install_plugin(
			$this->test_slug,
			'<?php /* Plugin Name: Delete Test */',
			'Delete test plugin',
			[]
		);
		$this->assertIsArray( $install );
		$this->assertFileExists( $this->plugin_dir . $this->test_slug . '.php' );

		// Delete it.
		$result = PluginInstaller::delete_generated_plugin( $this->test_slug );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertFalse( $result['deactivated'] ); // Was never activated.
		$this->assertDirectoryDoesNotExist( $this->plugin_dir );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Remove the test plugin directory if it exists.
	 *
	 * @return void
	 */
	private function cleanup_plugin_dir(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_file = $this->test_slug . '/' . $this->test_slug . '.php';
		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, true );
		}
		$this->remove_directory( $this->plugin_dir );
	}

	/**
	 * Remove a directory recursively if it exists.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function remove_directory( string $directory ): void {
		if ( is_dir( $directory ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			$fs = new \WP_Filesystem_Direct( [] );
			$fs->rmdir( $directory, true );
		}
	}

	/**
	 * Remove any generated_plugins DB records for the test slug.
	 *
	 * @return void
	 */
	private function cleanup_db_records(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup.
		$wpdb->delete(
			PluginInstaller::table_name(),
			[ 'slug' => $this->test_slug ],
			[ '%s' ]
		);
	}
}
