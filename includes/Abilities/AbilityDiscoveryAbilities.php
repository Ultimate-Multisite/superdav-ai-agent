<?php
/**
 * AbilityDiscoveryAbilities
 *
 * Meta-tools that let the AI discover and call any registered ability.
 * These abilities provide introspection capabilities for the Abilities API.
 *
 * @package AiAgent
 */

namespace AiAgent\Abilities;

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
			'ai-agent/discovery-list',
			[
				'label'               => __( 'List Abilities', 'ai-agent' ),
				'description'         => __( 'List all available WordPress abilities (from plugins, themes, and core). Returns ability names and brief descriptions.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => __( 'Optional category to filter abilities (e.g., "content", "media", "users")', 'ai-agent' ),
							'required'    => false,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'abilities' => [
							'type'        => 'array',
							'description' => __( 'List of abilities with their details', 'ai-agent' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'          => [
										'type'        => 'string',
										'description' => __( 'Ability identifier', 'ai-agent' ),
									],
									'name'        => [
										'type'        => 'string',
										'description' => __( 'Human-readable name', 'ai-agent' ),
									],
									'description' => [
										'type'        => 'string',
										'description' => __( 'Brief description of what the ability does', 'ai-agent' ),
									],
									'category'    => [
										'type'        => 'string',
										'description' => __( 'Category this ability belongs to', 'ai-agent' ),
									],
								],
							],
						],
						'count'     => [
							'type'        => 'integer',
							'description' => __( 'Total number of abilities returned', 'ai-agent' ),
						],
						'filter'    => [
							'type'        => 'string',
							'description' => __( 'Category filter applied (if any)', 'ai-agent' ),
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
			'ai-agent/discovery-get',
			[
				'label'               => __( 'Get Ability', 'ai-agent' ),
				'description'         => __( 'Get full details of a specific WordPress ability including its parameters schema, permissions, and usage information. Call this before execute_ability to understand what arguments are needed.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'ability' => [
							'type'        => 'string',
							'description' => __( 'The ability identifier (e.g., "memory/save_memory", "file/read_file")', 'ai-agent' ),
							'required'    => true,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'            => [
							'type'        => 'string',
							'description' => __( 'Ability identifier', 'ai-agent' ),
						],
						'name'          => [
							'type'        => 'string',
							'description' => __( 'Human-readable name', 'ai-agent' ),
						],
						'description'   => [
							'type'        => 'string',
							'description' => __( 'Full description of the ability', 'ai-agent' ),
						],
						'category'      => [
							'type'        => 'string',
							'description' => __( 'Category this ability belongs to', 'ai-agent' ),
						],
						'input_schema'  => [
							'type'        => 'object',
							'description' => __( 'JSON Schema for input parameters', 'ai-agent' ),
							'required'    => false,
						],
						'output_schema' => [
							'type'        => 'object',
							'description' => __( 'JSON Schema for output', 'ai-agent' ),
							'required'    => false,
						],
						'instructions'  => [
							'type'        => 'string',
							'description' => __( 'Additional instructions or notes', 'ai-agent' ),
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
			'ai-agent/discovery-execute',
			[
				'label'               => __( 'Execute Ability', 'ai-agent' ),
				'description'         => __( 'Execute a WordPress ability with the given arguments. Use get_ability first to understand required parameters.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'ability'   => [
							'type'        => 'string',
							'description' => __( 'The ability identifier to execute', 'ai-agent' ),
							'required'    => true,
						],
						'arguments' => [
							'type'        => 'object',
							'description' => __( 'Arguments to pass to the ability (schema varies by ability)', 'ai-agent' ),
							'required'    => false,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'ability' => [
							'type'        => 'string',
							'description' => __( 'Ability identifier that was executed', 'ai-agent' ),
						],
						'success' => [
							'type'        => 'boolean',
							'description' => __( 'Whether the execution was successful', 'ai-agent' ),
						],
						'result'  => [
							'type'        => 'object',
							'description' => __( 'Result of the ability execution', 'ai-agent' ),
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
	 * @param array $args Arguments (category).
	 * @return array|WP_Error Result or error.
	 */
	public static function handle_list_abilities( array $args ): array|WP_Error {
		$category = $args['category'] ?? '';

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'ai-agent' )
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
	 * @param array $args Arguments (ability).
	 * @return array|WP_Error Result or error.
	 */
	public static function handle_get_ability( array $args ): array|WP_Error {
		$ability_id = $args['ability'] ?? '';

		if ( empty( $ability_id ) ) {
			return new WP_Error(
				'invalid_argument',
				__( 'Ability identifier is required.', 'ai-agent' )
			);
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'ai-agent' )
			);
		}

		$ability = wp_get_ability( $ability_id );

		if ( $ability === null ) {
			return new WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability identifier */
					__( 'Ability not found: %s', 'ai-agent' ),
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
	 * @param array $args Arguments (ability, arguments).
	 * @return array|WP_Error Result or error.
	 */
	public static function handle_execute_ability( array $args ): array|WP_Error {
		$ability_id   = $args['ability'] ?? '';
		$ability_args = $args['arguments'] ?? [];

		if ( empty( $ability_id ) ) {
			return new WP_Error(
				'invalid_argument',
				__( 'Ability identifier is required.', 'ai-agent' )
			);
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'Abilities API not available. WordPress 6.9+ with the Abilities API is required.', 'ai-agent' )
			);
		}

		$ability = wp_get_ability( $ability_id );
		if ( $ability === null ) {
			return new WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability identifier */
					__( 'Ability not found: %s', 'ai-agent' ),
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
					__( 'Ability execution failed: %s', 'ai-agent' ),
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
