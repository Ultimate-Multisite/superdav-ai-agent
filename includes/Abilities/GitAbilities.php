<?php

declare(strict_types=1);
/**
 * Git-style file tracking abilities for the AI agent.
 *
 * Exposes snapshot, diff, restore, and list operations backed by
 * GitTracker / GitTrackerManager (Models layer). Allows the AI to:
 *   - Snapshot a file before editing (sd-ai-agent/git-snapshot)
 *   - Diff current vs original (sd-ai-agent/git-diff)
 *   - Restore original content (sd-ai-agent/git-restore)
 *   - List all tracked files (sd-ai-agent/git-list)
 *   - Get a summary for a package (sd-ai-agent/git-package-summary)
 *
 * Note: FileAbilities automatically fires `sd_ai_agent_before_file_write`
 * and `sd_ai_agent_before_file_edit` hooks, which GitTrackerManager hooks
 * into to snapshot files transparently. These abilities provide explicit
 * control and visibility for the AI agent.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GitAbilities {

	/**
	 * Register all git tracking abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/git-snapshot',
			[
				'label'         => __( 'Snapshot File', 'sd-ai-agent' ),
				'description'   => __( 'Explicitly snapshot a file before editing. Note: FileAbilities automatically snapshots files on write/edit — use this for manual control.', 'sd-ai-agent' ),
				'ability_class' => GitSnapshotAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/git-diff',
			[
				'label'         => __( 'Diff File', 'sd-ai-agent' ),
				'description'   => __( 'Show a unified diff between the original snapshot and the current file content.', 'sd-ai-agent' ),
				'ability_class' => GitDiffAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/git-restore',
			[
				'label'         => __( 'Restore File', 'sd-ai-agent' ),
				'description'   => __( 'Restore a file to its original snapshotted content, undoing all AI modifications.', 'sd-ai-agent' ),
				'ability_class' => GitRestoreAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/git-list',
			[
				'label'         => __( 'List Tracked Files', 'sd-ai-agent' ),
				'description'   => __( 'List all files that have been snapshotted, with their modification status.', 'sd-ai-agent' ),
				'ability_class' => GitListAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/git-package-summary',
			[
				'label'         => __( 'Package Change Summary', 'sd-ai-agent' ),
				'description'   => __( 'Get a summary of tracked and modified files for a specific plugin or theme package.', 'sd-ai-agent' ),
				'ability_class' => GitPackageSummaryAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/git-revert-package',
			[
				'label'         => __( 'Revert Package', 'sd-ai-agent' ),
				'description'   => __( 'Revert all modified files in a plugin or theme back to their original snapshotted content.', 'sd-ai-agent' ),
				'ability_class' => GitRevertPackageAbility::class,
			]
		);
	}
}
