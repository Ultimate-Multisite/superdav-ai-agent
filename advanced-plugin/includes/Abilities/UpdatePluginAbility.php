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
class UpdatePluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update Plugin', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Update an installed plugin to the latest version available from its source.', 'superdav-ai-agent' );
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
				'from'        => [ 'type' => 'string' ],
				'to'          => [ 'type' => 'string' ],
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
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$installed = get_plugins();

		// Resolve plugin_file from slug if needed.
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

		$from_version = isset( $installed[ $plugin_file ]['Version'] ) ? (string) $installed[ $plugin_file ]['Version'] : '';

		// Force a fresh update check so wp_update_plugins has current data.
		wp_clean_plugins_cache( false );
		wp_update_plugins();

		$updates    = get_site_transient( 'update_plugins' );
		$has_update = is_object( $updates ) && isset( $updates->response[ $plugin_file ] );

		if ( ! $has_update ) {
			return [
				'status'      => 'up_to_date',
				'message'     => sprintf(
					/* translators: 1: plugin file, 2: version */
					__( 'Plugin "%1$s" is already at the latest version (%2$s).', 'superdav-ai-agent' ),
					$plugin_file,
					$from_version
				),
				'plugin_file' => $plugin_file,
				'from'        => $from_version,
				'to'          => $from_version,
			];
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			$errors = $skin->get_errors();
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new WP_Error( 'sd_ai_agent_update_failed', __( 'Plugin update failed for unknown reason.', 'superdav-ai-agent' ) );
		}

		// Re-read version post-upgrade.
		wp_clean_plugins_cache( false );
		$installed_after = get_plugins();
		$to_version      = isset( $installed_after[ $plugin_file ]['Version'] ) ? (string) $installed_after[ $plugin_file ]['Version'] : '';

		return [
			'status'      => 'updated',
			'message'     => sprintf(
				/* translators: 1: plugin file, 2: old version, 3: new version */
				__( 'Plugin "%1$s" updated from %2$s to %3$s.', 'superdav-ai-agent' ),
				$plugin_file,
				$from_version,
				$to_version
			),
			'plugin_file' => $plugin_file,
			'from'        => $from_version,
			'to'          => $to_version,
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
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
