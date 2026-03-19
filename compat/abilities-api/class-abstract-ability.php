<?php
/**
 * Abstract Ability base class.
 *
 * Mirrors the WordPress\AI\Abstracts\Abstract_Ability pattern from the
 * WordPress/ai plugin (https://github.com/WordPress/ai), adapted for use
 * in the compat layer so it is available on WordPress < 7.0.
 *
 * Extend this class to define a self-contained ability with typed methods
 * instead of passing closures to wp_register_ability().
 *
 * @package GratisAiAgent
 * @since 1.0.0
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base implementation for a WordPress Ability.
 *
 * Subclasses must implement:
 *  - label(): string
 *  - description(): string
 *  - category(): string
 *  - input_schema(): array
 *  - output_schema(): array
 *  - execute_callback( $input ): mixed|WP_Error
 *  - permission_callback( $input ): bool|WP_Error
 *  - meta(): array
 *
 * @since 1.0.0
 */
abstract class GratisAiAgent_Abstract_Ability extends WP_Ability {

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
	 * from the abstract methods when they are not provided via $args. This supports
	 * direct instantiation (e.g. in tests) without passing $properties to the
	 * constructor, and ensures compatibility with WordPress 6.9.x which always
	 * requires label and description to be non-empty strings.
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
	 * Used when the ability is instantiated directly (e.g. in tests) without
	 * a 'label' property override. When registered via wp_register_ability()
	 * with 'ability_class', the registration label takes precedence.
	 *
	 * @since 1.0.0
	 *
	 * @return string Non-empty human-readable label.
	 */
	abstract protected function label(): string;

	/**
	 * Returns the description for this ability.
	 *
	 * Used when the ability is instantiated directly (e.g. in tests) without
	 * a 'description' property override. When registered via wp_register_ability()
	 * with 'ability_class', the registration description takes precedence.
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
	abstract protected function category(): string;

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
	abstract protected function meta(): array;

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
