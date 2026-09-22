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

abstract class AbstractFileAbility extends AbstractAbility {

	/**
	 * Validate and resolve a path within wp-content.
	 *
	 * @param string $relative_path Path relative to wp-content.
	 * @return string|WP_Error Full path on success, WP_Error on failure.
	 */
	protected function resolve_path( string $relative_path ) {
		$relative_path = ltrim( $relative_path, '/\\' );

		if ( empty( $relative_path ) ) {
			return new WP_Error( 'sd_ai_agent_empty_path', __( 'Path cannot be empty.', 'superdav-ai-agent' ) );
		}

		$wp_content_path = WordPressPaths::content_dir();
		$full_path       = $wp_content_path . '/' . $relative_path;

		// Long-lived PHP workers can retain stale stat and realpath entries after
		// another request creates a file or updates a symlinked content root.
		// Resolve each request against the current filesystem state.
		clearstatcache( true, $full_path );

		// Resolve real path for security check.
		$real_path = realpath( dirname( $full_path ) );
		if ( false === $real_path ) {
			// Directory doesn't exist yet, check parent chain.
			$parent = dirname( $full_path );
			while ( ! file_exists( $parent ) && $parent !== dirname( $parent ) ) {
				$parent = dirname( $parent );
			}
			$real_path = realpath( $parent );
		}

		$wp_content_real = realpath( $wp_content_path );

		if ( false === $real_path || false === $wp_content_real ) {
			return new WP_Error(
				'sd_ai_agent_path_resolve_failed',
				__( 'Cannot resolve path.', 'superdav-ai-agent' ),
				[
					'content_root_resolved' => false !== $wp_content_real,
					'parent_resolved'       => false !== $real_path,
					'reason'                => false === $wp_content_real ? 'unresolved_content_root' : 'unresolved_parent',
				]
			);
		}

		if ( ! PathCanonicalizer::path_is_inside( $real_path, $wp_content_real ) ) {
			return new WP_Error(
				'sd_ai_agent_path_traversal',
				__( 'Access denied: path is outside wp-content directory.', 'superdav-ai-agent' )
			);
		}

		$canonical = PathCanonicalizer::canonicalize_missing_path_inside( $full_path, $wp_content_real );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		return $canonical;
	}

	/**
	 * Build and record a path-safe missing-file diagnostic.
	 *
	 * The returned data deliberately contains no absolute path or file contents.
	 * It lets callers distinguish a missing leaf from a parent/root resolution
	 * failure while the event log retains only a hash of the caller input.
	 *
	 * @param string $relative_path Requested wp-content-relative path.
	 * @param string $full_path Canonical path returned by resolve_path().
	 * @return WP_Error
	 */
	protected function file_not_found_error( string $relative_path, string $full_path ): WP_Error {
		$parent_path = dirname( $full_path );
		clearstatcache( true, $full_path );

		$diagnostics = [
			'content_root_resolved' => false !== realpath( WordPressPaths::content_dir() ),
			'parent_exists'         => is_dir( $parent_path ),
			'parent_resolved'       => false !== realpath( $parent_path ),
			'reason'                => 'missing_leaf',
		];

		AgentEventLog::log(
			'file_read_not_found',
			AgentEventLog::SEVERITY_WARNING,
			[
				'ability'   => $this->name,
				'code'      => 'sd_ai_agent_file_not_found',
				'reason'    => 'missing_leaf',
				'args_hash' => AgentEventLog::payload_hash( [ 'path' => $relative_path ] ),
			]
		);

		return new WP_Error(
			'sd_ai_agent_file_not_found',
			sprintf( 'File not found: %s', $relative_path ),
			$diagnostics
		);
	}

	/**
	 * Check if a path is a PHP file.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	protected function is_php_file( string $path ): bool {
		return (bool) preg_match( '/\.php$/i', $path );
	}

	/**
	 * Split file content into logical lines without counting a trailing newline
	 * as an extra empty line.
	 *
	 * @param string $content File content.
	 * @return array<int,string>
	 */
	protected function split_file_lines( string $content ): array {
		$lines = preg_split( '/\R/', $content );
		$lines = is_array( $lines ) ? $lines : [];

		if ( '' !== $content && preg_match( '/\R\z/', $content ) && '' === end( $lines ) ) {
			array_pop( $lines );
		}

		return $lines;
	}

	/**
	 * Lint PHP content for syntax errors.
	 *
	 * Uses {@see token_get_all()} with `TOKEN_PARSE` to surface syntax errors as
	 * `\ParseError`. A scoped `set_error_handler()` converts any notices/warnings
	 * emitted by the tokeniser into `\ErrorException` so they do not leak to the
	 * site-wide error log. The handler is always restored in a `finally` block.
	 *
	 * Note: this intentionally does NOT call `error_reporting()` — toggling the
	 * global reporting level would interfere with the host site's debugging
	 * configuration. The custom error handler already intercepts emitted errors
	 * regardless of the configured reporting level.
	 *
	 * @param string $content PHP source code.
	 * @return array{valid: bool, error?: string, line?: int}
	 */
	protected function lint_php( string $content ): array {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped error handler is required to convert tokeniser notices into exceptions; restored in finally.
		set_error_handler(
			static function ( int $severity, string $message, string $file, int $line ): bool {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ErrorException constructor arguments are not output; PHPCS false positive.
				throw new \ErrorException( $message, 0, $severity, $file, $line );
			}
		);

		try {
			$tokens = token_get_all( $content, TOKEN_PARSE );
			unset( $tokens ); // Result unused — we only care about parse errors.
			return [ 'valid' => true ];
		} catch ( \ParseError | \ErrorException $e ) {
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} catch ( \Throwable $e ) {
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Check the tool permission for this ability.
	 *
	 * Returns the permission level: 'auto', 'propose', or 'disabled'.
	 * Defaults to 'auto' unless explicitly set in settings.
	 *
	 * @return string Permission level.
	 */
	protected function get_tool_permission(): string {
		$settings = Settings::instance();
		$perms    = $settings->get( 'tool_permissions' ) ?? [];

		// Check if there's an explicit permission set for this ability.
		if ( isset( $perms[ $this->name ] ) ) {
			return (string) $perms[ $this->name ];
		}

		// Default to 'auto' for all abilities.
		return 'auto';
	}

	/**
	 * Generate a unified diff for a file change.
	 *
	 * @param string $file_path The file path (relative to wp-content).
	 * @param string $new_content The new content.
	 * @return string The unified diff.
	 */
	protected function generate_diff( string $file_path, string $new_content ): string {
		// @phpstan-ignore-next-line
		$full_path = $this->resolve_path( $file_path );
		if ( is_wp_error( $full_path ) ) {
			return '';
		}

		if ( ! file_exists( $full_path ) ) {
			// New file — show all lines as additions.
			$lines = explode( "\n", $new_content );
			$diff  = "--- /dev/null\n+++ $file_path\n";
			foreach ( $lines as $line ) {
				$diff .= '+' . $line . "\n";
			}
			return $diff;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$old_content = file_get_contents( $full_path );
		if ( false === $old_content ) {
			return '';
		}

		// Use a simple line-by-line diff.
		$old_lines = explode( "\n", $old_content );
		$new_lines = explode( "\n", $new_content );

		$diff = "--- $file_path\n+++ $file_path\n";

		// Simple unified diff: show context and changes.
		$max_lines = max( count( $old_lines ), count( $new_lines ) );
		for ( $i = 0; $i < $max_lines; $i++ ) {
			$old_line = $old_lines[ $i ] ?? '';
			$new_line = $new_lines[ $i ] ?? '';

			if ( $old_line === $new_line ) {
				$diff .= ' ' . $old_line . "\n";
			} else {
				if ( isset( $old_lines[ $i ] ) ) {
					$diff .= '-' . $old_line . "\n";
				}
				if ( isset( $new_lines[ $i ] ) ) {
					$diff .= '+' . $new_line . "\n";
				}
			}
		}

		return $diff;
	}
}
