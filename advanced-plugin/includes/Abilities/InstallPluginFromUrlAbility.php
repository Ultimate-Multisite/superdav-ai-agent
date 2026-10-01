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
class InstallPluginFromUrlAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Install Plugin from URL', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Install a plugin from any direct ZIP URL, including GitHub release assets (e.g. https://github.com/owner/repo/releases/latest/download/plugin.zip). Optionally activate after installation.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'url'      => [
					'type'        => 'string',
					'description' => 'Direct URL to the plugin ZIP file. GitHub example: https://github.com/bjornfix/mcp-expose-abilities/releases/latest/download/mcp-expose-abilities.zip',
				],
				'activate' => [
					'type'        => 'boolean',
					'description' => 'Whether to activate the plugin after installation (default: false).',
				],
			],
			'required'   => [ 'url' ],
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
		$url      = isset( $input['url'] ) ? (string) $input['url'] : '';
		$activate = (bool) ( $input['activate'] ?? false );

		if ( '' === $url ) {
			return new WP_Error( 'sd_ai_agent_empty_url', __( 'A plugin ZIP URL is required.', 'superdav-ai-agent' ) );
		}

		// Basic URL validation — must be http(s).
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_url',
				__( 'URL must begin with http:// or https://.', 'superdav-ai-agent' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $url );

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
					'message'     => sprintf(
						/* translators: 1: plugin file, 2: error message */
						__( 'Plugin "%1$s" installed from URL but activation failed: %2$s', 'superdav-ai-agent' ),
						$plugin_file,
						$activate_result->get_error_message()
					),
					'plugin_file' => (string) $plugin_file,
					'active'      => false,
				];
			}
			return [
				'status'      => 'installed_and_activated',
				'message'     => sprintf(
					/* translators: %s: plugin file */
					__( 'Plugin "%s" installed from URL and activated successfully.', 'superdav-ai-agent' ),
					$plugin_file
				),
				'plugin_file' => (string) $plugin_file,
				'active'      => true,
			];
		}

		return [
			'status'      => 'installed',
			'message'     => sprintf(
				/* translators: %s: plugin file */
				__( 'Plugin "%s" installed from URL successfully.', 'superdav-ai-agent' ),
				$plugin_file ?? ''
			),
			'plugin_file' => (string) ( $plugin_file ?? '' ),
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
