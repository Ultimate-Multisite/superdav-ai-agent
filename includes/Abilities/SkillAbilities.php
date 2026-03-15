<?php

declare(strict_types=1);
/**
 * Register skill-related WordPress abilities (tools) for the AI agent.
 *
 * @package AiAgent
 */

namespace AiAgent\Abilities;

use AiAgent\Models\Skill;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SkillAbilities {

	/**
	 * Register skill abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the skill-load and skill-list abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/skill-load',
			[
				'label'               => __( 'Load Skill', 'ai-agent' ),
				'description'         => __( 'Load the full instructions for a specific skill guide by its slug.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => 'The skill slug to load (e.g. wordpress-admin, woocommerce)',
						],
					],
					'required'   => [ 'slug' ],
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_skill_load' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'ai-agent/skill-list',
			[
				'label'               => __( 'List Skills', 'ai-agent' ),
				'description'         => __( 'List all available skill guides with their slugs, names, and descriptions.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_skill_list' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);
	}

	/**
	 * Handle the skill-load ability call.
	 *
	 * @param array $input Input with slug.
	 * @return array Result with skill content.
	 */
	public static function handle_skill_load( array $input ): array {
		$slug = $input['slug'] ?? '';

		if ( empty( $slug ) ) {
			return [ 'error' => 'Skill slug is required.' ];
		}

		$skill = Skill::get_by_slug( $slug );

		if ( ! $skill ) {
			return [ 'error' => "Skill '$slug' not found." ];
		}

		if ( ! (int) $skill->enabled ) {
			return [ 'error' => "Skill '$slug' is disabled." ];
		}

		return [
			'name'    => $skill->name,
			'slug'    => $skill->slug,
			'content' => $skill->content,
		];
	}

	/**
	 * Handle the skill-list ability call.
	 *
	 * @return array Result with skills index.
	 */
	public static function handle_skill_list(): array {
		$skills = Skill::get_all( true );

		if ( empty( $skills ) ) {
			return [ 'message' => 'No skills available.' ];
		}

		$list = [];
		foreach ( $skills as $skill ) {
			$list[] = [
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
			];
		}

		return [ 'skills' => $list ];
	}
}
