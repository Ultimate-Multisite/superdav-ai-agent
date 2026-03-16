<?php

declare(strict_types=1);
/**
 * Abstract Ability base class for GratisAiAgent abilities.
 *
 * Provides a namespace-aware wrapper around GratisAiAgent_Abstract_Ability
 * (from the compat layer) so that plugin abilities can extend it using
 * PSR-4 namespaced class names.
 *
 * This mirrors the WordPress\AI\Abstracts\Abstract_Ability pattern from the
 * WordPress/ai plugin (https://github.com/WordPress/ai), enabling upstream
 * compatibility: when WordPress core ships Abstract_Ability, our abilities
 * can be migrated to extend it directly with minimal changes.
 *
 * Usage:
 *
 *     class MyAbility extends AbstractAbility {
 *         protected function category(): string { return 'gratis-ai-agent'; }
 *         protected function input_schema(): array { return [...]; }
 *         protected function output_schema(): array { return [...]; }
 *         protected function execute_callback( $input ) { ... }
 *         protected function permission_callback( $input ) { ... }
 *         protected function meta(): array { return [...]; }
 *     }
 *
 *     // Register via wp_register_ability() with ability_class:
 *     wp_register_ability( 'gratis-ai-agent/my-ability', [
 *         'label'         => __( 'My Ability', 'gratis-ai-agent' ),
 *         'description'   => __( 'Does something.', 'gratis-ai-agent' ),
 *         'ability_class' => MyAbility::class,
 *     ] );
 *
 * @package GratisAiAgent
 * @since 1.0.0
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for GratisAiAgent abilities.
 *
 * Extends GratisAiAgent_Abstract_Ability (compat layer) which itself extends
 * WP_Ability (core or compat). Subclasses must implement the five abstract
 * methods: category(), input_schema(), output_schema(), execute_callback(),
 * permission_callback(), and meta().
 *
 * @since 1.0.0
 */
abstract class AbstractAbility extends \GratisAiAgent_Abstract_Ability {

	/**
	 * Default category for all GratisAiAgent abilities.
	 *
	 * Subclasses may override this to use a different category.
	 *
	 * @since 1.0.0
	 *
	 * @return string The category slug.
	 */
	protected function category(): string {
		return 'gratis-ai-agent';
	}

	/**
	 * Default meta for GratisAiAgent abilities.
	 *
	 * Subclasses should override this to provide ability-specific annotations
	 * (readonly, destructive, idempotent) and show_in_rest settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Meta array.
	 */
	protected function meta(): array {
		return array(
			'annotations'  => array(
				'readonly'    => null,
				'destructive' => null,
				'idempotent'  => null,
			),
			'show_in_rest' => false,
		);
	}

	/**
	 * Returns the configured model ID from plugin settings, or empty string if none.
	 *
	 * Used by AI-powered abilities to pass a model preference to wp_ai_client_prompt().
	 *
	 * @since 1.0.0
	 *
	 * @return string Model ID or empty string.
	 */
	protected function get_configured_model(): string {
		if ( class_exists( \GratisAiAgent\Core\Settings::class ) ) {
			$model = \GratisAiAgent\Core\Settings::get( 'default_model' );
			return is_string( $model ) ? $model : '';
		}
		return '';
	}
}
