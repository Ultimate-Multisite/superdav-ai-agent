<?php

declare(strict_types=1);
/**
 * Registration facade for AI image generation abilities.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Abilities\ImageAbilities\GenerateImageAbility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registration facade and static proxy for the generate-image ability.
 *
 * @since 1.0.0
 */
class AiImageAbilities {

	/**
	 * Static proxy for backwards-compatible test access.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_generate( array $input = [] ) {
		$ability = new GenerateImageAbility( 'sd-ai-agent/generate-image' );
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all AI image abilities with the WordPress Abilities API.
	 */
	public static function register_abilities(): void {
		GenerateImageAbility::register();
	}
}
