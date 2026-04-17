<?php

declare(strict_types=1);
/**
 * Git List ability — list all tracked files across all packages.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\GitTrackerManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Git List ability — list all tracked files across all packages.
 *
 * @since 1.1.0
 */
class GitListAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Tracked Files', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all files that have been snapshotted, with their modification status.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'unchanged', 'modified', 'deleted' ],
					'description' => 'Filter by status. Omit to list all tracked files.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'files'    => [ 'type' => 'array' ],
				'count'    => [ 'type' => 'integer' ],
				'packages' => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$status_raw = $input['status'] ?? null;
		$status     = is_string( $status_raw ) && '' !== $status_raw ? $status_raw : null;

		$rows = GitTrackerManager::get_all_tracked_files( $status );

		$files = [];
		foreach ( $rows as $row ) {
			$files[] = [
				'id'           => (int) $row->id,
				'package_slug' => $row->package_slug,
				'file_type'    => $row->file_type,
				'file_path'    => $row->file_path,
				'status'       => $row->status,
				'tracked_at'   => $row->tracked_at,
				'modified_at'  => $row->modified_at,
			];
		}

		$packages = GitTrackerManager::get_modified_packages();

		return [
			'files'    => $files,
			'count'    => count( $files ),
			'packages' => $packages,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'   => true,
				'idempotent' => true,
			],
			'show_in_rest' => true,
		];
	}
}
