<?php

declare(strict_types=1);
/**
 * REST API controller for custom-tools, abilities, and abilities-explorer.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Abilities\Js\JsAbilityCatalog;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\CustomTools;
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
 * Uses #[Handler] + #[Action] instead of #[REST_Handler] because this
 * controller serves multiple basenames (/abilities, /custom-tools) and
 * the REST_Handler decorator supports only a single basename per class.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class ToolController {

	use PermissionTrait;

	/** @var Settings Injected settings dependency. */
	private Settings $settings;

	/**
	 * Constructor — accepts injected Settings for testability.
	 *
	 * @param Settings|null $settings Settings service (defaults to new Settings()).
	 */
	public function __construct( ?Settings $settings = null ) {
		$this->settings = $settings ?? new Settings();
	}

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
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$abilities = wp_get_abilities();
		$list      = array();

		foreach ( $abilities as $ability ) {
			$description = $ability->get_description();

			// Truncate long descriptions for the settings UI.
			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$list[] = array(
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $description,
				'category'    => $ability->get_category(),
			);
		}

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle the /abilities/explorer endpoint — richer ability data for the Abilities Explorer admin page.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_abilities_explorer(): WP_REST_Response {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$abilities = wp_get_abilities();
		$list      = array();

		// Build a map of configured provider IDs for configuration status checks.
		$configured_providers = array();
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $provider_meta ) {
			$key = $this->settings->get_provider_key( $provider_id );
			if ( '' !== $key ) {
				$configured_providers[] = $provider_id;
			}
		}

		foreach ( $abilities as $ability ) {
			$input_schema = $ability->get_input_schema();
			$meta         = $ability->get_meta();
			$annotations  = $meta['annotations'] ?? array();

			// Count required parameters from input schema.
			$required_params = array();
			if ( ! empty( $input_schema['required'] ) && is_array( $input_schema['required'] ) ) {
				$required_params = $input_schema['required'];
			}

			$param_count = 0;
			if ( ! empty( $input_schema['properties'] ) && is_array( $input_schema['properties'] ) ) {
				$param_count = count( $input_schema['properties'] );
			}

			// Derive configuration status from ability name/category.
			$ability_name      = $ability->get_name();
			$is_configured     = true;
			$required_api_keys = array();

			// Check for provider-specific abilities by name pattern.
			foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $provider_meta ) {
				if ( str_contains( $ability_name, $provider_id ) ) {
					$required_api_keys[] = $provider_meta['name'] . ' API Key';
					if ( ! in_array( $provider_id, $configured_providers, true ) ) {
						$is_configured = false;
					}
				}
			}

			$list[] = array(
				'name'              => $ability_name,
				'label'             => $ability->get_label(),
				'description'       => $ability->get_description(),
				'category'          => $ability->get_category(),
				'param_count'       => $param_count,
				'required_params'   => $required_params,
				'is_configured'     => $is_configured,
				'required_api_keys' => $required_api_keys,
				'annotations'       => array(
					// @phpstan-ignore-next-line
					'readonly'    => (bool) ( $annotations['readonly'] ?? false ),
					// @phpstan-ignore-next-line
					'destructive' => (bool) ( $annotations['destructive'] ?? false ),
					// @phpstan-ignore-next-line
					'idempotent'  => (bool) ( $annotations['idempotent'] ?? false ),
				),
				'output_schema'     => $ability->get_output_schema(),
				'show_in_rest'      => (bool) ( $meta['show_in_rest'] ?? false ),
			);
		}

		// Append client-side (JS) abilities from the catalog.
		foreach ( JsAbilityCatalog::get_descriptors() as $descriptor ) {
			$input_schema    = $descriptor['input_schema'] ?? array();
			$required_params = $input_schema['required'] ?? array();
			$param_count     = isset( $input_schema['properties'] ) ? count( $input_schema['properties'] ) : 0;
			$annotations     = $descriptor['annotations'] ?? array();

			$list[] = array(
				'name'              => $descriptor['name'],
				'label'             => $descriptor['label'],
				'description'       => $descriptor['description'],
				'category'          => $descriptor['category'],
				'param_count'       => $param_count,
				'required_params'   => $required_params,
				'is_configured'     => true,
				'required_api_keys' => array(),
				'annotations'       => array(
					'readonly'    => (bool) ( $annotations['readonly'] ?? false ),
					'destructive' => false,
					'idempotent'  => false,
				),
				'output_schema'     => $descriptor['output_schema'] ?? array(),
				'show_in_rest'      => false,
			);
		}

		// Sort by category then label for consistent display.
		usort(
			$list,
			static function ( array $a, array $b ): int {
				$cat_cmp = strcmp( $a['category'], $b['category'] );
				if ( 0 !== $cat_cmp ) {
					return $cat_cmp;
				}
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return new WP_REST_Response( $list, 200 );
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
			return new WP_Error( 'create_failed', __( 'Failed to create custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'update_failed', __( 'Failed to update custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 200 );
	}

	/**
	 * Delete a custom tool.
	 */
	public function handle_delete_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = self::get_int_param( $request, 'id' );

		if ( ! CustomTools::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'not_found', __( 'Tool not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		// @phpstan-ignore-next-line
		$result = CustomToolExecutor::execute( $tool, $input );

		return new WP_REST_Response( $result, 200 );
	}
}
