<?php
/**
 * Automation schedule enum.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Enums;

/**
 * Valid schedule intervals for automated tasks.
 */
enum Schedule: string {

	case Hourly     = 'hourly';
	case TwiceDaily = 'twicedaily';
	case Daily      = 'daily';
	case Weekly     = 'weekly';

	/**
	 * Get all schedule values as an array.
	 *
	 * @return array<string>
	 */
	public static function values(): array {
		return array_column( self::cases(), 'value' );
	}

	/**
	 * Check if a value is a valid schedule.
	 *
	 * @param string $value The schedule string to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid( string $value ): bool {
		return in_array( $value, self::values(), true );
	}
}
