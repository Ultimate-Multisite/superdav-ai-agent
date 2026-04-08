<?php

declare(strict_types=1);
/**
 * REST API controller for settings, providers, budget, usage, roles/permissions,
 * Claude Max token, WooCommerce status, and alerts.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\BudgetManager;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SettingsController {

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

		// Providers endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_providers' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Alerts endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/alerts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_alerts' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// WooCommerce store status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_woocommerce_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Settings endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_settings' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_update_settings' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
			)
		);

		// Claude Max token endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/claude-max-token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_set_claude_max_token' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Direct provider API key endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_provider_key' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'api_key'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Direct provider API key test endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_test_provider_key' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'api_key'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Role permissions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/role-permissions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_role_permissions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_update_role_permissions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'permissions' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		// Role permissions — available roles list.
		register_rest_route(
			self::NAMESPACE,
			'/role-permissions/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_roles' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
			)
		);

		// Fresh install detection endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/fresh-install',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_fresh_install_status' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);

		// Google Analytics credentials endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/google-analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
					'args'                => array(
						'property_id'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'service_account_json' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'handle_clear_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);

		// Google Search Console credentials endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/gsc-credentials',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_gsc_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'handle_delete_gsc_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);

		// Usage endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/usage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_usage' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'period'     => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start_date' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Budget status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/budget',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_budget' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);
	}

	/**
	 * Handle GET /settings.
	 */
	public function handle_get_settings(): WP_REST_Response {
		$settings = $this->settings->get();

		// Include built-in defaults so the UI can show them as placeholders.
		// @phpstan-ignore-next-line
		$settings['_defaults'] = array(
			'system_prompt'    => AgentLoop::get_default_system_prompt(),
			'greeting_message' => __( 'Send a message to start a conversation.', 'gratis-ai-agent' ),
		);

		// Indicate whether a Claude Max token is stored without exposing the token itself.
		// @phpstan-ignore-next-line
		$settings['_has_claude_max_token'] = '' !== Settings::get_claude_max_token();

		// Indicate which direct provider keys are configured (boolean per provider, no values).
		$provider_keys = array();
		foreach ( array_keys( Settings::DIRECT_PROVIDERS ) as $provider_id ) {
			$provider_keys[ $provider_id ] = '' !== Settings::get_provider_key( $provider_id );
		}
		// @phpstan-ignore-next-line
		$settings['_provider_keys'] = $provider_keys;

		// Indicate whether GSC credentials are configured (boolean + type only, no credential values).
		$gsc_creds = Settings::get_gsc_credentials();
		// @phpstan-ignore-next-line
		$settings['_gsc_credentials'] = array(
			'configured'       => Settings::has_gsc_credentials(),
			'type'             => $gsc_creds['type'] ?? null,
			'default_site_url' => $gsc_creds['default_site_url'] ?? null,
		);

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Handle POST /settings — partial update.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'error' => 'No data provided.' ), 400 );
		}

		// @phpstan-ignore-next-line
		$this->settings->update( $data );

		return new WP_REST_Response( $this->settings->get(), 200 );
	}

	/**
	 * Handle GET /role-permissions — return current role permissions config.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_role_permissions(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'permissions'    => RolePermissions::get(),
				'always_allowed' => RolePermissions::ALWAYS_ALLOWED_ROLES,
			),
			200
		);
	}

	/**
	 * Handle POST /role-permissions — update role permissions config.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_role_permissions( WP_REST_Request $request ) {
		$permissions = $request->get_param( 'permissions' );

		if ( ! is_array( $permissions ) ) {
			return new WP_Error(
				'invalid_permissions',
				__( 'Invalid permissions data.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		// @phpstan-ignore-next-line
		$success = RolePermissions::update( $permissions );

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to save role permissions.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'permissions'    => RolePermissions::get(),
				'always_allowed' => RolePermissions::ALWAYS_ALLOWED_ROLES,
			),
			200
		);
	}

	/**
	 * Handle GET /role-permissions/roles — return all registered WordPress roles.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_roles(): WP_REST_Response {
		return new WP_REST_Response( RolePermissions::get_all_roles(), 200 );
	}

	/**
	 * Handle POST /settings/claude-max-token — store the Claude Max OAuth token.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_set_claude_max_token( WP_REST_Request $request ): WP_REST_Response {
		$token = $request->get_param( 'token' );

		// Allow clearing the token by passing an empty string.
		$token = is_string( $token ) ? trim( $token ) : '';

		$success = Settings::set_claude_max_token( $token );

		if ( ! $success && ! empty( $token ) ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save token.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'        => true,
				'has_token'    => ! empty( $token ),
				'token_prefix' => ! empty( $token ) ? substr( $token, 0, 20 ) . '…' : '',
			),
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key — save or clear a direct provider API key.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_provider_key( WP_REST_Request $request ): WP_REST_Response {
		// @phpstan-ignore-next-line
		$provider = (string) $request->get_param( 'provider' );
		// @phpstan-ignore-next-line
		$api_key = (string) $request->get_param( 'api_key' );
		$api_key = trim( $api_key );

		if ( ! array_key_exists( $provider, Settings::DIRECT_PROVIDERS ) ) {
			return new WP_REST_Response( array( 'error' => 'Unknown provider.' ), 400 );
		}

		$success = Settings::set_provider_key( $provider, $api_key );

		if ( ! $success && ! empty( $api_key ) ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save API key.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'   => true,
				'has_key' => ! empty( $api_key ),
			),
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key/test — test a direct provider API key.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_test_provider_key( WP_REST_Request $request ): WP_REST_Response {
		// @phpstan-ignore-next-line
		$provider = (string) $request->get_param( 'provider' );
		// @phpstan-ignore-next-line
		$api_key = (string) $request->get_param( 'api_key' );
		$api_key = trim( $api_key );

		if ( ! array_key_exists( $provider, Settings::DIRECT_PROVIDERS ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Unknown provider.',
				),
				400
			);
		}

		// Use the provided key or fall back to the stored key.
		$key_to_test = '' !== $api_key ? $api_key : Settings::get_provider_key( $provider );

		if ( '' === $key_to_test ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'No API key configured.',
				),
				400
			);
		}

		$meta          = Settings::DIRECT_PROVIDERS[ $provider ];
		$default_model = $meta['default_model'];

		// Send a minimal test prompt.
		$test_body = array(
			'model'      => $default_model,
			'max_tokens' => 16,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Say "ok".',
				),
			),
		);

		if ( 'anthropic' === $provider ) {
			$response = wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'      => 'application/json',
						'x-api-key'         => $key_to_test,
						'anthropic-version' => '2023-06-01',
					],
					'body'    => (string) wp_json_encode( $test_body ),
				]
			);
		} elseif ( 'google' === $provider ) {
			$openai_body = [
				'model'      => $default_model,
				'max_tokens' => 16,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => 'Say "ok".',
					],
				],
			];
			$response    = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key_to_test,
					],
					'body'    => (string) wp_json_encode( $openai_body ),
				]
			);
		} else {
			// OpenAI.
			$openai_body = [
				'model'      => $default_model,
				'max_tokens' => 16,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => 'Say "ok".',
					],
				],
			];
			$response    = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key_to_test,
					],
					'body'    => (string) wp_json_encode( $openai_body ),
				]
			);
		}

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'error'   => $response->get_error_message(),
				],
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			// @phpstan-ignore-next-line
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code";
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $error_msg,
				),
				200
			);
		}

		// Extract model name from response.
		// @phpstan-ignore-next-line
		$model_name = $data['model'] ?? $default_model;

		return new WP_REST_Response(
			array(
				'success' => true,
				'model'   => $model_name,
			),
			200
		);
	}

	/**
	 * Handle GET /fresh-install — return fresh-install detection status.
	 */
	public static function handle_fresh_install_status(): WP_REST_Response {
		$status                      = FreshInstallDetector::getStatus();
		$status['site_builder_mode'] = (bool) Settings::get( 'site_builder_mode' );

		// Auto-enable site_builder_mode when a fresh install is detected and
		// the flag has not been explicitly set by the user yet.
		if ( $status['is_fresh_install'] && ! $status['site_builder_mode'] ) {
			Settings::update( array( 'site_builder_mode' => true ) );
			$status['site_builder_mode'] = true;
		}

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Handle GET /settings/google-analytics — return whether credentials are configured.
	 */
	public static function handle_get_ga_credentials(): WP_REST_Response {
		$creds = GoogleAnalyticsAbilities::get_credentials();
		return new WP_REST_Response(
			array(
				'has_credentials' => '' !== $creds['property_id'] && '' !== $creds['service_account_json'],
				'has_property_id' => '' !== $creds['property_id'],
				'property_id'     => $creds['property_id'],
				'has_service_key' => '' !== $creds['service_account_json'],
			),
			200
		);
	}

	/**
	 * Handle POST /settings/google-analytics — save GA4 credentials.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_ga_credentials( WP_REST_Request $request ): WP_REST_Response {
		// @phpstan-ignore-next-line
		$property_id = (string) $request->get_param( 'property_id' );
		// @phpstan-ignore-next-line
		$service_account_json = (string) $request->get_param( 'service_account_json' );

		// Validate property ID format (numeric string).
		$property_id = preg_replace( '/[^0-9]/', '', $property_id );
		if ( empty( $property_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'property_id must be a numeric GA4 property ID.', 'gratis-ai-agent' ) ), 400 );
		}

		// Validate service account JSON structure.
		$sa = json_decode( $service_account_json, true );
		if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'service_account_json must be a valid Google service account JSON key containing client_email and private_key.', 'gratis-ai-agent' ) ),
				400
			);
		}

		$success = GoogleAnalyticsAbilities::set_credentials( $property_id, $service_account_json );
		if ( ! $success ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to save Google Analytics credentials.', 'gratis-ai-agent' ) ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'           => true,
				'property_id'     => $property_id,
				'has_service_key' => true,
			),
			200
		);
	}

	/**
	 * Handle DELETE /settings/google-analytics — clear GA4 credentials.
	 */
	public static function handle_clear_ga_credentials(): WP_REST_Response {
		GoogleAnalyticsAbilities::clear_credentials();
		return new WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	/**
	 * Handle POST /settings/gsc-credentials — save Google Search Console credentials.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_gsc_credentials( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		if ( empty( $params ) || ! is_array( $params ) ) {
			return new WP_REST_Response( array( 'error' => 'No data provided.' ), 400 );
		}

		// @phpstan-ignore-next-line
		$type         = sanitize_text_field( $params['type'] ?? '' );
		$default_site = esc_url_raw( $params['default_site_url'] ?? '' );

		if ( 'service_account' === $type ) {
			$credentials_json = $params['credentials_json'] ?? '';

			// Accept either a JSON string or a pre-decoded object/array.
			if ( is_string( $credentials_json ) ) {
				$decoded = json_decode( $credentials_json, true );
			} else {
				$decoded = $credentials_json;
			}

			if ( ! is_array( $decoded ) ) {
				return new WP_REST_Response( array( 'error' => 'Invalid service account JSON.' ), 400 );
			}

			$required = array( 'client_email', 'private_key' );
			foreach ( $required as $field ) {
				if ( empty( $decoded[ $field ] ) ) {
					return new WP_REST_Response(
						/* translators: %s: field name */
						array( 'error' => sprintf( 'Missing required field: %s', $field ) ),
						400
					);
				}
			}

			$creds = array(
				'type'             => 'service_account',
				'client_email'     => sanitize_email( $decoded['client_email'] ),
				'private_key'      => $decoded['private_key'],
				'default_site_url' => $default_site,
			);

		} elseif ( 'access_token' === $type ) {
			$access_token = sanitize_text_field( $params['access_token'] ?? '' );

			if ( empty( $access_token ) ) {
				return new WP_REST_Response( array( 'error' => 'access_token is required.' ), 400 );
			}

			$creds = array(
				'type'             => 'access_token',
				'access_token'     => $access_token,
				'default_site_url' => $default_site,
			);

		} else {
			return new WP_REST_Response(
				array( 'error' => 'type must be "service_account" or "access_token".' ),
				400
			);
		}

		$success = Settings::set_gsc_credentials( $creds );

		if ( ! $success ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save GSC credentials.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'            => true,
				'type'             => $creds['type'],
				'has_credentials'  => true,
				'default_site_url' => $creds['default_site_url'],
			),
			200
		);
	}

	/**
	 * Handle DELETE /settings/gsc-credentials — remove Google Search Console credentials.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_delete_gsc_credentials( WP_REST_Request $request ): WP_REST_Response {
		Settings::set_gsc_credentials( array() );

		return new WP_REST_Response(
			array(
				'deleted'         => true,
				'has_credentials' => false,
			),
			200
		);
	}

	/**
	 * Handle the /providers endpoint — list registered AI providers and models.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_providers(): WP_REST_Response {
		$providers = array();

		// Direct providers (OpenAI, Anthropic, Google) — listed first, no WP SDK required.
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $meta ) {
			$key = Settings::get_provider_key( $provider_id );
			if ( '' === $key ) {
				continue;
			}
			$providers[] = array(
				'id'         => $provider_id,
				'name'       => $meta['name'],
				'type'       => 'direct',
				'configured' => true,
				'models'     => $meta['models'],
			);
		}

		// Collect IDs already added to avoid duplicates from the WP SDK registry.
		$added_ids = array_column( $providers, 'id' );

		// WP SDK providers (AI Experiments plugin, OpenAI-compatible connector, etc.).
		if ( class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			$registry     = null;
			$provider_ids = array();
			try {
				$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
				$provider_ids = $registry->getRegisteredProviderIds();
			} catch ( \Throwable $e ) {
				$provider_ids = array();
			}

			// Ensure credentials are loaded (same logic the agent loop uses).
			AgentLoop::ensure_provider_credentials_static();

			foreach ( $provider_ids as $provider_id ) {
				// Skip if already added as a direct provider.
				if ( in_array( $provider_id, $added_ids, true ) ) {
					continue;
				}

				if ( null === $registry ) {
					continue;
				}

				try {
					$class = $registry->getProviderClassName( $provider_id );

					// Only include providers that have authentication set.
					$auth = $registry->getProviderRequestAuthentication( $provider_id );
					if ( null === $auth ) {
						continue;
					}

					$metadata = $class::metadata();
					$models   = array();

					// For the OpenAI-compatible connector, fetch models directly
					// from the endpoint rather than going through the SDK model
					// directory (which can fail due to SDK transporter issues).
					if ( 'ai-provider-for-any-openai-compatible' === $provider_id
						&& function_exists( 'OpenAiCompatibleConnector\\rest_list_models' )
					) {
						$fake_request = new WP_REST_Request( 'GET' );
						$result       = \OpenAiCompatibleConnector\rest_list_models( $fake_request );
						if ( ! is_wp_error( $result ) ) {
							$data = $result->get_data();
							if ( is_array( $data ) ) {
								$models = $data;
							}
						}
					} else {
						try {
							$directory      = $class::modelMetadataDirectory();
							$model_metadata = $directory->listModelMetadata();

							foreach ( $model_metadata as $model_meta ) {
								$models[] = array(
									'id'   => $model_meta->getId(),
									'name' => $model_meta->getName(),
								);
							}
						} catch ( \Throwable $e ) {
							// Model listing failed — still include the provider.
						}
					}

					$providers[] = array(
						'id'         => $provider_id,
						'name'       => $metadata->getName(),
						'type'       => (string) $metadata->getType(),
						'configured' => true,
						'models'     => $models,
					);
				} catch ( \Throwable $e ) {
					continue;
				}
			}
		}

		return new WP_REST_Response( $providers, 200 );
	}

	/**
	 * Handle GET /woocommerce/status — detect WooCommerce and return store info.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_woocommerce_status(): WP_REST_Response {
		$active = class_exists( 'WooCommerce' );

		if ( ! $active ) {
			return new WP_REST_Response(
				array(
					'active'  => false,
					'version' => null,
				),
				200
			);
		}

		// Product counts.
		$product_counts     = wp_count_posts( 'product' );
		$published_products = $product_counts ? (int) ( $product_counts->publish ?? 0 ) : 0;
		$total_products     = 0;
		if ( $product_counts ) {
			foreach ( (array) $product_counts as $count ) {
				$total_products += (int) $count;
			}
		}

		// Order counts.
		$pending_orders    = 0;
		$processing_orders = 0;
		if ( function_exists( 'wc_orders_count' ) ) {
			$pending_orders    = (int) wc_orders_count( 'pending' );
			$processing_orders = (int) wc_orders_count( 'processing' );
		}

		return new WP_REST_Response(
			array(
				'active'             => true,
				'version'            => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
				'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
				'published_products' => $published_products,
				'total_products'     => $total_products,
				'pending_orders'     => $pending_orders,
				'processing_orders'  => $processing_orders,
				'shop_url'           => function_exists( 'wc_get_page_id' ) ? ( get_permalink( wc_get_page_id( 'shop' ) ) ?: '' ) : '',
			),
			200
		);
	}

	/**
	 * Handle GET /alerts — return proactive issues.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_alerts(): WP_REST_Response {
		$alerts = array();

		// Check whether at least one AI provider is configured.
		$has_provider = false;

		// Direct providers (API key stored in plugin options).
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $meta ) {
			if ( '' !== Settings::get_provider_key( $provider_id ) ) {
				$has_provider = true;
				break;
			}
		}

		// WP SDK providers (AI Experiments plugin, OpenAI-compatible connector, etc.).
		if ( ! $has_provider && class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			try {
				$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
				$provider_ids = $registry->getRegisteredProviderIds();
				AgentLoop::ensure_provider_credentials_static();
				foreach ( $provider_ids as $provider_id ) {
					$auth = $registry->getProviderRequestAuthentication( $provider_id );
					if ( null !== $auth ) {
						$has_provider = true;
						break;
					}
				}
			} catch ( \Throwable $e ) {
				// Registry unavailable — treat as no provider.
			}
		}

		if ( ! $has_provider ) {
			$alerts[] = array(
				'type'    => 'no_provider',
				'message' => __( 'No AI provider configured. Add an API key in Settings.', 'gratis-ai-agent' ),
			);
		}

		// Check whether site builder mode is active.
		$settings = $this->settings->get();
		// @phpstan-ignore-next-line
		if ( ! empty( $settings['site_builder_mode'] ) ) {
			$alerts[] = array(
				'type'    => 'site_builder_mode',
				'message' => __( 'Site builder mode is active. Open the chat to build your site.', 'gratis-ai-agent' ),
			);
		}

		return new WP_REST_Response(
			array(
				'count'               => count( $alerts ),
				'alerts'              => $alerts,
				// @phpstan-ignore-next-line
				'site_builder_mode'   => ! empty( $settings['site_builder_mode'] ),
				// @phpstan-ignore-next-line
				'onboarding_complete' => ! empty( $settings['onboarding_complete'] ),
			),
			200
		);
	}

	/**
	 * Handle GET /usage — get usage summary.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_get_usage( WP_REST_Request $request ): WP_REST_Response {
		$filters = array(
			'user_id' => get_current_user_id(),
		);

		if ( $request->has_param( 'period' ) ) {
			$filters['period'] = $request->get_param( 'period' );
		}
		if ( $request->has_param( 'start_date' ) ) {
			$filters['start_date'] = $request->get_param( 'start_date' );
		}
		if ( $request->has_param( 'end_date' ) ) {
			$filters['end_date'] = $request->get_param( 'end_date' );
		}

		$summary = $this->database->get_usage_summary( $filters );

		return new WP_REST_Response( $summary, 200 );
	}

	/**
	 * Handle GET /budget — return current budget status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_get_budget( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( BudgetManager::get_status() );
	}
}
