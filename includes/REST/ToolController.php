<?php

declare(strict_types=1);
/**
 * REST API controller for custom-tools, abilities, and abilities-explorer.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\REST;

use SdAiAgent\Services\AbilityExplorerService;
use SdAiAgent\Tools\CustomToolExecutor;
use SdAiAgent\Tools\CustomTools;
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
 * Manages custom-tools and abilities endpoints via REST.
 *
 * Ability listing domain logic is delegated to AbilityExplorerService.
 *
 * Uses #[Handler] + #[Action] instead of #[REST_Handler] because this
 * controller serves multiple basenames (/abilities, /custom-tools) and
 * the REST_Handler decorator supports only a single basename per class.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class ToolController {

	use PermissionTrait;

	/**
	 * Register REST routes.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {

		// Abilities endpoints.
		register_rest_route(
			RestController::NAMESPACE,
			'/abilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_abilities' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Abilities Explorer endpoint.
		register_rest_route(
			RestController::NAMESPACE,
			'/abilities/explorer',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_abilities_explorer' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Custom Tools endpoints.
		register_rest_route(
			RestController::NAMESPACE,
			'/custom-tools',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list_custom_tools' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_custom_tool' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'name'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'description'  => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'config'       => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
						'input_schema' => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
						'enabled'      => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => true,
						),
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/custom-tools/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'handle_update_custom_tool' ),
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
					'callback'            => array( $this, 'handle_delete_custom_tool' ),
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

		register_rest_route(
			RestController::NAMESPACE,
			'/custom-tools/(?P<id>\d+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_test_custom_tool' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'input' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);
	}

	/**
	 * Handle the /abilities endpoint — list available abilities.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_abilities(): WP_REST_Response {
		return new WP_REST_Response( AbilityExplorerService::get_abilities_list(), 200 );
	}

	/**
	 * Handle the /abilities/explorer endpoint — richer ability data for the Abilities Explorer admin page.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_abilities_explorer(): WP_REST_Response {
		return new WP_REST_Response( AbilityExplorerService::get_explorer_list(), 200 );
	}

	/**
	 * List custom tools.
	 */
	public function handle_list_custom_tools(): WP_REST_Response {
		return new WP_REST_Response( CustomTools::list(), 200 );
	}

	/**
	 * Create a custom tool.
	 */
	public function handle_create_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = CustomTools::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create custom tool.', 'sd-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 201 );
	}

	/**
	 * Update a custom tool.
	 */
	public function handle_update_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = self::get_int_param( $request, 'id' );
		$data = $request->get_json_params();

		if ( ! CustomTools::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update custom tool.', 'sd-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 200 );
	}

	/**
	 * Delete a custom tool.
	 */
	public function handle_delete_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = self::get_int_param( $request, 'id' );

		if ( ! CustomTools::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete custom tool.', 'sd-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Test-execute a custom tool with provided input.
	 */
	public function handle_test_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id    = self::get_int_param( $request, 'id' );
		$input = $request->get_param( 'input' ) ?: array();
		$tool  = CustomTools::get( $id );

		if ( ! $tool ) {
			return new WP_Error( 'not_found', __( 'Tool not found.', 'sd-ai-agent' ), array( 'status' => 404 ) );
		}

		// @phpstan-ignore-next-line
		$result = CustomToolExecutor::execute( $tool, $input );

		return new WP_REST_Response( $result, 200 );
	}
}
