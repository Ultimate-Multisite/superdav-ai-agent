<?php

declare(strict_types=1);
/**
 * Register stock image abilities for the AI agent.
 *
 * Provides keyword-based image import, featured-image assignment, and
 * automatic image insertion into post content during content creation.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockImageAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Import a stock image into the media library.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_import( array $input = [] ) {
		$ability = new ImportStockImageAbility(
			'gratis-ai-agent/import-stock-image',
			[
				'label'       => __( 'Import Stock Image', 'gratis-ai-agent' ),
				'description' => __( 'Import a stock image into the media library by keyword. Returns attachment ID and URL. Use site_url to target a subsite.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Set a stock image as the featured image for a post.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_set_featured_image( array $input = [] ) {
		$ability = new SetFeaturedImageAbility(
			'gratis-ai-agent/set-featured-image',
			[
				'label'       => __( 'Set Featured Image', 'gratis-ai-agent' ),
				'description' => __( 'Import a stock image by keyword and set it as the featured image (post thumbnail) for a post or page.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Auto-select and insert stock images into post content.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_auto_select_images( array $input = [] ) {
		$ability = new AutoSelectImagesAbility(
			'gratis-ai-agent/auto-select-images',
			[
				'label'       => __( 'Auto-Select Images', 'gratis-ai-agent' ),
				'description' => __( 'Automatically search for and insert relevant stock images into a post or page. Derives keywords from the post title and content, imports matching images, and inserts them as Gutenberg image blocks. Optionally sets a featured image.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Register abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all stock image abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/import-stock-image',
			[
				'label'         => __( 'Import Stock Image', 'gratis-ai-agent' ),
				'description'   => __( 'Import a stock image into the media library by keyword. Returns attachment ID and URL. Use site_url to target a subsite.', 'gratis-ai-agent' ),
				'ability_class' => ImportStockImageAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/set-featured-image',
			[
				'label'         => __( 'Set Featured Image', 'gratis-ai-agent' ),
				'description'   => __( 'Import a stock image by keyword and set it as the featured image (post thumbnail) for a post or page.', 'gratis-ai-agent' ),
				'ability_class' => SetFeaturedImageAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/auto-select-images',
			[
				'label'         => __( 'Auto-Select Images', 'gratis-ai-agent' ),
				'description'   => __( 'Automatically search for and insert relevant stock images into a post or page. Derives keywords from the post title and content, imports matching images, and inserts them as Gutenberg image blocks. Optionally sets a featured image.', 'gratis-ai-agent' ),
				'ability_class' => AutoSelectImagesAbility::class,
			]
		);
	}
}

/**
 * Shared image download/import helper trait.
 *
 * @since 1.0.0
 */
trait StockImageDownloaderTrait {

	/**
	 * Download an image from Lorem Flickr and import it into the media library.
	 *
	 * Falls back to Picsum Photos if Lorem Flickr is unavailable.
	 *
	 * @param string $keyword  Search keyword.
	 * @param int    $width    Image width in pixels.
	 * @param int    $height   Image height in pixels.
	 * @param int    $post_id  Post ID to attach the image to (0 = unattached).
	 * @return array<string, mixed>|\WP_Error Result array or WP_Error on failure.
	 */
	protected function download_and_import( string $keyword, int $width, int $height, int $post_id = 0 ) {
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

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

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

	/**
	 * Switch to a subsite by URL, returning whether a switch occurred.
	 *
	 * @param string $site_url Subsite URL. Empty string = no switch.
	 * @return bool|\WP_Error True if switched, false if no switch needed, WP_Error on failure.
	 */
	protected function maybe_switch_blog( string $site_url ) {
		if ( empty( $site_url ) || ! is_multisite() ) {
			return false;
		}

		$blog_id = get_blog_id_from_url(
			wp_parse_url( $site_url, PHP_URL_HOST ),
			wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
		);

		if ( ! $blog_id ) {
			return new WP_Error(
				'site_not_found',
				sprintf(
					/* translators: %s: site URL */
					__( 'Could not find a site matching URL: %s', 'gratis-ai-agent' ),
					$site_url
				)
			);
		}

		if ( $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			return true;
		}

		return false;
	}
}

/**
 * Import Stock Image ability.
 *
 * @since 1.0.0
 */
class ImportStockImageAbility extends AbstractAbility {

	use StockImageDownloaderTrait;

	protected function label(): string {
		return __( 'Import Stock Image', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Import a stock image into the media library by keyword. Returns attachment ID and URL. Use site_url to target a subsite.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
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
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'attachment_id' => [ 'type' => 'integer' ],
				'url'           => [ 'type' => 'string' ],
				'alt'           => [ 'type' => 'string' ],
				'title'         => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
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

		$switched = $this->maybe_switch_blog( $site_url );
		if ( is_wp_error( $switched ) ) {
			return $switched;
		}

		$result = $this->download_and_import( $keyword, $width, $height );

		if ( $switched ) {
			restore_current_blog();
		}

		return $result;
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Set Featured Image ability.
 *
 * Imports a stock image by keyword and sets it as the featured image
 * (post thumbnail) for a given post or page.
 *
 * @since 1.0.0
 */
class SetFeaturedImageAbility extends AbstractAbility {

	use StockImageDownloaderTrait;

	protected function label(): string {
		return __( 'Set Featured Image', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Import a stock image by keyword and set it as the featured image (post thumbnail) for a post or page.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'  => [
					'type'        => 'integer',
					'description' => 'ID of the post or page to set the featured image on.',
				],
				'keyword'  => [
					'type'        => 'string',
					'description' => 'Search term for the featured image (e.g. "technology", "team meeting"). If omitted, the post title is used.',
				],
				'width'    => [
					'type'        => 'integer',
					'description' => 'Image width in pixels (default: 1200)',
				],
				'height'   => [
					'type'        => 'integer',
					'description' => 'Image height in pixels (default: 628)',
				],
				'site_url' => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite. Omit for the main site.',
				],
			],
			'required'   => [ 'post_id' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'       => [ 'type' => 'integer' ],
				'attachment_id' => [ 'type' => 'integer' ],
				'url'           => [ 'type' => 'string' ],
				'alt'           => [ 'type' => 'string' ],
				'keyword_used'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$site_url = $input['site_url'] ?? '';
		$width    = max( 200, min( 3000, (int) ( $input['width'] ?? 1200 ) ) );
		$height   = max( 200, min( 3000, (int) ( $input['height'] ?? 628 ) ) );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_param', __( 'post_id is required.', 'gratis-ai-agent' ) );
		}

		$switched = $this->maybe_switch_blog( $site_url );
		if ( is_wp_error( $switched ) ) {
			return $switched;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return new WP_Error(
				'post_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Post %d not found.', 'gratis-ai-agent' ),
					$post_id
				)
			);
		}

		// Derive keyword from post title if not provided.
		$keyword = sanitize_text_field( $input['keyword'] ?? '' );
		if ( empty( $keyword ) ) {
			$keyword = $post->post_title;
		}

		$image = $this->download_and_import( $keyword, $width, $height, $post_id );

		if ( is_wp_error( $image ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return $image;
		}

		set_post_thumbnail( $post_id, $image['attachment_id'] );

		if ( $switched ) {
			restore_current_blog();
		}

		return [
			'post_id'       => $post_id,
			'attachment_id' => $image['attachment_id'],
			'url'           => $image['url'],
			'alt'           => $image['alt'],
			'keyword_used'  => $keyword,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Auto-Select Images ability.
 *
 * Automatically derives image keywords from a post's title and content,
 * imports matching stock images, inserts them as Gutenberg image blocks
 * at natural break points in the content, and optionally sets a featured image.
 *
 * @since 1.0.0
 */
class AutoSelectImagesAbility extends AbstractAbility {

	use StockImageDownloaderTrait;

	protected function label(): string {
		return __( 'Auto-Select Images', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Automatically search for and insert relevant stock images into a post or page. Derives keywords from the post title and content, imports matching images, and inserts them as Gutenberg image blocks. Optionally sets a featured image.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'          => [
					'type'        => 'integer',
					'description' => 'ID of the post or page to add images to.',
				],
				'keywords'         => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Explicit list of keywords to use for image searches. If omitted, keywords are derived automatically from the post title and H2/H3 headings.',
				],
				'image_count'      => [
					'type'        => 'integer',
					'description' => 'Number of images to insert into the content (default: 1, max: 5).',
				],
				'set_featured'     => [
					'type'        => 'boolean',
					'description' => 'Whether to also set a featured image (post thumbnail). Default: true.',
				],
				'featured_keyword' => [
					'type'        => 'string',
					'description' => 'Keyword for the featured image. If omitted, the post title is used.',
				],
				'width'            => [
					'type'        => 'integer',
					'description' => 'Width of inline content images in pixels (default: 1200).',
				],
				'height'           => [
					'type'        => 'integer',
					'description' => 'Height of inline content images in pixels (default: 800).',
				],
				'featured_width'   => [
					'type'        => 'integer',
					'description' => 'Width of the featured image in pixels (default: 1200).',
				],
				'featured_height'  => [
					'type'        => 'integer',
					'description' => 'Height of the featured image in pixels (default: 628).',
				],
				'site_url'         => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite. Omit for the main site.',
				],
			],
			'required'   => [ 'post_id' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'         => [ 'type' => 'integer' ],
				'images_inserted' => [ 'type' => 'integer' ],
				'featured_set'    => [ 'type' => 'boolean' ],
				'featured_image'  => [ 'type' => 'object' ],
				'inserted_images' => [ 'type' => 'array' ],
				'keywords_used'   => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$post_id      = (int) ( $input['post_id'] ?? 0 );
		$site_url     = $input['site_url'] ?? '';
		$image_count  = max( 1, min( 5, (int) ( $input['image_count'] ?? 1 ) ) );
		$set_featured = (bool) ( $input['set_featured'] ?? true );
		$feat_keyword = sanitize_text_field( $input['featured_keyword'] ?? '' );
		$width        = max( 200, min( 3000, (int) ( $input['width'] ?? 1200 ) ) );
		$height       = max( 200, min( 3000, (int) ( $input['height'] ?? 800 ) ) );
		$feat_width   = max( 200, min( 3000, (int) ( $input['featured_width'] ?? 1200 ) ) );
		$feat_height  = max( 200, min( 3000, (int) ( $input['featured_height'] ?? 628 ) ) );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_param', __( 'post_id is required.', 'gratis-ai-agent' ) );
		}

		$switched = $this->maybe_switch_blog( $site_url );
		if ( is_wp_error( $switched ) ) {
			return $switched;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return new WP_Error(
				'post_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Post %d not found.', 'gratis-ai-agent' ),
					$post_id
				)
			);
		}

		// Resolve keywords: explicit list or auto-derived.
		$explicit_keywords = $input['keywords'] ?? [];
		$keywords          = $this->resolve_keywords( $post, $explicit_keywords, $image_count );

		// Import and insert inline images.
		$inserted_images = [];
		$keywords_used   = [];
		$new_content     = $post->post_content;

		foreach ( array_slice( $keywords, 0, $image_count ) as $keyword ) {
			$keyword = sanitize_text_field( $keyword );
			if ( empty( $keyword ) ) {
				continue;
			}

			$image = $this->download_and_import( $keyword, $width, $height, $post_id );

			if ( is_wp_error( $image ) ) {
				// Skip failed images; don't abort the whole operation.
				continue;
			}

			$keywords_used[]   = $keyword;
			$inserted_images[] = $image;

			// Build a Gutenberg image block and append it to the content.
			$new_content .= $this->build_image_block( $image );
		}

		// Update post content if any images were inserted.
		if ( ! empty( $inserted_images ) ) {
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => $new_content,
				]
			);
		}

		// Set featured image.
		$featured_result = null;
		$featured_set    = false;

		if ( $set_featured ) {
			$feat_kw  = ! empty( $feat_keyword ) ? $feat_keyword : $post->post_title;
			$feat_img = $this->download_and_import( $feat_kw, $feat_width, $feat_height, $post_id );

			if ( ! is_wp_error( $feat_img ) ) {
				set_post_thumbnail( $post_id, $feat_img['attachment_id'] );
				$featured_result = $feat_img;
				$featured_set    = true;
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}

		return [
			'post_id'         => $post_id,
			'images_inserted' => count( $inserted_images ),
			'featured_set'    => $featured_set,
			'featured_image'  => $featured_result,
			'inserted_images' => $inserted_images,
			'keywords_used'   => $keywords_used,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}

	/**
	 * Resolve image keywords from explicit input or auto-derive from post content.
	 *
	 * Auto-derivation strategy (in priority order):
	 *  1. Post title (always first).
	 *  2. H2/H3 heading text extracted from Gutenberg block markup.
	 *  3. First sentence of each paragraph (truncated to 5 words).
	 *
	 * @param \WP_Post          $post     The post object.
	 * @param array<int,string> $explicit Explicit keyword list from input.
	 * @param int               $count    Number of keywords needed.
	 * @return array<int,string> Keyword list (may be shorter than $count if content is thin).
	 */
	private function resolve_keywords( \WP_Post $post, array $explicit, int $count ): array {
		if ( ! empty( $explicit ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $explicit ) ) );
		}

		$keywords = [];

		// 1. Post title.
		if ( ! empty( $post->post_title ) ) {
			$keywords[] = $post->post_title;
		}

		if ( count( $keywords ) >= $count ) {
			return array_slice( $keywords, 0, $count );
		}

		// 2. H2/H3 headings from block markup.
		$content = $post->post_content;
		if ( ! empty( $content ) ) {
			preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $content, $heading_matches );
			foreach ( $heading_matches[1] as $heading_html ) {
				$text = wp_strip_all_tags( $heading_html );
				if ( ! empty( $text ) ) {
					$keywords[] = $text;
				}
				if ( count( $keywords ) >= $count ) {
					return array_slice( $keywords, 0, $count );
				}
			}

			// 3. First few words of each paragraph.
			preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $para_matches );
			foreach ( $para_matches[1] as $para_html ) {
				$text  = wp_strip_all_tags( $para_html );
				$words = preg_split( '/\s+/', trim( $text ), 6 );
				if ( is_array( $words ) && count( $words ) >= 3 ) {
					$keywords[] = implode( ' ', array_slice( $words, 0, 5 ) );
				}
				if ( count( $keywords ) >= $count ) {
					return array_slice( $keywords, 0, $count );
				}
			}
		}

		return array_slice( $keywords, 0, $count );
	}

	/**
	 * Build a serialized Gutenberg core/image block for the given image data.
	 *
	 * @param array<string,mixed> $image Image data with attachment_id, url, alt, title.
	 * @return string Serialized block HTML.
	 */
	private function build_image_block( array $image ): string {
		$attachment_id = (int) $image['attachment_id'];
		$url           = esc_url( (string) $image['url'] );
		$alt           = esc_attr( (string) $image['alt'] );

		$attrs = wp_json_encode(
			[
				'id'              => $attachment_id,
				'sizeSlug'        => 'large',
				'linkDestination' => 'none',
			]
		);

		return sprintf(
			"\n\n<!-- wp:image %s -->\n<figure class=\"wp-block-image size-large\"><img src=\"%s\" alt=\"%s\" class=\"wp-image-%d\"/></figure>\n<!-- /wp:image -->",
			$attrs,
			$url,
			$alt,
			$attachment_id
		);
	}
}
