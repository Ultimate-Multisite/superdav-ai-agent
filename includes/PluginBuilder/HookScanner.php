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
 * HookScanner — discovers hooks in plugin/theme PHP files.
 *
 * @since 1.5.0
 */
class HookScanner {

	/**
	 * Scan an installed plugin for all add_action() and add_filter() calls,
	 * and also do_action() / apply_filters() to discover hookable points.
	 *
	 * @param string $plugin_slug Plugin slug (directory name under wp-content/plugins/).
	 * @return array{hooks: list<array{type: string, name: string, file: string, line: int}>}|\WP_Error
	 */
	public static function scan_plugin( string $plugin_slug ): array|\WP_Error {
		$plugin_slug = sanitize_title( $plugin_slug );
		$plugin_dir  = WP_CONTENT_DIR . '/plugins/' . $plugin_slug . '/';

		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_plugin_not_found',
				/* translators: %s: plugin slug */
				sprintf( __( 'Plugin not found: %s', 'gratis-ai-agent' ), $plugin_slug )
			);
		}

		return self::scan_directory( $plugin_dir );
	}

	/**
	 * Scan an installed theme for all hooks.
	 *
	 * @param string $theme_slug Theme slug (directory name under wp-content/themes/).
	 * @return array{hooks: list<array{type: string, name: string, file: string, line: int}>}|\WP_Error
	 */
	public static function scan_theme( string $theme_slug ): array|\WP_Error {
		$theme_slug = sanitize_title( $theme_slug );
		$theme_dir  = WP_CONTENT_DIR . '/themes/' . $theme_slug . '/';

		if ( ! is_dir( $theme_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_theme_not_found',
				/* translators: %s: theme slug */
				sprintf( __( 'Theme not found: %s', 'gratis-ai-agent' ), $theme_slug )
			);
		}

		return self::scan_directory( $theme_dir );
	}

	/**
	 * Scan a directory for WordPress hooks.
	 *
	 * @param string $dir Absolute path to the directory to scan.
	 * @return array{hooks: list<array{type: string, name: string, file: string, line: int}>}
	 */
	private static function scan_directory( string $dir ): array {
		$hooks     = [];
		$php_files = self::find_php_files( $dir );

		foreach ( $php_files as $file ) {
			$contents = file_get_contents( $file );
			if ( false === $contents ) {
				continue;
			}

			$file_hooks = self::extract_hooks_from_source( $contents, $file, $dir );
			$hooks      = array_merge( $hooks, $file_hooks );
		}

		// Sort by file then line number.
		usort(
			$hooks,
			static function ( array $a, array $b ) {
				$file_cmp = strcmp( $a['file'], $b['file'] );
				if ( 0 !== $file_cmp ) {
					return $file_cmp;
				}
				return $a['line'] <=> $b['line'];
			}
		);

		return [ 'hooks' => $hooks ];
	}

	/**
	 * Extract hook calls from PHP source code.
	 *
	 * Matches:
	 *   - do_action( 'hook-name', ... )
	 *   - apply_filters( 'hook-name', ... )
	 *   - add_action( 'hook-name', ... )
	 *   - add_filter( 'hook-name', ... )
	 *   - do_action_ref_array( 'hook-name', ... )
	 *   - apply_filters_ref_array( 'hook-name', ... )
	 *
	 * @param string $source      PHP source code.
	 * @param string $file        Absolute file path.
	 * @param string $base_dir    Base directory for computing relative path.
	 * @return list<array{type: string, name: string, file: string, line: int}>
	 */
	private static function extract_hooks_from_source( string $source, string $file, string $base_dir ): array {
		$hooks         = [];
		$relative_file = str_replace( $base_dir, '', $file );
		$lines         = explode( "\n", $source );

		// Map function names to hook types.
		$function_map = [
			'do_action'               => 'action',
			'do_action_ref_array'     => 'action',
			'apply_filters'           => 'filter',
			'apply_filters_ref_array' => 'filter',
			'add_action'              => 'add_action',
			'add_filter'              => 'add_filter',
		];

		$function_list = implode( '|', array_keys( $function_map ) );
		$pattern       = '/(?<func>' . $function_list . ')\s*\(\s*[\'"](?<name>[^\'"]+)[\'"]/';

		foreach ( $lines as $line_index => $line_content ) {
			preg_match_all( $pattern, $line_content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				$func      = $match['func'];
				$hook_name = $match['name'];
				$hook_type = $function_map[ $func ]; // key always present — regex matches only keys in $function_map.

				$hooks[] = [
					'type' => $hook_type,
					'name' => $hook_name,
					'file' => ltrim( $relative_file, '/\\' ),
					'line' => $line_index + 1,
				];
			}
		}

		return $hooks;
	}

	/**
	 * Find all PHP files in a directory recursively.
	 *
	 * @param string $dir Directory path.
	 * @return list<string>
	 */
	private static function find_php_files( string $dir ): array {
		$files    = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$real_path = $file->getRealPath();
				if ( false !== $real_path ) {
					$files[] = $real_path;
				}
			}
		}
		return $files;
	}
}
