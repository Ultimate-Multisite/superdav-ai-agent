<?php

declare(strict_types=1);
/**
 * Small validation boundary around untrusted remote MCP schemas and results.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpPolicy {

	/**
	 * @param array<mixed> $value Decoded object or persisted record.
	 * @return array<string,mixed>
	 */
	public static function record( array $value ): array {
		$record = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) ) {
				$record[ $key ] = $item;
			}
		}
		return $record;
	}

	/**
	 * @param mixed $value Untrusted data.
	 */
	public static function bounded( mixed $value, int $depth = 0 ): bool {
		if ( $depth > 8 ) {
			return false;
		}
		if ( is_string( $value ) ) {
			return strlen( $value ) <= 65536 && ! self::has_hostile_instructions( $value );
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > 100 ) {
				return false;
			}
			foreach ( $value as $key => $item ) {
				if ( ( is_string( $key ) && strlen( $key ) > 128 ) || ! self::bounded( $item, $depth + 1 ) ) {
					return false;
				}
			}
			return true;
		}
		return is_scalar( $value ) || null === $value;
	}

	/** Reject known role/tool forgeries; this is not a claim of complete injection detection. */
	public static function has_hostile_instructions( string $text ): bool {
		return 1 === preg_match( '/<\/?(?:system|developer|tool_call|function_call)\b|\[\/?INST\]|ignore\s+(?:all\s+|the\s+)?(?:previous|prior)\s+instructions|(?:system|developer)\s+(?:message|prompt)\s*:/i', $text );
	}

	/**
	 * @param array<string,mixed> $schema Discovered schema.
	 */
	public static function supported_schema( array $schema ): bool {
		$types = (array) ( $schema['type'] ?? 'object' );
		if ( ! self::bounded( $schema ) || array() === $types || array() !== array_diff( $types, array( 'object', 'array', 'string', 'integer', 'number', 'boolean', 'null' ) ) ) {
			return false;
		}
		// Deliberately use the WordPress REST schema dialect, not a bespoke engine.
		$allowed = array( 'type', 'properties', 'required', 'additionalProperties', 'items', 'enum', 'description', 'title', 'default', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'minItems', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties', 'format', 'pattern', 'patternProperties', 'examples', 'const', 'readOnly', 'anyOf', 'oneOf', '$schema' );
		foreach ( $schema as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				return false;
			}
			if ( 'properties' === $key ) {
				if ( ! is_array( $value ) ) {
					return false;
				}
				foreach ( $value as $property ) {
					if ( ! is_array( $property ) || ! self::supported_schema( $property ) ) {
						return false;
					}
				}
			}
			if ( in_array( $key, array( 'items', 'additionalProperties' ), true ) && is_array( $value ) && ! self::supported_schema( $value ) ) {
				return false;
			}
			if ( in_array( $key, array( 'anyOf', 'oneOf' ), true ) ) {
				if ( ! is_array( $value ) || empty( $value ) ) {
					return false;
				}
				foreach ( $value as $variant ) {
					if ( ! is_array( $variant ) || ! self::supported_schema( $variant ) ) {
						return false;
					}
				}
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $arguments Tool input.
	 * @param array<string,mixed> $schema Tool schema.
	 */
	public static function validate_arguments( array $arguments, array $schema ): true|WP_Error {
		$encoded = wp_json_encode( $arguments );
		if ( ! self::supported_schema( $schema ) || ! self::bounded( $arguments ) || ! is_string( $encoded ) || strlen( $encoded ) > 65536 ) {
			return self::error( 'unsupported_schema', __( 'This tool uses unsupported or oversized input. Review the server’s tool schema.', 'superdav-ai-agent' ) );
		}
		$valid = rest_validate_value_from_schema( $arguments, $schema, 'arguments' );
		return is_wp_error( $valid ) ? self::error( 'invalid_arguments', __( 'The tool arguments do not match its input schema.', 'superdav-ai-agent' ) ) : true;
	}

	/**
	 * @param array<string,mixed> $result Remote tools/call envelope.
	 */
	public static function validate_result( array $result ): true|WP_Error {
		$json = wp_json_encode( $result );
		if ( ! self::bounded( $result ) || ! is_string( $json ) || strlen( $json ) > 65536 || ! isset( $result['content'] ) || ! is_array( $result['content'] ) || ! array_is_list( $result['content'] ) ) {
			return self::error( 'invalid_result', __( 'The server returned unsupported, oversized or unsafe tool output.', 'superdav-ai-agent' ) );
		}
		foreach ( $result['content'] as $part ) {
			if ( ! is_array( $part ) || 'text' !== ( $part['type'] ?? '' ) || ! is_string( $part['text'] ?? null ) ) {
				return self::error( 'unsupported_result', __( 'This connection supports text and structured tool results, not embedded media or client actions.', 'superdav-ai-agent' ) );
			}
		}
		if ( isset( $result['isError'] ) && ! is_bool( $result['isError'] ) ) {
			return self::error( 'invalid_result', __( 'The server returned an invalid tool result.', 'superdav-ai-agent' ) );
		}
		if ( isset( $result['structuredContent'] ) && ! is_array( $result['structuredContent'] ) ) {
			return self::error( 'invalid_result', __( 'The server returned invalid structured content.', 'superdav-ai-agent' ) );
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $result Validated remote data.
	 * @param array<string,mixed> $credentials Internal credentials, never returned.
	 * @return array<string,mixed>
	 */
	public static function redact_credentials( array $result, array $credentials ): array {
		$secrets = array();
		foreach ( array( 'value', 'refresh_token', 'client_secret' ) as $key ) {
			if ( is_string( $credentials[ $key ] ?? null ) && '' !== $credentials[ $key ] ) {
				$secrets[] = $credentials[ $key ];
			}
		}
		foreach ( $result as $key => $value ) {
			$result[ $key ] = self::redact_value( $value, $secrets );
		}
		return $result;
	}

	/**
	 * @param mixed    $value Remote value to scrub.
	 * @param string[] $secrets Known local credential values.
	 */
	private static function redact_value( mixed $value, array $secrets ): mixed {
		if ( is_string( $value ) ) {
			return str_replace( $secrets, '[redacted]', $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::redact_value( $item, $secrets );
			}
		}
		return $value;
	}

	private static function error( string $code, string $message ): WP_Error {
		return new WP_Error( 'sd_ai_agent_remote_mcp_' . $code, $message );
	}
}
