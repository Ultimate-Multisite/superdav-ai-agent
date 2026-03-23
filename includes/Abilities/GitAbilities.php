<?php

declare(strict_types=1);
/**
 * Git-style file tracking abilities for the AI agent.
 *
 * Exposes snapshot, diff, restore, and list operations backed by
 * GitTracker / GitTrackerManager (Models layer). Allows the AI to:
 *   - Snapshot a file before editing (gratis-ai-agent/git-snapshot)
 *   - Diff current vs original (gratis-ai-agent/git-diff)
 *   - Restore original content (gratis-ai-agent/git-restore)
 *   - List all tracked files (gratis-ai-agent/git-list)
 *   - Get a summary for a package (gratis-ai-agent/git-package-summary)
 *
 * Note: FileAbilities automatically fires `gratis_ai_agent_before_file_write`
 * and `gratis_ai_agent_before_file_edit` hooks, which GitTrackerManager hooks
 * into to snapshot files transparently. These abilities provide explicit
 * control and visibility for the AI agent.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\GitTracker;
use GratisAiAgent\Models\GitTrackerManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GitAbilities {

	/**
	 * Register git tracking abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all git tracking abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/git-snapshot',
			[
				'label'         => __( 'Snapshot File', 'gratis-ai-agent' ),
				'description'   => __( 'Explicitly snapshot a file before editing. Note: FileAbilities automatically snapshots files on write/edit — use this for manual control.', 'gratis-ai-agent' ),
				'ability_class' => GitSnapshotAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/git-diff',
			[
				'label'         => __( 'Diff File', 'gratis-ai-agent' ),
				'description'   => __( 'Show a unified diff between the original snapshot and the current file content.', 'gratis-ai-agent' ),
				'ability_class' => GitDiffAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/git-restore',
			[
				'label'         => __( 'Restore File', 'gratis-ai-agent' ),
				'description'   => __( 'Restore a file to its original snapshotted content, undoing all AI modifications.', 'gratis-ai-agent' ),
				'ability_class' => GitRestoreAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/git-list',
			[
				'label'         => __( 'List Tracked Files', 'gratis-ai-agent' ),
				'description'   => __( 'List all files that have been snapshotted, with their modification status.', 'gratis-ai-agent' ),
				'ability_class' => GitListAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/git-package-summary',
			[
				'label'         => __( 'Package Change Summary', 'gratis-ai-agent' ),
				'description'   => __( 'Get a summary of tracked and modified files for a specific plugin or theme package.', 'gratis-ai-agent' ),
				'ability_class' => GitPackageSummaryAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/git-revert-package',
			[
				'label'         => __( 'Revert Package', 'gratis-ai-agent' ),
				'description'   => __( 'Revert all modified files in a plugin or theme back to their original snapshotted content.', 'gratis-ai-agent' ),
				'ability_class' => GitRevertPackageAbility::class,
			]
		);
	}
}

/**
 * Git Snapshot ability — explicitly snapshot a file.
 *
 * @since 1.1.0
 */
class GitSnapshotAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Snapshot File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Explicitly snapshot a file before editing. Note: FileAbilities automatically snapshots files on write/edit — use this for manual control.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Absolute filesystem path to the file to snapshot.',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'    => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path = $input['path'] ?? null;

		if ( ! is_string( $path ) || '' === $path ) {
			return new WP_Error( 'gratis_ai_agent_invalid_path', __( 'Path must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		$result = GitTrackerManager::snapshot_before_modify( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'path'    => $path,
			'message' => sprintf(
				/* translators: %s: file path */
				__( 'File snapshotted successfully: %s', 'gratis-ai-agent' ),
				$path
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => false,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Git Diff ability — show changes since last snapshot.
 *
 * @since 1.1.0
 */
class GitDiffAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Diff File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Show a unified diff between the original snapshot and the current file content.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'         => [
					'type'        => 'string',
					'description' => 'Absolute filesystem path to the file.',
				],
				'package_slug' => [
					'type'        => 'string',
					'description' => 'Plugin file slug (e.g. "akismet/akismet.php") or theme slug (e.g. "twentytwentyfour").',
				],
				'package_type' => [
					'type'        => 'string',
					'enum'        => [ 'plugin', 'theme' ],
					'description' => 'Whether the package is a plugin or theme.',
				],
			],
			'required'   => [ 'path', 'package_slug', 'package_type' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'        => [ 'type' => 'string' ],
				'has_changes' => [ 'type' => 'boolean' ],
				'diff'        => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path         = $input['path'] ?? null;
		$package_slug = $input['package_slug'] ?? null;
		$package_type = $input['package_type'] ?? null;

		if ( ! is_string( $path ) || '' === $path ) {
			return new WP_Error( 'gratis_ai_agent_invalid_path', __( 'Path must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_slug ) || '' === $package_slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'Package slug must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_type ) || '' === $package_type ) {
			$package_type = 'plugin';
		}

		if ( 'theme' === $package_type ) {
			$tracker = GitTrackerManager::for_theme( $package_slug );
		} else {
			$tracker = GitTrackerManager::for_plugin( $package_slug );
		}

		if ( is_wp_error( $tracker ) ) {
			return $tracker;
		}

		$diff = $tracker->get_diff( $path );

		if ( is_wp_error( $diff ) ) {
			return $diff;
		}

		return [
			'path'        => $path,
			'has_changes' => '' !== $diff,
			'diff'        => $diff,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => true,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Git Restore ability — revert a file to its original snapshot.
 *
 * @since 1.1.0
 */
class GitRestoreAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Restore File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Restore a file to its original snapshotted content, undoing all AI modifications.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'         => [
					'type'        => 'string',
					'description' => 'Absolute filesystem path to the file.',
				],
				'package_slug' => [
					'type'        => 'string',
					'description' => 'Plugin file slug (e.g. "akismet/akismet.php") or theme slug (e.g. "twentytwentyfour").',
				],
				'package_type' => [
					'type'        => 'string',
					'enum'        => [ 'plugin', 'theme' ],
					'description' => 'Whether the package is a plugin or theme.',
				],
			],
			'required'   => [ 'path', 'package_slug', 'package_type' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'    => [ 'type' => 'string' ],
				'action'  => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path         = $input['path'] ?? null;
		$package_slug = $input['package_slug'] ?? null;
		$package_type = $input['package_type'] ?? null;

		if ( ! is_string( $path ) || '' === $path ) {
			return new WP_Error( 'gratis_ai_agent_invalid_path', __( 'Path must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_slug ) || '' === $package_slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'Package slug must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_type ) || '' === $package_type ) {
			$package_type = 'plugin';
		}

		if ( 'theme' === $package_type ) {
			$tracker = GitTrackerManager::for_theme( $package_slug );
		} else {
			$tracker = GitTrackerManager::for_plugin( $package_slug );
		}

		if ( is_wp_error( $tracker ) ) {
			return $tracker;
		}

		$result = $tracker->revert_file( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'path'    => $path,
			'action'  => 'restored',
			'message' => sprintf(
				/* translators: %s: file path */
				__( 'File restored to original snapshot: %s', 'gratis-ai-agent' ),
				$path
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'destructive' => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Git List ability — list all tracked files across all packages.
 *
 * @since 1.1.0
 */
class GitListAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Tracked Files', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all files that have been snapshotted, with their modification status.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'unchanged', 'modified', 'deleted' ],
					'description' => 'Filter by status. Omit to list all tracked files.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'files'    => [ 'type' => 'array' ],
				'count'    => [ 'type' => 'integer' ],
				'packages' => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$status_raw = $input['status'] ?? null;
		$status     = is_string( $status_raw ) && '' !== $status_raw ? $status_raw : null;

		$rows = GitTrackerManager::get_all_tracked_files( $status );

		$files = [];
		foreach ( $rows as $row ) {
			$files[] = [
				'id'           => (int) $row->id,
				'package_slug' => $row->package_slug,
				'file_type'    => $row->file_type,
				'file_path'    => $row->file_path,
				'status'       => $row->status,
				'tracked_at'   => $row->tracked_at,
				'modified_at'  => $row->modified_at,
			];
		}

		$packages = GitTrackerManager::get_modified_packages();

		return [
			'files'    => $files,
			'count'    => count( $files ),
			'packages' => $packages,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => true,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Git Package Summary ability — get a summary for a specific package.
 *
 * @since 1.1.0
 */
class GitPackageSummaryAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Package Change Summary', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Get a summary of tracked and modified files for a specific plugin or theme package.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'package_slug' => [
					'type'        => 'string',
					'description' => 'Plugin file slug (e.g. "akismet/akismet.php") or theme slug (e.g. "twentytwentyfour").',
				],
				'package_type' => [
					'type'        => 'string',
					'enum'        => [ 'plugin', 'theme' ],
					'description' => 'Whether the package is a plugin or theme.',
				],
			],
			'required'   => [ 'package_slug', 'package_type' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'           => [ 'type' => 'string' ],
				'type'           => [ 'type' => 'string' ],
				'path'           => [ 'type' => 'string' ],
				'total_tracked'  => [ 'type' => 'integer' ],
				'modified_count' => [ 'type' => 'integer' ],
				'by_status'      => [ 'type' => 'object' ],
				'modified_files' => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$package_slug = $input['package_slug'] ?? null;
		$package_type = $input['package_type'] ?? null;

		if ( ! is_string( $package_slug ) || '' === $package_slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'Package slug must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_type ) || '' === $package_type ) {
			$package_type = 'plugin';
		}

		$summary = GitTrackerManager::get_package_summary( $package_slug, $package_type );

		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		return $summary;
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => true,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Git Revert Package ability — revert all modified files in a package.
 *
 * @since 1.1.0
 */
class GitRevertPackageAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Revert Package', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Revert all modified files in a plugin or theme back to their original snapshotted content.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'package_slug' => [
					'type'        => 'string',
					'description' => 'Plugin file slug (e.g. "akismet/akismet.php") or theme slug (e.g. "twentytwentyfour").',
				],
				'package_type' => [
					'type'        => 'string',
					'enum'        => [ 'plugin', 'theme' ],
					'description' => 'Whether the package is a plugin or theme.',
				],
			],
			'required'   => [ 'package_slug', 'package_type' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'package_slug' => [ 'type' => 'string' ],
				'reverted'     => [ 'type' => 'integer' ],
				'failed'       => [ 'type' => 'integer' ],
				'message'      => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$package_slug = $input['package_slug'] ?? null;
		$package_type = $input['package_type'] ?? null;

		if ( ! is_string( $package_slug ) || '' === $package_slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'Package slug must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		if ( ! is_string( $package_type ) || '' === $package_type ) {
			$package_type = 'plugin';
		}

		$result = GitTrackerManager::revert_package( $package_slug, $package_type );

		return [
			'package_slug' => $package_slug,
			'reverted'     => $result['reverted'],
			'failed'       => $result['failed'],
			'message'      => sprintf(
				/* translators: 1: reverted count, 2: failed count, 3: package slug */
				__( 'Reverted %1$d file(s), %2$d failed for package %3$s.', 'gratis-ai-agent' ),
				$result['reverted'],
				$result['failed'],
				$package_slug
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'destructive' => true,
			],
			'show_in_rest' => true,
		];
	}
}
