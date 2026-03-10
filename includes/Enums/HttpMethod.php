<?php
/**
 * HTTP method enum.
 *
 * @package AiAgent
 */

declare(strict_types=1);

namespace AiAgent\Enums;

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
	 */
	public static function isValid( string $value ): bool {
		return in_array( strtoupper( $value ), self::values(), true );
	}
}
