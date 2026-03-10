<?php
/**
 * Automation schedule enum.
 *
 * @package AiAgent
 */

declare(strict_types=1);

namespace AiAgent\Enums;

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
	 */
	public static function isValid( string $value ): bool {
		return in_array( $value, self::values(), true );
	}
}
