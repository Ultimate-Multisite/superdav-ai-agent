<?php

declare(strict_types=1);
/**
 * Advanced mutating filesystem abilities for Superdav AI Agent.
 *
 * @package SdAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\ChangeLogger;
use SdAiAgent\Core\Database;
use SdAiAgent\Core\Filesystem\FileModGate;
use SdAiAgent\Core\Health\PostMutationHealthCheck;
use SdAiAgent\Models\ChangesLog;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers advanced file mutation abilities supplied by the companion plugin.
 */
class FileMutationAbilities {

	/**
	 * Register mutating filesystem abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// These broad paths can address any wp-content subtree, including another
		// tenant's uploads. Customer media changes use scoped media abilities.
		if ( ! FileModGate::shared_code_modifications_allowed() ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/file-write',
			[
				'label'         => __( 'Write File', 'superdav-ai-agent' ),
				'description'   => __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use sd-ai-agent/file-edit instead.', 'superdav-ai-agent' ),
				'ability_class' => FileWriteAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/file-edit',
			[
				'label'         => __( 'Edit File', 'superdav-ai-agent' ),
				'description'   => __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'superdav-ai-agent' ),
				'ability_class' => FileEditAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/file-delete',
			[
				'label'         => __( 'Delete File', 'superdav-ai-agent' ),
				'description'   => __( 'Delete a file within the wp-content directory.', 'superdav-ai-agent' ),
				'ability_class' => FileDeleteAbility::class,
			]
		);
	}
}
