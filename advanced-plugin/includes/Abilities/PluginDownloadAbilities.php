<?php

declare(strict_types=1);
/**
 * Plugin download abilities for the AI agent.
 *
 * Provides the ability to list AI-modified plugins and generate
 * download links so admins can retrieve modified plugin zips.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\Database;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PluginDownloadAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * List AI-modified plugins.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_list_modified_plugins( array $input = [] ) {
		$ability = new ListModifiedPluginsAbility(
			'sd-ai-agent/list-modified-plugins',
			[
				'label'       => __( 'List Modified Plugins', 'superdav-ai-agent' ),
				'description' => __( 'List all plugins that have been modified by the AI agent, with modification counts and download links.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get a download URL for an AI-modified plugin.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_plugin_download_url( array $input = [] ) {
		$ability = new GetPluginDownloadUrlAbility(
			'sd-ai-agent/get-plugin-download-url',
			[
				'label'       => __( 'Get Plugin Download URL', 'superdav-ai-agent' ),
				'description' => __( 'Get a download URL for a plugin that has been modified by the AI agent.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all plugin download abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/list-modified-plugins',
			[
				'label'         => __( 'List Modified Plugins', 'superdav-ai-agent' ),
				'description'   => __( 'List all plugins that have been modified by the AI agent, with modification counts and download links.', 'superdav-ai-agent' ),
				'ability_class' => ListModifiedPluginsAbility::class,
				'show_in_rest'  => true,
			]
		);

		wp_register_ability(
			'sd-ai-agent/get-plugin-download-url',
			[
				'label'         => __( 'Get Plugin Download URL', 'superdav-ai-agent' ),
				'description'   => __( 'Get a download URL for a plugin that has been modified by the AI agent.', 'superdav-ai-agent' ),
				'ability_class' => GetPluginDownloadUrlAbility::class,
				'show_in_rest'  => true,
			]
		);
	}
}
