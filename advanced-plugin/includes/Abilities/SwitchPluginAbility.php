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
class SwitchPluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Switch Plugin', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Preview or perform a plugin switch atomically. Set dry_run=true to exercise the ability, inspect a proposed replacement, or verify what would change without activating or deactivating plugins. Useful for switching between competing plugins (e.g. SEO, caching, or anti-spam plugins).', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'activate'   => [
					'type'        => 'string',
					'description' => 'Slug or plugin file of the plugin to activate.',
				],
				'deactivate' => [
					'type'        => 'array',
					'description' => 'Array of slugs or plugin files to deactivate before activating the target.',
					'items'       => [ 'type' => 'string' ],
				],
				'dry_run'    => [
					'type'        => 'boolean',
					'description' => 'When true, preview the switch and return what would be activated/deactivated without changing active plugins. Use this for benchmark prompts or safety checks that say not to actually switch.',
					'default'     => false,
				],
			],
			'required'   => [ 'activate' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'           => [ 'type' => 'string' ],
				'message'          => [ 'type' => 'string' ],
				'activated'        => [ 'type' => 'string' ],
				'deactivated'      => [ 'type' => 'array' ],
				'rolled_back'      => [ 'type' => 'array' ],
				'dry_run'          => [ 'type' => 'boolean' ],
				'would_activate'   => [ 'type' => 'string' ],
				'would_deactivate' => [ 'type' => 'array' ],
				'target_installed' => [ 'type' => 'boolean' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$activate_target = isset( $input['activate'] ) ? (string) $input['activate'] : '';
		$deactivate_list = isset( $input['deactivate'] ) && is_array( $input['deactivate'] ) ? $input['deactivate'] : [];
		$dry_run         = ! empty( $input['dry_run'] );

		if ( '' === $activate_target ) {
			return new WP_Error( 'sd_ai_agent_missing_activate', __( '"activate" is required.', 'superdav-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$installed = get_plugins();

		/** @var array<string, array<string, mixed>> $installed */
		// Resolve activate target to plugin_file.
		$activate_file = $this->resolve_plugin_file( $activate_target, $installed );
		if ( $dry_run ) {
			$deactivate_files = [];
			foreach ( $deactivate_list as $target ) {
				$file = $this->resolve_plugin_file( (string) $target, $installed );
				if ( null !== $file ) {
					$deactivate_files[] = $file;
				}
			}

			return [
				'status'           => 'preview',
				'message'          => sprintf(
					/* translators: 1: plugin target, 2: count of deactivation targets */
					__( 'Dry run only: would activate "%1$s" and deactivate %2$d plugin(s). No plugins were changed.', 'superdav-ai-agent' ),
					$activate_file ?? $activate_target,
					count( $deactivate_files )
				),
				'activated'        => '',
				'deactivated'      => [],
				'rolled_back'      => [],
				'dry_run'          => true,
				'would_activate'   => $activate_file ?? $activate_target,
				'would_deactivate' => $deactivate_files,
				'target_installed' => null !== $activate_file,
			];
		}
		if ( null === $activate_file ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_installed',
				sprintf(
					/* translators: %s: plugin identifier */
					__( 'Plugin to activate not found: %s', 'superdav-ai-agent' ),
					$activate_target
				)
			);
		}

		// Resolve deactivate targets.
		$deactivate_files = [];
		foreach ( $deactivate_list as $target ) {
			$file = $this->resolve_plugin_file( (string) $target, $installed );
			if ( null !== $file ) {
				$deactivate_files[] = $file;
			}
		}

		// Deactivate the requested plugins.
		$actually_deactivated = [];
		foreach ( $deactivate_files as $file ) {
			if ( is_plugin_active( $file ) ) {
				deactivate_plugins( $file );
				$actually_deactivated[] = $file;
			}
		}

		// Activate the target.
		$result = activate_plugin( $activate_file );

		if ( is_wp_error( $result ) ) {
			// Rollback: re-activate anything we deactivated.
			$rolled_back = [];
			foreach ( $actually_deactivated as $file ) {
				$rb = activate_plugin( $file );
				if ( ! is_wp_error( $rb ) ) {
					$rolled_back[] = $file;
				}
			}

			return [
				'status'      => 'failed',
				'message'     => sprintf(
					/* translators: 1: plugin file, 2: error message, 3: rollback count */
					__( 'Failed to activate "%1$s": %2$s. Rolled back %3$d deactivation(s).', 'superdav-ai-agent' ),
					$activate_file,
					$result->get_error_message(),
					count( $rolled_back )
				),
				'activated'   => '',
				'deactivated' => [],
				'rolled_back' => $rolled_back,
			];
		}

		return [
			'status'      => 'switched',
			'message'     => sprintf(
				/* translators: 1: activated plugin, 2: count of deactivated plugins */
				__( 'Activated "%1$s" and deactivated %2$d plugin(s).', 'superdav-ai-agent' ),
				$activate_file,
				count( $actually_deactivated )
			),
			'activated'   => $activate_file,
			'deactivated' => $actually_deactivated,
			'rolled_back' => [],
		];
	}

	/**
	 * Resolve a slug or plugin_file string to the installed plugin file key.
	 *
	 * @param string                              $target    Slug or plugin file.
	 * @param array<string, array<string, mixed>> $installed Installed plugins map.
	 * @return string|null
	 */
	private function resolve_plugin_file( string $target, array $installed ): ?string {
		// Exact match (already a plugin file).
		if ( isset( $installed[ $target ] ) ) {
			return $target;
		}
		// Slug match.
		foreach ( $installed as $file => $_data ) {
			if ( strpos( $file, $target . '/' ) === 0 || $file === $target . '.php' ) {
				return $file;
			}
		}
		return null;
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
