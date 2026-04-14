<?php

declare(strict_types=1);
/**
 * Hook Scanner — scans installed plugins and themes for WordPress hooks
 * (actions and filters) to enable extension-plugin generation.
 *
 * @package GratisAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\PluginBuilder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HookScanner — discovers apply_filters() and do_action() calls in plugin/theme PHP files.
 *
 * Enables the AI agent to build extension/addon plugins that hook into existing plugins.
 *
 * @since 1.5.0
 */
class HookScanner {

	/**
	 * Regex pattern to match WordPress hook-firing functions with a string hook name.
	 *
	 * Captures:
	 *   Group 1 — function name (do_action, apply_filters, etc.)
	 *   Group 2 — hook name string literal
	 *
	 * Skips dynamic/variable hook names (e.g. do_action( $hook )).
	 */
	private const HOOK_PATTERN = '/\b(do_action|do_action_ref_array|apply_filters|apply_filters_ref_array)\s*\(\s*[\'"]([^\'"]+)[\'"]/';

	/**
	 * Directory names to skip during recursive traversal.
	 */
	private const SKIP_DIRS = [ 'vendor', 'node_modules', '.git', 'tests' ];

	/**
	 * Maximum number of PHP files to scan per directory tree.
	 */
	private const FILE_LIMIT = 500;

	/**
	 * Transient cache TTL in seconds (1 hour).
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Number of context lines to capture on each side of a hook call.
	 */
	private const CONTEXT_LINES = 2;

	// ─── Public API ──────────────────────────────────────────────────────

	/**
	 * Scan an installed plugin for all do_action() and apply_filters() calls.
	 *
	 * Results are cached in a WordPress transient keyed by slug and plugin version.
	 * Pass $force_refresh = true to bypass the cache.
	 *
	 * @since 1.5.0
	 *
	 * @param string $slug          Plugin slug (directory name under wp-content/plugins/).
	 * @param bool   $force_refresh Whether to bypass the transient cache.
	 * @return array<mixed,mixed>|WP_Error
	 */
	public static function scan_plugin( string $slug, bool $force_refresh = false ): array|WP_Error {
		$slug       = sanitize_title( $slug );
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';

		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_plugin_not_found',
				/* translators: %s: plugin slug */
				sprintf( __( 'Plugin not found: %s', 'gratis-ai-agent' ), $slug )
			);
		}

		$cache_key = self::transient_key( 'plugin', $slug, $plugin_dir );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = self::scan_directory( $plugin_dir, $slug, 'plugin' );
		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Scan an installed theme for all do_action() and apply_filters() calls.
	 *
	 * Results are cached in a WordPress transient keyed by slug and theme version.
	 * Pass $force_refresh = true to bypass the cache.
	 *
	 * @since 1.5.0
	 *
	 * @param string $slug          Theme slug (directory name under wp-content/themes/).
	 * @param bool   $force_refresh Whether to bypass the transient cache.
	 * @return array<mixed,mixed>|WP_Error
	 */
	public static function scan_theme( string $slug, bool $force_refresh = false ): array|WP_Error {
		$slug      = sanitize_title( $slug );
		$theme_dir = WP_CONTENT_DIR . '/themes/' . $slug . '/';

		if ( ! is_dir( $theme_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_theme_not_found',
				/* translators: %s: theme slug */
				sprintf( __( 'Theme not found: %s', 'gratis-ai-agent' ), $slug )
			);
		}

		$cache_key = self::transient_key( 'theme', $slug, $theme_dir );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = self::scan_directory( $theme_dir, $slug, 'theme' );
		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Scan a single PHP file for WordPress hook calls.
	 *
	 * Returns a flat array of hook records. Does not cache results.
	 *
	 * @since 1.5.0
	 *
	 * @param string $file_path Absolute path to the PHP file.
	 * @return list<array<string,mixed>>
	 */
	public static function scan_file( string $file_path ): array {
		$contents = @file_get_contents( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return [];
		}

		return self::extract_hooks_from_source( $contents, $file_path, dirname( $file_path ) . '/' );
	}

	// ─── Private helpers ─────────────────────────────────────────────────

	/**
	 * Recursively scan a directory for hooks and build the result array.
	 *
	 * @param string $dir  Absolute path to the directory to scan.
	 * @param string $slug Plugin/theme slug for the result envelope.
	 * @param string $type Either 'plugin' or 'theme'.
	 * @return array<string,mixed>
	 */
	private static function scan_directory( string $dir, string $slug, string $type ): array {
		$hooks     = [];
		$php_files = self::find_php_files( $dir );

		foreach ( $php_files as $file ) {
			$contents = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $contents ) {
				continue;
			}

			$file_hooks = self::extract_hooks_from_source( $contents, $file, $dir );
			$hooks      = array_merge( $hooks, $file_hooks );
		}

		// Sort by file then line number for deterministic output.
		usort(
			$hooks,
			static function ( array $a, array $b ): int {
				$file_cmp = strcmp( $a['file'], $b['file'] );
				if ( 0 !== $file_cmp ) {
					return $file_cmp;
				}
				return $a['line'] <=> $b['line'];
			}
		);

		$total_actions = 0;
		$total_filters = 0;
		foreach ( $hooks as $hook ) {
			if ( 'action' === $hook['type'] ) {
				++$total_actions;
			} else {
				++$total_filters;
			}
		}

		return [
			'slug'          => $slug,
			'type'          => $type,
			'hooks'         => $hooks,
			'total_hooks'   => count( $hooks ),
			'total_actions' => $total_actions,
			'total_filters' => $total_filters,
		];
	}

	/**
	 * Extract hook calls from PHP source code.
	 *
	 * Only captures calls with string-literal hook names (dynamic variable names
	 * are skipped). For each match, the surrounding context (CONTEXT_LINES lines
	 * on each side) and the parameter count (additional arguments after the hook
	 * name) are captured.
	 *
	 * @param string $source   PHP source code.
	 * @param string $file     Absolute file path for reporting.
	 * @param string $base_dir Base directory used to compute relative paths.
	 * @return list<array<string,mixed>>
	 */
	private static function extract_hooks_from_source( string $source, string $file, string $base_dir ): array {
		$hooks         = [];
		$relative_file = ltrim( str_replace( $base_dir, '', $file ), '/\\' );
		$lines         = explode( "\n", $source );
		$line_count    = count( $lines );

		$function_type_map = [
			'do_action'               => 'action',
			'do_action_ref_array'     => 'action',
			'apply_filters'           => 'filter',
			'apply_filters_ref_array' => 'filter',
		];

		foreach ( $lines as $line_index => $line_content ) {
			if ( ! preg_match_all( self::HOOK_PATTERN, $line_content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			foreach ( $matches as $match ) {
				$func      = $match[1][0];
				$hook_name = $match[2][0];
				$hook_type = $function_type_map[ $func ];

				// Capture surrounding context lines.
				$ctx_start = max( 0, $line_index - self::CONTEXT_LINES );
				$ctx_end   = min( $line_count - 1, $line_index + self::CONTEXT_LINES );
				$context   = implode( "\n", array_slice( $lines, $ctx_start, $ctx_end - $ctx_start + 1 ) );

				// Count additional parameters after the hook name.
				$param_count = self::count_params_after_hook_name( $line_content, (int) $match[0][1] );

				$hooks[] = [
					'name'        => $hook_name,
					'type'        => $hook_type,
					'file'        => $relative_file,
					'line'        => $line_index + 1,
					'context'     => $context,
					'param_count' => $param_count,
				];
			}
		}

		return $hooks;
	}

	/**
	 * Count the number of additional parameters passed after the hook name argument.
	 *
	 * Parses the text following the hook-name string literal in the same function
	 * call and counts top-level commas (i.e., not nested inside parentheses or
	 * brackets). This is a best-effort line-level approximation.
	 *
	 * @param string $line        The source line containing the function call.
	 * @param int    $match_start Byte offset of the full match within $line.
	 * @return int Number of additional arguments (0 if none or unparseable).
	 */
	private static function count_params_after_hook_name( string $line, int $match_start ): int {
		// Find the closing quote of the hook name.
		$after_match = substr( $line, $match_start );
		// Find the position just after the hook name closing quote.
		if ( ! preg_match( '/[\'"]([^\'"]+)[\'"]/', $after_match, $qm, PREG_OFFSET_CAPTURE ) ) {
			return 0;
		}

		$after_hook = substr( $after_match, $qm[0][1] + strlen( $qm[0][0] ) );

		// Count top-level commas until closing parenthesis.
		$depth  = 0;
		$commas = 0;
		$len    = strlen( $after_hook );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $after_hook[ $i ];
			if ( '(' === $ch || '[' === $ch || '{' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch || ']' === $ch || '}' === $ch ) {
				if ( 0 === $depth ) {
					// Closing paren of the hook call itself.
					break;
				}
				--$depth;
			} elseif ( ',' === $ch && 0 === $depth ) {
				++$commas;
			}
		}

		return $commas;
	}

	/**
	 * Recursively find all PHP files under $dir, skipping excluded directories.
	 *
	 * Returns at most FILE_LIMIT paths.
	 *
	 * @param string $dir Absolute path to the root directory.
	 * @return list<string>
	 */
	private static function find_php_files( string $dir ): array {
		$files    = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				static function ( \SplFileInfo $file ): bool {
					if ( $file->isDir() ) {
						return ! in_array( $file->getBasename(), HookScanner::SKIP_DIRS, true );
					}
					return true;
				}
			)
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$real_path = $file->getRealPath();
				if ( is_string( $real_path ) && '' !== $real_path ) {
					$files[] = $real_path;
					if ( count( $files ) >= self::FILE_LIMIT ) {
						break;
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Build a deterministic transient cache key for a plugin or theme scan.
	 *
	 * The key incorporates the slug and a hash of the directory mtime so that
	 * modifying plugin files invalidates the cache automatically.
	 *
	 * @param string $kind      Either 'plugin' or 'theme'.
	 * @param string $slug      Plugin/theme slug.
	 * @param string $dir       Absolute directory path.
	 * @return string Transient key (max 172 chars to stay within WP 45-char recommended limit when hashed).
	 */
	private static function transient_key( string $kind, string $slug, string $dir ): string {
		$mtime = @filemtime( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$hash  = substr( md5( $slug . '|' . $kind . '|' . (string) $mtime ), 0, 12 );
		return 'gratis_ai_hookscan_' . $kind . '_' . sanitize_key( $slug ) . '_' . $hash;
	}
}
