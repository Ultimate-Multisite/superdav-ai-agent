<?php

declare(strict_types=1);
/**
 * Update Plugin (Sandboxed) ability — safe plugin code updates with rollback.
 *
 * @package GratisAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\PluginBuilder\PluginUpdater;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update Plugin (Sandboxed) ability.
 *
 * @since 1.5.0
 */
class UpdatePluginSandboxedAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update Plugin (Sandboxed)', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Update a running plugin with new code: backup → stage → sandbox test → swap. Rolls back automatically on failure.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'  => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name under wp-content/plugins/).',
				],
				'files' => [
					'type'        => 'object',
					'description' => 'Map of relative file paths to new PHP source code.',
				],
			],
			'required'   => [ 'slug', 'files' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'swapped'     => [ 'type' => 'boolean' ],
				'plugin_file' => [ 'type' => 'string' ],
				'was_active'  => [ 'type' => 'boolean' ],
				'backup_dir'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$slug = (string) ( $input['slug'] ?? '' );

		// Coerce to array<string,string>: PluginUpdater::update() requires that shape.
		$raw_files = is_array( $input['files'] ?? null ) ? $input['files'] : [];
		/** @var array<string,string> $files */
		$files = array_filter(
			$raw_files,
			static fn( $v ) => is_string( $v )
		);

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}
		if ( empty( $files ) ) {
			return new WP_Error( 'gratis_ai_agent_no_files', __( 'files must not be empty.', 'gratis-ai-agent' ) );
		}

		return ( new PluginUpdater() )->update( $slug, $files );
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
