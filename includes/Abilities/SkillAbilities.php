<?php

declare(strict_types=1);
/**
 * Register skill-related WordPress abilities (tools) for the AI agent.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\Skill;

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
				'label'               => __( 'Load Skill', 'gratis-ai-agent' ),
				'description'         => __( 'Load the full instructions for a specific skill guide by its slug.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
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
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'name'    => [ 'type' => 'string' ],
						'slug'    => [ 'type' => 'string' ],
						'content' => [ 'type' => 'string' ],
						'error'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_skill_load' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'ai-agent/skill-list',
			[
				'label'               => __( 'List Skills', 'gratis-ai-agent' ),
				'description'         => __( 'List all available skill guides with their slugs, names, and descriptions.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'skills'  => [ 'type' => 'array' ],
						'message' => [ 'type' => 'string' ],
					],
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
	 * @param array<string,mixed> $input Input with slug.
	 * @return array<string,mixed>|\WP_Error Result with skill content.
	 */
	public static function handle_skill_load( array $input ) {
		$slug = $input['slug'] ?? '';

		if ( empty( $slug ) ) {
			return new \WP_Error( 'missing_slug', 'Skill slug is required.' );
		}

		// @phpstan-ignore-next-line
		$skill = Skill::get_by_slug( $slug );

		if ( ! $skill ) {
			// @phpstan-ignore-next-line
			return new \WP_Error( 'skill_not_found', "Skill '$slug' not found." );
		}

		if ( ! (int) $skill->enabled ) {
			// @phpstan-ignore-next-line
			return new \WP_Error( 'skill_disabled', "Skill '$slug' is disabled." );
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
	 * @return array<string,mixed> Result with skills index.
	 */
	public static function handle_skill_list(): array {
		$skills = Skill::get_all( true );

		if ( empty( $skills ) ) {
			return [ 'message' => 'No skills available.' ];
		}

		$list = [];
		foreach ( $skills as $skill ) {
			$list[] = [
				// @phpstan-ignore-next-line
				'slug'        => $skill->slug,
				// @phpstan-ignore-next-line
				'name'        => $skill->name,
				// @phpstan-ignore-next-line
				'description' => $skill->description,
			];
		}

		return [ 'skills' => $list ];
	}
}
