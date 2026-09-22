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
class DeletePluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Delete Plugin', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Permanently delete an inactive WordPress plugin. Deactivate it first with deactivate-plugin if needed.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'The plugin directory slug (e.g. "akismet"). Either slug or plugin_file is required.',
				],
				'plugin_file' => [
					'type'        => 'string',
					'description' => 'The plugin file relative to the plugins directory (e.g. "akismet/akismet.php"). Either slug or plugin_file is required.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'      => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
				'plugin_file' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$slug        = isset( $input['slug'] ) ? (string) $input['slug'] : '';
		$plugin_file = isset( $input['plugin_file'] ) ? (string) $input['plugin_file'] : '';

		if ( '' === $slug && '' === $plugin_file ) {
			return new WP_Error( 'sd_ai_agent_missing_plugin', __( 'Either "slug" or "plugin_file" is required.', 'superdav-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$installed = get_plugins();

		if ( '' === $plugin_file ) {
			foreach ( $installed as $file => $_data ) {
				if ( strpos( $file, $slug . '/' ) === 0 || $file === $slug . '.php' ) {
					$plugin_file = $file;
					break;
				}
			}
		}

		if ( '' === $plugin_file || ! isset( $installed[ $plugin_file ] ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_installed',
				sprintf(
					/* translators: %s: plugin identifier */
					__( 'Plugin not installed: %s', 'superdav-ai-agent' ),
					'' !== $slug ? $slug : $plugin_file
				)
			);
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_active',
				sprintf(
					/* translators: %s: plugin file */
					__( 'Plugin "%s" is currently active. Deactivate it first before deleting.', 'superdav-ai-agent' ),
					$plugin_file
				)
			);
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = delete_plugins( [ $plugin_file ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new WP_Error( 'sd_ai_agent_delete_failed', __( 'Plugin deletion failed for unknown reason.', 'superdav-ai-agent' ) );
		}

		return [
			'status'      => 'deleted',
			'message'     => sprintf(
				/* translators: %s: plugin file */
				__( 'Plugin "%s" deleted successfully.', 'superdav-ai-agent' ),
				$plugin_file
			),
			'plugin_file' => $plugin_file,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
