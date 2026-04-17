<?php

declare(strict_types=1);
/**
 * REST API controller for skills.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Models\Skill;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages agent skills via REST.
 *
 * Endpoints:
 *  GET    /skills            — list all skills
 *  POST   /skills            — create a custom skill
 *  PATCH  /skills/{id}       — update a skill
 *  DELETE /skills/{id}       — delete a custom skill
 *  POST   /skills/{id}/reset — reset a built-in skill to defaults
 */
#[REST_Handler(
	namespace: RestController::NAMESPACE,
	basename: 'skills',
	container: 'gratis-ai-agent',
)]
final class SkillController extends XWP_REST_Controller {

	use PermissionTrait;

	/**
	 * Handle GET /skills — list all skills.
	 *
	 * @return WP_REST_Response
	 */
	#[REST_Route(
		route: '',
		methods: WP_REST_Server::READABLE,
		guard: 'check_permission',
	)]
	public function handle_list_skills(): WP_REST_Response {
		$skills = Skill::get_all();

		$list = array_map(
			function ( $s ) {
				return array(
					// @phpstan-ignore-next-line
					'id'          => (int) $s->id,
					// @phpstan-ignore-next-line
					'slug'        => $s->slug,
					// @phpstan-ignore-next-line
					'name'        => $s->name,
					// @phpstan-ignore-next-line
					'description' => $s->description,
					// @phpstan-ignore-next-line
					'content'     => $s->content,
					// @phpstan-ignore-next-line
					'is_builtin'  => (bool) (int) $s->is_builtin,
					// @phpstan-ignore-next-line
					'enabled'     => (bool) (int) $s->enabled,
					// @phpstan-ignore-next-line
					'word_count'  => str_word_count( $s->content ),
					// @phpstan-ignore-next-line
					'created_at'  => $s->created_at,
					// @phpstan-ignore-next-line
					'updated_at'  => $s->updated_at,
				);
			},
			$skills
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /skills — create a custom skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: '',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_create_args',
		guard: 'check_permission',
	)]
	public function handle_create_skill( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		// @phpstan-ignore-next-line
		$existing = Skill::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'gratis_ai_agent_skill_slug_exists',
				__( 'A skill with this slug already exists.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = Skill::create(
			array(
				'slug'        => $slug,
				'name'        => $request->get_param( 'name' ),
				'description' => $request->get_param( 'description' ),
				'content'     => $request->get_param( 'content' ),
				'is_builtin'  => false,
				'enabled'     => true,
			)
		);

		if ( false === $id ) {
			return new WP_Error(
				'gratis_ai_agent_skill_create_failed',
				__( 'Failed to create skill.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$skill = Skill::get( $id );

		if ( ! $skill ) {
			return new WP_Error( 'gratis_ai_agent_skill_not_found', __( 'Skill not found after creation.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => false,
				'enabled'     => true,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /skills/{id} — update a skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: '(?P<id>\d+)',
		methods: 'PATCH',
		vars: 'get_update_args',
		guard: 'check_permission',
	)]
	public function handle_update_skill( WP_REST_Request $request ) {
		$id   = self::get_int_param( $request, 'id' );
		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}
		if ( $request->has_param( 'enabled' ) ) {
			$data['enabled'] = $request->get_param( 'enabled' );
		}

		$updated = Skill::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_skill_update_failed',
				__( 'Failed to update skill.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$skill = Skill::get( $id );

		if ( ! $skill ) {
			return new WP_Error( 'gratis_ai_agent_skill_not_found', __( 'Skill not found after update.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => (bool) (int) $skill->is_builtin,
				'enabled'     => (bool) (int) $skill->enabled,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /skills/{id} — delete a custom skill (refuses built-in).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: '(?P<id>\d+)',
		methods: WP_REST_Server::DELETABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
	public function handle_delete_skill( WP_REST_Request $request ) {
		$id     = self::get_int_param( $request, 'id' );
		$result = Skill::delete( $id );

		if ( $result === 'builtin' ) {
			return new WP_Error(
				'gratis_ai_agent_skill_builtin_delete',
				__( 'Built-in skills cannot be deleted. You can disable them instead.', 'gratis-ai-agent' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $result ) {
			return new WP_Error(
				'gratis_ai_agent_skill_delete_failed',
				__( 'Failed to delete skill or skill not found.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /skills/{id}/reset — reset a built-in skill to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: '(?P<id>\d+)/reset',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
	public function handle_reset_skill( WP_REST_Request $request ) {
		$id    = self::get_int_param( $request, 'id' );
		$reset = Skill::reset_builtin( $id );

		if ( ! $reset ) {
			return new WP_Error(
				'gratis_ai_agent_skill_reset_failed',
				__( 'Failed to reset skill. Only built-in skills can be reset.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		$skill = Skill::get( $id );

		if ( ! $skill ) {
			return new WP_Error( 'gratis_ai_agent_skill_not_found', __( 'Skill not found after reset.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => (bool) (int) $skill->is_builtin,
				'enabled'     => (bool) (int) $skill->enabled,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			200
		);
	}

	/**
	 * Schema arguments for POST /skills (create).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_create_args(): array {
		return array(
			'slug'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'name'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'content'     => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
		);
	}

	/**
	 * Schema arguments for PATCH /skills/{id} (update).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_update_args(): array {
		return array(
			'id'          => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'name'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'content'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'enabled'     => array(
				'required' => false,
				'type'     => 'boolean',
			),
		);
	}

	/**
	 * Schema arguments for routes that only need an ID parameter.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_id_args(): array {
		return array(
			'id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}
}
