<?php

declare(strict_types=1);
/**
 * Register skill-related WordPress abilities (tools) for the AI agent.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Models\Skill;
use SdAiAgent\Core\SkillContextDelivery;
use SdAiAgent\Repositories\SkillUsageRepository;
use SdAiAgent\Tools\ModelHealthTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SkillAbilities {

	/**
	 * Register the skill-load and skill-list abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/skill-load',
			[
				'label'               => __( 'Load Skill', 'superdav-ai-agent' ),
				'description'         => __( 'Load bounded, actionable skill guidance by slug. The first page includes a section index; request an exact section only when needed. Follow next_offset with the same section/content_version for more, rather than repeatedly loading the entire guide.', 'superdav-ai-agent' ),
				'category'            => 'sd-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug'            => [
							'type'        => 'string',
							'description' => 'The skill slug to load (e.g. wordpress-admin, woocommerce)',
						],
						'section'         => [
							'type'        => 'string',
							'description' => 'Exact Markdown heading from the section index. Omit for the overview.',
						],
						'offset'          => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Byte offset returned as next_offset for the same section.',
						],
						'content_version' => [
							'type'        => 'string',
							'description' => 'Version from the previous result; rejects stale continuation after edits.',
						],
						'reload'          => [
							'type'        => 'boolean',
							'description' => 'Use only when earlier guidance was removed from the current context by compaction. Ordinary repeated pages are deduplicated.',
						],
					],
					'required'   => [ 'slug' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'name'            => [ 'type' => 'string' ],
						'slug'            => [ 'type' => 'string' ],
						'content'         => [ 'type' => 'string' ],
						'error'           => [ 'type' => 'string' ],
						'sections'        => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'content_version' => [ 'type' => 'string' ],
						'next_offset'     => [ 'type' => [ 'integer', 'null' ] ],
						'truncated'       => [ 'type' => 'boolean' ],
						'already_loaded'  => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'mcp'         => [ 'public' => true ],
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_skill_load' ],
				'permission_callback' => function () {
					// Dual gate: per-tool cap AND core cap from CORE_CAP_MAP.
					return ToolCapabilities::current_user_can( 'sd-ai-agent/skill-load' );
				},
			]
		);

		wp_register_ability(
			'sd-ai-agent/skill-list',
			[
				'label'               => __( 'List Skills', 'superdav-ai-agent' ),
				'description'         => __( 'List all available skill guides with their slugs, names, and descriptions.', 'superdav-ai-agent' ),
				'category'            => 'sd-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => (object) [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'skills'  => [ 'type' => 'array' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'mcp'         => [ 'public' => true ],
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_skill_list' ],
				'permission_callback' => function () {
					// Dual gate: per-tool cap AND core cap from CORE_CAP_MAP.
					return ToolCapabilities::current_user_can( 'sd-ai-agent/skill-list' );
				},
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

		// Check explicit enabled flag first.
		if ( ! (int) $skill->enabled ) {
			// Check if the skill should be auto-enabled based on environment.
			if ( ! Skill::is_skill_auto_enabled( $slug ) ) {
				$error_msg = self::get_skill_disabled_message( $slug );
				// @phpstan-ignore-next-line
				return new \WP_Error( 'skill_disabled', $error_msg );
			}
		}

		$result = SkillContextDelivery::build( (string) $skill->slug, (string) $skill->name, (string) $skill->content, $input );
		if ( is_wp_error( $result ) || $result['already_loaded'] ) {
			return $result;
		}

		// Record tool_call usage for telemetry (Phase 1 / t215).
		// Model ID is unknown here (abilities don't receive request context),
		// so model_id defaults to '' and session_id to 0.
		SkillUsageRepository::create(
			[
				'skill_id'        => $skill->id,
				'session_id'      => 0,
				'trigger_type'    => 'tool_call',
				'injected_tokens' => SkillUsageRepository::estimate_tokens( $result['content'] ),
				'outcome'         => 'unknown',
				'model_id'        => '',
			]
		);

		// Record this voluntary skill-load call in ModelHealthTracker (Phase 2 / t217).
		// Strong models that call skill-load on their own confirm they can use
		// the index-only path effectively. This signal is used in Phase 3 to
		// tune the auto-injection threshold per model.
		ModelHealthTracker::record_skill_load();

		return $result;
	}

	/**
	 * Handle the skill-list ability call.
	 *
	 * @return array<string,mixed> Result with skills index.
	 */
	public static function handle_skill_list(): array {
		$skills = Skill::get_all( true );

		if ( empty( $skills ) ) {
			return [
				'skills'  => [],
				'message' => 'No skills available.',
			];
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

		return [
			'skills'  => $list,
			'message' => '',
		];
	}

	/**
	 * Generate a helpful error message for a disabled skill.
	 *
	 * Explains why the skill is disabled and what conditions would enable it.
	 *
	 * @param string $slug Skill slug.
	 * @return string Error message.
	 */
	private static function get_skill_disabled_message( string $slug ): string {
		// Check if the skill has a known auto-enable condition.
		$plugin_map = [
			'woocommerce'          => 'WooCommerce plugin',
			'kadence-blocks'       => 'Kadence Blocks plugin',
			'multisite-management' => 'WordPress Multisite',
		];

		if ( isset( $plugin_map[ $slug ] ) ) {
			// translators: %1$s is the skill slug, %2$s is the plugin name.
			return sprintf(
				__( "Skill '%1\$s' is disabled. It will be automatically enabled when %2\$s is active.", 'superdav-ai-agent' ),
				$slug,
				$plugin_map[ $slug ]
			);
		}

		// Check if the skill is theme-aware.
		$theme_map = [
			'wp-block-themes' => __( 'block-theme generation or editing is requested', 'superdav-ai-agent' ),
			'classic-themes'  => __( 'a classic (non-block) theme is active', 'superdav-ai-agent' ),
			'kadence-theme'   => __( 'the Kadence theme is active', 'superdav-ai-agent' ),
		];

		if ( isset( $theme_map[ $slug ] ) ) {
			// translators: %1$s is the skill slug, %2$s is the theme condition.
			return sprintf(
				__( "Skill '%1\$s' is disabled. It will be automatically enabled when %2\$s.", 'superdav-ai-agent' ),
				$slug,
				$theme_map[ $slug ]
			);
		}

		// Default message for truly disabled skills.
		// translators: %s is the skill slug.
		return sprintf(
			// translators: %s is the skill slug.
			__( "Skill '%s' is disabled.", 'superdav-ai-agent' ),
			$slug
		);
	}
}
