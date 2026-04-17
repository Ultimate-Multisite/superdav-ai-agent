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

use GratisAiAgent\Models\GitTrackerManager;

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
