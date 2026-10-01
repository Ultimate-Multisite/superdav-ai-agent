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

class GetPluginDownloadUrlAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get Plugin Download URL', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Get a download URL for a plugin that has been modified by the AI agent.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugin_slug' => [
					'type'        => 'string',
					'description' => 'The plugin directory slug (e.g. "my-plugin")',
				],
			],
			'required'   => [ 'plugin_slug' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugin_slug'        => [ 'type' => 'string' ],
				'download_url'       => [ 'type' => 'string' ],
				'modification_count' => [ 'type' => 'integer' ],
				'last_modified'      => [ 'type' => 'string' ],
				'plugin_dir_exists'  => [ 'type' => 'boolean' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		// @phpstan-ignore-next-line
		$slug = sanitize_key( $input['plugin_slug'] ?? '' );

		if ( empty( $slug ) ) {
			return new WP_Error( 'sd_ai_agent_invalid_slug', __( 'Plugin slug cannot be empty.', 'superdav-ai-agent' ) );
		}

		// Verify the plugin directory exists.
		// NOTE: Using WP_PLUGIN_DIR per wp.org guidelines.
		// See: https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				sprintf(
					/* translators: %s: plugin slug */
					__( 'Plugin directory not found: %s', 'superdav-ai-agent' ),
					$slug
				)
			);
		}

		// Get modification stats.
		$rows  = Database::get_modified_files_for_plugin( $slug );
		$count = count( $rows );

		if ( 0 === $count ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_modified',
				sprintf(
					/* translators: %s: plugin slug */
					__( 'No AI modifications recorded for plugin: %s', 'superdav-ai-agent' ),
					$slug
				)
			);
		}

		$last_modified = $rows[0]->modified_at ?? '';

		$nonce        = wp_create_nonce( 'sd_ai_agent_download_plugin_' . $slug );
		$rest_url     = rest_url( 'sd-ai-agent/v1/download-plugin/' . rawurlencode( $slug ) );
		$download_url = add_query_arg( '_wpnonce', $nonce, $rest_url );

		return [
			'plugin_slug'        => $slug,
			'download_url'       => $download_url,
			'modification_count' => $count,
			'last_modified'      => $last_modified,
			'plugin_dir_exists'  => true,
		];
	}

	protected function permission_callback( $input ): bool {
		// Dual gate: per-tool cap AND core cap from CORE_CAP_MAP.
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'   => true,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}
