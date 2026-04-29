<?php

declare(strict_types=1);
/**
 * SchemaExampleBuilder
 *
 * Helpers for turning a JSON-Schema input definition + a validation error
 * message into copy-paste-friendly hints that weak models can act on.
 *
 * Two operations:
 *
 *   • {@see build_example()} — walk an input_schema and produce a stub
 *     `example_arguments` object containing every required field, with
 *     `<type — description>` placeholder values that the model substitutes.
 *
 *   • {@see extract_missing_required()} — pull the names of the missing
 *     required fields out of a `WP_Ability::validate_input()` error
 *     message such as "username is a required property of input." so the
 *     model gets the most specific signal first.
 *
 * Used by AbilityFunctionResolver and ToolDiscovery::handle_ability_call to
 * enrich `ability_invalid_input` responses.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SchemaExampleBuilder {

	/**
	 * Walk an input_schema and produce a stub arguments object containing
	 * every required field with a placeholder value of the form
	 * `<{type} — {description}>`. Optional fields are omitted.
	 *
	 * Returns an empty array when the schema has no required fields, no
	 * properties, or is malformed.
	 *
	 * @param mixed $schema The ability input_schema (assoc array).
	 * @return array<string, string>
	 */
	public static function build_example( $schema ): array {
		if ( ! is_array( $schema ) ) {
			return array();
		}

		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

		if ( empty( $required ) ) {
			return array();
		}

		$example = array();
		foreach ( $required as $field ) {
			if ( ! is_string( $field ) || '' === $field ) {
				continue;
			}
			$prop = isset( $properties[ $field ] ) && is_array( $properties[ $field ] ) ? $properties[ $field ] : array();

			$type = isset( $prop['type'] ) ? $prop['type'] : 'value';
			if ( is_array( $type ) ) {
				$type = implode( '|', $type );
			} else {
				$type = (string) $type;
			}

			$desc = isset( $prop['description'] ) ? trim( (string) $prop['description'] ) : '';
			if ( strlen( $desc ) > 80 ) {
				$desc = substr( $desc, 0, 77 ) . '...';
			}

			// If the schema lists an enum, hint with the allowed values
			// instead of the description — much more actionable.
			if ( isset( $prop['enum'] ) && is_array( $prop['enum'] ) && ! empty( $prop['enum'] ) ) {
				$enum_summary = implode( '|', array_map( static fn( $v ) => (string) $v, array_slice( $prop['enum'], 0, 5 ) ) );
				$desc         = "one of: {$enum_summary}";
			}

			$example[ $field ] = '' !== $desc
				? "<{$type} — {$desc}>"
				: "<{$type}>";
		}

		return $example;
	}

	/**
	 * Extract the names of missing required fields from a validation
	 * error message produced by `WP_Ability::validate_input()`.
	 *
	 * Handles two phrasings the WP REST validator emits:
	 *   - "{field} is a required property of input."
	 *   - "{field} is a required property of {param}."
	 *
	 * Returns an empty array when no field names can be extracted.
	 *
	 * @param string $error_message The validation error message.
	 * @return string[]
	 */
	public static function extract_missing_required( string $error_message ): array {
		if ( '' === $error_message ) {
			return array();
		}

		$matches = array();
		// Match patterns like `xxx is a required property` (the "of input"
		// suffix is optional and depends on the validator path).
		if ( preg_match_all( '/`?([\w_-]+)`?\s+is\s+a\s+required\s+property/i', $error_message, $matches ) ) {
			if ( ! empty( $matches[1] ) ) {
				return array_values( array_unique( $matches[1] ) );
			}
		}

		return array();
	}
}
