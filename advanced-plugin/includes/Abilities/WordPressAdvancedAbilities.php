<?php

declare(strict_types=1);
/**
 * Advanced WordPress management abilities for Superdav AI Agent.
 *
 * @package SdAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers advanced WordPress management abilities supplied by the companion plugin.
 */
class WordPressAdvancedAbilities {

	/**
	 * Register advanced WordPress management abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/run-php',
			[
				'label'         => __( 'Call WordPress Function', 'superdav-ai-agent' ),
				'description'   => __( 'Low-level fallback: call a whitelisted WordPress function directly. Use ONLY when no dedicated ability exists for the task. For posts (use `sd-ai-agent/create-post`), users, options, plugins, themes, and other common operations, call `sd-ai-agent/ability-search` first to find a purpose-built tool — dedicated abilities have typed schemas and better error recovery than passing positional args through `run-php`.', 'superdav-ai-agent' ),
				'ability_class' => RunPhpAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/update-plugin',
			[
				'label'         => __( 'Update Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Update an installed plugin to the latest version available from its source.', 'superdav-ai-agent' ),
				'ability_class' => UpdatePluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/activate-plugin',
			[
				'label'         => __( 'Activate Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Activate an installed WordPress plugin by slug or plugin file.', 'superdav-ai-agent' ),
				'ability_class' => ActivatePluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/deactivate-plugin',
			[
				'label'         => __( 'Deactivate Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Deactivate an active WordPress plugin by slug or plugin file.', 'superdav-ai-agent' ),
				'ability_class' => DeactivatePluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/delete-plugin',
			[
				'label'         => __( 'Delete Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Permanently delete an inactive WordPress plugin. The plugin must be deactivated first.', 'superdav-ai-agent' ),
				'ability_class' => DeletePluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/switch-plugin',
			[
				'label'         => __( 'Switch Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Preview or perform a plugin switch: activate one plugin and optionally deactivate one or more others. Set dry_run=true to exercise or inspect the switch without changing active plugins.', 'superdav-ai-agent' ),
				'ability_class' => SwitchPluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/install-plugin-from-url',
			[
				'label'         => __( 'Install Plugin from URL', 'superdav-ai-agent' ),
				'description'   => __( 'Install a plugin from any direct ZIP URL, including GitHub release assets. Optionally activate after installation.', 'superdav-ai-agent' ),
				'ability_class' => InstallPluginFromUrlAbility::class,
			]
		);
	}
}
