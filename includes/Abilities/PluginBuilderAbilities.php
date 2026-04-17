<?php

declare(strict_types=1);
/**
 * Plugin Builder abilities — AI-powered plugin generation, sandboxing, and activation.
 *
 * Registers six abilities via the WordPress 7.0+ Abilities API:
 *   - gratis-ai-agent/generate-plugin
 *   - gratis-ai-agent/sandbox-test-plugin
 *   - gratis-ai-agent/sandbox-activate-plugin
 *   - gratis-ai-agent/update-plugin-sandboxed
 *   - gratis-ai-agent/scan-plugin-hooks
 *   - gratis-ai-agent/scan-theme-hooks
 *
 * Individual ability classes live in their own PSR-4 files in this directory:
 *   - GeneratePluginAbility.php
 *   - SandboxTestPluginAbility.php
 *   - SandboxActivatePluginAbility.php
 *   - UpdatePluginSandboxedAbility.php
 *   - ScanPluginHooksAbility.php
 *   - ScanThemeHooksAbility.php
 *
 * @package GratisAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginBuilderAbilities — static registry and proxy class.
 *
 * @since 1.5.0
 */
class PluginBuilderAbilities {

	/**
	 * Register all plugin builder abilities with the WordPress Abilities API.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/generate-plugin',
			[
				'label'         => __( 'Generate Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Generate a WordPress plugin from a natural-language description. Returns the implementation plan and complete PHP source code.', 'gratis-ai-agent' ),
				'ability_class' => GeneratePluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/sandbox-test-plugin',
			[
				'label'         => __( 'Sandbox Test Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Run layers 1 and 2 of the sandbox safety check against a plugin: PHP syntax validation and isolated subprocess include test.', 'gratis-ai-agent' ),
				'ability_class' => SandboxTestPluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/sandbox-activate-plugin',
			[
				'label'         => __( 'Sandbox Activate Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Activate a plugin using layer 3 transactional safety: error handler + shutdown guard. Auto-deactivates on fatal error.', 'gratis-ai-agent' ),
				'ability_class' => SandboxActivatePluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/update-plugin-sandboxed',
			[
				'label'         => __( 'Update Plugin (Sandboxed)', 'gratis-ai-agent' ),
				'description'   => __( 'Update a running plugin with new code: backup → stage → sandbox test → swap. Rolls back automatically on failure.', 'gratis-ai-agent' ),
				'ability_class' => UpdatePluginSandboxedAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/scan-plugin-hooks',
			[
				'label'         => __( 'Scan Plugin Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed plugin for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanPluginHooksAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/scan-theme-hooks',
			[
				'label'         => __( 'Scan Theme Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed theme for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanThemeHooksAbility::class,
			]
		);
	}
}
