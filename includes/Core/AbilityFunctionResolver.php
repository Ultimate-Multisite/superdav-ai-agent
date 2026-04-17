<?php

declare(strict_types=1);
/**
 * Ability function resolver wrapper.
 *
 * Subclasses the WordPress core resolver to fix one paper cut: when the model
 * issues a tool call with no arguments (e.g. for a parameterless ability like
 * `gratis-ai-agent/get-plugins`), the parent resolver passes `null` to
 * `WP_Ability::execute()`, which fails schema validation with
 * `input is not of type object`. We pass an empty associative array instead
 * so object-typed schemas with no required properties accept the call.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Tools\AbilityUsageTracker;
use GratisAiAgent\Tools\ModelHealthTracker;
use GratisAiAgent\Tools\SchemaExampleBuilder;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbilityFunctionResolver extends \WP_AI_Client_Ability_Function_Resolver {

	/**
	 * Allowed ability names — own copy because the parent's is private.
	 *
	 * @var array<string, true>
	 */
	private array $allowed = array();

	/**
	 * @param \WP_Ability|string ...$abilities Allowed abilities (objects or names).
	 */
	public function __construct( ...$abilities ) {
		parent::__construct( ...$abilities );

		foreach ( $abilities as $ability ) {
			if ( $ability instanceof \WP_Ability ) {
				$this->allowed[ $ability->get_name() ] = true;
			} elseif ( is_string( $ability ) ) {
				$this->allowed[ $ability ] = true;
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Reimplements the parent so that empty arg lists become `[]` rather
	 * than `null`. The parent's `! empty( $args ) ? $args : null` clause is
	 * the source of the validation failure for parameterless abilities.
	 */
	public function execute_ability( FunctionCall $call ): FunctionResponse {
		$function_name = $call->getName() ?? 'unknown';
		$function_id   = $call->getId() ?? 'unknown';

		if ( ! $this->is_ability_call( $call ) ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => __( 'Not an ability function call', 'gratis-ai-agent' ),
					'code'  => 'invalid_ability_call',
				)
			);
		}

		$ability_name = self::function_name_to_ability_name( $function_name );

		if ( ! isset( $this->allowed[ $ability_name ] ) ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => sprintf(
						/* translators: %s: ability name */
						__( 'Ability "%s" was not specified in the allowed abilities list.', 'gratis-ai-agent' ),
						$ability_name
					),
					'code'  => 'ability_not_allowed',
				)
			);
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability instanceof \WP_Ability ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => sprintf(
						/* translators: %s: ability name */
						__( 'Ability "%s" not found', 'gratis-ai-agent' ),
						$ability_name
					),
					'code'  => 'ability_not_found',
				)
			);
		}

		$args = $call->getArgs();

		// The AI Client SDK's FunctionCall::getArgs() returns `mixed`.
		// Provider JSON decoders may return a top-level stdClass for
		// object-typed arguments. Convert it to an array instead of
		// discarding all arguments (the previous `array()` fallback).
		if ( $args instanceof \stdClass ) {
			$args = (array) $args;
		} elseif ( ! is_array( $args ) ) {
			$args = array();
		}

		// Recursively convert any remaining nested stdClass objects to
		// associative arrays. Abilities expect plain PHP arrays throughout.
		$args = self::normalize_args( $args );

		// @phpstan-ignore-next-line — execute() exists at runtime in WP 7.0.
		$result = $ability->execute( $args );

		if ( is_wp_error( $result ) ) {
			$error_code    = (string) $result->get_error_code();
			$response_data = array(
				'error' => $result->get_error_message(),
				'code'  => $error_code,
			);

			// For input-validation failures, inline the input_schema so the
			// model can self-correct on the next turn instead of guessing
			// the same arguments forever. Also feeds model-health telemetry
			// so weak models accumulate a worse score over time.
			if ( 'ability_invalid_input' === $error_code ) {
				// @phpstan-ignore-next-line — get_input_schema() exists at runtime in WP 7.0.
				$schema                        = $ability->get_input_schema();
				$response_data['input_schema'] = $schema;

				// Pull the specific missing field name(s) from the error
				// message and synthesise a copy-paste-ready example. This
				// is the single biggest weak-model unblocker.
				$missing                                  = SchemaExampleBuilder::extract_missing_required( (string) $result->get_error_message() );
				$response_data['missing_required_fields'] = $missing;
				$response_data['example_arguments']       = SchemaExampleBuilder::build_example( $schema );
				$response_data['hint']                    = 'Copy `example_arguments`, replace each `<placeholder>` with a real value, then call ability-call again. Do not retry with empty arguments.';

				ModelHealthTracker::record_validation_error();
			}

			// Per-call spin detection: after the second identical failure
			// (same ability + same args + same error code), replace the
			// hint with a hard nudge that tells the model to stop and
			// either supply different args or call a different ability.
			$count = IdenticalFailureTracker::record( $ability_name, $args, $error_code );
			if ( IdenticalFailureTracker::should_nudge( $count ) ) {
				$schema_for_nudge       = $response_data['input_schema'] ?? $ability->get_input_schema();
				$response_data['nudge'] = IdenticalFailureTracker::nudge_message( $ability_name, $schema_for_nudge );
				ModelHealthTracker::record_nudge();
			}

			return new FunctionResponse(
				$function_id,
				$function_name,
				$response_data
			);
		}

		// Record successful usage so the auto-discovery layer can promote
		// frequently-used abilities into Tier 1 on subsequent runs, and
		// improve the current model's health score.
		AbilityUsageTracker::record( $ability_name );
		ModelHealthTracker::record_success();

		return new FunctionResponse( $function_id, $function_name, $result );
	}

	/**
	 * Recursively convert stdClass objects to associative arrays.
	 *
	 * AI provider JSON decoders may return nested stdClass objects for
	 * function-call arguments. WordPress abilities expect plain arrays.
	 *
	 * @param array<string, mixed> $args Function call arguments.
	 * @return array<string, mixed> Normalized arguments with all stdClass converted.
	 */
	private static function normalize_args( array $args ): array {
		foreach ( $args as $key => $value ) {
			if ( $value instanceof \stdClass ) {
				$args[ $key ] = self::normalize_args( (array) $value );
			} elseif ( is_array( $value ) ) {
				$args[ $key ] = self::normalize_args( $value );
			}
		}
		return $args;
	}
}
