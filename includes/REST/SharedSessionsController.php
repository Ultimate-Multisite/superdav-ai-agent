<?php

declare(strict_types=1);
/**
 * REST API controller for shared conversation sessions.
 *
 * Allows admin users to share sessions with other admin users and
 * list sessions shared with them.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\Database;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SharedSessionsController {

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Register REST routes for shared sessions.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// GET /sessions/{id}/shares — list users a session is shared with.
		// POST /sessions/{id}/shares — share with a user.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/shares',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $instance, 'handle_list_shares' ],
					'permission_callback' => [ $instance, 'check_owner_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $instance, 'handle_share_session' ],
					'permission_callback' => [ $instance, 'check_owner_permission' ],
					'args'                => [
						'id'                  => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'shared_with_user_id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'permission'          => [
							'required'          => false,
							'type'              => 'string',
							'default'           => 'contribute',
							'sanitize_callback' => 'sanitize_text_field',
							'enum'              => [ 'view', 'contribute' ],
						],
					],
				],
			]
		);

		// DELETE /sessions/{id}/shares/{user_id} — revoke a user's access.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $instance, 'handle_unshare_session' ],
				'permission_callback' => [ $instance, 'check_owner_permission' ],
				'args'                => [
					'id'      => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'user_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// GET /sessions/shared — list sessions shared with the current user.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/shared',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_list_shared_with_me' ],
				'permission_callback' => [ $instance, 'check_permission' ],
			]
		);

		// GET /users/admins — list admin users for the share picker.
		register_rest_route(
			self::NAMESPACE,
			'/users/admins',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_list_admins' ],
				'permission_callback' => [ $instance, 'check_permission' ],
			]
		);
	}

	/**
	 * Permission check — admin only.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check — admin + session owner.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function check_owner_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$session_id = absint( $request->get_param( 'id' ) );
		$session    = Database::get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		return (int) $session->user_id === get_current_user_id();
	}

	/**
	 * GET /sessions/{id}/shares
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_list_shares( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$shares     = Database::get_session_shares( $session_id );

		return new WP_REST_Response( $shares, 200 );
	}

	/**
	 * POST /sessions/{id}/shares
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_share_session( WP_REST_Request $request ) {
		$session_id          = absint( $request->get_param( 'id' ) );
		$shared_with_user_id = absint( $request->get_param( 'shared_with_user_id' ) );
		$permission          = sanitize_text_field( $request->get_param( 'permission' ) ?? 'contribute' );

		if ( ! in_array( $permission, [ 'view', 'contribute' ], true ) ) {
			$permission = 'contribute';
		}

		// Prevent sharing with yourself.
		if ( $shared_with_user_id === get_current_user_id() ) {
			return new WP_Error(
				'gratis_ai_agent_share_self',
				__( 'You cannot share a session with yourself.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		// Verify the target user exists and is an admin.
		$target_user = get_userdata( $shared_with_user_id );
		if ( ! $target_user || ! user_can( $shared_with_user_id, 'manage_options' ) ) {
			return new WP_Error(
				'gratis_ai_agent_share_invalid_user',
				__( 'Target user not found or does not have admin access.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$result = Database::share_session_with_user(
			$session_id,
			get_current_user_id(),
			$shared_with_user_id,
			$permission
		);

		if ( false === $result ) {
			return new WP_Error(
				'gratis_ai_agent_share_failed',
				__( 'Failed to share session.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$shares = Database::get_session_shares( $session_id );

		return new WP_REST_Response( $shares, 200 );
	}

	/**
	 * DELETE /sessions/{id}/shares/{user_id}
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_unshare_session( WP_REST_Request $request ) {
		$session_id          = absint( $request->get_param( 'id' ) );
		$shared_with_user_id = absint( $request->get_param( 'user_id' ) );

		Database::unshare_session_with_user( $session_id, $shared_with_user_id );

		$shares = Database::get_session_shares( $session_id );

		return new WP_REST_Response( $shares, 200 );
	}

	/**
	 * GET /sessions/shared — sessions shared with the current user.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_shared_with_me( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$sessions = Database::list_shared_sessions( $user_id );

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * GET /users/admins — list admin users for the share picker (excludes current user).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_admins( WP_REST_Request $request ) {
		$current_user_id = get_current_user_id();

		$admins = get_users(
			[
				'role'    => 'administrator',
				'exclude' => [ $current_user_id ],
				'fields'  => [ 'ID', 'display_name', 'user_email' ],
				'number'  => 100,
			]
		);

		$result = array_map(
			static function ( $user ) {
				return [
					'id'           => (int) $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
				];
			},
			$admins
		);

		return new WP_REST_Response( $result, 200 );
	}
}
