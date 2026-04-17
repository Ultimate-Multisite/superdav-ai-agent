<?php

declare(strict_types=1);
/**
 * Git Snapshot ability — explicitly snapshot a file.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\GitTrackerManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Git Snapshot ability — explicitly snapshot a file.
 *
 * @since 1.1.0
 */
class GitSnapshotAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Snapshot File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Explicitly snapshot a file before editing. Note: FileAbilities automatically snapshots files on write/edit — use this for manual control.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Absolute filesystem path to the file to snapshot.',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'    => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path = $input['path'] ?? null;

		if ( ! is_string( $path ) || '' === $path ) {
			return new WP_Error( 'gratis_ai_agent_invalid_path', __( 'Path must be a non-empty string.', 'gratis-ai-agent' ) );
		}

		$result = GitTrackerManager::snapshot_before_modify( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'path'    => $path,
			'message' => sprintf(
				/* translators: %s: file path */
				__( 'File snapshotted successfully: %s', 'gratis-ai-agent' ),
				$path
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => false,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}
