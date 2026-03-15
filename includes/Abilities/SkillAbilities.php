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
				'label'         => __( 'Load Skill', 'gratis-ai-agent' ),
				'description'   => __( 'Load the full instructions for a specific skill guide by its slug.', 'gratis-ai-agent' ),
				'ability_class' => SkillLoadAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/skill-list',
			[
				'label'         => __( 'List Skills', 'gratis-ai-agent' ),
				'description'   => __( 'List all available skill guides with their slugs, names, and descriptions.', 'gratis-ai-agent' ),
				'ability_class' => SkillListAbility::class,
			]
		);
	}
}

/**
 * Load Skill ability.
 *
 * @since 1.0.0
 */
class SkillLoadAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug' => [
					'type'        => 'string',
					'description' => 'The skill slug to load (e.g. wordpress-admin, woocommerce)',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'    => [ 'type' => 'string' ],
				'slug'    => [ 'type' => 'string' ],
				'content' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
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

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * List Skills ability.
 *
 * @since 1.0.0
 */
class SkillListAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'skills'  => [ 'type' => 'array' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
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

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}
