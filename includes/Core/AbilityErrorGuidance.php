<?php

declare(strict_types=1);
/**
 * AbilityErrorGuidance
 *
 * Builds the model-visible `next_steps` list attached to failed ability
 * responses. An error message alone tells the agent what went wrong; the
 * next steps tell it what to do about it, so it can self-correct or stop
 * instead of guessing and spinning on the same failing call.
 *
 * Resolution order:
 *
 *  1. Explicit steps: an ability may return `array( 'next_steps' => array(...) )`
 *     in its WP_Error data. These win because the ability knows its own
 *     recovery path (for example "call sd-ai-agent/list-menus first").
 *  2. Code-pattern defaults: ability error codes follow stable naming
 *     conventions (`*_not_found`, `*_not_configured`, `*_forbidden`, ...), so a
 *     small ordered rule table covers hundreds of codes without per-ability edits.
 *
 * Only the dedicated `next_steps` key is read from error data. Other keys may
 * carry implementation detail or secrets and are never copied.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbilityErrorGuidance {

	/**
	 * Maximum number of steps returned for one failure.
	 */
	public const MAX_STEPS = 4;

	/**
	 * Maximum length of a single step, in characters.
	 */
	public const MAX_STEP_LENGTH = 300;

	/**
	 * Ordered default rules. The first rule whose token sequence appears in the
	 * error code wins, so permission and configuration rules sit before the
	 * broader `invalid` / `failed` rules.
	 *
	 * Each pattern is matched against the code wrapped in underscores
	 * (`_code_`), so `not_found` matches `post_not_found` but not `knot_foundry`.
	 *
	 * @var list<array{patterns: list<string>, steps: list<string>}>
	 */
	private const RULES = array(
		array(
			'patterns' => array( 'not_configured', 'missing_credentials', 'missing_api_key', 'no_api_key', 'credentials_missing' ),
			'steps'    => array(
				'This integration is not set up. Do not retry the call.',
				'Tell the user which service needs configuring (name it from the error message) and that an administrator must add the credentials in the plugin settings.',
				'Continue with any part of the task that does not need this service.',
			),
		),
		array(
			'patterns' => array( 'forbidden', 'permission', 'permissions', 'denied', 'insufficient', 'unauthorized', 'unauthorised', 'not_allowed', 'cannot' ),
			'steps'    => array(
				'The current user is not allowed to do this. Do not retry with the same or reworded arguments.',
				'Use a read-only ability to gather what the user needs, or tell the user which capability or role is required.',
			),
		),
		array(
			'patterns' => array( 'disabled', 'turned_off' ),
			'steps'    => array(
				'This feature is switched off on this site. Do not retry.',
				'Tell the user it must be enabled in the plugin settings, then continue with unaffected parts of the task.',
			),
		),
		array(
			'patterns' => array( 'not_found', 'no_results', 'does_not_exist', 'unknown' ),
			'steps'    => array(
				'The target was not found. Do not retry with the same identifier.',
				'Look up the correct identifier first with a list or search ability (for example sd-ai-agent/ability-search to find the right ability, or the matching list/get ability for the content type), then retry with the real value.',
			),
		),
		array(
			'patterns' => array( 'exists', 'duplicate', 'conflict', 'changed', 'stale' ),
			'steps'    => array(
				'The target already exists or changed since you last read it. Do not repeat the create or write call.',
				'Fetch the current state with a get or list ability, then update the existing item or retry with the fresh revision.',
			),
		),
		array(
			'patterns' => array( 'missing', 'required', 'empty', 'not_provided', 'no_input' ),
			'steps'    => array(
				'A required argument is missing or empty. Name the missing field from the error message.',
				'Check the ability input_schema, supply a real value for that field (ask the user if you cannot infer it), and retry once with the complete arguments.',
			),
		),
		array(
			'patterns' => array( 'invalid', 'unsupported', 'malformed', 'too_long', 'too_large', 'out_of_range' ),
			'steps'    => array(
				'An argument has the wrong value or format. Do not retry with the same arguments.',
				'Compare your arguments with the ability input_schema, change the value the error message points at, and retry once.',
			),
		),
		array(
			'patterns' => array( 'timeout', 'timed_out', 'unavailable', 'rate_limit', 'rate_limited', 'too_many' ),
			'steps'    => array(
				'The failure looks temporary or external. Retry at most once, and only after other useful work.',
				'If it fails again, report it to the user and continue with other parts of the task instead of looping on this call.',
			),
		),
		array(
			'patterns' => array( 'failed', 'error', 'exception' ),
			'steps'    => array(
				'The operation did not complete. Read the error message for the cause before acting.',
				'Retry once only if the cause is fixable by changing the arguments. Otherwise report the failure to the user and move on.',
			),
		),
	);

	/**
	 * Generic fallback used when no rule matches.
	 *
	 * @var list<string>
	 */
	private const FALLBACK = array(
		'Read the error message, change what it points at, and retry at most once. If it fails again, report it to the user instead of repeating the call.',
	);

	/**
	 * Resolve the next steps for a failed ability call.
	 *
	 * @param string $error_code Error code returned by the ability.
	 * @param mixed  $error_data WP_Error data; only a `next_steps` list is read.
	 * @return list<string> Between 1 and MAX_STEPS plain-text steps.
	 */
	public static function for_error( string $error_code, $error_data = null ): array {
		if ( is_array( $error_data ) && isset( $error_data['next_steps'] ) ) {
			$explicit = self::sanitize_steps( $error_data['next_steps'] );
			if ( array() !== $explicit ) {
				return $explicit;
			}
		}

		return self::default_steps( $error_code );
	}

	/**
	 * Add `next_steps` to a model-visible error payload.
	 *
	 * Payloads that already carry a corrective `hint` (input-validation failures)
	 * or an explicit `next_steps` list are returned unchanged so the model does
	 * not receive two competing instructions.
	 *
	 * @param array<string, mixed> $response_data Error payload built by the resolver.
	 * @param string               $error_code    Error code returned by the ability.
	 * @param mixed                $error_data    WP_Error data, if any.
	 * @return array<string, mixed> Payload with `next_steps` when applicable.
	 */
	public static function enrich( array $response_data, string $error_code, $error_data = null ): array {
		if ( isset( $response_data['next_steps'] ) || isset( $response_data['hint'] ) ) {
			return $response_data;
		}

		$response_data['next_steps'] = self::for_error( $error_code, $error_data );

		return $response_data;
	}

	/**
	 * Match an error code against the default rule table.
	 *
	 * @param string $error_code Error code returned by the ability.
	 * @return list<string> Default steps for the code.
	 */
	private static function default_steps( string $error_code ): array {
		$haystack = '_' . strtolower( str_replace( '-', '_', $error_code ) ) . '_';

		foreach ( self::RULES as $rule ) {
			foreach ( $rule['patterns'] as $pattern ) {
				if ( str_contains( $haystack, '_' . $pattern . '_' ) ) {
					return $rule['steps'];
				}
			}
		}

		return self::FALLBACK;
	}

	/**
	 * Reduce ability-supplied steps to a bounded list of plain strings.
	 *
	 * @param mixed $steps Raw `next_steps` value from WP_Error data.
	 * @return list<string> Sanitised steps, possibly empty.
	 */
	private static function sanitize_steps( $steps ): array {
		if ( is_string( $steps ) ) {
			$steps = array( $steps );
		}
		if ( ! is_array( $steps ) ) {
			return array();
		}

		$clean = array();
		foreach ( $steps as $step ) {
			if ( ! is_string( $step ) ) {
				continue;
			}

			$step = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $step ) ) ?? '' );
			if ( '' === $step ) {
				continue;
			}

			$clean[] = mb_substr( $step, 0, self::MAX_STEP_LENGTH );
			if ( count( $clean ) >= self::MAX_STEPS ) {
				break;
			}
		}

		return $clean;
	}
}
