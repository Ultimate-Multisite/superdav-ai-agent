<?php

declare(strict_types=1);
/**
 * Native WordPress core checksum verification for the WP-CLI-style dispatcher.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SdAiAgent\Core\Net\SafeHttpClient;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verify installed WordPress core files without spawning a WP-CLI process.
 */
final class CoreChecksumVerifier {

	/** WordPress.org checksum endpoint. */
	private const CHECKSUM_ENDPOINT = 'https://api.wordpress.org/core/checksums/1.0/';

	/** Bound tool output when a badly damaged install produces many findings. */
	private const MAX_REPORTED_FILES = 200;

	/** Prevent an include-root scan from walking an unexpectedly huge tree. */
	private const MAX_SCANNED_FILES = 50000;

	/**
	 * Run the equivalent of `wp core verify-checksums`.
	 *
	 * Supported options are `include-root`, `version`, `locale`, and `exclude`.
	 * The unsafe WP-CLI `--insecure` fallback is deliberately not supported.
	 *
	 * @param array<string,string|bool> $args Parsed WP-CLI-style options.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function verify( array $args ) {
		$validation = self::validate_args( $args );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$version      = self::resolve_version( $args );
		$locale       = self::resolve_locale( $args );
		$include_root = array_key_exists( 'include-root', $args );
		$excluded     = self::resolve_excluded_files( $args );

		if ( is_wp_error( $version ) ) {
			return $version;
		}
		if ( is_wp_error( $locale ) ) {
			return $locale;
		}
		if ( is_wp_error( $excluded ) ) {
			return $excluded;
		}

		$checksums = self::fetch_checksums( $version, $locale );
		if ( is_wp_error( $checksums ) ) {
			return $checksums;
		}

		$missing_files  = array();
		$modified_files = array();
		$checked_files  = 0;

		foreach ( $checksums as $file => $checksum ) {
			if ( str_starts_with( $file, 'wp-content/' ) || in_array( $file, $excluded, true ) ) {
				continue;
			}

			++$checked_files;
			$absolute_path = ABSPATH . $file;
			if ( ! is_file( $absolute_path ) ) {
				$missing_files[] = $file;
				continue;
			}

			$actual_checksum = md5_file( $absolute_path );
			if ( ! is_string( $actual_checksum ) || ! hash_equals( $checksum, $actual_checksum ) ) {
				$modified_files[] = $file;
			}
		}

		$actual_files = self::collect_core_files( $include_root );
		if ( is_wp_error( $actual_files ) ) {
			return $actual_files;
		}

		$expected_files = array();
		foreach ( array_keys( $checksums ) as $file ) {
			if ( self::should_scan_file( $file, $include_root ) ) {
				$expected_files[ $file ] = true;
			}
		}

		$unexpected_files = array();
		foreach ( $actual_files as $file ) {
			if ( isset( $expected_files[ $file ] ) || in_array( $file, $excluded, true ) ) {
				continue;
			}
			$unexpected_files[] = $file;
		}

		sort( $missing_files, SORT_STRING );
		sort( $modified_files, SORT_STRING );
		sort( $unexpected_files, SORT_STRING );

		$verified = empty( $missing_files ) && empty( $modified_files );

		return array(
			'command'               => 'core verify-checksums',
			'verified'              => $verified,
			'message'               => $verified
				? __( 'WordPress installation verifies against checksums.', 'superdav-ai-agent' )
				: __( 'WordPress installation does not verify against checksums.', 'superdav-ai-agent' ),
			'version'               => $version,
			'locale'                => $locale,
			'include_root'          => $include_root,
			'checked_file_count'    => $checked_files,
			'missing_file_count'    => count( $missing_files ),
			'modified_file_count'   => count( $modified_files ),
			'unexpected_file_count' => count( $unexpected_files ),
			'missing_files'         => array_slice( $missing_files, 0, self::MAX_REPORTED_FILES ),
			'modified_files'        => array_slice( $modified_files, 0, self::MAX_REPORTED_FILES ),
			'unexpected_files'      => array_slice( $unexpected_files, 0, self::MAX_REPORTED_FILES ),
			'findings_truncated'    => count( $missing_files ) > self::MAX_REPORTED_FILES
				|| count( $modified_files ) > self::MAX_REPORTED_FILES
				|| count( $unexpected_files ) > self::MAX_REPORTED_FILES,
		);
	}

	/**
	 * Validate the bounded option set accepted by the native implementation.
	 *
	 * @param array<string,string|bool> $args Parsed arguments.
	 * @return true|WP_Error
	 */
	private static function validate_args( array $args ) {
		$allowed = array( 'include-root', 'version', 'locale', 'exclude' );
		foreach ( array_keys( $args ) as $name ) {
			if ( ! in_array( $name, $allowed, true ) ) {
				return new WP_Error(
					'wp_cli_checksum_unsupported_argument',
					sprintf(
						/* translators: %s: unsupported WP-CLI option name */
						__( 'The option "--%s" is not supported for native core checksum verification.', 'superdav-ai-agent' ),
						$name
					),
					array( 'status' => 400 )
				);
			}
		}

		foreach ( array( 'version', 'locale', 'exclude' ) as $name ) {
			if ( isset( $args[ $name ] ) && ! is_string( $args[ $name ] ) ) {
				return new WP_Error(
					'wp_cli_checksum_invalid_argument',
					sprintf(
						/* translators: %s: WP-CLI option name */
						__( 'The option "--%s" requires a value.', 'superdav-ai-agent' ),
						$name
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Resolve and validate the WordPress version.
	 *
	 * @param array<string,string|bool> $args Parsed arguments.
	 * @return string|WP_Error
	 */
	private static function resolve_version( array $args ) {
		global $wp_version;

		$version = isset( $args['version'] ) && is_string( $args['version'] )
			? trim( $args['version'] )
			: (string) $wp_version;

		if ( '' === $version || 1 !== preg_match( '/^[A-Za-z0-9._-]{1,32}$/', $version ) ) {
			return new WP_Error(
				'wp_cli_checksum_invalid_version',
				__( 'The WordPress version for checksum verification is invalid.', 'superdav-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		return $version;
	}

	/**
	 * Resolve and validate the installed package locale.
	 *
	 * @param array<string,string|bool> $args Parsed arguments.
	 * @return string|WP_Error
	 */
	private static function resolve_locale( array $args ) {
		global $wp_local_package;

		$locale = isset( $args['locale'] ) && is_string( $args['locale'] )
			? trim( $args['locale'] )
			: (string) ( $wp_local_package ?? '' );
		$locale = '' !== $locale ? $locale : 'en_US';

		if ( 1 !== preg_match( '/^[A-Za-z0-9_.@-]{1,32}$/', $locale ) ) {
			return new WP_Error(
				'wp_cli_checksum_invalid_locale',
				__( 'The WordPress locale for checksum verification is invalid.', 'superdav-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		return $locale;
	}

	/**
	 * Parse and validate exact file exclusions.
	 *
	 * @param array<string,string|bool> $args Parsed arguments.
	 * @return string[]|WP_Error
	 */
	private static function resolve_excluded_files( array $args ) {
		if ( ! isset( $args['exclude'] ) || ! is_string( $args['exclude'] ) || '' === trim( $args['exclude'] ) ) {
			return array();
		}

		$excluded = array();
		foreach ( explode( ',', $args['exclude'] ) as $file ) {
			$file = self::normalise_relative_path( trim( $file ) );
			if ( ! self::is_safe_relative_path( $file ) ) {
				return new WP_Error(
					'wp_cli_checksum_invalid_exclude',
					__( 'Checksum exclusions must be relative paths within the WordPress installation.', 'superdav-ai-agent' ),
					array( 'status' => 400 )
				);
			}
			$excluded[] = $file;
		}

		return array_values( array_unique( $excluded ) );
	}

	/**
	 * Fetch and validate checksums from the fixed WordPress.org endpoint.
	 *
	 * @return array<string,string>|WP_Error
	 */
	private static function fetch_checksums( string $version, string $locale ) {
		$url = add_query_arg(
			array(
				'version' => $version,
				'locale'  => $locale,
			),
			self::CHECKSUM_ENDPOINT
		);

		$response = SafeHttpClient::instance()->safe_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'headers'             => array( 'Accept' => 'application/json' ),
				'limit_response_size' => 5 * MB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wp_cli_checksum_fetch_failed',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'wp_cli_checksum_fetch_failed',
				sprintf(
					/* translators: %d: HTTP response status code */
					__( 'WordPress.org returned HTTP %d while fetching core checksums.', 'superdav-ai-agent' ),
					$status_code
				),
				array( 'status' => 502 )
			);
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) || ! isset( $payload['checksums'] ) || ! is_array( $payload['checksums'] ) ) {
			return new WP_Error(
				'wp_cli_checksum_invalid_response',
				__( 'WordPress.org returned an invalid core checksum response.', 'superdav-ai-agent' ),
				array( 'status' => 502 )
			);
		}

		$checksums = array();
		foreach ( $payload['checksums'] as $file => $checksum ) {
			$file = is_string( $file ) ? self::normalise_relative_path( $file ) : '';
			if (
				! self::is_safe_relative_path( $file )
				|| ! is_string( $checksum )
				|| 1 !== preg_match( '/^[a-f0-9]{32}$/i', $checksum )
			) {
				return new WP_Error(
					'wp_cli_checksum_invalid_response',
					__( 'WordPress.org returned an invalid core checksum entry.', 'superdav-ai-agent' ),
					array( 'status' => 502 )
				);
			}
			$checksums[ $file ] = strtolower( $checksum );
		}

		if ( empty( $checksums ) ) {
			return new WP_Error(
				'wp_cli_checksum_invalid_response',
				__( 'WordPress.org returned no core checksums for this version and locale.', 'superdav-ai-agent' ),
				array( 'status' => 502 )
			);
		}

		return $checksums;
	}

	/**
	 * Collect files checked for unexpected additions by WP-CLI semantics.
	 *
	 * @return string[]|WP_Error
	 */
	private static function collect_core_files( bool $include_root ) {
		try {
			$files = $include_root
				? self::collect_root_files()
				: self::collect_default_core_files();
		} catch ( Throwable $throwable ) {
			return new WP_Error(
				'wp_cli_checksum_scan_failed',
				$throwable->getMessage(),
				array( 'status' => 500 )
			);
		}

		if ( count( $files ) > self::MAX_SCANNED_FILES ) {
			return new WP_Error(
				'wp_cli_checksum_scan_limit',
				__( 'Core checksum verification stopped because the scan exceeded 50,000 files.', 'superdav-ai-agent' ),
				array( 'status' => 422 )
			);
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Collect wp-admin, wp-includes, and wp-* root files.
	 *
	 * @return string[]
	 */
	private static function collect_default_core_files(): array {
		$files = array();
		foreach ( array( 'wp-admin', 'wp-includes' ) as $directory ) {
			$path = ABSPATH . $directory;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $iterator as $file_info ) {
				if ( $file_info->isFile() ) {
					$files[] = self::path_relative_to_abspath( $file_info->getPathname() );
				}
				if ( count( $files ) > self::MAX_SCANNED_FILES ) {
					return $files;
				}
			}
		}

		$root = new \DirectoryIterator( ABSPATH );
		foreach ( $root as $file_info ) {
			if ( $file_info->isFile() && self::should_scan_file( $file_info->getFilename(), false ) ) {
				$files[] = $file_info->getFilename();
			}
		}

		return $files;
	}

	/**
	 * Collect all root files except mutable or sensitive WordPress paths.
	 *
	 * @return string[]
	 */
	private static function collect_root_files(): array {
		$directory = new RecursiveDirectoryIterator( ABSPATH, RecursiveDirectoryIterator::SKIP_DOTS );
		$filter    = new RecursiveCallbackFilterIterator(
			$directory,
			static function ( \SplFileInfo $current ): bool {
				$relative = self::path_relative_to_abspath( $current->getPathname() );
				if ( $current->isDir() && ( 'wp-content' === $relative || str_starts_with( $relative, 'wp-content/' ) ) ) {
					return false;
				}
				return self::should_scan_file( $relative, true );
			}
		);
		$iterator  = new RecursiveIteratorIterator(
			$filter,
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		$files     = array();

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isFile() ) {
				$files[] = self::path_relative_to_abspath( $file_info->getPathname() );
			}
			if ( count( $files ) > self::MAX_SCANNED_FILES ) {
				return $files;
			}
		}

		return $files;
	}

	/** Whether a relative file belongs in the unexpected-file comparison. */
	private static function should_scan_file( string $file, bool $include_root ): bool {
		$file = self::normalise_relative_path( $file );
		if ( $include_root ) {
			return ! in_array( $file, array( '.htaccess', '.maintenance', 'wp-config.php' ), true )
				&& 'wp-content' !== $file
				&& ! str_starts_with( $file, 'wp-content/' );
		}

		return str_starts_with( $file, 'wp-admin/' )
			|| str_starts_with( $file, 'wp-includes/' )
			|| ( ! str_contains( $file, '/' ) && 1 === preg_match( '/^wp-(?!config\.php).+$/', $file ) );
	}

	/** Convert an absolute path below ABSPATH to a normalized relative path. */
	private static function path_relative_to_abspath( string $path ): string {
		return self::normalise_relative_path( substr( $path, strlen( ABSPATH ) ) );
	}

	/** Normalize path separators used by checksum keys. */
	private static function normalise_relative_path( string $path ): string {
		return ltrim( str_replace( '\\', '/', $path ), '/' );
	}

	/** Ensure a checksum path cannot escape ABSPATH. */
	private static function is_safe_relative_path( string $path ): bool {
		return '' !== $path
			&& ! str_contains( $path, "\0" )
			&& 1 !== preg_match( '#(^|/)\.\.?(/|$)#', $path );
	}
}
