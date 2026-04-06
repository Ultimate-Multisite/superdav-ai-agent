<?php

declare(strict_types=1);
/**
 * REST API controller for sessions, messages, folders, sharing, export/import,
 * site-builder, job-status, process, and tool confirmation.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Export;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Models\Agent;
use GratisAiAgent\REST\SseStreamer;
use GratisAiAgent\REST\WebhookDatabase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SessionController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/** @var Settings Injected settings dependency. */
	private Settings $settings;

	/** @var Database Injected database dependency. */
	private Database $database;

	/**
	 * Constructor — accepts injected dependencies for testability.
	 *
	 * @param Settings|null $settings  Settings service (defaults to new Settings()).
	 * @param Database|null $database  Database service (defaults to new Database()).
	 */
	public function __construct( ?Settings $settings = null, ?Database $database = null ) {
		$this->settings = $settings ?? new Settings();
		$this->database = $database ?? new Database();
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// Sessions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_sessions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'status' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'active',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'folder' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'search' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'pinned' => array(
							'required' => false,
							'type'     => 'boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_session' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'title'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider_id' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'agent_id'    => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/folders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_folders' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_bulk_sessions' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'ids'    => array(
						'required' => true,
						'type'     => 'array',
					),
					'action' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'folder' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/trash',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $instance, 'handle_empty_trash' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id'     => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'title'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'pinned' => array(
							'required' => false,
							'type'     => 'boolean',
						),
						'folder' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Export endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_export_session' ),
				'permission_callback' => array( $instance, 'check_session_permission' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'format' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'json',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Import endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_import_session' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Shared sessions list endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/shared',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_shared_sessions' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Share / unshare a session.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/share',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_share_session' ),
					'permission_callback' => array( $instance, 'check_session_owner_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_unshare_session' ),
					'permission_callback' => array( $instance, 'check_session_owner_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Job status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_job_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Process endpoint (background worker).
		register_rest_route(
			self::NAMESPACE,
			'/process',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_process' ),
				'permission_callback' => array( $instance, 'check_process_permission' ),
				'args'                => array(
					'job_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Run endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_run' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'message'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'history'            => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'abilities'          => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'system_instruction' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'max_iterations'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'provider_id'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'model_id'           => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_context'       => array(
						'required'          => false,
						'type'              => array( 'object', 'string' ),
						'default'           => array(),
						'sanitize_callback' => array( RestController::class, 'sanitize_page_context' ),
					),
					'agent_id'           => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Tool confirmation endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_confirm_tool' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'always_allow' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_reject_tool' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Site builder endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/site-builder/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_site_builder_start' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/site-builder/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_site_builder_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);
	}

	/**
	 * Handle GET /sessions — list sessions for current user.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_sessions( WP_REST_Request $request ): WP_REST_Response {
		$filters = array();

		if ( $request->has_param( 'status' ) ) {
			$filters['status'] = $request->get_param( 'status' );
		}
		if ( $request->has_param( 'folder' ) ) {
			$filters['folder'] = $request->get_param( 'folder' );
		}
		if ( $request->has_param( 'search' ) ) {
			$filters['search'] = $request->get_param( 'search' );
		}
		if ( $request->has_param( 'pinned' ) ) {
			$filters['pinned'] = $request->get_param( 'pinned' );
		}

		$sessions = $this->database->list_sessions( get_current_user_id(), $filters );

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle GET /sessions/folders — list folders for current user.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_folders(): WP_REST_Response {
		$folders = $this->database->list_folders( get_current_user_id() );

		return new WP_REST_Response( $folders, 200 );
	}

	/**
	 * Handle POST /sessions/bulk — bulk update sessions.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_bulk_sessions( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$ids    = array_map( 'absint', $request->get_param( 'ids' ) );
		$action = $request->get_param( 'action' );

		$data = array();
		switch ( $action ) {
			case 'archive':
				$data['status'] = 'archived';
				break;
			case 'restore':
				$data['status'] = 'active';
				break;
			case 'trash':
				$data['status'] = 'trash';
				break;
			case 'pin':
				$data['pinned'] = 1;
				break;
			case 'unpin':
				$data['pinned'] = 0;
				break;
			case 'move':
				// @phpstan-ignore-next-line
				$data['folder'] = sanitize_text_field( $request->get_param( 'folder' ) ?? '' );
				break;
			default:
				return new WP_Error( 'gratis_ai_agent_invalid_action', __( 'Invalid bulk action.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$count = $this->database->bulk_update_sessions( $ids, get_current_user_id(), $data );

		return new WP_REST_Response( array( 'updated' => $count ), 200 );
	}

	/**
	 * Handle DELETE /sessions/trash — empty trash for current user.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_empty_trash(): WP_REST_Response {
		$count = $this->database->empty_trash( get_current_user_id() );

		return new WP_REST_Response( array( 'deleted' => $count ), 200 );
	}

	/**
	 * Handle GET /sessions/{id} — get full session with messages.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error(
				'gratis_ai_agent_session_not_found',
				__( 'Session not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		$shared    = Database::get_shared_session( (int) $session->id );
		$is_shared = $shared !== null;

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => json_decode( $session->messages, true ) ?: array(),
				'tool_calls'  => json_decode( $session->tool_calls, true ) ?: array(),
				'token_usage' => array(
					'prompt'     => (int) ( $session->prompt_tokens ?? 0 ),
					'completion' => (int) ( $session->completion_tokens ?? 0 ),
				),
				'is_shared'   => $is_shared,
				'shared_by'   => $is_shared ? (int) $shared->shared_by : null,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			200
		);
	}

	/**
	 * Handle POST /sessions — create a new session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_session( WP_REST_Request $request ) {
		$provider_id = $request->get_param( 'provider_id' ) ?? '';
		$model_id    = $request->get_param( 'model_id' ) ?? '';

		// If an agent is selected, resolve its provider/model overrides so the
		// session is stored with the agent's effective provider/model rather than
		// the caller's pre-agent selection.
		// @phpstan-ignore-next-line
		$agent_id = (int) ( $request->get_param( 'agent_id' ) ?? 0 );
		if ( $agent_id > 0 ) {
			$agent_options = Agent::get_loop_options( $agent_id );
			if ( ! empty( $agent_options['provider_id'] ) ) {
				$provider_id = $agent_options['provider_id'];
			}
			if ( ! empty( $agent_options['model_id'] ) ) {
				$model_id = $agent_options['model_id'];
			}
		}

		$session_id = $this->database->create_session(
			array(
				'user_id'     => get_current_user_id(),
				'title'       => $request->get_param( 'title' ),
				'provider_id' => $provider_id,
				'model_id'    => $model_id,
			)
		);

		if ( ! $session_id ) {
			return new WP_Error(
				'gratis_ai_agent_session_create_failed',
				__( 'Failed to create session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$session = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'gratis_ai_agent_session_not_found', __( 'Session not found after creation.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => array(),
				'tool_calls'  => array(),
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /sessions/{id} — update session fields.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );

		$data = array();
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}
		if ( $request->has_param( 'status' ) ) {
			$status = $request->get_param( 'status' );
			if ( in_array( $status, array( 'active', 'archived', 'trash' ), true ) ) {
				$data['status'] = $status;
			}
		}
		if ( $request->has_param( 'pinned' ) ) {
			$data['pinned'] = $request->get_param( 'pinned' ) ? 1 : 0;
		}
		if ( $request->has_param( 'folder' ) ) {
			$data['folder'] = $request->get_param( 'folder' );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'gratis_ai_agent_no_data', __( 'No fields to update.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$updated = $this->database->update_session( $session_id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_session_update_failed',
				__( 'Failed to update session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$session = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'gratis_ai_agent_session_not_found', __( 'Session not found after update.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'status'      => $session->status,
				'pinned'      => (bool) (int) $session->pinned,
				'folder'      => $session->folder,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /sessions/{id} — delete a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );

		$deleted = $this->database->delete_session( $session_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'gratis_ai_agent_session_delete_failed',
				__( 'Failed to delete session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle GET /sessions/shared — list all sessions shared with admins.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_shared_sessions(): WP_REST_Response {
		$sessions = Database::list_shared_sessions();

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle POST /sessions/{id}/share — share a session with all admins.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_share_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );
		$success    = Database::share_session( $session_id, get_current_user_id() );

		if ( ! $success ) {
			return new WP_Error(
				'gratis_ai_agent_share_failed',
				__( 'Failed to share session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'shared' => true ), 200 );
	}

	/**
	 * Handle DELETE /sessions/{id}/share — unshare a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_unshare_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );
		$success    = Database::unshare_session( $session_id );

		if ( ! $success ) {
			return new WP_Error(
				'gratis_ai_agent_unshare_failed',
				__( 'Failed to unshare session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'shared' => false ), 200 );
	}

	/**
	 * Handle GET /sessions/{id}/export — export a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_session( WP_REST_Request $request ) {
		$session_id = self::get_int_param( $request, 'id' );
		$format     = $request->get_param( 'format' ) ?: 'json';
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'gratis_ai_agent_session_not_found', __( 'Session not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		// @phpstan-ignore-next-line
		$result = Export::export( $session, $format );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle POST /sessions/import — import a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_import_session( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			return new WP_Error( 'gratis_ai_agent_import_empty', __( 'No import data provided.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$session_id = Export::import_json( $data, get_current_user_id() );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		$session = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'gratis_ai_agent_session_not_found', __( 'Session not found after import.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			201
		);
	}

	/**
	 * Handle the /job/{id} polling endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_job_status( WP_REST_Request $request ) {
		$job_id = self::get_string_param( $request, 'id' );
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );

		if ( false === $job || ! is_array( $job ) ) {
			return new WP_Error(
				'gratis_ai_agent_job_not_found',
				__( 'Job not found or expired.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		/** @var array<string, mixed> $job */

		$response = array( 'status' => $job['status'] );

		if ( 'awaiting_confirmation' === $job['status'] && isset( $job['pending_tools'] ) ) {
			$response['pending_tools'] = $job['pending_tools'];
			return new WP_REST_Response( $response, 200 );
		}

		if ( 'complete' === $job['status'] && isset( $job['result'] ) ) {
			// @phpstan-ignore-next-line
			$response['reply'] = $job['result']['reply'] ?? '';
			// @phpstan-ignore-next-line
			$response['history'] = $job['result']['history'] ?? array();
			// @phpstan-ignore-next-line
			$response['tool_calls'] = $job['result']['tool_calls'] ?? array();
			// @phpstan-ignore-next-line
			$response['session_id'] = $job['result']['session_id'] ?? null;
			// @phpstan-ignore-next-line
			$response['token_usage'] = $job['result']['token_usage'] ?? array(
				'prompt'     => 0,
				'completion' => 0,
			);
			// @phpstan-ignore-next-line
			$response['model_id'] = $job['result']['model_id'] ?? ( $job['params']['model_id'] ?? '' );
			// @phpstan-ignore-next-line
			$response['iterations_used'] = $job['result']['iterations_used'] ?? 0;

			// Include generated title if one was produced.
			// @phpstan-ignore-next-line
			if ( isset( $job['result']['generated_title'] ) ) {
				$response['generated_title'] = $job['result']['generated_title'];
			}

			// Compute cost estimate from token usage and model.
			$model                     = $response['model_id'];
			$tokens                    = $response['token_usage'];
			$response['cost_estimate'] = CostCalculator::calculate_cost(
				// @phpstan-ignore-next-line
				$model,
				// @phpstan-ignore-next-line
				(int) ( $tokens['prompt'] ?? 0 ),
				// @phpstan-ignore-next-line
				(int) ( $tokens['completion'] ?? 0 )
			);

			// Clean up — result has been delivered.
			delete_transient( RestController::JOB_PREFIX . $job_id );
		}

		if ( 'error' === $job['status'] && isset( $job['error'] ) ) {
			$response['message'] = $job['error'];

			// Clean up.
			delete_transient( RestController::JOB_PREFIX . $job_id );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle POST /job/{id}/confirm — user approves a pending tool call.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_confirm_tool( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$job_id = (string) $request->get_param( 'id' );
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'gratis_ai_agent_forbidden', __( 'Not authorized.', 'gratis-ai-agent' ), array( 'status' => 403 ) );
		}

		// "Always allow" — update tool_permissions to auto.
		if ( $request->get_param( 'always_allow' ) && ! empty( $job['pending_tools'] ) ) {
			$settings = $this->settings->get();
			/** @var array<string, mixed> $settings */
			$perms = $settings['tool_permissions'] ?? array();
			/** @var array<string, mixed> $perms */
			// @phpstan-ignore-next-line
			foreach ( $job['pending_tools'] as $tool ) {
				/** @var array<string, mixed> $tool */
				// @phpstan-ignore-next-line
				$perms[ (string) $tool['name'] ] = 'auto';
			}
			$this->settings->update( array( 'tool_permissions' => $perms ) );
		}

		return $this->resume_job( $job_id, $job, 'confirm' );
	}

	/**
	 * Handle POST /job/{id}/reject — user denies a pending tool call.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reject_tool( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$job_id = (string) $request->get_param( 'id' );
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'gratis_ai_agent_forbidden', __( 'Not authorized.', 'gratis-ai-agent' ), array( 'status' => 403 ) );
		}

		return $this->resume_job( $job_id, $job, 'reject' );
	}

	/**
	 * Resume a paused job after confirmation or rejection.
	 *
	 * @param string               $job_id Job identifier.
	 * @param array<string, mixed> $job    Job transient data.
	 * @param string               $action 'confirm' or 'reject'.
	 * @return WP_REST_Response
	 */
	private static function resume_job( string $job_id, array $job, string $action ): WP_REST_Response {
		$token = wp_generate_password( 40, false );

		$job['status'] = 'processing';
		$job['token']  = $token;
		$job['resume'] = $action;

		set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );

		// Spawn background worker.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'body'     => (string) wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return new WP_REST_Response(
			array(
				'status' => 'processing',
				'job_id' => $job_id,
			),
			200
		);
	}

	/**
	 * Handle the /run endpoint.
	 *
	 * Creates a job, spawns a background worker, and returns immediately.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_run( WP_REST_Request $request ) {
		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );

		$job = array(
			'status'  => 'processing',
			'token'   => $token,
			'user_id' => get_current_user_id(),
			'params'  => array(
				'message'            => $request->get_param( 'message' ),
				'history'            => $request->get_param( 'history' ),
				'abilities'          => $request->get_param( 'abilities' ),
				'system_instruction' => $request->get_param( 'system_instruction' ),
				'max_iterations'     => $request->get_param( 'max_iterations' ),
				'session_id'         => $request->get_param( 'session_id' ),
				'provider_id'        => $request->get_param( 'provider_id' ),
				'model_id'           => $request->get_param( 'model_id' ),
				'page_context'       => $request->get_param( 'page_context' ),
				'agent_id'           => $request->get_param( 'agent_id' ),
			),
		);

		set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );

		// Spawn background worker via non-blocking loopback.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'body'     => (string) wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return new WP_REST_Response(
			array(
				'job_id' => $job_id,
				'status' => 'processing',
			),
			202
		);
	}

	/**
	 * Handle the internal /process endpoint (background worker).
	 *
	 * Runs the Agent_Loop and stores the result in the job transient.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_process( WP_REST_Request $request ): WP_REST_Response {
		ignore_user_abort( true );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Agent loops need extended execution time.
		set_time_limit( 600 );

		// @phpstan-ignore-next-line
		$job_id = (string) $request->get_param( 'job_id' );
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['params'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		/** @var array<string, mixed> $job */

		// Restore the user context — the loopback request has no cookies,
		// but the AI Client needs a user for provider auth binding.
		if ( ! empty( $job['user_id'] ) ) {
			// @phpstan-ignore-next-line
			wp_set_current_user( (int) $job['user_id'] );
		}

		$params = $job['params'];
		/** @var array<string, mixed> $params */
		// @phpstan-ignore-next-line
		$session_id = ! empty( $params['session_id'] ) ? (int) $params['session_id'] : 0;

		// Load history from session if session_id is provided.
		$history = array();
		if ( $session_id ) {
			$session = $this->database->get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: array();
				if ( ! empty( $session_messages ) ) {
					try {
						// @phpstan-ignore-next-line
						$history = AgentLoop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = array();
					}
				}
			}
		} elseif ( ! empty( $params['history'] ) && is_array( $params['history'] ) ) {
			try {
				/** @var list<array<string, mixed>> $params_history */
				$params_history = $params['history'];
				$history        = AgentLoop::deserialize_history( $params_history );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Invalid conversation history format.', 'gratis-ai-agent' );
				unset( $job['token'] );
				set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );
				return new WP_REST_Response( array( 'ok' => false ), 200 );
			}
		}

		$options = array(
			'max_iterations' => $params['max_iterations'] ?? 10,
		);

		if ( ! empty( $params['system_instruction'] ) ) {
			$options['system_instruction'] = $params['system_instruction'];
		}

		if ( ! empty( $params['provider_id'] ) ) {
			$options['provider_id'] = $params['provider_id'];
		}

		if ( ! empty( $params['model_id'] ) ) {
			$options['model_id'] = $params['model_id'];
		}

		if ( ! empty( $params['page_context'] ) ) {
			$options['page_context'] = $params['page_context'];
		}

		// Pass session_id to AgentLoop for change attribution.
		if ( ! empty( $params['session_id'] ) ) {
			// @phpstan-ignore-next-line
			$options['session_id'] = (int) $params['session_id'];
		}

		// Apply agent overrides (agent_id takes precedence over individual params).
		if ( ! empty( $params['agent_id'] ) ) {
			// @phpstan-ignore-next-line
			$agent_options = Agent::get_loop_options( (int) $params['agent_id'] );
			$options       = array_merge( $options, $agent_options );
		}

		// Record start time for webhook duration tracking.
		$start_ms = (int) round( microtime( true ) * 1000 );

		// Check if this is a resume from a tool confirmation/rejection.
		$is_resume = ! empty( $job['resume'] );

		if ( $is_resume ) {
			$confirmed = 'confirm' === $job['resume'];
			$state     = $job['confirmation_state'] ?? array();

			try {
				// @phpstan-ignore-next-line
				$resume_history = AgentLoop::deserialize_history( $state['history'] ?? array() );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Failed to resume conversation.', 'gratis-ai-agent' );
				unset( $job['token'] );
				set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );
				return new WP_REST_Response( array( 'ok' => false ), 200 );
			}

			$resume_options = $options;
			// @phpstan-ignore-next-line
			$resume_options['tool_call_log'] = $state['tool_call_log'] ?? array();
			// @phpstan-ignore-next-line
			$resume_options['token_usage'] = $state['token_usage'] ?? array(
				'prompt'     => 0,
				'completion' => 0,
			);

			$loop = new AgentLoop( '', array(), $resume_history, $resume_options );
			// @phpstan-ignore-next-line
			$result = $loop->resume_after_confirmation( $confirmed, $state['iterations_remaining'] ?? 5 );
		} else {
			$abilities = $params['abilities'] ?? array();
			// @phpstan-ignore-next-line
			$loop   = new AgentLoop( (string) $params['message'], is_array( $abilities ) ? $abilities : array(), $history, $options );
			$result = $loop->run();
		}

		if ( is_wp_error( $result ) ) {
			$job['status'] = 'error';
			$job['error']  = $result->get_error_message();

			// Log webhook execution failure.
			if ( ! empty( $job['webhook_id'] ) ) {
				$duration_ms = $start_ms > 0 ? (int) round( microtime( true ) * 1000 ) - $start_ms : 0;
				WebhookDatabase::log_execution(
					// @phpstan-ignore-next-line
					(int) $job['webhook_id'],
					'error',
					'',
					array(),
					0,
					0,
					$duration_ms,
					$result->get_error_message()
				);
			}
		} elseif ( is_array( $result ) && ! empty( $result['awaiting_confirmation'] ) ) {
			/** @var array<string, mixed> $result */
			$job['status']             = 'awaiting_confirmation';
			$job['pending_tools']      = $result['pending_tools'] ?? array();
			$job['confirmation_state'] = array(
				'history'              => $result['history'] ?? array(),
				'tool_call_log'        => $result['tool_call_log'] ?? array(),
				'token_usage'          => $result['token_usage'] ?? array(
					'prompt'     => 0,
					'completion' => 0,
				),
				'iterations_remaining' => $result['iterations_remaining'] ?? 5,
			);
			// Keep token and params for the resume flow.
			unset( $job['token'] );
			set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		} else {
			/** @var array<string, mixed> $result */
			$job['status'] = 'complete';
			$job['result'] = $result;

			// Persist to session if session_id is provided.
			if ( $session_id ) {
				$job['result']['session_id'] = $session_id;

				// The full history from the loop includes existing + new messages.
				// Slice off only the new ones to append.
				$session        = $this->database->get_session( $session_id );
				$existing_count = 0;
				if ( $session ) {
					$existing_messages = json_decode( $session->messages, true ) ?: array();
					// @phpstan-ignore-next-line
					$existing_count = count( $existing_messages );
				}

				$full_history = $result['history'] ?? array();
				/** @var array<mixed> $full_history */
				$appended = array_slice( $full_history, $existing_count );
				/** @var list<array<string, mixed>> $tool_calls_result */
				$tool_calls_result = $result['tool_calls'] ?? array();
				$this->database->append_to_session( $session_id, array_values( $appended ), $tool_calls_result );

				// Persist token usage.
				$token_usage = $result['token_usage'] ?? array();
				/** @var array<string, mixed> $token_usage */
				if ( ! empty( $token_usage ) ) {
					$this->database->update_session_tokens(
						$session_id,
						// @phpstan-ignore-next-line
						(int) ( $token_usage['prompt'] ?? 0 ),
						// @phpstan-ignore-next-line
						(int) ( $token_usage['completion'] ?? 0 )
					);
				}

				// Log to usage tracking table.
				// Use resolved options (which include agent overrides) rather than raw params.
				// @phpstan-ignore-next-line
				$provider_id = (string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' );
				// @phpstan-ignore-next-line
				$model_id = (string) ( $options['model_id'] ?? $params['model_id'] ?? '' );
				// @phpstan-ignore-next-line
				$prompt_t = (int) ( $token_usage['prompt'] ?? 0 );
				// @phpstan-ignore-next-line
				$completion_t = (int) ( $token_usage['completion'] ?? 0 );

				if ( $prompt_t > 0 || $completion_t > 0 ) {
					$cost = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
					$this->database->log_usage(
						array(
							'user_id'           => $job['user_id'] ?? 0,
							'session_id'        => $session_id,
							'provider_id'       => $provider_id,
							'model_id'          => $model_id,
							'prompt_tokens'     => $prompt_t,
							'completion_tokens' => $completion_t,
							'cost_usd'          => $cost,
						)
					);
				}

				// Auto-generate title from first user message if empty.
				if ( $session && empty( $session->title ) ) {
					// @phpstan-ignore-next-line
					$reply = (string) ( $result['reply'] ?? '' );
					$title = RestController::generate_session_title(
						// @phpstan-ignore-next-line
						(string) $params['message'],
						$reply,
						// @phpstan-ignore-next-line
						(string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' ),
						// @phpstan-ignore-next-line
						(string) ( $options['model_id'] ?? $params['model_id'] ?? '' )
					);
					$this->database->update_session( $session_id, array( 'title' => $title ) );
					$job['result']['generated_title'] = $title;
				}
			}

			// Log webhook execution success.
			if ( ! empty( $job['webhook_id'] ) ) {
				$token_usage = $result['token_usage'] ?? array(
					'prompt'     => 0,
					'completion' => 0,
				);
				/** @var array<string, mixed> $token_usage */
				$duration_ms = $start_ms > 0 ? (int) round( microtime( true ) * 1000 ) - $start_ms : 0;
				/** @var list<array<string, mixed>> $tool_calls_webhook */
				$tool_calls_webhook = $result['tool_calls'] ?? array();
				WebhookDatabase::log_execution(
					// @phpstan-ignore-next-line
					(int) $job['webhook_id'],
					'success',
					// @phpstan-ignore-next-line
					(string) ( $result['reply'] ?? '' ),
					$tool_calls_webhook,
					// @phpstan-ignore-next-line
					(int) ( $token_usage['prompt'] ?? 0 ),
					// @phpstan-ignore-next-line
					(int) ( $token_usage['completion'] ?? 0 ),
					$duration_ms,
					''
				);
			}
		}

		// Clear the token — no longer needed.
		unset( $job['token'] );
		set_transient( RestController::JOB_PREFIX . $job_id, $job, RestController::JOB_TTL );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Handle POST /site-builder/start.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_site_builder_start(): WP_REST_Response {
		// Enable site builder mode in settings.
		$this->settings->update( array( 'site_builder_mode' => true ) );

		// Create a dedicated session for the site builder conversation.
		$session_id = Database::create_session(
			array(
				'user_id'     => get_current_user_id(),
				'title'       => __( 'Site Builder', 'gratis-ai-agent' ),
				'provider_id' => $this->settings->get( 'default_provider' ) ?: '',
				'model_id'    => $this->settings->get( 'default_model' ) ?: '',
			)
		);

		return new WP_REST_Response(
			array(
				'started'           => true,
				'site_builder_mode' => true,
				'session_id'        => $session_id,
				'system_prompt'     => AgentLoop::get_site_builder_system_prompt(),
				'message'           => __( 'Site builder mode enabled. The widget will open automatically.', 'gratis-ai-agent' ),
			),
			200
		);
	}

	/**
	 * Handle GET /site-builder/status.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_site_builder_status(): WP_REST_Response {
		$settings = $this->settings->get();

		// Run fresh install detection.
		$fresh_install = \GratisAiAgent\Abilities\SiteBuilderAbilities::check_fresh_install();

		return new WP_REST_Response(
			array(
				// @phpstan-ignore-next-line
				'site_builder_mode'   => (bool) ( $settings['site_builder_mode'] ?? false ),
				// @phpstan-ignore-next-line
				'onboarding_complete' => (bool) ( $settings['onboarding_complete'] ?? false ),
				'is_fresh_install'    => $fresh_install['is_fresh'],
				'post_count'          => $fresh_install['post_count'],
				'page_count'          => $fresh_install['page_count'],
				'site_title'          => get_bloginfo( 'name' ),
			),
			200
		);
	}
}
