<?php
/**
 * AbilityDiscoveryAbilities
 *
 * Meta-tools that let the AI discover and call any registered ability.
 * These abilities provide introspection capabilities for the Abilities API.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AbilityDiscoveryAbilities class
 *
 * Provides meta-tools for discovering and executing abilities.
 */
class AbilityDiscoveryAbilities {

	/**
	 * Register ability discovery abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * List all registered abilities.
	 *
	 * @param array<string,mixed> $input Input args (supports 'category' filter).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_list_abilities( array $input = [] ) {
		$ability = new DiscoveryListAbility( 'gratis-ai-agent/discovery-list' );
		return $ability->execute( $input );
	}

	/**
	 * Get details of a specific ability.
	 *
	 * @param array<string,mixed> $input Input args (requires 'ability' key).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_ability( array $input = [] ) {
		$ability = new DiscoveryGetAbility( 'gratis-ai-agent/discovery-get' );
		return $ability->execute( $input );
	}

	/**
	 * Execute a registered ability.
	 *
	 * @param array<string,mixed> $input Input args (requires 'ability' key).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_execute_ability( array $input = [] ) {
		$ability = new DiscoveryExecuteAbility( 'gratis-ai-agent/discovery-execute' );
		return $ability->execute( $input );
	}

	/**
	 * Register all ability discovery abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/discovery-list',
			[
				'label'         => __( 'List Abilities', 'gratis-ai-agent' ),
				'description'   => __( 'List all available WordPress abilities (from plugins, themes, and core). Returns ability names and brief descriptions.', 'gratis-ai-agent' ),
				'ability_class' => DiscoveryListAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/discovery-get',
			[
				'label'         => __( 'Get Ability', 'gratis-ai-agent' ),
				'description'   => __( 'Get full details of a specific WordPress ability including its parameters schema, permissions, and usage information. Call this before execute_ability to understand what arguments are needed.', 'gratis-ai-agent' ),
				'ability_class' => DiscoveryGetAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/discovery-execute',
			[
				'label'         => __( 'Execute Ability', 'gratis-ai-agent' ),
				'description'   => __( 'Execute a WordPress ability with the given arguments. Use get_ability first to understand required parameters.', 'gratis-ai-agent' ),
				'ability_class' => DiscoveryExecuteAbility::class,
			]
		);
	}
}

/**
 * Discovery List ability.
 *
 * @since 1.0.0
 */
class DiscoveryListAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Abilities', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all available WordPress abilities (from plugins, themes, and core). Returns ability names and brief descriptions.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'category' => [
					'type'        => 'string',
					'description' => __( 'Optional category to filter abilities (e.g., "content", "media", "users")', 'gratis-ai-agent' ),
					'required'    => false,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'abilities' => [
					'type'        => 'array',
					'description' => __( 'List of abilities with their details', 'gratis-ai-agent' ),
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'          => [ 'type' => 'string' ],
							'name'        => [ 'type' => 'string' ],
							'description' => [ 'type' => 'string' ],
							'category'    => [ 'type' => 'string' ],
						],
					],
				],
				'count'     => [ 'type' => 'integer' ],
				'filter'    => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$category = $input['category'] ?? '';

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'gratis-ai-agent' )
			);
		}

		$abilities = wp_get_abilities();

		if ( ! empty( $category ) ) {
			$abilities = array_filter(
				$abilities,
				function ( $ability ) use ( $category ) {
					return $ability->get_category() === $category;
				}
			);
		}

		$result = [];
		foreach ( $abilities as $ability ) {
			$result[] = [
				'id'          => $ability->get_name(),
				'name'        => $ability->get_label(),
				'description' => $ability->get_description(),
				'category'    => $ability->get_category(),
			];
		}

		return [
			'abilities' => $result,
			'count'     => count( $result ),
			'filter'    => $category ?: null,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'idempotent'  => true,
				'destructive' => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Discovery Get ability.
 *
 * @since 1.0.0
 */
class DiscoveryGetAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get Ability', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Get full details of a specific WordPress ability including its parameters schema, permissions, and usage information. Call this before execute_ability to understand what arguments are needed.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'ability' => [
					'type'        => 'string',
					'description' => __( 'The ability identifier (e.g., "memory/save_memory", "file/read_file")', 'gratis-ai-agent' ),
					'required'    => true,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'            => [ 'type' => 'string' ],
				'name'          => [ 'type' => 'string' ],
				'description'   => [ 'type' => 'string' ],
				'category'      => [ 'type' => 'string' ],
				'input_schema'  => [ 'type' => 'object' ],
				'output_schema' => [ 'type' => 'object' ],
				'instructions'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$ability_id = $input['ability'] ?? '';

		if ( empty( $ability_id ) ) {
			return new WP_Error(
				'invalid_argument',
				__( 'Ability identifier is required.', 'gratis-ai-agent' )
			);
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'gratis-ai-agent' )
			);
		}

		$ability = wp_get_ability( $ability_id );

		if ( $ability === null ) {
			return new WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability identifier */
					__( 'Ability not found: %s', 'gratis-ai-agent' ),
					$ability_id
				)
			);
		}

		$meta = $ability->get_meta();
		return [
			'id'            => $ability->get_name(),
			'name'          => $ability->get_label(),
			'description'   => $ability->get_description(),
			'category'      => $ability->get_category(),
			'input_schema'  => $ability->get_input_schema(),
			'output_schema' => $ability->get_output_schema(),
			'instructions'  => $meta['annotations']['instructions'] ?? '',
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'idempotent'  => true,
				'destructive' => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Discovery Execute ability.
 *
 * @since 1.0.0
 */
class DiscoveryExecuteAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Execute Ability', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Execute a WordPress ability with the given arguments. Use get_ability first to understand required parameters.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'ability'   => [
					'type'        => 'string',
					'description' => __( 'The ability identifier to execute', 'gratis-ai-agent' ),
					'required'    => true,
				],
				'arguments' => [
					'type'        => 'object',
					'description' => __( 'Arguments to pass to the ability (schema varies by ability)', 'gratis-ai-agent' ),
					'required'    => false,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'ability' => [ 'type' => 'string' ],
				'success' => [ 'type' => 'boolean' ],
				'result'  => [ 'type' => 'object' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$ability_id   = $input['ability'] ?? '';
		$ability_args = $input['arguments'] ?? [];

		if ( empty( $ability_id ) ) {
			return new WP_Error(
				'invalid_argument',
				__( 'Ability identifier is required.', 'gratis-ai-agent' )
			);
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'gratis-ai-agent' )
			);
		}

		$ability = wp_get_ability( $ability_id );
		if ( $ability === null ) {
			return new WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability identifier */
					__( 'Ability not found: %s', 'gratis-ai-agent' ),
					$ability_id
				)
			);
		}

		$input_data = ! empty( $ability_args ) ? $ability_args : null;
		$result     = $ability->execute( $input_data );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			return new WP_Error(
				'ability_execution_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Ability execution failed: %s', 'gratis-ai-agent' ),
					$error_message
				)
			);
		}

		return [
			'ability' => $ability_id,
			'success' => true,
			'result'  => $result,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'idempotent'  => false,
				'destructive' => false,
			],
			'show_in_rest' => true,
		];
	}
}
