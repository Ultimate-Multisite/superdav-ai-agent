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

class InstallPluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Install Plugin', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'     => [
					'type'        => 'string',
					'description' => 'The plugin slug from wordpress.org (e.g., "akismet", "contact-form-7")',
				],
				'activate' => [
					'type'        => 'boolean',
					'description' => 'Whether to activate the plugin after installation (default: false)',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'      => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
				'plugin_file' => [ 'type' => 'string' ],
				'active'      => [ 'type' => 'boolean' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$slug     = $input['slug'] ?? '';
		$activate = (bool) ( $input['activate'] ?? false );

		if ( empty( $slug ) ) {
			return new WP_Error( 'sd_ai_agent_empty_slug', __( 'Plugin slug is required.', 'superdav-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Check if already installed.
		$installed_plugins = get_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			// @phpstan-ignore-next-line
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				$is_active = is_plugin_active( $plugin_file );

				if ( $activate && ! $is_active ) {
					$result = activate_plugin( $plugin_file );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return [
						'status'      => 'activated',
						// @phpstan-ignore-next-line
						'message'     => sprintf( 'Plugin "%s" was already installed and has been activated.', $slug ),
						'plugin_file' => $plugin_file,
						'active'      => true,
					];
				}

				return [
					'status'      => 'already_installed',
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" is already installed%s.', $slug, $is_active ? ' and active' : '' ),
					'plugin_file' => $plugin_file,
					'active'      => $is_active,
				];
			}
		}

		// Get plugin info from wordpress.org.
		$api = plugins_api(
			'plugin_information',
			// @phpstan-ignore-next-line
			[
				'slug'   => $slug,
				'fields' => [
					'sections'          => false,
					'short_description' => true,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		// Install the plugin.
		$skin          = new \WP_Ajax_Upgrader_Skin();
		$upgrader      = new \Plugin_Upgrader( $skin );
		$download_link = is_object( $api ) ? $api->download_link : '';
		$result        = $upgrader->install( $download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			$errors = $skin->get_errors();
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new WP_Error( 'sd_ai_agent_install_failed', __( 'Installation failed for unknown reason.', 'superdav-ai-agent' ) );
		}

		$plugin_file = $upgrader->plugin_info();

		if ( $activate && $plugin_file ) {
			$activate_result = activate_plugin( $plugin_file );
			if ( is_wp_error( $activate_result ) ) {
				return [
					'status'      => 'installed',
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" installed but activation failed: %s', $slug, $activate_result->get_error_message() ),
					'plugin_file' => $plugin_file,
					'active'      => false,
				];
			}
			return [
				'status'      => 'installed_and_activated',
				// @phpstan-ignore-next-line
				'message'     => sprintf( 'Plugin "%s" installed and activated successfully.', $slug ),
				'plugin_file' => $plugin_file,
				'active'      => true,
			];
		}

		return [
			'status'      => 'installed',
			// @phpstan-ignore-next-line
			'message'     => sprintf( 'Plugin "%s" installed successfully.', $slug ),
			'plugin_file' => $plugin_file ?: '',
			'active'      => false,
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
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
