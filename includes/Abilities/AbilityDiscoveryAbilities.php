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

	/**
	 * Register all ability discovery abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// list_abilities - List all registered abilities.
		wp_register_ability(
			'gratis-ai-agent/discovery-list',
			[
				'label'               => __( 'List Abilities', 'gratis-ai-agent' ),
				'description'         => __( 'List all available WordPress abilities (from plugins, themes, and core). Returns ability names and brief descriptions.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => __( 'Optional category to filter abilities (e.g., "content", "media", "users")', 'gratis-ai-agent' ),
							'required'    => false,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'abilities' => [
							'type'        => 'array',
							'description' => __( 'List of abilities with their details', 'gratis-ai-agent' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'          => [
										'type'        => 'string',
										'description' => __( 'Ability identifier', 'gratis-ai-agent' ),
									],
									'name'        => [
										'type'        => 'string',
										'description' => __( 'Human-readable name', 'gratis-ai-agent' ),
									],
									'description' => [
										'type'        => 'string',
										'description' => __( 'Brief description of what the ability does', 'gratis-ai-agent' ),
									],
									'category'    => [
										'type'        => 'string',
										'description' => __( 'Category this ability belongs to', 'gratis-ai-agent' ),
									],
								],
							],
						],
						'count'     => [
							'type'        => 'integer',
							'description' => __( 'Total number of abilities returned', 'gratis-ai-agent' ),
						],
						'filter'    => [
							'type'        => 'string',
							'description' => __( 'Category filter applied (if any)', 'gratis-ai-agent' ),
							'required'    => false,
						],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'idempotent'  => true,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_abilities' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// get_ability - Get full details of a specific ability.
		wp_register_ability(
			'gratis-ai-agent/discovery-get',
			[
				'label'               => __( 'Get Ability', 'gratis-ai-agent' ),
				'description'         => __( 'Get full details of a specific WordPress ability including its parameters schema, permissions, and usage information. Call this before execute_ability to understand what arguments are needed.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'ability' => [
							'type'        => 'string',
							'description' => __( 'The ability identifier (e.g., "memory/save_memory", "file/read_file")', 'gratis-ai-agent' ),
							'required'    => true,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'            => [
							'type'        => 'string',
							'description' => __( 'Ability identifier', 'gratis-ai-agent' ),
						],
						'name'          => [
							'type'        => 'string',
							'description' => __( 'Human-readable name', 'gratis-ai-agent' ),
						],
						'description'   => [
							'type'        => 'string',
							'description' => __( 'Full description of the ability', 'gratis-ai-agent' ),
						],
						'category'      => [
							'type'        => 'string',
							'description' => __( 'Category this ability belongs to', 'gratis-ai-agent' ),
						],
						'input_schema'  => [
							'type'        => 'object',
							'description' => __( 'JSON Schema for input parameters', 'gratis-ai-agent' ),
							'required'    => false,
						],
						'output_schema' => [
							'type'        => 'object',
							'description' => __( 'JSON Schema for output', 'gratis-ai-agent' ),
							'required'    => false,
						],
						'instructions'  => [
							'type'        => 'string',
							'description' => __( 'Additional instructions or notes', 'gratis-ai-agent' ),
							'required'    => false,
						],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'idempotent'  => true,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_ability' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// execute_ability - Execute an ability with arguments.
		wp_register_ability(
			'gratis-ai-agent/discovery-execute',
			[
				'label'               => __( 'Execute Ability', 'gratis-ai-agent' ),
				'description'         => __( 'Execute a WordPress ability with the given arguments. Use get_ability first to understand required parameters.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
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
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'ability' => [
							'type'        => 'string',
							'description' => __( 'Ability identifier that was executed', 'gratis-ai-agent' ),
						],
						'success' => [
							'type'        => 'boolean',
							'description' => __( 'Whether the execution was successful', 'gratis-ai-agent' ),
						],
						'result'  => [
							'type'        => 'object',
							'description' => __( 'Result of the ability execution', 'gratis-ai-agent' ),
							'required'    => false,
						],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'idempotent'  => false,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_execute_ability' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Handle list_abilities execution.
	 *
	 * @param array<string, mixed> $args Arguments (category).
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	public static function handle_list_abilities( array $args ): array|WP_Error {
		$category = $args['category'] ?? '';

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

	/**
	 * Handle get_ability execution.
	 *
	 * @param array<string, mixed> $args Arguments (ability).
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	public static function handle_get_ability( array $args ): array|WP_Error {
		$ability_id = $args['ability'] ?? '';

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

	/**
	 * Handle execute_ability execution.
	 *
	 * @param array<string, mixed> $args Arguments (ability, arguments).
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	public static function handle_execute_ability( array $args ): array|WP_Error {
		$ability_id   = $args['ability'] ?? '';
		$ability_args = $args['arguments'] ?? [];

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

		$input  = ! empty( $ability_args ) ? $ability_args : null;
		$result = $ability->execute( $input );

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
}
