<?php
/**
 * Tool type enum.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid custom tool types.
 */
enum ToolType: string {

	case Http   = 'http';
	case Action = 'action';
	case Cli    = 'cli';

	/**
	 * Get all type values as an array.
	 *
	 * @return array<string>
	 */
	public static function values(): array {
		return array_column( self::cases(), 'value' );
	}

	/**
	 * Check if a value is a valid tool type.
	 *
	 * @param string $value The tool type string to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid( string $value ): bool {
		return in_array( $value, self::values(), true );
	}
}
