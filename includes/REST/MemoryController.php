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
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

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
 *
 * Uses #[Handler] + INIT_IMMEDIATELY so register_routes() is called directly
 * on rest_api_init, which is the only strategy that works in the PHPUnit test
 * environment (the #[REST_Handler] INIT_DEFFERED path fails to fire its
 * do_action chain when rest_api_init is manually triggered by test setUp()).
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class MemoryController {

	use PermissionTrait;

	/**
	 * Register REST routes for memory management.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/memory',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_create_args(),
				),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/memory/forget',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_forget_memory' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_forget_args(),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/memory/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'handle_update_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_delete_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_delete_args(),
				),
			)
		);
	}

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
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
