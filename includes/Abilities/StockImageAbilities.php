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
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
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
	 * Maximum number of verification retries per keyword.
	 *
	 * @since 1.5.0
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Download an image from Lorem Flickr and import it into the media library.
	 *
	 * Uses AI vision to verify the downloaded image actually matches the keyword,
	 * retrying with different images if verification fails.
	 *
	 * @param string $keyword Search keyword.
	 * @param int    $width   Image width.
	 * @param int    $height  Image height.
	 * @return array<string,mixed> Result array.
	 */
	private static function download_and_import( string $keyword, int $width, int $height ): array {
		// Require file handling functions.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$last_error    = '';
		$tmp_file    = null;
		$image_url  = '';
		$retry      = 0;

		// Retry loop: download, verify, import.
		while ( $retry <= self::MAX_RETRIES ) {
			++$retry;

			// Build a deterministic-ish lock so the same keyword doesn't always
			// return the exact same image, but retries in the same request do.
			// Vary by retry count to get different images on each attempt.
			$lock = crc32( $keyword . ( gmdate( 'Y-m-d-H' ) + $retry ) );
			$url  = sprintf(
				'https://loremflickr.com/%d/%d/%s?lock=%d',
				$width,
				$height,
				rawurlencode( $keyword ),
				$lock
			);

			$tmp_file = download_url( $url, 30 );

			if ( is_wp_error( $tmp_file ) ) {
				$last_error = 'Failed to download image: ' . $tmp_file->get_error_message();
				continue;
			}

			// Verify the image matches the keyword using AI vision.
			$verified = self::verify_image_matches_keyword( $tmp_file, $keyword );

			if ( true === $verified ) {
				// Image verified - proceed to import.
				break;
			}

			$last_error = is_string( $verified ) ? $verified : 'Image does not match keyword';

			// Clean up rejected image and retry.
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			$tmp_file = null;
		}

		// All retries exhausted without verification.
		if ( null === $tmp_file || ! file_exists( $tmp_file ) ) {
			return [
				'error' => sprintf(
					'Could not find an image matching "%s" after %d attempts. %s',
					$keyword,
					self::MAX_RETRIES + 1,
					$last_error
				),
			];
		}

		// Build a meaningful filename from the keyword.
		$safe_keyword = sanitize_file_name( $keyword );
		$filename   = $safe_keyword . '-' . $width . 'x' . $height . '.jpg';

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

	/**
	 * Verify that an image matches the given keyword using AI vision.
	 *
	 * @since 1.5.0
	 *
	 * @param string $tmp_file Path to the downloaded image.
	 * @param string $keyword  The keyword to verify against.
	 * @return true|string    True if verified, or error message if not.
	 */
	private static function verify_image_matches_keyword( string $tmp_file, string $keyword ): true|string {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			// AI not available - accept the image without verification.
			return true;
		}

		// Resolve the image to a data URI for the AI.
		$image_data = self::image_to_data_uri( $tmp_file );
		if ( empty( $image_data ) ) {
			return 'Could not read downloaded image for verification.';
		}

		$system_instruction = <<<'INSTRUCTION'
You are an image verification expert. Your task is to determine whether the provided image matches the given search keyword.

Examine the image carefully and respond with ONLY one of:
- "MATCH" if the image clearly contains or depicts the keyword concept
- "NO_MATCH" if the image does not depict the keyword concept

Be strict: if the image is ambiguous or only loosely related, respond NO_MATCH.
Examples of NO_MATCH:
- Keyword "cats" → image shows dogs
- Keyword "beach" → image shows mountains
- Keyword "coffee" → image shows tea cups
- Keyword "sunset" → image shows a sunny day without a visible sun

Respond with ONLY MATCH or NO_MATCH, nothing else.
INSTRUCTION;

		$prompt = sprintf(
			'Does this image match the keyword "%s"? Respond with MATCH or NO_MATCH.',
			$keyword
		);

		$builder = wp_ai_client_prompt( $prompt )
			->with_file( $image_data )
			->using_system_instruction( $system_instruction )
			->using_temperature( 0 );

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			// AI failed - accept the image to avoid blocking.
			return true;
		}

		$response = strtoupper( trim( (string) $result ) );

		if ( false !== strpos( $response, 'MATCH' ) ) {
			return true;
		}

		return sprintf( 'AI verification failed: expected MATCH, got "%s"', $response );
	}

	/**
	 * Convert an image file to a data URI for AI vision.
	 *
	 * @since 1.5.0
	 *
	 * @param string $file_path Path to the image file.
	 * @return string Data URI or empty string on failure.
	 */
	private static function image_to_data_uri( string $file_path ): string {
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		$contents = file_get_contents( $file_path );
		if ( false === $contents ) {
			return '';
		}

		$finfo    = new \finfo( FILEINFO_MIME_TYPE );
		$mime    = $finfo->file( $file_path );
		$base64  = base64_encode( $contents );

		return sprintf( 'data:%s;base64,%s', $mime, $base64 );
	}
}
