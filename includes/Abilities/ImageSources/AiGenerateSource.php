<?php

declare(strict_types=1);
/**
 * AI Image Generation source using WordPress AI SDK.
 *
 * Uses the WordPress AI SDK (wp-ai-client) to support any configured
 * image generation provider - OpenAI DALL-E, Stability AI, or self-hosted.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\ImageSources;

use WordPress\AI\Client as AI_Client;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Image Generation source.
 *
 * Uses the WordPress AI SDK for provider-agnostic image generation.
 *
 * @since 1.5.0
 */
class AiGenerateSource implements ImageSourceInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'generate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'AI Generate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		if ( ! class_exists( AI_Client::class ) ) {
			return false;
		}

		// Check if any provider supports image generation.
		$registry = \WordPress\AI\AiClient::defaultRegistry();
		$providers = $registry->getProviders();

		foreach ( $providers as $provider_id => $provider ) {
			$models = $registry->getModels( $provider_id );
			foreach ( $models as $model ) {
				if ( $model->supportsImageGeneration() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 *
	 * For AI generation, search returns a single "synthetic" result
	 * that can be used to trigger generation.
	 */
	public function search( string $keyword, int $per_page = 10 ): array|\WP_Error {
		// AI generation doesn't search - it generates.
		// Return a synthetic hit that represents the generation intent.
		return [
			'hits'   => [
				[
					'id'      => 'generate:' . rawurlencode( $keyword ),
					'preview' => '', // No preview - generated on demand.
					'prompt'  => $keyword,
					'source'  => 'generate',
				],
			],
			'total' => 1,
			'source' => 'generate',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_image( string $image_id ): array|\WP_Error {
		// Not applicable for generation.
		return new WP_Error( 'not_applicable', 'Use download() method for AI generation.' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Generates an image using the WordPress AI SDK.
	 */
	public function download( string $prompt, int $width = 0, int $height = 0 ): string|\WP_Error {
		// Strip the generate: prefix if present.
		$prompt = str_starts_with( $prompt, 'generate:' )
			? substr( $prompt, 9 )
			: $prompt;

		if ( empty( $prompt ) ) {
			return new WP_Error( 'missing_prompt', 'Prompt is required for image generation.' );
		}

		try {
			$result = AI_Client::generateImageResult( $prompt );

			if ( ! $result->isSupported() ) {
				return new WP_Error(
					'generation_unsupported',
					__( 'No configured provider supports image generation. Please configure an image generation provider in Settings > AI Credentials.', 'gratis-ai-agent' )
				);
			}

			if ( $result->isError() ) {
				return new WP_Error(
					'generation_failed',
					$result->getErrorMessage()
				);
			}

			// Get the base64 image from the result.
			$base64_image = $result->toBase64();

			if ( empty( $base64_image ) ) {
				return new WP_Error( 'generation_failed', 'No image data returned.' );
			}

			// Write the base64 image to a temp file.
			$image_data = base64_decode( $base64_image );
			if ( false === $image_data ) {
				return new WP_Error( 'generation_failed', 'Failed to decode base64 image.' );
			}

			$tmp_dir  = get_temp_dir();
			$tmp_file = $tmp_dir . 'gratis-ai-' . uniqid() . '.png';

			$written = file_put_contents( $tmp_file, $image_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_put_contents
			if ( false === $written ) {
				return new WP_Error( 'generation_failed', 'Failed to write temp image file.' );
			}

			return $tmp_file;

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'generation_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_cost_type(): string {
		return 'api';
	}
}