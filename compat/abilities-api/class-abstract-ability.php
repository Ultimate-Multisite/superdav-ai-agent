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
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base implementation for a WordPress Ability.
 *
 * Subclasses must implement:
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
	 * @since 1.0.0
	 *
	 * @param string              $name       The namespaced ability name (e.g. 'gratis-ai-agent/memory-save').
	 * @param array<string,mixed> $properties Optional overrides. Supports 'label' and 'description'.
	 */
	public function __construct( string $name, array $properties = array() ) {
		parent::__construct(
			$name,
			array(
				'label'               => $properties['label'] ?? '',
				'description'         => $properties['description'] ?? '',
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
}
