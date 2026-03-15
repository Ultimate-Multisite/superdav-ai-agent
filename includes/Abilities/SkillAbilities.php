<?php

declare(strict_types=1);
/**
 * Register skill-related WordPress abilities (tools) for the AI agent.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\Skill;
use WP_Error;

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
			'gratis-ai-agent/skill-load',
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
				'execute_callback'    => [ __CLASS__, 'handle_skill_load' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'gratis-ai-agent/skill-list',
			[
				'label'               => __( 'List Skills', 'gratis-ai-agent' ),
				'description'         => __( 'List all available skill guides with their slugs, names, and descriptions.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
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
	 * @param array<string, mixed> $input Input with slug.
	 * @return array<string, mixed>|\WP_Error Result with skill content or WP_Error on failure.
	 */
	public static function handle_skill_load( array $input ): array|\WP_Error {
		$slug = $input['slug'] ?? '';

		if ( empty( $slug ) ) {
			return new WP_Error( 'missing_param', __( 'Skill slug is required.', 'gratis-ai-agent' ) );
		}

		$skill = Skill::get_by_slug( $slug );

		if ( ! $skill ) {
			return new WP_Error(
				'not_found',
				sprintf(
					/* translators: %s: skill slug */
					__( "Skill '%s' not found.", 'gratis-ai-agent' ),
					$slug
				)
			);
		}

		if ( ! (int) $skill->enabled ) {
			return new WP_Error(
				'skill_disabled',
				sprintf(
					/* translators: %s: skill slug */
					__( "Skill '%s' is disabled.", 'gratis-ai-agent' ),
					$slug
				)
			);
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
	 * @return array<string, mixed> Result with skills index.
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
