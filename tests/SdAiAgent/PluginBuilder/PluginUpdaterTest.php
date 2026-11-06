<?php
/**
 * Test case for PluginUpdater class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\PluginBuilder;

use SdAiAgent\PluginBuilder\PluginUpdater;
use WP_UnitTestCase;

/**
 * Tests for PluginUpdater — sandboxed live update flow.
 *
 * Each test uses a unique slug prefixed with 'sd-test-updater-phpunit' and
 * cleans up all created directories in tearDown().
 */
class PluginUpdaterTest extends WP_UnitTestCase {

	/**
	 * Plugin slug used across tests.
	 *
	 * @var string
	 */
	private string $slug = 'sd-test-updater-phpunit';

	/**
	 * Absolute path to the test plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * PluginUpdater instance under test.
	 *
	 * @var PluginUpdater
	 */
	private PluginUpdater $updater;

	/**
	 * Temporary upload basedir used during this test class.
	 *
	 * Redirects wp_upload_dir() so tests can prove private storage never uses
	 * the public uploads tree.
	 *
	 * @var string
	 */
	private string $tmp_upload_basedir;

	/**
	 * Private plugin-builder root used during this test class.
	 *
	 * @var string
	 */
	private string $tmp_private_root;

	/**
	 * Set up test directories and a minimal plugin before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->tmp_upload_basedir = sys_get_temp_dir() . '/sd-ai-agent-phpunit';
		$this->tmp_private_root    = sys_get_temp_dir() . '/sd-ai-agent-phpunit-private';
		add_filter( 'upload_dir', [ $this, 'filter_upload_dir_to_tmp' ] );
		add_filter( 'sd_ai_agent_plugin_builder_private_root', [ $this, 'filter_private_root_to_tmp' ] );
		// Allow file modifications for tests.
		add_filter(
			'file_mod_allowed',
			static function () {
				return true;
			},
			10,
			2
		);
		$this->plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->slug . '/';
		$this->updater    = new PluginUpdater();
		$this->cleanup_all();
		$this->create_minimal_plugin();
	}

	/**
	 * Remove all created directories after each test.
	 */
	public function tearDown(): void {
		$this->cleanup_all();
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir_to_tmp' ] );
		remove_filter( 'sd_ai_agent_plugin_builder_private_root', [ $this, 'filter_private_root_to_tmp' ] );
		remove_all_filters( 'file_mod_allowed' );
		parent::tearDown();
	}

	/**
	 * Redirect wp_upload_dir() basedir to a writable tmp directory.
	 *
	 * @param array<string,string> $uploads Upload directory info passed by WP.
	 * @return array<string,string>
	 */
	public function filter_upload_dir_to_tmp( array $uploads ): array {
		$uploads['basedir'] = $this->tmp_upload_basedir;
		$uploads['path']    = $this->tmp_upload_basedir;
		$uploads['baseurl'] = 'http://example.org/tmp-uploads';
		$uploads['url']     = 'http://example.org/tmp-uploads';
		return $uploads;
	}

	/**
	 * Redirect private plugin-builder storage to a test-only temporary root.
	 *
	 * @return string
	 */
	public function filter_private_root_to_tmp(): string {
		return $this->tmp_private_root;
	}

	// ─── backup() ────────────────────────────────────────────────────────────

	/**
	 * backup() should return a path to a new directory containing the plugin files.
	 */
	public function test_backup_creates_backup_dir(): void {
		$result = $this->updater->backup( $this->slug );

		$this->assertIsString( $result );
		$this->assertDirectoryExists( $result );
		$this->assertFileExists( $result . $this->slug . '.php' );
		$this->assertStringStartsWith( trailingslashit( $this->tmp_private_root ), $result );
		$this->assertStringNotContainsString( trailingslashit( $this->tmp_upload_basedir ), $result );
	}

	/**
	 * Same-second backups must use distinct immutable snapshots.
	 */
	public function test_backup_same_second_snapshots_are_distinct_and_immutable(): void {
		$first = $this->updater->backup( $this->slug );
		$this->assertIsString( $first );

		$updated_content = '<?php /* Plugin Name: Second Snapshot */';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Mutating the owned test plugin verifies snapshot immutability.
		file_put_contents( $this->plugin_dir . $this->slug . '.php', $updated_content );

		$second = $this->updater->backup( $this->slug );
		$this->assertIsString( $second );
		$this->assertNotSame( $first, $second );
		$this->assertStringContainsString( 'Test Updater Plugin', (string) file_get_contents( $first . $this->slug . '.php' ) );
		$this->assertSame( $updated_content, file_get_contents( $second . $this->slug . '.php' ) );
	}

	/**
	 * backup() should fail closed when configured inside the public uploads tree.
	 */
	public function test_backup_rejects_public_storage_root(): void {
		$public_root = static function ( string $root ): string {
			unset( $root );
			return sys_get_temp_dir() . '/sd-ai-agent-phpunit/public-backups';
		};
		add_filter( 'sd_ai_agent_plugin_builder_private_root', $public_root, 20 );

		$result = $this->updater->backup( $this->slug );

		remove_filter( 'sd_ai_agent_plugin_builder_private_root', $public_root, 20 );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_private_storage_public', $result->get_error_code() );
	}

	/**
	 * Historical public backups should move only after a verified private copy.
	 */
	public function test_migrate_legacy_storage_moves_verified_backup(): void {
		$legacy_dir = trailingslashit( $this->tmp_upload_basedir )
			. 'sd-ai-backups/' . $this->slug . '-2020-01-01-000001/';
		wp_mkdir_p( $legacy_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Synthetic migration fixture.
		file_put_contents( $legacy_dir . 'config.json', '{"marker":"synthetic-only"}' );

		$result = $this->updater->migrate_legacy_storage();

		$this->assertTrue( $result );
		$this->assertDirectoryDoesNotExist( $legacy_dir );
		$migrated = $this->backups_root() . $this->slug . '-2020-01-01-000001/config.json';
		$this->assertFileExists( $migrated );
		$this->assertSame( '{"marker":"synthetic-only"}', file_get_contents( $migrated ) );
	}

	/**
	 * backup() should return WP_Error for an empty slug.
	 */
	public function test_backup_empty_slug_returns_error(): void {
		$result = $this->updater->backup( '' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * backup() should return WP_Error when the plugin directory does not exist.
	 */
	public function test_backup_missing_plugin_dir_returns_error(): void {
		$result = $this->updater->backup( 'non-existent-plugin-phpunit' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * Backup directory name should include the timestamp and operation identity.
	 */
	public function test_backup_dir_name_format(): void {
		$result = $this->updater->backup( $this->slug );

		$this->assertIsString( $result );
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertMatchesRegularExpression(
			'/^' . preg_quote( $this->slug, '/' ) . '-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{24}$/',
			$basename
		);
	}

	// ─── stage() ─────────────────────────────────────────────────────────────

	/**
	 * stage() should create a staging directory containing all live plugin files.
	 */
	public function test_stage_creates_staging_dir_with_all_live_files(): void {
		$result = $this->updater->stage( $this->slug, [] );

		$this->assertIsString( $result );
		$this->assertDirectoryExists( $result );
		// Main plugin file should be copied from live.
		$this->assertFileExists( $result . $this->slug . '.php' );
		$this->assertStringStartsWith( trailingslashit( $this->tmp_private_root ), $result );
		$this->assertStringNotContainsString( trailingslashit( $this->tmp_upload_basedir ), $result );
	}

	/**
	 * stage() should overlay the provided modified files on top of the live copy.
	 */
	public function test_stage_overlays_modified_files(): void {
		$new_content = '<?php /* updated content */';
		$result      = $this->updater->stage( $this->slug, [ $this->slug . '.php' => $new_content ] );

		$this->assertIsString( $result );
		$staged_file = $result . $this->slug . '.php';
		$this->assertFileExists( $staged_file );
		$this->assertStringContainsString( 'updated content', (string) file_get_contents( $staged_file ) );
	}

	/**
	 * Separate updater instances must not share or overwrite staging directories.
	 */
	public function test_interleaved_stages_are_isolated_and_cannot_be_cross_swapped(): void {
		$updater_a = new PluginUpdater();
		$updater_b = new PluginUpdater();
		$backup_a  = $updater_a->backup( $this->slug );
		$stage_a   = $updater_a->stage( $this->slug, [ $this->slug . '.php' => '<?php /* candidate A */' ] );
		$stage_b   = $updater_b->stage( $this->slug, [ $this->slug . '.php' => '<?php /* candidate B */' ] );

		$this->assertIsString( $backup_a );
		$this->assertIsString( $stage_a );
		$this->assertIsString( $stage_b );
		$this->assertNotSame( $stage_a, $stage_b );
		$this->assertStringContainsString( 'candidate A', (string) file_get_contents( $stage_a . $this->slug . '.php' ) );
		$this->assertStringContainsString( 'candidate B', (string) file_get_contents( $stage_b . $this->slug . '.php' ) );

		$result = $updater_a->swap( $this->slug, $stage_b, $backup_a );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_update_resource_not_owned', $result->get_error_code() );
		$this->assertDirectoryExists( $stage_a );
		$this->assertDirectoryExists( $stage_b );
		$this->assertStringContainsString( 'Test Updater Plugin', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );
	}

	/**
	 * A failed stage must not remove another operation's completed candidate.
	 */
	public function test_stage_abort_removes_only_its_own_candidate(): void {
		$stage_a = ( new PluginUpdater() )->stage( $this->slug, [ $this->slug . '.php' => '<?php /* candidate A */' ] );
		$this->assertIsString( $stage_a );

		$stage_b = ( new PluginUpdater() )->stage( $this->slug, [ $this->slug . '.php' => '<?php $broken = ;' ] );
		$this->assertWPError( $stage_b );
		$this->assertSame( 'sd_ai_agent_php_syntax_error', $stage_b->get_error_code() );
		$this->assertDirectoryExists( $stage_a );
		$this->assertStringContainsString( 'candidate A', (string) file_get_contents( $stage_a . $this->slug . '.php' ) );
	}

	/**
	 * stage() should return WP_Error for an empty slug.
	 */
	public function test_stage_empty_slug_returns_error(): void {
		$result = $this->updater->stage( '', [] );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * stage() should return WP_Error when the plugin directory does not exist.
	 */
	public function test_stage_missing_plugin_returns_error(): void {
		$result = $this->updater->stage( 'non-existent-plugin-phpunit', [] );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * stage() should return WP_Error when a PHP file has syntax errors.
	 */
	public function test_stage_rejects_invalid_php_syntax(): void {
		$broken_php = '<?php $x = ;'; // Missing value after =
		$result     = $this->updater->stage( $this->slug, [ $this->slug . '.php' => $broken_php ] );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_php_syntax_error', $result->get_error_code() );
		$this->assertStringContainsString( 'syntax error', $result->get_error_message() );

		// Verify the failed operation's unique staging directory was cleaned up.
		$stages = glob( $this->staging_root() . $this->slug . '-*', GLOB_ONLYDIR );
		$this->assertSame( [], $stages, 'Staging directory should be cleaned up after lint failure' );
	}

	/**
	 * stage() should accept valid PHP syntax.
	 */
	public function test_stage_accepts_valid_php_syntax(): void {
		$valid_php = '<?php function test() { return 1; }';
		$result    = $this->updater->stage( $this->slug, [ $this->slug . '.php' => $valid_php ] );

		$this->assertIsString( $result );
		$staged_file = $result . $this->slug . '.php';
		$this->assertFileExists( $staged_file );
		$this->assertStringContainsString( 'function test', (string) file_get_contents( $staged_file ) );
	}

	// ─── test_staged() ───────────────────────────────────────────────────────

	/**
	 * test_staged() should return an array with `passed` key.
	 */
	public function test_test_staged_returns_array_with_passed_key(): void {
		$staging_dir = $this->updater->stage( $this->slug, [] );
		$this->assertIsString( $staging_dir );

		$result = $this->updater->test_staged( $this->slug, $staging_dir );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'passed', $result );
		$this->assertArrayHasKey( 'errors', $result );
	}

	/**
	 * test_staged() should pass for a valid PHP plugin file.
	 */
	public function test_test_staged_passes_for_valid_plugin(): void {
		$staging_dir = $this->updater->stage( $this->slug, [] );
		$this->assertIsString( $staging_dir );

		$result = $this->updater->test_staged( $this->slug, $staging_dir );

		$this->assertIsArray( $result );
		$this->assertTrue( (bool) $result['passed'] );
		$this->assertEmpty( $result['errors'] );
	}

	// ─── rollback() ──────────────────────────────────────────────────────────

	/**
	 * rollback() should restore plugin files from a backup directory.
	 */
	public function test_rollback_restores_from_backup(): void {
		$backup_dir = $this->updater->backup( $this->slug );
		$this->assertIsString( $backup_dir );

		// Corrupt the live plugin.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test: write corrupted content to simulate a failed swap.
		file_put_contents( $this->plugin_dir . $this->slug . '.php', '<?php /* corrupted */' );

		$result = $this->updater->rollback( $this->slug, $backup_dir );

		$this->assertIsArray( $result );
		$this->assertTrue( (bool) $result['rolled_back'] );

		// Restored file should have original content.
		$restored_content = (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' );
		$this->assertStringContainsString( 'Plugin Name', $restored_content );
	}

	/**
	 * rollback() should return WP_Error when backup directory does not exist.
	 */
	public function test_rollback_missing_backup_returns_error(): void {
		$result = $this->updater->rollback( $this->slug, '/tmp/non-existent-backup-phpunit/' );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_backup_not_found', $result->get_error_code() );
	}

	/**
	 * rollback() must reject existing directories outside private backup storage.
	 */
	public function test_rollback_rejects_directory_outside_backup_storage(): void {
		$result = $this->updater->rollback( $this->slug, $this->plugin_dir );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_backup_not_owned', $result->get_error_code() );
		$this->assertFileExists( $this->plugin_dir . $this->slug . '.php' );
	}

	/**
	 * Explicit rollback uses the same per-plugin transaction lock as update().
	 */
	public function test_rollback_refuses_competing_update_lock(): void {
		$backup = $this->updater->backup( $this->slug );
		$this->assertIsString( $backup );
		$lock = $this->acquire_external_update_lock( $this->slug );
		$no_wait = static function (): int {
			return 0;
		};
		add_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );

		$result = $this->updater->rollback( $this->slug, $backup );

		remove_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );
		flock( $lock, LOCK_UN );
		fclose( $lock );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_update_in_progress', $result->get_error_code() );
	}

	// ─── cleanup_old_backups() ───────────────────────────────────────────────

	/**
	 * cleanup_old_backups() should return 0 when there are no backup directories.
	 */
	public function test_cleanup_old_backups_returns_zero_when_no_backups(): void {
		$count = $this->updater->cleanup_old_backups();
		$this->assertSame( 0, $count );
	}

	/**
	 * cleanup_old_backups() should preserve the most recent backup regardless of age.
	 */
	public function test_cleanup_old_backups_preserves_most_recent(): void {
		// Create two backup directories for the slug.
		$backups_root = $this->backups_root();
		wp_mkdir_p( $backups_root );

		$old_dir = $backups_root . $this->slug . '-2020-01-01-000001/';
		$new_dir = $backups_root . $this->slug . '-2020-01-02-000001-0123456789abcdef01234567/';
		wp_mkdir_p( $old_dir );
		wp_mkdir_p( $new_dir );

		// Backdate the old dir's mtime so it's well outside the 7-day window.
		touch( $old_dir, strtotime( '-30 days' ) );

		$count = $this->updater->cleanup_old_backups( 7 );

		// Old backup should be removed; most recent (new_dir) should survive.
		$this->assertSame( 1, $count );
		$this->assertDirectoryDoesNotExist( $old_dir );
		$this->assertDirectoryExists( $new_dir );
	}

	// ─── update() ────────────────────────────────────────────────────────────

	/**
	 * update() should return WP_Error when the plugin slug is invalid.
	 */
	public function test_update_invalid_slug_returns_error(): void {
		$result = $this->updater->update( '', [] );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * update() should return WP_Error when the plugin directory does not exist.
	 */
	public function test_update_missing_plugin_returns_error(): void {
		$result = $this->updater->update( 'non-existent-plugin-phpunit', [ 'main.php' => '<?php' ] );
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * update() should replace live plugin files and include backup_dir in the result.
	 */
	public function test_update_replaces_live_plugin_files(): void {
		$new_content = '<?php /* Plugin Name: Updated Test Plugin */';

		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => $new_content ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'backup_dir', $result );
		$this->assertTrue( (bool) $result['swapped'] );

		// Main plugin file should contain the updated content.
		$live_content = (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' );
		$this->assertStringContainsString( 'Updated Test Plugin', $live_content );
	}

	/**
	 * update() must refuse a competing operation for the same live plugin.
	 */
	public function test_update_serializes_competing_operations_per_plugin(): void {
		$lock = $this->acquire_external_update_lock( $this->slug );
		$no_wait = static function (): int {
			return 0;
		};
		add_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );

		$result = ( new PluginUpdater() )->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* blocked candidate */' ]
		);

		remove_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );
		flock( $lock, LOCK_UN );
		fclose( $lock );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_plugin_update_in_progress', $result->get_error_code() );
		$this->assertStringContainsString( 'Test Updater Plugin', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );
	}

	/**
	 * A lock for one slug must not prevent updates to another plugin.
	 */
	public function test_update_locks_are_independent_between_plugins(): void {
		$other_slug = $this->slug . '-other';
		$this->create_minimal_plugin( $other_slug );
		$lock = $this->acquire_external_update_lock( $this->slug );

		$result = ( new PluginUpdater() )->update(
			$other_slug,
			[ $other_slug . '.php' => '<?php /* Plugin Name: Independently Updated */' ]
		);

		flock( $lock, LOCK_UN );
		fclose( $lock );
		$this->assertIsArray( $result );
		$this->assertTrue( (bool) $result['swapped'] );
		$this->assertStringContainsString(
			'Independently Updated',
			(string) file_get_contents( trailingslashit( WP_PLUGIN_DIR ) . $other_slug . '/' . $other_slug . '.php' )
		);
	}

	/**
	 * Post-swap validation remains inside the same-plugin transaction lock.
	 */
	public function test_update_holds_lock_through_post_swap_validation(): void {
		$no_wait = static function (): int {
			return 0;
		};
		add_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );
		$competing_result = null;

		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: First Transaction */' ],
			function () use ( &$competing_result ): true {
				$competing_result = ( new PluginUpdater() )->update(
					$this->slug,
					[ $this->slug . '.php' => '<?php /* Plugin Name: Competing Transaction */' ]
				);
				return true;
			}
		);

		remove_filter( 'sd_ai_agent_plugin_update_lock_timeout_ms', $no_wait );
		$this->assertIsArray( $result );
		$this->assertWPError( $competing_result );
		$this->assertSame( 'sd_ai_agent_plugin_update_in_progress', $competing_result->get_error_code() );
		$this->assertStringContainsString( 'First Transaction', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );
	}

	/**
	 * A failed post-swap health check can restore its own immutable backup.
	 */
	public function test_update_post_swap_failure_rolls_back_before_unlocking(): void {
		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: Unhealthy Transaction */' ],
			function ( array $swap_result, string $backup_dir ) {
				unset( $swap_result );
				$rollback = $this->updater->rollback( $this->slug, $backup_dir );
				$this->assertIsArray( $rollback );
				return new \WP_Error( 'synthetic_health_failure', 'Synthetic health failure.' );
			}
		);

		$this->assertWPError( $result );
		$this->assertSame( 'synthetic_health_failure', $result->get_error_code() );
		$this->assertStringContainsString( 'Test Updater Plugin', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );

		$next = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: Healthy Follow-up */' ]
		);
		$this->assertIsArray( $next );
		$this->assertStringContainsString( 'Healthy Follow-up', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );
	}

	/**
	 * Callback exceptions roll back safely and still release the transaction lock.
	 */
	public function test_update_post_swap_exception_rolls_back_and_releases_lock(): void {
		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: Exception Transaction */' ],
			static function (): void {
				throw new \RuntimeException( 'Synthetic callback exception.' );
			}
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_post_swap_validation_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'Test Updater Plugin', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );

		$next = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: After Exception */' ]
		);
		$this->assertIsArray( $next );
		$this->assertStringContainsString( 'After Exception', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );
	}

	/**
	 * Callback exceptions report a failed rollback instead of claiming recovery.
	 */
	public function test_update_post_swap_exception_reports_rollback_failure(): void {
		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: Unrecoverable Transaction */' ],
			function ( array $swap_result, string $backup_dir ): void {
				unset( $swap_result );
				$this->remove_dir( $backup_dir );
				throw new \RuntimeException( 'Synthetic callback exception.' );
			}
		);

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_post_swap_rollback_failed', $result->get_error_code() );
		$this->assertSame( 'sd_ai_agent_backup_not_found', $result->get_error_data()['rollback_error_code'] );
		$this->assertStringContainsString( 'Unrecoverable Transaction', (string) file_get_contents( $this->plugin_dir . $this->slug . '.php' ) );

		$next = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => '<?php /* Plugin Name: Recovered Later */' ]
		);
		$this->assertIsArray( $next );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Create a minimal valid plugin in the test plugin directory.
	 *
	 * @return void
	 */
	private function create_minimal_plugin( ?string $slug = null ): void {
		$slug       = $slug ?? $this->slug;
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';
		wp_mkdir_p( $plugin_dir );
		$content = '<?php /* Plugin Name: Test Updater Plugin */' . "\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test setup: creating a minimal plugin file.
		file_put_contents( $plugin_dir . $slug . '.php', $content );
	}

	/**
	 * Remove all directories created during the test.
	 *
	 * @return void
	 */
	private function cleanup_all(): void {
		$dirs_to_clean = [
			trailingslashit( WP_PLUGIN_DIR ) . $this->slug . '/',
			trailingslashit( WP_PLUGIN_DIR ) . $this->slug . '-other/',
			trailingslashit( WP_PLUGIN_DIR ) . 'non-existent-plugin-phpunit/',
			trailingslashit( $this->tmp_upload_basedir ) . 'sd-ai-backups/' . $this->slug . '-2020-01-01-000001/',
		];

		foreach ( $dirs_to_clean as $dir ) {
			$this->remove_dir( $dir );
		}

		// Remove all backup directories for this slug.
		$backups_root = $this->backups_root();
		if ( is_dir( $backups_root ) ) {
			$entries = glob( $backups_root . $this->slug . '-*', GLOB_ONLYDIR );
			if ( false !== $entries ) {
				foreach ( $entries as $entry ) {
					$this->remove_dir( $entry );
				}
			}
		}

		$staging_root = $this->staging_root();
		if ( is_dir( $staging_root ) ) {
			$entries = glob( $staging_root . $this->slug . '-*', GLOB_ONLYDIR );
			if ( false !== $entries ) {
				foreach ( $entries as $entry ) {
					$this->remove_dir( $entry );
				}
			}
		}
	}

	/**
	 * Hold the production per-plugin lock to simulate a competing request.
	 *
	 * @param string $slug Plugin slug.
	 * @return resource Locked file handle.
	 */
	private function acquire_external_update_lock( string $slug ) {
		$locks_root = trailingslashit( $this->tmp_private_root ) . 'locks/';
		wp_mkdir_p( $locks_root );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Match production's private-root permissions in this synthetic fixture.
		chmod( $this->tmp_private_root, 0700 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Test fixture must hold the same OS lock as production.
		$handle = fopen( $locks_root . hash( 'sha256', $slug ) . '.lock', 'c+' );
		if ( false === $handle ) {
			$this->fail( 'Could not open the synthetic update lock.' );
		}
		$this->assertTrue( flock( $handle, LOCK_EX | LOCK_NB ) );
		return $handle;
	}

	/**
	 * Mirror PluginUpdater::backups_root() so tests can locate backup dirs.
	 */
	private function backups_root(): string {
		return trailingslashit( $this->tmp_private_root ) . 'backups/';
	}

	/**
	 * Mirror PluginUpdater::staging_root() so tests can locate staging dirs.
	 */
	private function staging_root(): string {
		return trailingslashit( $this->tmp_private_root ) . 'staging/';
	}

	/**
	 * Recursively remove a directory using WP_Filesystem_Direct.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( [] );
		$fs->rmdir( $dir, true );
	}
}
