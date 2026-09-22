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

class ListPluginUpdatesAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Plugin Updates', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all installed plugins that have updates available. Forces a fresh check against the update API.', 'superdav-ai-agent' );
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
				'updates' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'plugin_file'     => [ 'type' => 'string' ],
							'name'            => [ 'type' => 'string' ],
							'current_version' => [ 'type' => 'string' ],
							'new_version'     => [ 'type' => 'string' ],
							'update_url'      => [ 'type' => 'string' ],
						],
					],
				],
				'count'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed>|null $input */
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		// Force a fresh update check.
		wp_clean_plugins_cache( false );
		wp_update_plugins();

		$installed = get_plugins();
		$updates   = get_site_transient( 'update_plugins' );
		$response  = is_object( $updates ) && isset( $updates->response ) ? (array) $updates->response : [];

		$result = [];
		foreach ( $response as $plugin_file => $update_data ) {
			$plugin_file = (string) $plugin_file;
			$name        = isset( $installed[ $plugin_file ]['Name'] ) ? (string) $installed[ $plugin_file ]['Name'] : $plugin_file;
			$current     = isset( $installed[ $plugin_file ]['Version'] ) ? (string) $installed[ $plugin_file ]['Version'] : '';
			$new_version = is_object( $update_data ) && isset( $update_data->new_version ) ? (string) $update_data->new_version : '';
			$update_url  = is_object( $update_data ) && isset( $update_data->package ) ? (string) $update_data->package : '';

			$result[] = [
				'plugin_file'     => $plugin_file,
				'name'            => $name,
				'current_version' => $current,
				'new_version'     => $new_version,
				'update_url'      => $update_url,
			];
		}

		return [
			'updates' => $result,
			'count'   => count( $result ),
		];
	}

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
