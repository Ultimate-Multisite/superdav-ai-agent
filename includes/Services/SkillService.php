<?php

declare(strict_types=1);
/**
 * Service class for skill domain logic.
 *
 * Extracted from SkillController to separate domain concerns from HTTP handling.
 * Centralises skill-to-array formatting and business rules so controllers
 * stay focused on request/response mechanics.
 *
 * @package SdAiAgent\Services
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Services;

use SdAiAgent\Models\Skill;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles skill domain operations independently of the REST layer.
 */
final class SkillService {

	/**
	 * Format a single skill database row as an array for REST responses.
	 *
	 * Centralises the mapping that was previously duplicated across
	 * handle_list_skills(), handle_create_skill(), handle_update_skill(),
	 * and handle_reset_skill() in SkillController.
	 *
	 * @param object $skill Skill database row.
	 * @return array<string, mixed> Formatted skill data.
	 */
	public static function format_skill( object $skill ): array {
		return array(
			'id'            => (int) $skill->id,
			'slug'          => $skill->slug,
			'name'          => $skill->name,
			'description'   => $skill->description,
			'content'       => $skill->content,
			'is_builtin'    => (bool) (int) $skill->is_builtin,
			'enabled'       => (bool) (int) $skill->enabled,
			'version'       => (string) ( $skill->version ?? '' ),
			'source_url'    => (string) ( $skill->source_url ?? '' ),
			'user_modified' => (bool) (int) ( $skill->user_modified ?? 0 ),
			'word_count'    => str_word_count( $skill->content ),
			'created_at'    => $skill->created_at,
			'updated_at'    => $skill->updated_at,
		);
	}

	/**
	 * Create a new custom skill, enforcing the unique-slug business rule.
	 *
	 * @param string $slug        Skill slug (must be unique).
	 * @param string $name        Skill name.
	 * @param string $description Skill description.
	 * @param string $content     Skill content.
	 * @return int|WP_Error New skill ID on success, WP_Error on failure.
	 */
	public static function create_skill( string $slug, string $name, string $description, string $content ): int|WP_Error {
		// Business rule: slugs must be unique across all skills.
		// @phpstan-ignore-next-line
		$existing = Skill::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'sd_ai_agent_skill_slug_exists',
				__( 'A skill with this slug already exists.', 'sd-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = Skill::create(
			array(
				'slug'        => $slug,
				'name'        => $name,
				'description' => $description,
				'content'     => $content,
				'is_builtin'  => false,
				'enabled'     => true,
			)
		);

		if ( false === $id ) {
			return new WP_Error(
				'sd_ai_agent_skill_create_failed',
				__( 'Failed to create skill.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return $id;
	}

	/**
	 * Delete a custom skill, refusing deletion of built-in skills.
	 *
	 * @param int $id Skill ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_skill( int $id ): true|WP_Error {
		$result = Skill::delete( $id );

		if ( $result === 'builtin' ) {
			return new WP_Error(
				'sd_ai_agent_skill_builtin_delete',
				__( 'Built-in skills cannot be deleted. You can disable them instead.', 'sd-ai-agent' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $result ) {
			return new WP_Error(
				'sd_ai_agent_skill_delete_failed',
				__( 'Failed to delete skill or skill not found.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}
}
