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
		$installed_plugins = $this->get_installed_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			// @phpstan-ignore-next-line
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				$is_active = $this->is_plugin_active( $plugin_file );

				if ( $activate && ! $is_active ) {
					$result = $this->activate_plugin( $plugin_file );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					if ( ! $this->is_plugin_active( $plugin_file ) ) {
						return new WP_Error( 'sd_ai_agent_activation_not_verified', __( 'WordPress did not confirm that the plugin was activated.', 'superdav-ai-agent' ) );
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
		$api = $this->get_plugin_information( $slug );

		if ( is_wp_error( $api ) ) {
			return $api;
		}
		if ( ! is_object( $api ) || ! isset( $api->download_link ) || ! is_string( $api->download_link ) || '' === $api->download_link ) {
			return new WP_Error( 'sd_ai_agent_plugin_api_invalid_response', __( 'WordPress.org did not return a valid plugin download.', 'superdav-ai-agent' ) );
		}

		// Install the plugin.
		$download_link = $api->download_link;
		$installation  = $this->install_package( $download_link );
		$result        = $installation['result'];

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( true !== $result ) {
			$errors = $installation['errors'];
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new WP_Error(
				'sd_ai_agent_install_aborted',
				__( 'Installation was aborted before WordPress confirmed that the plugin files were written. Check filesystem credentials and permissions, then try again.', 'superdav-ai-agent' )
			);
		}

		$plugin_file = $this->resolve_installed_plugin_file( $slug, $installation['plugin_file'] );

		if ( '' === $plugin_file ) {
			return new WP_Error(
				'sd_ai_agent_plugin_file_not_resolved',
				/* translators: %s: plugin slug */
				sprintf( __( 'WordPress completed the installer for plugin "%s", but no matching main plugin file exists.', 'superdav-ai-agent' ), $slug )
			);
		}

		if ( $activate ) {
			$activate_result = $this->activate_plugin( $plugin_file );
			if ( is_wp_error( $activate_result ) ) {
				return [
					'status'      => 'installed',
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" installed but activation failed: %s', $slug, $activate_result->get_error_message() ),
					'plugin_file' => $plugin_file,
					'active'      => false,
				];
			}
			if ( ! $this->is_plugin_active( $plugin_file ) ) {
				return [
					'status'      => 'installed',
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" installed, but WordPress did not confirm activation.', $slug ),
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

	/**
	 * Retrieve plugin information from WordPress.org.
	 *
	 * @return object|WP_Error
	 */
	protected function get_plugin_information( string $slug ) {
		$response = plugins_api(
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

		if ( $response instanceof WP_Error || is_object( $response ) ) {
			return $response;
		}

		return new WP_Error( 'sd_ai_agent_plugin_api_invalid_response', __( 'WordPress.org returned an invalid plugin response.', 'superdav-ai-agent' ) );
	}

	/**
	 * Install a downloaded package and retain the upgrader diagnostics.
	 *
	 * WordPress may return null when filesystem credentials are declined, even
	 * though Plugin_Upgrader documents a boolean result.
	 *
	 * @return array{result:mixed,errors:WP_Error,plugin_file:mixed}
	 */
	protected function install_package( string $download_link ): array {
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $download_link );

		return [
			'result'      => $result,
			'errors'      => $skin->get_errors(),
			'plugin_file' => true === $result ? $upgrader->plugin_info() : '',
		];
	}

	/**
	 * Resolve a main plugin file only when WordPress can enumerate it on disk.
	 *
	 * @param string $slug      Requested WordPress.org plugin slug.
	 * @param mixed  $candidate Main file reported by the upgrader.
	 */
	protected function resolve_installed_plugin_file( string $slug, $candidate ): string {
		wp_clean_plugins_cache();
		$installed_plugins = $this->get_installed_plugins();

		if ( is_string( $candidate ) && $this->plugin_file_matches_slug( $candidate, $slug ) && isset( $installed_plugins[ $candidate ] ) && $this->plugin_file_exists( $candidate ) ) {
			return $candidate;
		}

		foreach ( $installed_plugins as $installed_plugin_file => $_plugin_data ) {
			if ( $this->plugin_file_matches_slug( $installed_plugin_file, $slug ) && $this->plugin_file_exists( $installed_plugin_file ) ) {
				return $installed_plugin_file;
			}
		}

		return '';
	}

	protected function plugin_file_matches_slug( string $plugin_file, string $slug ): bool {
		return strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php';
	}

	/**
	 * @return array<array>
	 */
	protected function get_installed_plugins(): array {
		return get_plugins();
	}

	protected function plugin_file_exists( string $plugin_file ): bool {
		return is_file( WP_PLUGIN_DIR . '/' . $plugin_file );
	}

	/**
	 * @return null|WP_Error
	 */
	protected function activate_plugin( string $plugin_file ) {
		return activate_plugin( $plugin_file );
	}

	protected function is_plugin_active( string $plugin_file ): bool {
		return is_plugin_active( $plugin_file );
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
