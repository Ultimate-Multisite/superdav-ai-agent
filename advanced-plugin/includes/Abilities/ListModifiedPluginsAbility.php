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

class ListModifiedPluginsAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Modified Plugins', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all plugins that have been modified by the AI agent, with modification counts and download links.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => (object) [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugins' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'plugin_slug'        => [ 'type' => 'string' ],
							'modification_count' => [ 'type' => 'integer' ],
							'last_modified'      => [ 'type' => 'string' ],
							'download_url'       => [ 'type' => 'string' ],
						],
					],
				],
				'count'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$rows    = Database::get_modified_plugins();
		$plugins = [];

		foreach ( $rows as $row ) {
			$slug         = $row->plugin_slug ?? '';
			$nonce        = wp_create_nonce( 'sd_ai_agent_download_plugin_' . $slug );
			$rest_url     = rest_url( 'sd-ai-agent/v1/download-plugin/' . rawurlencode( $slug ) );
			$download_url = add_query_arg( '_wpnonce', $nonce, $rest_url );

			$plugins[] = [
				'plugin_slug'        => $slug,
				'modification_count' => (int) ( $row->modification_count ?? 0 ),
				'last_modified'      => $row->last_modified ?? '',
				'download_url'       => $download_url,
			];
		}

		return [
			'plugins' => $plugins,
			'count'   => count( $plugins ),
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
