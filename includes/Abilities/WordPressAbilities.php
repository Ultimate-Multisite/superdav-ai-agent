<?php

declare(strict_types=1);
/**
 * WordPress management abilities for the AI agent.
 *
 * Provides core plugin/theme listing, WordPress.org plugin installation, and
 * compatibility proxies for advanced companion-plugin abilities.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\AbilityPluginRegistry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WordPressAbilities {

	/**
	 * Return a consistent disabled response when an advanced-only WordPress ability is unavailable.
	 *
	 * @param string $ability_name Ability name.
	 * @return WP_Error
	 */
	private static function advanced_plugin_required( string $ability_name ): WP_Error {
		return new WP_Error(
			'sd_ai_agent_advanced_plugin_required',
			sprintf(
				/* translators: %s: ability name */
				__( 'The %s ability is provided by the free SD AI Agent Advanced plugin. Open the SD AI account settings, then choose Install and activate.', 'superdav-ai-agent' ),
				$ability_name
			)
		);
	}

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * List all installed plugins.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_plugins( array $input = [] ) {
		$ability = new GetPluginsAbility(
			'sd-ai-agent/get-plugins',
			[
				'label'       => __( 'List Plugins', 'superdav-ai-agent' ),
				'description' => __( 'List all installed WordPress plugins with their status (active/inactive).', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * List all installed themes.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_themes( array $input = [] ) {
		$ability = new GetThemesAbility(
			'sd-ai-agent/get-themes',
			[
				'label'       => __( 'List Themes', 'superdav-ai-agent' ),
				'description' => __( 'List all installed WordPress themes with their status.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Install a plugin from WordPress.org.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_install_plugin( array $input = [] ) {
		$ability = new InstallPluginAbility(
			'sd-ai-agent/install-plugin',
			[
				'label'       => __( 'Install Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Update an installed plugin to the latest version.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_update_plugin( array $input = [] ) {
		if ( ! class_exists( UpdatePluginAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/update-plugin' );
		}

		$ability = new UpdatePluginAbility(
			'sd-ai-agent/update-plugin',
			[
				'label'       => __( 'Update Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Update an installed plugin to the latest version available from its source.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Install a plugin from any URL (GitHub releases, direct ZIPs, etc.).
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_install_plugin_from_url( array $input = [] ) {
		if ( ! class_exists( InstallPluginFromUrlAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/install-plugin-from-url' );
		}

		$ability = new InstallPluginFromUrlAbility(
			'sd-ai-agent/install-plugin-from-url',
			[
				'label'       => __( 'Install Plugin from URL', 'superdav-ai-agent' ),
				'description' => __( 'Install a plugin from any direct ZIP URL, including GitHub release assets. Optionally activate after installation.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Activate an installed plugin.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_activate_plugin( array $input = [] ) {
		if ( ! class_exists( ActivatePluginAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/activate-plugin' );
		}

		$ability = new ActivatePluginAbility(
			'sd-ai-agent/activate-plugin',
			[
				'label'       => __( 'Activate Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Activate an installed WordPress plugin by slug or plugin file.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Deactivate an active plugin.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_deactivate_plugin( array $input = [] ) {
		if ( ! class_exists( DeactivatePluginAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/deactivate-plugin' );
		}

		$ability = new DeactivatePluginAbility(
			'sd-ai-agent/deactivate-plugin',
			[
				'label'       => __( 'Deactivate Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Deactivate an active WordPress plugin by slug or plugin file.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Delete an inactive plugin.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_delete_plugin( array $input = [] ) {
		if ( ! class_exists( DeletePluginAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/delete-plugin' );
		}

		$ability = new DeletePluginAbility(
			'sd-ai-agent/delete-plugin',
			[
				'label'       => __( 'Delete Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Permanently delete an inactive WordPress plugin. The plugin must be deactivated first.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * List available plugin updates.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_list_plugin_updates( array $input = [] ) {
		$ability = new ListPluginUpdatesAbility(
			'sd-ai-agent/list-plugin-updates',
			[
				'label'       => __( 'List Plugin Updates', 'superdav-ai-agent' ),
				'description' => __( 'List all installed plugins that have updates available.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Search the WordPress.org plugin directory.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_search_plugin_directory( array $input = [] ) {
		$ability = new SearchPluginDirectoryAbility(
			'sd-ai-agent/search-plugin-directory',
			[
				'label'       => __( 'Search Plugin Directory', 'superdav-ai-agent' ),
				'description' => __( 'Search the official WordPress.org plugin directory by keyword.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Switch plugins: activate one, deactivate others, with rollback on failure.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_switch_plugin( array $input = [] ) {
		if ( ! class_exists( SwitchPluginAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/switch-plugin' );
		}

		$ability = new SwitchPluginAbility(
			'sd-ai-agent/switch-plugin',
			[
				'label'       => __( 'Switch Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Preview or perform a plugin switch: activate one plugin and optionally deactivate one or more others. Set dry_run=true to exercise or inspect the switch without changing active plugins.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Recommend plugins for a given need category.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_recommend_plugin( array $input = [] ) {
		$ability = new RecommendPluginAbility(
			'sd-ai-agent/recommend-plugin',
			[
				'label'       => __( 'Recommend Plugin', 'superdav-ai-agent' ),
				'description' => __( 'Given a need category, return ranked plugin recommendations from the curated abilities registry. Preference order: has abilities > has blocks > popular.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Call a whitelisted WordPress function by name with arguments.
	 *
	 * Returns a WP_Error when Superdav AI Agent Advanced is not active.
	 *
	 * @param array<string,mixed> $input Input args (function, args).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_run_php( array $input = [] ) {
		if ( ! class_exists( RunPhpAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/run-php' );
		}

		$ability = new RunPhpAbility(
			'sd-ai-agent/run-php',
			[
				'label'       => __( 'Call WordPress Function', 'superdav-ai-agent' ),
				'description' => __( 'Low-level fallback: call a whitelisted WordPress function directly. Use ONLY when no dedicated ability exists. For posts, users, options, plugins, themes, and other common operations, call `sd-ai-agent/ability-search` first — dedicated abilities have typed schemas and better error recovery.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all WordPress management abilities.
	 *
	 * Core registers read/discovery and WordPress.org-directory install
	 * plugin abilities. Advanced plugin state changes, arbitrary ZIP installs,
	 * and run-php are registered by Superdav AI Agent Advanced.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// ─── Always-registered: read-only / discovery / WP.org-only install ────

		wp_register_ability(
			'sd-ai-agent/get-plugins',
			[
				'label'         => __( 'List Plugins', 'superdav-ai-agent' ),
				'description'   => __( 'List all installed WordPress plugins with their status (active/inactive).', 'superdav-ai-agent' ),
				'ability_class' => GetPluginsAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/get-themes',
			[
				'label'         => __( 'List Themes', 'superdav-ai-agent' ),
				'description'   => __( 'List all installed WordPress themes with their status.', 'superdav-ai-agent' ),
				'ability_class' => GetThemesAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/install-plugin',
			[
				'label'         => __( 'Install Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'superdav-ai-agent' ),
				'ability_class' => InstallPluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/recommend-plugin',
			[
				'label'         => __( 'Recommend Plugin', 'superdav-ai-agent' ),
				'description'   => __( 'Given a need category, return ranked plugin recommendations from the curated abilities registry. Preference order: has abilities > has blocks > popular.', 'superdav-ai-agent' ),
				'ability_class' => RecommendPluginAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/list-plugin-updates',
			[
				'label'         => __( 'List Plugin Updates', 'superdav-ai-agent' ),
				'description'   => __( 'List all installed plugins that have updates available.', 'superdav-ai-agent' ),
				'ability_class' => ListPluginUpdatesAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/search-plugin-directory',
			[
				'label'         => __( 'Search Plugin Directory', 'superdav-ai-agent' ),
				'description'   => __( 'Search the official WordPress.org plugin directory by keyword.', 'superdav-ai-agent' ),
				'ability_class' => SearchPluginDirectoryAbility::class,
			]
		);

		// Advanced-only plugin state changes, arbitrary ZIP installs, and
		// low-level run-php are registered by Superdav AI Agent Advanced.
		// Core keeps only read/discovery and WordPress.org-directory install
		// plugin abilities for WordPress.org distribution.
	}
}
