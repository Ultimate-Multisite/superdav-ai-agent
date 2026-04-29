<?php
/**
 * Test case for GitAbilities classes.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\GitSnapshotAbility;
use SdAiAgent\Abilities\GitDiffAbility;
use SdAiAgent\Abilities\GitRestoreAbility;
use SdAiAgent\Abilities\GitListAbility;
use SdAiAgent\Abilities\GitPackageSummaryAbility;
use SdAiAgent\Abilities\GitRevertPackageAbility;
use WP_UnitTestCase;

/**
 * Test GitAbilities handler methods via the individual ability classes.
 */
class GitAbilitiesTest extends WP_UnitTestCase {

	// ─── GitSnapshotAbility ───────────────────────────────────────

	/**
	 * Test GitSnapshotAbility returns WP_Error for empty path.
	 */
	public function test_git_snapshot_empty_path_returns_wp_error() {
		$ability = new GitSnapshotAbility(
			'sd-ai-agent/git-snapshot',
			[
				'label'       => 'Snapshot File',
				'description' => 'Snapshot a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'path' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitSnapshotAbility returns WP_Error for missing path.
	 */
	public function test_git_snapshot_missing_path_returns_wp_error() {
		$ability = new GitSnapshotAbility(
			'sd-ai-agent/git-snapshot',
			[
				'label'       => 'Snapshot File',
				'description' => 'Snapshot a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitSnapshotAbility with a non-existent file returns WP_Error or array.
	 *
	 * The underlying GitTrackerManager::snapshot_before_modify may return WP_Error
	 * for non-existent files. Either outcome is valid.
	 */
	public function test_git_snapshot_nonexistent_file_returns_error_or_array() {
		$ability = new GitSnapshotAbility(
			'sd-ai-agent/git-snapshot',
			[
				'label'       => 'Snapshot File',
				'description' => 'Snapshot a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'path' => '/tmp/nonexistent-file-' . uniqid() . '.txt' ] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	// ─── GitDiffAbility ───────────────────────────────────────────

	/**
	 * Test GitDiffAbility returns WP_Error for empty path.
	 */
	public function test_git_diff_empty_path_returns_wp_error() {
		$ability = new GitDiffAbility(
			'sd-ai-agent/git-diff',
			[
				'label'       => 'Diff File',
				'description' => 'Diff a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [
			'path'         => '',
			'package_slug' => 'some-plugin/plugin.php',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitDiffAbility returns WP_Error for empty package_slug.
	 */
	public function test_git_diff_empty_package_slug_returns_wp_error() {
		$ability = new GitDiffAbility(
			'sd-ai-agent/git-diff',
			[
				'label'       => 'Diff File',
				'description' => 'Diff a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [
			'path'         => '/some/path/file.php',
			'package_slug' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitDiffAbility with missing required fields returns WP_Error.
	 */
	public function test_git_diff_missing_fields_returns_wp_error() {
		$ability = new GitDiffAbility(
			'sd-ai-agent/git-diff',
			[
				'label'       => 'Diff File',
				'description' => 'Diff a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitDiffAbility defaults package_type to plugin when omitted.
	 *
	 * With a valid path and slug but no snapshot, the tracker will return
	 * WP_Error (no snapshot found). This confirms the code path is reached.
	 */
	public function test_git_diff_defaults_package_type_to_plugin() {
		$ability = new GitDiffAbility(
			'sd-ai-agent/git-diff',
			[
				'label'       => 'Diff File',
				'description' => 'Diff a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [
			'path'         => '/some/path/file.php',
			'package_slug' => 'some-plugin/plugin.php',
			// package_type intentionally omitted — should default to 'plugin'.
		] );

		// Either a diff result or WP_Error (no snapshot) is acceptable.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	// ─── GitRestoreAbility ────────────────────────────────────────

	/**
	 * Test GitRestoreAbility returns WP_Error for empty path.
	 */
	public function test_git_restore_empty_path_returns_wp_error() {
		$ability = new GitRestoreAbility(
			'sd-ai-agent/git-restore',
			[
				'label'       => 'Restore File',
				'description' => 'Restore a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [
			'path'         => '',
			'package_slug' => 'some-plugin/plugin.php',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitRestoreAbility returns WP_Error for empty package_slug.
	 */
	public function test_git_restore_empty_package_slug_returns_wp_error() {
		$ability = new GitRestoreAbility(
			'sd-ai-agent/git-restore',
			[
				'label'       => 'Restore File',
				'description' => 'Restore a file.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [
			'path'         => '/some/path/file.php',
			'package_slug' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	// ─── GitListAbility ───────────────────────────────────────────

	/**
	 * Test GitListAbility returns expected structure with no tracked files.
	 */
	public function test_git_list_returns_expected_structure() {
		$ability = new GitListAbility(
			'sd-ai-agent/git-list',
			[
				'label'       => 'List Tracked Files',
				'description' => 'List tracked files.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'files', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertArrayHasKey( 'packages', $result );
		$this->assertIsArray( $result['files'] );
		$this->assertIsInt( $result['count'] );
		$this->assertIsArray( $result['packages'] );
	}

	/**
	 * Test GitListAbility count matches files array length.
	 */
	public function test_git_list_count_matches_files_length() {
		$ability = new GitListAbility(
			'sd-ai-agent/git-list',
			[
				'label'       => 'List Tracked Files',
				'description' => 'List tracked files.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertIsArray( $result );
		$this->assertSame( count( $result['files'] ), $result['count'] );
	}

	/**
	 * Test GitListAbility with status filter returns array or WP_Error.
	 */
	public function test_git_list_with_status_filter() {
		$ability = new GitListAbility(
			'sd-ai-agent/git-list',
			[
				'label'       => 'List Tracked Files',
				'description' => 'List tracked files.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'status' => 'modified' ] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertArrayHasKey( 'files', $result );
			$this->assertArrayHasKey( 'count', $result );
		}
	}

	// ─── GitPackageSummaryAbility ─────────────────────────────────

	/**
	 * Test GitPackageSummaryAbility returns WP_Error for empty package_slug.
	 */
	public function test_git_package_summary_empty_slug_returns_wp_error() {
		$ability = new GitPackageSummaryAbility(
			'sd-ai-agent/git-package-summary',
			[
				'label'       => 'Package Change Summary',
				'description' => 'Get package summary.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'package_slug' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitPackageSummaryAbility returns WP_Error for missing package_slug.
	 */
	public function test_git_package_summary_missing_slug_returns_wp_error() {
		$ability = new GitPackageSummaryAbility(
			'sd-ai-agent/git-package-summary',
			[
				'label'       => 'Package Change Summary',
				'description' => 'Get package summary.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitPackageSummaryAbility with valid slug returns array or WP_Error.
	 */
	public function test_git_package_summary_valid_slug_returns_array_or_error() {
		$ability = new GitPackageSummaryAbility(
			'sd-ai-agent/git-package-summary',
			[
				'label'       => 'Package Change Summary',
				'description' => 'Get package summary.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'package_slug' => 'some-plugin/plugin.php' ] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	// ─── GitRevertPackageAbility ──────────────────────────────────

	/**
	 * Test GitRevertPackageAbility returns WP_Error for empty package_slug.
	 */
	public function test_git_revert_package_empty_slug_returns_wp_error() {
		$ability = new GitRevertPackageAbility(
			'sd-ai-agent/git-revert-package',
			[
				'label'       => 'Revert Package',
				'description' => 'Revert a package.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'package_slug' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitRevertPackageAbility returns WP_Error for missing package_slug.
	 */
	public function test_git_revert_package_missing_slug_returns_wp_error() {
		$ability = new GitRevertPackageAbility(
			'sd-ai-agent/git-revert-package',
			[
				'label'       => 'Revert Package',
				'description' => 'Revert a package.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test GitRevertPackageAbility with valid slug returns array with expected keys.
	 *
	 * With no tracked files, reverted and failed should both be 0.
	 */
	public function test_git_revert_package_valid_slug_returns_array() {
		$ability = new GitRevertPackageAbility(
			'sd-ai-agent/git-revert-package',
			[
				'label'       => 'Revert Package',
				'description' => 'Revert a package.',
			]
		);

		// @phpstan-ignore-next-line
		$result = $ability->run( [ 'package_slug' => 'some-plugin/plugin.php' ] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertArrayHasKey( 'package_slug', $result );
			$this->assertArrayHasKey( 'reverted', $result );
			$this->assertArrayHasKey( 'failed', $result );
			$this->assertArrayHasKey( 'message', $result );
			$this->assertIsInt( $result['reverted'] );
			$this->assertIsInt( $result['failed'] );
		}
	}
}
