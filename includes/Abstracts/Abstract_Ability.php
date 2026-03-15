<?php

declare(strict_types=1);
/**
 * Abstract Ability base class.
 *
 * Provides a class-per-ability OOP pattern that mirrors the Abstract_Ability
 * interface used in the WordPress/ai experiments plugin. Concrete subclasses
 * implement the abstract methods and are registered via
 * {@see Abstract_Ability::register()}.
 *
 * ## Usage
 *
 * Extend this class and implement the abstract methods, then register
 * the ability on the `wp_abilities_api_init` action:
 *
 *     class My_Ability extends Abstract_Ability {
 *         protected function ability_name(): string {
 *             return 'my-plugin/my-ability';
 *         }
 *         protected function ability_label(): string {
 *             return __( 'My Ability', 'my-plugin' );
 *         }
 *         protected function ability_description(): string {
 *             return __( 'Does something useful.', 'my-plugin' );
 *         }
 *         protected function ability_category(): string {
 *             return 'my-plugin';
 *         }
 *         protected function input_schema(): array {
 *             return [];
 *         }
 *         protected function output_schema(): array {
 *             return [];
 *         }
 *         protected function execute_callback( $input = null ) {
 *             return [ 'result' => 'ok' ];
 *         }
 *         protected function permission_callback( $input = null ) {
 *             return current_user_can( 'manage_options' );
 *         }
 *         protected function meta(): array {
 *             return [];
 *         }
 *     }
 *
 *     add_action( 'wp_abilities_api_init', function() {
 *         ( new My_Ability() )->register();
 *     } );
 *
 * @package AiAgent\Abstracts
 * @since   1.0.0
 */

namespace AiAgent\Abstracts;

use WP_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base implementation for a WordPress Ability.
 *
 * Mirrors the Abstract_Ability pattern from the WordPress/ai experiments plugin
 * (https://github.com/WordPress/ai) so that abilities written for that plugin
 * can be ported to this plugin with minimal changes.
 *
 * Method naming uses `ability_*` prefixes for the abstract interface methods
 * to avoid conflicts with the public getter methods inherited from WP_Ability
 * (get_label, get_description, get_category).
 *
 * The constructor signature matches WP_Ability (string $name, array $args) so
 * that the WP_Abilities_Registry can instantiate subclasses via the
 * `ability_class` argument. Subclass-defined methods take precedence over any
 * values passed in $args, ensuring the class is the single source of truth.
 *
 * @since 1.0.0
 */
abstract class Abstract_Ability extends WP_Ability {

	/**
	 * Constructor.
	 *
	 * Merges the subclass-defined properties into $args before delegating to
	 * the parent WP_Ability constructor. This allows the WP_Abilities_Registry
	 * to instantiate subclasses via `new $ability_class($name, $args)` while
	 * still using the subclass methods as the authoritative source of truth.
	 *
	 * Do not call this constructor directly. Use {@see Abstract_Ability::register()}
	 * to register the ability with the Abilities API.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $_name Ignored; ability_name() is the authoritative source.
	 * @param array<string, mixed> $_args Ignored; subclass methods are the authoritative source.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	// @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter
	public function __construct( string $_name = '', array $_args = [] ) {
		parent::__construct(
			$this->ability_name(),
			[
				'label'               => $this->ability_label(),
				'description'         => $this->ability_description(),
				'category'            => $this->ability_category(),
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'execute_callback'    => [ $this, 'execute_callback' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'meta'                => $this->meta(),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Abstract methods — must be implemented by concrete subclasses.
	// -------------------------------------------------------------------------

	/**
	 * Returns the fully-namespaced ability name.
	 *
	 * Must follow the pattern `namespace/ability-name` (2–4 slash-separated
	 * lowercase alphanumeric segments), e.g. `my-plugin/do-something`.
	 *
	 * @since 1.0.0
	 *
	 * @return string The ability name including namespace.
	 */
	abstract protected function ability_name(): string;

	/**
	 * Returns the human-readable label for the ability.
	 *
	 * @since 1.0.0
	 *
	 * @return string The ability label (should be translated).
	 */
	abstract protected function ability_label(): string;

	/**
	 * Returns the detailed description of what the ability does.
	 *
	 * @since 1.0.0
	 *
	 * @return string The ability description (should be translated).
	 */
	abstract protected function ability_description(): string;

	/**
	 * Returns the ability category slug.
	 *
	 * The category must be registered via {@see wp_register_ability_category()}
	 * before this ability is registered.
	 *
	 * @since 1.0.0
	 *
	 * @return string The category slug.
	 */
	abstract protected function ability_category(): string;

	/**
	 * Returns the JSON Schema definition for the ability's input.
	 *
	 * Return an empty array if the ability accepts no input.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The input schema.
	 */
	abstract protected function input_schema(): array;

	/**
	 * Returns the JSON Schema definition for the ability's output.
	 *
	 * Return an empty array if the output schema is not defined.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The output schema.
	 */
	abstract protected function output_schema(): array;

	/**
	 * Executes the ability with the given input.
	 *
	 * Called by the Abilities API after input validation and permission checks.
	 * Must return a result value or a WP_Error on failure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Optional. The validated input data. Default null.
	 * @return mixed|WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	abstract protected function execute_callback( $input = null );

	/**
	 * Checks whether the current user has permission to execute the ability.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Optional. The input data (same as execute_callback). Default null.
	 * @return bool|WP_Error True if the user has permission, false or WP_Error otherwise.
	 */
	abstract protected function permission_callback( $input = null );

	/**
	 * Returns the ability metadata array.
	 *
	 * Supports `annotations` (readonly, destructive, idempotent) and `show_in_rest`.
	 * Return an empty array to use the WP_Ability defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The ability metadata.
	 */
	abstract protected function meta(): array;

	// -------------------------------------------------------------------------
	// Registration helper.
	// -------------------------------------------------------------------------

	/**
	 * Registers this ability with the WordPress Abilities API.
	 *
	 * Passes `ability_class` so the registry instantiates this class (via
	 * `new static($name, $args)`) rather than the base WP_Ability class.
	 * The constructor ignores the registry-supplied $name/$args and uses the
	 * subclass methods instead.
	 *
	 * Must be called inside a `wp_abilities_api_init` action callback:
	 *
	 *     add_action( 'wp_abilities_api_init', function() {
	 *         ( new My_Ability() )->register();
	 *     } );
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			$this->ability_name(),
			[
				'label'               => $this->ability_label(),
				'description'         => $this->ability_description(),
				'category'            => $this->ability_category(),
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'execute_callback'    => [ $this, 'execute_callback' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'meta'                => $this->meta(),
				'ability_class'       => static::class,
			]
		);
	}
}
