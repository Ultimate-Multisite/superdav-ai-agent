<?php

declare(strict_types=1);
/**
 * Feature-flag registry.
 *
 * Each feature is backed by a PHP constant that site owners (or resellers)
 * can define in wp-config.php before the plugin loads. The constants default
 * to `true` so stock installations retain all functionality; setting one to
 * `false` disables the corresponding UI and REST surface.
 *
 * Defined constants (all default true):
 *  - GRATIS_AI_AGENT_FEATURE_BRANDING      — White-label / branding settings:
 *    agent name, brand colours, logo URL, greeting message.
 *  - GRATIS_AI_AGENT_FEATURE_ACCESS_CONTROL — Role-based access control:
 *    the Role Permissions manager and its /role-permissions REST routes.
 *
 * Usage example (wp-config.php):
 *   define( 'GRATIS_AI_AGENT_FEATURE_BRANDING', false );
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Features {

	/**
	 * Feature: white-label branding (agent name, colours, logo).
	 * Constant: GRATIS_AI_AGENT_FEATURE_BRANDING
	 */
	const BRANDING = 'branding';

	/**
	 * Feature: role-based access control (Role Permissions manager).
	 * Constant: GRATIS_AI_AGENT_FEATURE_ACCESS_CONTROL
	 */
	const ACCESS_CONTROL = 'access_control';

	/**
	 * Map of feature name → backing constant name.
	 *
	 * @var array<string, string>
	 */
	private const CONSTANT_MAP = array(
		self::BRANDING        => 'GRATIS_AI_AGENT_FEATURE_BRANDING',
		self::ACCESS_CONTROL  => 'GRATIS_AI_AGENT_FEATURE_ACCESS_CONTROL',
	);

	/**
	 * Check whether a feature is enabled.
	 *
	 * Returns `true` when the backing constant is not defined (default-on).
	 * Returns `(bool) CONSTANT_VALUE` when the constant is defined.
	 *
	 * @param string $feature One of the Features::* class constants.
	 * @return bool
	 */
	public static function is_enabled( string $feature ): bool {
		$constant = self::CONSTANT_MAP[ $feature ] ?? null;

		if ( null === $constant ) {
			// Unknown feature — fail open (enabled) to avoid breaking valid calls.
			return true;
		}

		if ( ! defined( $constant ) ) {
			// Constant not set by the site owner → default enabled.
			return true;
		}

		return (bool) constant( $constant );
	}

	/**
	 * Return a map of all features and their current enabled state.
	 *
	 * Suitable for serialising into REST responses or wp_localize_script data.
	 *
	 * @return array<string, bool>
	 */
	public static function all(): array {
		$result = array();
		foreach ( array_keys( self::CONSTANT_MAP ) as $feature ) {
			$result[ $feature ] = self::is_enabled( $feature );
		}
		return $result;
	}
}
