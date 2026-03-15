<?php

declare(strict_types=1);
/**
 * Register a stock image import ability for the AI agent.
 *
 * Provides a simple keyword-based image import tool that avoids the complexity
 * of the WP-CLI media/import schema (porcelain typing, redirect URLs, etc.).
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

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
			'gratis-ai-agent/import-stock-image',
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
				'execute_callback'    => [ __CLASS__, 'handle_import' ],
				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},
			]
		);
	}

	/**
	 * Handle the import-stock-image ability call.
	 *
	 * @param array $input Input with keyword, optional site_url, width, height.
	 * @return array|\WP_Error Result with attachment_id, url, alt, title or WP_Error on failure.
	 */
	public static function handle_import( array $input ): array|\WP_Error {
		$keyword  = sanitize_text_field( $input['keyword'] ?? '' );
		$site_url = $input['site_url'] ?? '';
		$width    = (int) ( $input['width'] ?? 1200 );
		$height   = (int) ( $input['height'] ?? 800 );

		if ( empty( $keyword ) ) {
			return new WP_Error( 'missing_param', __( 'keyword is required.', 'gratis-ai-agent' ) );
		}

		// Clamp dimensions to reasonable range.
		$width  = max( 200, min( 3000, $width ) );
		$height = max( 200, min( 3000, $height ) );

		// Switch to target subsite if requested.
		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			} elseif ( ! $blog_id ) {
				return new WP_Error(
					'site_not_found',
					sprintf(
						/* translators: %s: site URL */
						__( 'Could not find a site matching URL: %s', 'gratis-ai-agent' ),
						$site_url
					)
				);
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
	 * @return array|\WP_Error Result array or WP_Error on failure.
	 */
	private static function download_and_import( string $keyword, int $width, int $height ): array|\WP_Error {
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
				return new WP_Error(
					'download_failed',
					sprintf(
						/* translators: %s: error message */
						__( 'Failed to download image: %s', 'gratis-ai-agent' ),
						$tmp_file->get_error_message()
					)
				);
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
			return new WP_Error(
				'import_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to import image: %s', 'gratis-ai-agent' ),
					$attachment_id->get_error_message()
				)
			);
		}

		// Set alt text from keyword.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

		$attachment_url = wp_get_attachment_url( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'url'           => $attachment_url,
			'alt'           => $title,
			'title'         => $title,
		];
	}
}
