<?php

declare(strict_types=1);
/**
 * File operation abilities for the AI agent.
 *
 * Provides read, write, edit, delete, list, and search operations
 * scoped to the wp-content directory with path traversal protection.
 *
 * Modelled after akirk/ai-assistant's file tools with WordPress Abilities API integration.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\AgentEventLog;
use SdAiAgent\Core\Filesystem\PathCanonicalizer;
use SdAiAgent\Core\Settings;
use SdAiAgent\Core\WordPressPaths;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileReadAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Read File', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Read the contents of a file within the wp-content directory, optionally limited to a line range.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'       => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content (e.g., "plugins/my-plugin/file.php")',
				],
				'start_line' => [
					'type'        => 'integer',
					'description' => 'Optional 1-based first line to read.',
				],
				'end_line'   => [
					'type'        => 'integer',
					'description' => 'Optional 1-based final line to read.',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'        => [ 'type' => 'string' ],
				'content'     => [ 'type' => 'string' ],
				'size'        => [ 'type' => 'integer' ],
				'modified'    => [ 'type' => 'string' ],
				'start_line'  => [ 'type' => 'integer' ],
				'end_line'    => [ 'type' => 'integer' ],
				'total_lines' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path = $input['path'] ?? '';
		// @phpstan-ignore-next-line
		$full_path = $this->resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		clearstatcache( true, $full_path );
		if ( ! file_exists( $full_path ) ) {
			return $this->file_not_found_error( $path, $full_path );
		}

		if ( ! is_readable( $full_path ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_not_readable', sprintf( 'File not readable: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file, not remote URL.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		$range_requested = array_key_exists( 'start_line', $input ) || array_key_exists( 'end_line', $input );
		if ( $range_requested ) {
			$start_line = isset( $input['start_line'] ) ? (int) $input['start_line'] : 1;
			$end_line   = isset( $input['end_line'] ) ? (int) $input['end_line'] : null;

			if ( null !== $end_line && $end_line < $start_line ) {
				return new WP_Error(
					'sd_ai_agent_invalid_line_range',
					__( 'Invalid line range: end_line must be greater than or equal to start_line.', 'superdav-ai-agent' )
				);
			}

			$lines       = $this->split_file_lines( $content );
			$total_lines = count( $lines );
			$start_line  = max( 1, $start_line );
			$end_line    = null === $end_line ? $total_lines : max( 1, $end_line );
			if ( $total_lines > 0 ) {
				$start_line = min( $start_line, $total_lines );
				$end_line   = min( $end_line, $total_lines );
			}

			$content = implode( "\n", array_slice( $lines ?: [], $start_line - 1, max( 0, $end_line - $start_line + 1 ) ) );

			return [
				'path'        => $path,
				'content'     => $content,
				'size'        => filesize( $full_path ),
				'modified'    => gmdate( 'Y-m-d H:i:s', (int) filemtime( $full_path ) ),
				'start_line'  => $start_line,
				'end_line'    => $end_line,
				'total_lines' => $total_lines,
			];
		}

		return [
			'path'     => $path,
			'content'  => $content,
			'size'     => filesize( $full_path ),
			'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $full_path ) ),
		];
	}

	protected function permission_callback( $input ): bool {
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
