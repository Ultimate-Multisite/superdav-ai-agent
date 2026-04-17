<?php

declare(strict_types=1);
/**
 * REST API controller for memories.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Models\Memory;
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
 * Manages agent memories via REST.
 *
 * Endpoints:
 *  GET    /memory        — list all memories (optionally filtered by category)
 *  POST   /memory        — create a memory
 *  PATCH  /memory/{id}   — update a memory
 *  DELETE /memory/{id}   — delete a memory
 *  POST   /memory/forget — bulk-delete by topic
 */
#[REST_Handler(
	namespace: RestController::NAMESPACE,
	basename: 'memory',
	container: 'gratis-ai-agent',
)]
final class MemoryController extends XWP_REST_Controller {

	use PermissionTrait;

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	#[REST_Route(
		route: '',
		methods: WP_REST_Server::READABLE,
		guard: 'check_permission',
	)]
	public function handle_list_memory( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		// @phpstan-ignore-next-line
		$memories = Memory::get_all( $category ?: null );

		$list = array_map(
			function ( $m ) {
				return array(
					// @phpstan-ignore-next-line
					'id'         => (int) $m->id,
					// @phpstan-ignore-next-line
					'category'   => $m->category,
					// @phpstan-ignore-next-line
					'content'    => $m->content,
					// @phpstan-ignore-next-line
					'created_at' => $m->created_at,
					// @phpstan-ignore-next-line
					'updated_at' => $m->updated_at,
				);
			},
			$memories
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /memory — create a memory.
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
	public function handle_create_memory( WP_REST_Request $request ) {
		$category = $request->get_param( 'category' );
		$content  = $request->get_param( 'content' );

		// @phpstan-ignore-next-line
		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'gratis_ai_agent_memory_create_failed', __( 'Failed to create memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'       => $id,
				'category' => $category,
				'content'  => $content,
			),
			201
		);
	}

	/**
	 * Handle PATCH /memory/{id} — update a memory.
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
	public function handle_update_memory( WP_REST_Request $request ) {
		$id   = self::get_int_param( $request, 'id' );
		$data = array();

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		$updated = Memory::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'gratis_ai_agent_memory_update_failed', __( 'Failed to update memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'updated' => true,
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Handle DELETE /memory/{id} — delete a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: '(?P<id>\d+)',
		methods: WP_REST_Server::DELETABLE,
		vars: 'get_delete_args',
		guard: 'check_permission',
	)]
	public function handle_delete_memory( WP_REST_Request $request ) {
		$id      = self::get_int_param( $request, 'id' );
		$deleted = Memory::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'gratis_ai_agent_memory_delete_failed', __( 'Failed to delete memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /memory/forget — delete memories matching a topic.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	#[REST_Route(
		route: 'forget',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_forget_args',
		guard: 'check_permission',
	)]
	public function handle_forget_memory( WP_REST_Request $request ): WP_REST_Response {
		$topic = $request->get_param( 'topic' );
		// @phpstan-ignore-next-line
		$deleted = Memory::forget_by_topic( $topic );

		return new WP_REST_Response(
			array(
				'deleted' => $deleted,
				'topic'   => $topic,
			),
			200
		);
	}

	/**
	 * Schema arguments for POST /memory (create).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_create_args(): array {
		return array(
			'category' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'  => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Schema arguments for PATCH /memory/{id} (update).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_update_args(): array {
		return array(
			'id'       => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'category' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Schema arguments for DELETE /memory/{id}.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_delete_args(): array {
		return array(
			'id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Schema arguments for POST /memory/forget.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_forget_args(): array {
		return array(
			'topic' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
