<?php

declare(strict_types=1);
/**
 * Unified image ability that chooses between search and generation.
 *
 * The agent can ask for "images of X" or "generate an image of X" and this
 * ability will handle both - using the best available source.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\ImageAbilities;

use GratisAiAgent\Abilities\ImageSources\ImageSourceFactory;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified image ability - handles both search and AI generation.
 *
 * @since 1.5.0
 */
class UnifiedImageAbility extends \GratisAiAgent\Abilities\AbstractAbility {

	/**
	 * Register this ability.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/image',
			[
				'label'         => __( 'Image', 'gratis-ai-agent' ),
				'description'   => __( 'Find or generate images. For searches like "images of cows", uses free stock image APIs (Openverse, Pixabay). For generation prompts, uses DALL-E 3 AI. Returns attachment ID and URL.', 'gratis-ai-agent' ),
				'ability_class' => self::class,
			]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function label(): string {
		return __( 'Image', 'gratis-ai-agent' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function description(): string {
		return __( 'Find or generate images. For searches like "images of cows", uses free stock image APIs (Openverse, Pixabay). For generation prompts, uses DALL-E 3 AI. Returns attachment ID and URL.', 'gratis-ai-agent' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'keyword'  => [
					'type'        => 'string',
					'description' => 'Search term or generation prompt. For "images of X", searches free stock APIs. For "generate an image of X", uses AI generation.',
				],
				'source'   => [
					'type'        => 'string',
					'enum'        => [ 'openverse', 'pixabay', 'generate', 'auto' ],
					'description' => 'Preferred source: "openverse" (free CC0), "pixabay" (free with API key), "generate" (AI), "auto" (best available). Defaults to "auto".',
				],
				'width'    => [
					'type'        => 'integer',
					'description' => 'Image width in pixels (default: 1200)',
				],
				'height'   => [
					'type'        => 'integer',
					'description' => 'Image height in pixels (default: 800)',
				],
				'site_url' => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite (omit for main site)',
				],
			],
			'required'   => [ 'keyword' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'attachment_id' => [ 'type' => 'integer' ],
				'url'           => [ 'type' => 'string' ],
				'alt'           => [ 'type' => 'string' ],
				'title'         => [ 'type' => 'string' ],
				'source'        => [ 'type' => 'string' ],
				'sources'       => [ 'type' => 'array' ],
				'error'        => [ 'type' => 'string' ],
				'tip'           => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function permission_callback( $input = null ): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute_callback( $input ) {
		// @phpstan-ignore-next-line
		$keyword  = sanitize_text_field( $input['keyword'] ?? '' );
		$source   = sanitize_text_field( $input['source'] ?? 'auto' );
		$width    = (int) ( $input['width'] ?? 1200 );
		$height   = (int) ( $input['height'] ?? 800 );
		$site_url = sanitize_text_field( $input['site_url'] ?? '' );

		if ( empty( $keyword ) ) {
			return new WP_Error( 'missing_keyword', 'keyword is required.' );
		}

		// Determine source: check if user wants generation.
		$source_id = 'auto' === $source ? '' : $source;
		$is_generate_intent = preg_match(
			'/^(generate|create|make|draw|draw|produce)\s+(an?\s+)?(image|photo|picture)/i',
			$keyword
		);

		if ( $is_generate_intent ) {
			// Extract the prompt from the keyword.
			$prompt = preg_replace(
				'/^(generate|create|make|draw|produce)\s+(an?\s+)?(image|photo|picture)\s+(of\s+)?/i',
				'',
				$keyword
			);
			$source_id = 'generate';
		} else {
			$prompt = $keyword;
		}

		$options = [
			'site_url' => $site_url,
		];

		// Import the image.
		$result = ImageSourceFactory::import_image(
			$prompt,
			$source_id,
			$width,
			$height,
			$options
		);

		if ( is_wp_error( $result ) ) {
			return [
				'error'   => $result->get_error_message(),
				'sources' => ImageSourceFactory::get_source_info(),
			];
		}

		// Add available sources to response.
		$result['sources'] = ImageSourceFactory::get_source_info();

		return $result;
	}
}