<?php
/**
 * Handler: normalise `wp_register_ability_args` input schemas.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\WordPress\Abilities;

use SdAiAgent\Infrastructure\Schema\EmptyJsonObject;
use SdAiAgent\Infrastructure\Schema\SchemaNormalizer;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepts every `wp_register_ability()` call and forces a well-formed
 * JSON Schema draft-2020-12 object on `input_schema`, so third-party
 * abilities that register `input_schema => []` (or omit the key entirely)
 * don't blow up Anthropic / OpenAI / Ollama tool-use validators downstream.
 *
 * Runs at the default priority so it applies before the core Abilities API
 * instantiates the {@see \WP_Ability} object.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AbilitySchemaFilter {

	/**
	 * Normalise the `input_schema` entry on the ability registration args.
	 *
	 * Missing `input_schema` is backfilled with a permissive empty-object
	 * schema so downstream LLM validators never see a bare array.
	 *
	 * @param array<string,mixed> $args Ability registration args.
	 * @return array<string,mixed> Args with a draft-2020-12-compatible input_schema.
	 */
	#[Filter( tag: 'wp_register_ability_args', priority: 10 )]
	public function normalize_ability_args( array $args ): array {
		if ( ! isset( $args['input_schema'] ) ) {
			$args['input_schema'] = array(
				'type'       => 'object',
				'properties' => new EmptyJsonObject(),
			);
			return $args;
		}

		$args['input_schema'] = SchemaNormalizer::normalize( $args['input_schema'] );
		return $args;
	}
}
