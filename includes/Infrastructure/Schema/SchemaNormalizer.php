<?php
/**
 * JSON Schema normaliser for WordPress Abilities API input schemas.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Infrastructure\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coerces arbitrary (often third-party) ability input schemas into a shape that
 * passes strict JSON Schema draft-2020-12 validation used by Anthropic, OpenAI
 * and Ollama tool-use validators.
 *
 * History: we hit 400-level errors from Anthropic when third-party abilities
 * like `core/get-user-info` or `mcp-adapter/discover-abilities` registered
 * `input_schema => []`. Ollama was even more brittle — it crashed with
 * "Value looks like object, but can't find closing '}' symbol" when the
 * `properties` key encoded as JSON `[]` instead of `{}`, which happens when
 * the source was an empty PHP array.
 *
 * The class is intentionally stateless and static so it can be called both
 * from a DI-managed filter handler and from procedural code paths (e.g. the
 * CLI benchmark suite) without instantiating the container.
 */
final class SchemaNormalizer {

	/**
	 * Recursively normalise a JSON schema so it satisfies Anthropic's
	 * draft-2020-12 tool-use validator.
	 *
	 * Behaviour summary:
	 *  - Empty schemas become `{ type: object, properties: {} }`.
	 *  - Object schemas inferred by `properties` / `required` gain `type`.
	 *  - Empty `properties` / `items` arrays are coerced to `stdClass` so
	 *    JSON encodes them as `{}` rather than `[]`.
	 *  - Draft-04 boolean `required` on child properties is promoted to the
	 *    parent `required` array (draft-2020-12 form).
	 *  - Array schemas without `items` receive a permissive `{}` placeholder
	 *    (OpenAI rejects bare `"type":"array"`).
	 *  - `default: []` on object schemas is stripped (mis-types the schema).
	 *  - Recurses into `anyOf` / `oneOf` / `allOf`.
	 *
	 * @param mixed $schema Schema node (array or scalar).
	 * @return mixed Normalised schema.
	 */
	public static function normalize( mixed $schema ): mixed {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		if ( empty( $schema ) ) {
			return array(
				'type'       => 'object',
				'properties' => (object) array(),
			);
		}

		if ( ! isset( $schema['type'] ) && ( isset( $schema['properties'] ) || isset( $schema['required'] ) ) ) {
			$schema['type'] = 'object';
		}

		if ( array_key_exists( 'properties', $schema ) ) {
			$schema = self::normalize_properties( $schema );
		}

		if ( isset( $schema['type'] ) && 'object' === $schema['type'] && ! isset( $schema['properties'] ) ) {
			$schema['properties'] = (object) array();
		}

		if ( array_key_exists( 'items', $schema ) && is_array( $schema['items'] ) ) {
			if ( empty( $schema['items'] ) || array_is_list( $schema['items'] ) ) {
				$schema['items'] = (object) array();
			} else {
				$schema['items'] = self::normalize( $schema['items'] );
			}
		}

		if ( isset( $schema['type'] ) && 'array' === $schema['type'] && ! array_key_exists( 'items', $schema ) ) {
			$schema['items'] = (object) array();
		}

		if ( isset( $schema['default'] ) && is_array( $schema['default'] ) && empty( $schema['default'] ) ) {
			unset( $schema['default'] );
		}

		foreach ( array( 'anyOf', 'oneOf', 'allOf' ) as $combiner ) {
			if ( ! isset( $schema[ $combiner ] ) || ! is_array( $schema[ $combiner ] ) ) {
				continue;
			}
			foreach ( $schema[ $combiner ] as $k => $sub ) {
				$schema[ $combiner ][ $k ] = self::normalize( $sub );
			}
		}

		return $schema;
	}

	/**
	 * Normalise the `properties` sub-key, promoting draft-04 `required` flags.
	 *
	 * Extracted from {@see self::normalize()} so the main method stays under
	 * the WordPress Coding Standards complexity threshold.
	 *
	 * @param array<string,mixed> $schema Schema with a `properties` key.
	 * @return array<string,mixed> Schema with normalised properties and an
	 *                             updated `required` array if applicable.
	 */
	private static function normalize_properties( array $schema ): array {
		$props = $schema['properties'];

		if ( is_array( $props ) && empty( $props ) ) {
			$schema['properties'] = (object) array();
			return $schema;
		}

		if ( ! is_array( $props ) ) {
			return $schema;
		}

		$promoted_required = array();
		foreach ( $props as $k => $v ) {
			if ( is_array( $v ) && array_key_exists( 'required', $v ) && is_bool( $v['required'] ) ) {
				if ( true === $v['required'] ) {
					$promoted_required[] = $k;
				}
				unset( $v['required'] );
			}
			$props[ $k ] = self::normalize( $v );
		}
		$schema['properties'] = $props;

		if ( ! empty( $promoted_required ) ) {
			$existing           = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();
			$schema['required'] = array_values( array_unique( array_merge( $existing, $promoted_required ) ) );
		}

		return $schema;
	}
}
