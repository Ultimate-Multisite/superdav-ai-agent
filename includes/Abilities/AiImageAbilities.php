<?php

declare(strict_types=1);
/**
 * AI image generation abilities for the Gratis AI Agent.
 *
 * Provides text-to-image generation via DALL-E 3 (OpenAI) and optionally
 * Stable Diffusion. Generated images are downloaded and imported into the
 * WordPress media library, returning an attachment ID and URL for use in
 * content creation workflows.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static registration facade for AI image generation abilities.
 *
 * @since 1.0.0
 */
class AiImageAbilities {

	/**
	 * Static proxy for generate-image (for backwards-compatible test access).
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_generate( array $input = [] ) {
		$ability = new GenerateImageAbility(
			'gratis-ai-agent/generate-image',
			[
				'label'       => __( 'Generate AI Image', 'gratis-ai-agent' ),
				'description' => __( 'Generate an image from a text prompt using DALL-E 3 (OpenAI). The image is imported into the media library and the attachment ID and URL are returned.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all AI image abilities with the WordPress Abilities API.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/generate-image',
			[
				'label'         => __( 'Generate AI Image', 'gratis-ai-agent' ),
				'description'   => __( 'Generate an image from a text prompt using DALL-E 3 (OpenAI). The image is imported into the media library and the attachment ID and URL are returned.', 'gratis-ai-agent' ),
				'ability_class' => GenerateImageAbility::class,
			]
		);
	}
}

/**
 * Generate Image ability.
 *
 * Calls the DALL-E 3 API with a text prompt, downloads the resulting image,
 * and imports it into the WordPress media library. Returns the attachment ID,
 * URL, and revised prompt (DALL-E may rewrite the prompt for safety).
 *
 * Settings used (from Settings::instance()->get()):
 *   - image_generation_size    : '1024x1024' | '1792x1024' | '1024x1792'
 *   - image_generation_quality : 'standard' | 'hd'
 *   - image_generation_style   : 'vivid' | 'natural'
 *
 * @since 1.0.0
 */
class GenerateImageAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Generate AI Image', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Generate an image from a text prompt using DALL-E 3 (OpenAI). The image is imported into the media library and the attachment ID and URL are returned.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'prompt'   => [
					'type'        => 'string',
					'description' => 'Detailed text description of the image to generate. Be specific about style, composition, lighting, and subject matter for best results.',
				],
				'size'     => [
					'type'        => 'string',
					'enum'        => [ '1024x1024', '1792x1024', '1024x1792' ],
					'description' => 'Image dimensions. 1024x1024 = square, 1792x1024 = landscape, 1024x1792 = portrait. Defaults to the site setting.',
				],
				'quality'  => [
					'type'        => 'string',
					'enum'        => [ 'standard', 'hd' ],
					'description' => 'Image quality. "hd" produces finer details and greater consistency but costs more. Defaults to the site setting.',
				],
				'style'    => [
					'type'        => 'string',
					'enum'        => [ 'vivid', 'natural' ],
					'description' => 'Image style. "vivid" is hyper-real and dramatic; "natural" is more subdued and realistic. Defaults to the site setting.',
				],
				'title'    => [
					'type'        => 'string',
					'description' => 'Optional title for the media library attachment. Defaults to a truncated version of the prompt.',
				],
				'post_id'  => [
					'type'        => 'integer',
					'description' => 'Optional post ID to attach the generated image to in the media library.',
				],
				'site_url' => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite. Omit for the main site.',
				],
			],
			'required'   => [ 'prompt' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'attachment_id'  => [ 'type' => 'integer' ],
				'url'            => [ 'type' => 'string' ],
				'title'          => [ 'type' => 'string' ],
				'alt'            => [ 'type' => 'string' ],
				'revised_prompt' => [ 'type' => 'string' ],
				'size'           => [ 'type' => 'string' ],
				'quality'        => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		// @phpstan-ignore-next-line
		$prompt = sanitize_textarea_field( $input['prompt'] ?? '' );
		// @phpstan-ignore-next-line
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$site_url = $input['site_url'] ?? '';

		if ( empty( $prompt ) ) {
			return new WP_Error( 'missing_param', __( 'prompt is required.', 'gratis-ai-agent' ) );
		}

		// Resolve size/quality/style: per-call input overrides site setting.
		$settings = Settings::instance()->get();
		$size     = $this->resolve_enum(
			// @phpstan-ignore-next-line
			$input['size'] ?? '',
			[ '1024x1024', '1792x1024', '1024x1792' ],
			// @phpstan-ignore-next-line
			(string) ( $settings['image_generation_size'] ?? '1024x1024' )
		);
		$quality = $this->resolve_enum(
			// @phpstan-ignore-next-line
			$input['quality'] ?? '',
			[ 'standard', 'hd' ],
			// @phpstan-ignore-next-line
			(string) ( $settings['image_generation_quality'] ?? 'standard' )
		);
		$style = $this->resolve_enum(
			// @phpstan-ignore-next-line
			$input['style'] ?? '',
			[ 'vivid', 'natural' ],
			// @phpstan-ignore-next-line
			(string) ( $settings['image_generation_style'] ?? 'vivid' )
		);

		// Resolve title for the media library entry.
		// @phpstan-ignore-next-line
		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( empty( $title ) ) {
			$title = mb_substr( $prompt, 0, 80 );
		}

		// Multisite: optionally switch to a subsite.
		// @phpstan-ignore-next-line
		$switched = $this->maybe_switch_blog( $site_url );
		if ( is_wp_error( $switched ) ) {
			return $switched;
		}

		// Call DALL-E 3.
		$api_result = $this->call_dalle( $prompt, $size, $quality, $style );

		if ( is_wp_error( $api_result ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return $api_result;
		}

		// Download and import the generated image.
		$import_result = $this->import_image_from_url(
			$api_result['url'],
			$title,
			$post_id
		);

		if ( $switched ) {
			restore_current_blog();
		}

		if ( is_wp_error( $import_result ) ) {
			return $import_result;
		}

		return [
			'attachment_id'  => $import_result['attachment_id'],
			'url'            => $import_result['url'],
			'title'          => $title,
			'alt'            => $title,
			'revised_prompt' => $api_result['revised_prompt'] ?? $prompt,
			'size'           => $size,
			'quality'        => $quality,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'upload_files' );
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

	// ─── Private helpers ─────────────────────────────────────────────────────

	/**
	 * Call the DALL-E 3 Images API.
	 *
	 * @param string $prompt  Text prompt.
	 * @param string $size    Image dimensions.
	 * @param string $quality 'standard' or 'hd'.
	 * @param string $style   'vivid' or 'natural'.
	 * @return array<string,string>|\WP_Error Array with 'url' and 'revised_prompt', or WP_Error.
	 */
	private function call_dalle( string $prompt, string $size, string $quality, string $style ) {
		$api_key = Settings::instance()->get_provider_key( 'openai' );

		if ( '' === $api_key ) {
			return new WP_Error(
				'no_openai_key',
				__( 'OpenAI API key is not configured. Add it in Settings > Providers.', 'gratis-ai-agent' )
			);
		}

		$body = wp_json_encode(
			[
				'model'   => 'dall-e-3',
				'prompt'  => $prompt,
				'n'       => 1,
				'size'    => $size,
				'quality' => $quality,
				'style'   => $style,
			]
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			[
				'timeout' => 60,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'DALL-E API request failed: %s', 'gratis-ai-agent' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( 200 !== $status_code ) {
			// @phpstan-ignore-next-line
			$error_message = $data['error']['message'] ?? $raw_body;
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'DALL-E API error (HTTP %1$d): %2$s', 'gratis-ai-agent' ),
					$status_code,
					// @phpstan-ignore-next-line
					$error_message
				)
			);
		}

		// @phpstan-ignore-next-line
		$image_url = $data['data'][0]['url'] ?? '';
		// @phpstan-ignore-next-line
		$revised_prompt = $data['data'][0]['revised_prompt'] ?? $prompt;

		if ( empty( $image_url ) ) {
			return new WP_Error(
				'no_image_url',
				__( 'DALL-E API returned no image URL.', 'gratis-ai-agent' )
			);
		}

		// @phpstan-ignore-next-line
		return [
			'url'            => $image_url,
			'revised_prompt' => $revised_prompt,
		];
	}

	/**
	 * Download an image from a URL and import it into the WordPress media library.
	 *
	 * @param string $url     Remote image URL.
	 * @param string $title   Attachment title and alt text.
	 * @param int    $post_id Post ID to attach to (0 = unattached).
	 * @return array<string,mixed>|\WP_Error
	 */
	private function import_image_from_url( string $url, string $title, int $post_id = 0 ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// DALL-E URLs are temporary (expire after ~1 hour), so we must download promptly.
		$tmp_file = download_url( $url, 60 );

		if ( is_wp_error( $tmp_file ) ) {
			return new WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to download generated image: %s', 'gratis-ai-agent' ),
					$tmp_file->get_error_message()
				)
			);
		}

		$safe_title = sanitize_file_name( $title );
		$filename   = $safe_title . '-ai-generated.png';

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return new WP_Error(
				'import_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to import generated image: %s', 'gratis-ai-agent' ),
					$attachment_id->get_error_message()
				)
			);
		}

		// Set alt text.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

		return [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
		];
	}

	/**
	 * Resolve an enum value: use $input_value if valid, otherwise fall back to $fallback.
	 *
	 * @param string        $input_value Value from the ability input.
	 * @param array<string> $allowed     Allowed values.
	 * @param string        $fallback    Fallback value when input is empty or invalid.
	 * @return string
	 */
	private function resolve_enum( string $input_value, array $allowed, string $fallback ): string {
		if ( '' !== $input_value && in_array( $input_value, $allowed, true ) ) {
			return $input_value;
		}
		if ( in_array( $fallback, $allowed, true ) ) {
			return $fallback;
		}
		return $allowed[0];
	}

	/**
	 * Switch to a subsite by URL, returning whether a switch occurred.
	 *
	 * @param string $site_url Subsite URL. Empty string = no switch.
	 * @return bool|\WP_Error True if switched, false if no switch needed, WP_Error on failure.
	 */
	private function maybe_switch_blog( string $site_url ) {
		if ( empty( $site_url ) || ! is_multisite() ) {
			return false;
		}

		$blog_id = get_blog_id_from_url(
			(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
			(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
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
