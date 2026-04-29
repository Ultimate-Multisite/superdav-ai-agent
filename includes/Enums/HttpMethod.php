<?php
/**
 * HTTP method enum.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid HTTP methods for custom HTTP tools.
 */
enum HttpMethod: string {

	case Get    = 'GET';
	case Post   = 'POST';
	case Put    = 'PUT';
	case Patch  = 'PATCH';
	case Delete = 'DELETE';

	/**
	 * Get all method values as an array.
	 *
	 * @return array<string>
	 */
	public static function values(): array {
		return array_column( self::cases(), 'value' );
	}

	/**
	 * Check if a value is a valid HTTP method.
	 *
	 * @param string $value The HTTP method string to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid( string $value ): bool {
		return in_array( strtoupper( $value ), self::values(), true );
	}
}
