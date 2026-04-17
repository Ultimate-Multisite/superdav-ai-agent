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
use GratisAiAgent\Services\SkillService;
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
 * Domain logic (formatting, business rules) is delegated to SkillService.
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
		// @phpstan-ignore-next-line
		$list = array_map( array( SkillService::class, 'format_skill' ), $skills );
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
		$id = SkillService::create_skill(
			$request->get_param( 'slug' ),
			$request->get_param( 'name' ),
			$request->get_param( 'description' ) ?? '',
			$request->get_param( 'content' )
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$skill = Skill::get( $id );

		if ( ! $skill ) {
			return new WP_Error( 'gratis_ai_agent_skill_not_found', __( 'Skill not found after creation.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( SkillService::format_skill( $skill ), 201 );
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

		return new WP_REST_Response( SkillService::format_skill( $skill ), 200 );
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
		$result = SkillService::delete_skill( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
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

		return new WP_REST_Response( SkillService::format_skill( $skill ), 200 );
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
