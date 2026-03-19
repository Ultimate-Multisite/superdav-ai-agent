<?php

declare(strict_types=1);
/**
 * Register a stock image import ability for the AI agent.
 *
 * Provides a simple keyword-based image import tool that avoids the complexity
 * of the WP-CLI media/import schema (porcelain typing, redirect URLs, etc.).
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockImageAbilities {

	/**
	 * Register abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the import-stock-image ability.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/import-stock-image',
			[
				'label'               => __( 'Import Stock Image', 'gratis-ai-agent' ),
				'description'         => __( 'Import a stock image into the media library by keyword. Returns attachment ID and URL. Use site_url to target a subsite.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'keyword'  => [
							'type'        => 'string',
							'description' => 'Search term for finding a relevant image (e.g. "dogs", "mountain landscape", "coffee shop")',
						],
						'site_url' => [
							'type'        => 'string',
							'description' => 'Subsite URL to import into (e.g. "https://example.com/mysite"). Omit for the main site.',
						],
						'width'    => [
							'type'        => 'integer',
							'description' => 'Image width in pixels (default: 1200)',
						],
						'height'   => [
							'type'        => 'integer',
							'description' => 'Image height in pixels (default: 800)',
						],
					],
					'required'   => [ 'keyword' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'attachment_id' => [ 'type' => 'integer' ],
						'url'           => [ 'type' => 'string' ],
						'alt'           => [ 'type' => 'string' ],
						'title'         => [ 'type' => 'string' ],
						'error'         => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_import' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Permission callback: check upload_files on the target blog, not just the current one.
	 *
	 * On multisite, a user who can upload on site A but not site B must not be
	 * allowed to import media into site B by passing its URL.
	 *
	 * @param array<string,mixed> $input Input with optional site_url.
	 * @return bool Whether the current user can upload files on the target blog.
	 */
	public static function check_permission( array $input ): bool {
		// @phpstan-ignore-next-line
		$site_url = (string) ( $input['site_url'] ?? '' );

		if ( '' === $site_url || ! is_multisite() ) {
			return current_user_can( 'upload_files' );
		}

		$blog_id = get_blog_id_from_url(
			(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
			(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
		);

		if ( ! $blog_id ) {
			return false;
		}

		if ( (int) $blog_id === get_current_blog_id() ) {
			return current_user_can( 'upload_files' );
		}

		switch_to_blog( $blog_id );
		$allowed = current_user_can( 'upload_files' );
		restore_current_blog();

		return $allowed;
	}

	/**
	 * Handle the import-stock-image ability call.
	 *
	 * @param array<string,mixed> $input Input with keyword, optional site_url, width, height.
	 * @return array<string,mixed>|\WP_Error Result with attachment_id, url, alt, title or error.
	 */
	public static function handle_import( array $input ) {
		// @phpstan-ignore-next-line
		$keyword  = sanitize_text_field( $input['keyword'] ?? '' );
		$site_url = $input['site_url'] ?? '';
		// @phpstan-ignore-next-line
		$width = (int) ( $input['width'] ?? 1200 );
		// @phpstan-ignore-next-line
		$height = (int) ( $input['height'] ?? 800 );

		if ( empty( $keyword ) ) {
			return new \WP_Error( 'missing_keyword', 'keyword is required.' );
		}

		// Clamp dimensions to reasonable range.
		$width  = max( 200, min( 3000, $width ) );
		$height = max( 200, min( 3000, $height ) );

		// Switch to target subsite if requested.
		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			} elseif ( ! $blog_id ) {
				// @phpstan-ignore-next-line
				return [ 'error' => "Could not find a site matching URL: {$site_url}" ];
			}
		}

		$result = self::download_and_import( $keyword, $width, $height );

		if ( $switched ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Download an image from Lorem Flickr and import it into the media library.
	 *
	 * @param string $keyword Search keyword.
	 * @param int    $width   Image width.
	 * @param int    $height  Image height.
	 * @return array<string,mixed> Result array.
	 */
	private static function download_and_import( string $keyword, int $width, int $height ): array {
		// Build a deterministic-ish lock so the same keyword doesn't always
		// return the exact same image, but retries in the same request do.
		$lock = crc32( $keyword . gmdate( 'Y-m-d-H' ) );
		$url  = sprintf(
			'https://loremflickr.com/%d/%d/%s?lock=%d',
			$width,
			$height,
			rawurlencode( $keyword ),
			$lock
		);

		// Require file handling functions.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = download_url( $url, 30 );

		if ( is_wp_error( $tmp_file ) ) {
			// Fallback to Picsum (no keyword, but reliable).
			$fallback_url = sprintf( 'https://picsum.photos/%d/%d', $width, $height );
			$tmp_file     = download_url( $fallback_url, 30 );

			if ( is_wp_error( $tmp_file ) ) {
				return [ 'error' => 'Failed to download image: ' . $tmp_file->get_error_message() ];
			}
		}

		// Build a meaningful filename from the keyword.
		$safe_keyword = sanitize_file_name( $keyword );
		$filename     = $safe_keyword . '-' . $width . 'x' . $height . '.jpg';

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		];

		$title = ucwords( str_replace( [ '-', '_' ], ' ', $keyword ) );

		$attachment_id = media_handle_sideload( $file_array, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			// Clean up temp file if sideload failed.
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return [ 'error' => 'Failed to import image: ' . $attachment_id->get_error_message() ];
		}

		// Set alt text from keyword.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

		$attachment_url = wp_get_attachment_url( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'url'           => $attachment_url,
			'alt'           => $title,
			'title'         => $title,
			'tip'           => 'Use this attachment_id as featured_image_id when calling create-post or update-post.',
		];
	}
}
