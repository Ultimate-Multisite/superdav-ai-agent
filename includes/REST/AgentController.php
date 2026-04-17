<?php

declare(strict_types=1);
/**
 * REST API controller for agents and conversation templates.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Models\Agent;
use GratisAiAgent\Models\ConversationTemplate;
use GratisAiAgent\Models\DTO\AgentRow;
use GratisAiAgent\Models\DTO\ConversationTemplateRow;
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
 * Manages agents and conversation templates via REST.
 *
 * Uses #[Handler] + #[Action] because this controller serves multiple
 * basenames (/agents, /conversation-templates).
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AgentController {

	use PermissionTrait;

	/**
	 * Register REST routes.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {

		// Agents endpoints.
		register_rest_route(
			RestController::NAMESPACE,
			'/agents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list_agents' ),
					'permission_callback' => array( $this, 'check_chat_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_agent' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'system_prompt'  => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'provider_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tool_profile'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'temperature'    => array(
							'required' => false,
							'type'     => 'number',
						),
						'max_iterations' => array(
							'required' => false,
							'type'     => 'integer',
						),
						'greeting'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'avatar_icon'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/agents/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_agent' ),
					'permission_callback' => array( $this, 'check_permission' ),
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
					'callback'            => array( $this, 'handle_update_agent' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'             => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'name'           => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'system_prompt'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'provider_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tool_profile'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'temperature'    => array(
							'required' => false,
							'type'     => 'number',
						),
						'max_iterations' => array(
							'required' => false,
							'type'     => 'integer',
						),
						'greeting'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'avatar_icon'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'enabled'        => array(
							'required' => false,
							'type'     => 'boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_delete_agent' ),
					'permission_callback' => array( $this, 'check_permission' ),
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

		// Conversation Templates endpoints.
		register_rest_route(
			RestController::NAMESPACE,
			'/conversation-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list_conversation_templates' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_conversation_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'name'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'prompt' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/conversation-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'handle_update_conversation_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
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
					'callback'            => array( $this, 'handle_delete_conversation_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
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
	}

	/**
	 * Handle GET /agents — list all agents.
	 *
	 * Returns only public-safe fields so that chat users cannot read system_prompt or
	 * provider/model configuration.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_agents(): WP_REST_Response {
		$agents = Agent::get_all();
		$list   = array_map(
			static function ( AgentRow $agent ): array {
				return array(
					'id'          => $agent->id,
					'slug'        => $agent->slug,
					'name'        => $agent->name,
					'description' => $agent->description,
					'avatar_icon' => $agent->avatar_icon,
					'greeting'    => $agent->greeting,
					'enabled'     => $agent->enabled,
				);
			},
			$agents
		);
		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle GET /agents/{id} — get a single agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_agent( WP_REST_Request $request ) {
		$id    = self::get_int_param( $request, 'id' );
		$agent = Agent::get( $id );

		if ( ! $agent ) {
			return new WP_Error(
				'gratis_ai_agent_agent_not_found',
				__( 'Agent not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( Agent::to_array( $agent ), 200 );
	}

	/**
	 * Handle POST /agents — create a new agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_agent( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		// @phpstan-ignore-next-line
		$existing = Agent::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'gratis_ai_agent_agent_slug_exists',
				__( 'An agent with this slug already exists.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = Agent::create(
			array(
				'slug'           => $slug,
				'name'           => $request->get_param( 'name' ),
				'description'    => $request->get_param( 'description' ) ?? '',
				'system_prompt'  => $request->get_param( 'system_prompt' ) ?? '',
				'provider_id'    => $request->get_param( 'provider_id' ) ?? '',
				'model_id'       => $request->get_param( 'model_id' ) ?? '',
				'tool_profile'   => $request->get_param( 'tool_profile' ) ?? '',
				'temperature'    => $request->get_param( 'temperature' ),
				'max_iterations' => $request->get_param( 'max_iterations' ),
				'greeting'       => $request->get_param( 'greeting' ) ?? '',
				'avatar_icon'    => $request->get_param( 'avatar_icon' ) ?? '',
				'enabled'        => true,
			)
		);

		if ( false === $id ) {
			return new WP_Error(
				'gratis_ai_agent_agent_create_failed',
				__( 'Failed to create agent.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$agent = Agent::get( $id );

		if ( ! $agent ) {
			return new WP_Error( 'gratis_ai_agent_agent_not_found', __( 'Agent not found after creation.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( Agent::to_array( $agent ), 201 );
	}

	/**
	 * Handle PATCH /agents/{id} — update an agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_agent( WP_REST_Request $request ) {
		$id   = self::get_int_param( $request, 'id' );
		$data = array();

		$fields = array(
			'name',
			'description',
			'system_prompt',
			'provider_id',
			'model_id',
			'tool_profile',
			'temperature',
			'max_iterations',
			'greeting',
			'avatar_icon',
			'enabled',
		);

		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		$updated = Agent::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_agent_update_failed',
				__( 'Failed to update agent.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$agent = Agent::get( $id );

		if ( ! $agent ) {
			return new WP_Error( 'gratis_ai_agent_agent_not_found', __( 'Agent not found after update.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( Agent::to_array( $agent ), 200 );
	}

	/**
	 * Handle DELETE /agents/{id} — delete an agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_agent( WP_REST_Request $request ) {
		$id     = self::get_int_param( $request, 'id' );
		$result = Agent::delete( $id );

		if ( ! $result ) {
			return new WP_Error(
				'gratis_ai_agent_agent_delete_failed',
				__( 'Failed to delete agent or agent not found.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * List conversation templates, optionally filtered by category.
	 */
	public function handle_list_conversation_templates( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		// @phpstan-ignore-next-line
		$templates = ConversationTemplate::get_all( $category ?: null );

		return new WP_REST_Response( $templates, 200 );
	}

	/**
	 * Create a conversation template.
	 */
	public function handle_create_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = ConversationTemplate::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create conversation template.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( ConversationTemplate::get( $id ), 201 );
	}

	/**
	 * Update a conversation template.
	 */
	public function handle_update_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = self::get_int_param( $request, 'id' );
		$data = $request->get_json_params();

		if ( ! ConversationTemplate::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update conversation template.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( ConversationTemplate::get( $id ), 200 );
	}

	/**
	 * Delete a conversation template. Built-in templates cannot be deleted.
	 */
	public function handle_delete_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = self::get_int_param( $request, 'id' );

		if ( ! ConversationTemplate::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete conversation template. Built-in templates cannot be deleted.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}
}
