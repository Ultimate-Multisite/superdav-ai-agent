<?php

declare(strict_types=1);
/**
 * Abstract Ability base class for GratisAiAgent abilities.
 *
 * Extends WP_Ability (WordPress 7.0+ core) with typed abstract methods
 * for defining abilities in a structured, OOP style.
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
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for GratisAiAgent abilities.
 *
 * Extends WP_Ability (WordPress 7.0+ core). Subclasses must implement the
 * abstract methods: label(), description(), category(), input_schema(),
 * output_schema(), execute_callback(), permission_callback(), and meta().
 *
 * @since 1.0.0
 */
abstract class AbstractAbility extends \WP_Ability {

	/**
	 * Constructor.
	 *
	 * Builds the WP_Ability args array from the abstract method implementations
	 * and delegates to the parent constructor.
	 *
	 * When registered via wp_register_ability() with 'ability_class', the registry
	 * passes the registration 'label' and 'description' as $properties, which take
	 * precedence over the abstract method return values. When instantiated directly
	 * (e.g. in tests), the abstract methods provide the values.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $name       The namespaced ability name (e.g. 'gratis-ai-agent/memory-save').
	 * @param array<string,mixed> $properties Optional overrides. Supports 'label' and 'description'.
	 */
	public function __construct( string $name, array $properties = array() ) {
		parent::__construct(
			$name,
			array(
				'label'               => ! empty( $properties['label'] ) ? $properties['label'] : $this->label(),
				'description'         => ! empty( $properties['description'] ) ? $properties['description'] : $this->description(),
				'category'            => $this->category(),
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'execute_callback'    => array( $this, 'execute_callback' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'meta'                => $this->meta(),
			)
		);
	}

	/**
	 * Prepares and validates the ability properties.
	 *
	 * Overrides WP_Ability::prepare_properties() to inject label and description
	 * from the abstract methods when they are not provided via $args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $args The ability args array.
	 * @return array<string,mixed> The validated and prepared properties.
	 */
	protected function prepare_properties( array $args ): array {
		if ( empty( $args['label'] ) ) {
			$args['label'] = $this->label();
		}
		if ( empty( $args['description'] ) ) {
			$args['description'] = $this->description();
		}
		return parent::prepare_properties( $args );
	}

	/**
	 * Returns the human-readable label for this ability.
	 *
	 * @since 1.0.0
	 *
	 * @return string Non-empty human-readable label.
	 */
	abstract protected function label(): string;

	/**
	 * Returns the description for this ability.
	 *
	 * @since 1.0.0
	 *
	 * @return string Non-empty description string.
	 */
	abstract protected function description(): string;

	/**
	 * Returns the category slug for this ability.
	 *
	 * @since 1.0.0
	 *
	 * @return string The category slug (must be registered via wp_register_ability_category()).
	 */
	protected function category(): string {
		return 'gratis-ai-agent';
	}

	/**
	 * Returns the JSON Schema definition for the ability's input.
	 *
	 * Return an empty array if the ability accepts no input.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> JSON Schema array.
	 */
	abstract protected function input_schema(): array;

	/**
	 * Returns the JSON Schema definition for the ability's output.
	 *
	 * Return an empty array if the output is unstructured.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> JSON Schema array.
	 */
	abstract protected function output_schema(): array;

	/**
	 * Executes the ability with the given input.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The validated input data, or null if no input schema is defined.
	 * @return mixed|\WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	abstract protected function execute_callback( $input );

	/**
	 * Checks whether the current user has permission to execute this ability.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The input data (same as passed to execute_callback).
	 * @return bool|\WP_Error True if permitted, false or WP_Error otherwise.
	 */
	abstract protected function permission_callback( $input );

	/**
	 * Returns the meta array for this ability.
	 *
	 * Should include 'annotations' (readonly, destructive, idempotent) and
	 * optionally 'show_in_rest'.
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

	/**
	 * Executes the ability callback directly, bypassing permission checks.
	 *
	 * Used by static proxy methods for backwards-compatible test access.
	 * Unlike execute(), this method does not check permissions, validate input
	 * against the schema, or fire WordPress hooks. It calls execute_callback()
	 * directly, matching the behaviour of the old static method approach.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The input data.
	 * @return mixed|\WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	public function run( $input = null ) {
		return $this->execute_callback( $input );
	}
}
