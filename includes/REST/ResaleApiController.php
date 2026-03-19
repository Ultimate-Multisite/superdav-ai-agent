<?php

declare(strict_types=1);
/**
 * Resale API controller.
 *
 * Exposes two groups of endpoints:
 *
 * 1. Admin CRUD (requires manage_options):
 *    GET    /gratis-ai-agent/v1/resale/clients
 *    POST   /gratis-ai-agent/v1/resale/clients
 *    GET    /gratis-ai-agent/v1/resale/clients/{id}
 *    PATCH  /gratis-ai-agent/v1/resale/clients/{id}
 *    DELETE /gratis-ai-agent/v1/resale/clients/{id}
 *    POST   /gratis-ai-agent/v1/resale/clients/{id}/rotate-key
 *    GET    /gratis-ai-agent/v1/resale/clients/{id}/usage
 *    GET    /gratis-ai-agent/v1/resale/clients/{id}/usage/summary
 *
 * 2. Proxy endpoint (authenticated by client API key):
 *    POST   /gratis-ai-agent/v1/resale/proxy
 *
 * The proxy endpoint accepts an OpenAI-compatible chat completions request,
 * authenticates via the X-Resale-API-Key header, enforces quota limits,
 * forwards the request to the site's configured AI provider, logs usage,
 * and returns the response to the caller.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ResaleApiController {

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Register all resale API REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// ─── Proxy endpoint ──────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/resale/proxy',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $instance, 'handle_proxy' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'model'       => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'messages'    => [
						'required' => true,
						'type'     => 'array',
					],
					'temperature' => [
						'required' => false,
						'type'     => 'number',
					],
					'max_tokens'  => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'stream'      => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					],
				],
			]
		);

		// ─── Admin CRUD endpoints ────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/resale/clients',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $instance, 'handle_list_clients' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $instance, 'handle_create_client' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
					'args'                => [
						'name'                => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description'         => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'monthly_token_quota' => [
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						],
						'allowed_models'      => [
							'required' => false,
							'type'     => 'array',
							'default'  => [],
						],
						'markup_percent'      => [
							'required' => false,
							'type'     => 'number',
							'default'  => 0.0,
						],
						'enabled'             => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => true,
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/resale/clients/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $instance, 'handle_get_client' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $instance, 'handle_update_client' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $instance, 'handle_delete_client' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/resale/clients/(?P<id>\d+)/rotate-key',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $instance, 'handle_rotate_key' ],
				'permission_callback' => [ $instance, 'check_admin_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/resale/clients/(?P<id>\d+)/usage',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_get_usage' ],
				'permission_callback' => [ $instance, 'check_admin_permission' ],
				'args'                => [
					'id'     => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'limit'  => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
					'offset' => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/resale/clients/(?P<id>\d+)/usage/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_get_usage_summary' ],
				'permission_callback' => [ $instance, 'check_admin_permission' ],
				'args'                => [
					'id'         => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'start_date' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Permission check — admin only.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ─── Proxy handler ───────────────────────────────────────────────

	/**
	 * POST /resale/proxy — forward an AI request on behalf of a resale client.
	 *
	 * Authentication: X-Resale-API-Key header must match a stored client key.
	 *
	 * The endpoint accepts an OpenAI-compatible messages array, resolves the
	 * site's configured AI provider, forwards the request, logs usage, and
	 * returns the response in OpenAI-compatible format.
	 *
	 * Quota enforcement: if the client has a monthly_token_quota > 0 and
	 * tokens_used_this_month >= monthly_token_quota, the request is rejected
	 * with HTTP 429.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_proxy( WP_REST_Request $request ) {
		// ── 1. Authenticate ──────────────────────────────────────────
		$api_key = $request->get_header( 'X-Resale-API-Key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'resale_api_key_required',
				__( 'X-Resale-API-Key header is required.', 'gratis-ai-agent' ),
				[ 'status' => 401 ]
			);
		}

		$client = ResaleApiDatabase::get_client_by_key( $api_key );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_unauthorized',
				__( 'Invalid API key.', 'gratis-ai-agent' ),
				[ 'status' => 401 ]
			);
		}

		// ── 2. Check enabled state ───────────────────────────────────
		if ( ! (bool) $client->enabled ) {
			return new WP_Error(
				'resale_api_disabled',
				__( 'This API client is disabled.', 'gratis-ai-agent' ),
				[ 'status' => 403 ]
			);
		}

		// ── 3. Check monthly quota ───────────────────────────────────
		$quota = (int) $client->monthly_token_quota;
		if ( $quota > 0 ) {
			// Auto-reset quota if the reset date has passed.
			$reset_at = $client->quota_reset_at;
			if ( $reset_at && strtotime( $reset_at ) <= time() ) {
				ResaleApiDatabase::reset_monthly_quota( (int) $client->id );
				// Re-fetch to get updated counters.
				$client = ResaleApiDatabase::get_client_by_key( $api_key );
				if ( ! $client ) {
					return new WP_Error( 'resale_api_unauthorized', __( 'Invalid API key.', 'gratis-ai-agent' ), [ 'status' => 401 ] );
				}
			}

			$used = (int) ( $client->tokens_used_this_month ?? 0 );
			if ( $used >= $quota ) {
				return new WP_Error(
					'resale_api_quota_exceeded',
					__( 'Monthly token quota exceeded.', 'gratis-ai-agent' ),
					[ 'status' => 429 ]
				);
			}
		}

		// ── 4. Resolve model ─────────────────────────────────────────
		// @phpstan-ignore-next-line
		$requested_model = sanitize_text_field( (string) ( $request->get_param( 'model' ) ?? '' ) );
		$allowed_models  = json_decode( (string) ( $client->allowed_models ?? '[]' ), true );

		// @phpstan-ignore-next-line
		if ( ! empty( $allowed_models ) && ! in_array( $requested_model, $allowed_models, true ) ) {
			return new WP_Error(
				'resale_api_model_not_allowed',
				sprintf(
					/* translators: %s: model ID */
					__( 'Model "%s" is not allowed for this client.', 'gratis-ai-agent' ),
					$requested_model
				),
				[ 'status' => 403 ]
			);
		}

		// ── 5. Build the prompt from messages array ──────────────────
		$messages = $request->get_param( 'messages' );
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_Error(
				'resale_api_messages_required',
				__( 'messages array is required.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		// Extract system instruction and user message from messages array.
		$system_instruction = '';
		$user_message       = '';

		foreach ( $messages as $msg ) {
			// @phpstan-ignore-next-line
			$role    = sanitize_text_field( (string) ( $msg['role'] ?? '' ) );
			// @phpstan-ignore-next-line
			$content = (string) ( $msg['content'] ?? '' );

			if ( 'system' === $role && '' === $system_instruction ) {
				$system_instruction = $content;
			} elseif ( 'user' === $role ) {
				// Use the last user message as the prompt.
				$user_message = $content;
			}
		}

		if ( '' === $user_message ) {
			return new WP_Error(
				'resale_api_no_user_message',
				__( 'No user message found in messages array.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		// ── 6. Dispatch via AgentLoop ────────────────────────────────
		$options = [
			'max_iterations' => 1, // Single-turn for proxy — no agentic loops.
		];

		if ( '' !== $system_instruction ) {
			$options['system_instruction'] = $system_instruction;
		}

		if ( '' !== $requested_model ) {
			$options['model_id'] = $requested_model;
		}

		// @phpstan-ignore-next-line
		$max_tokens  = absint( $request->get_param( 'max_tokens' ) ?? 0 );
		$temperature = $request->get_param( 'temperature' );

		if ( $max_tokens > 0 ) {
			$options['max_output_tokens'] = $max_tokens;
		}

		if ( null !== $temperature ) {
			// @phpstan-ignore-next-line
			$options['temperature'] = (float) $temperature;
		}

		AgentLoop::ensure_provider_credentials_static();

		$start_ms = (int) round( microtime( true ) * 1000 );
		$loop     = new AgentLoop( $user_message, [], [], $options );
		$result   = $loop->run();
		$duration = (int) round( microtime( true ) * 1000 ) - $start_ms;

		// ── 7. Log usage ─────────────────────────────────────────────
		if ( is_wp_error( $result ) ) {
			ResaleApiDatabase::log_usage(
				(int) $client->id,
				'',
				$requested_model,
				0,
				0,
				0.0,
				'error',
				$result->get_error_message(),
				$duration
			);

			return new WP_Error(
				'resale_api_upstream_error',
				$result->get_error_message(),
				[ 'status' => 502 ]
			);
		}

		// @phpstan-ignore-next-line
		$reply             = (string) ( $result['reply'] ?? '' );
		$token_usage       = $result['token_usage'] ?? [
			'prompt'     => 0,
			'completion' => 0,
		];
		// @phpstan-ignore-next-line
		$prompt_tokens     = (int) ( $token_usage['prompt'] ?? 0 );
		// @phpstan-ignore-next-line
		$completion_tokens = (int) ( $token_usage['completion'] ?? 0 );
		// @phpstan-ignore-next-line
		$model_used        = (string) ( $result['model_id'] ?? $requested_model );
		// @phpstan-ignore-next-line
		$provider_used     = (string) ( $result['provider_id'] ?? '' );

		$cost = CostCalculator::calculate_cost( $model_used, $prompt_tokens, $completion_tokens );

		// Apply markup if configured.
		$markup = (float) ( $client->markup_percent ?? 0.0 );
		if ( $markup > 0.0 ) {
			$cost = round( $cost * ( 1 + $markup / 100 ), 6 );
		}

		ResaleApiDatabase::log_usage(
			(int) $client->id,
			$provider_used,
			$model_used,
			$prompt_tokens,
			$completion_tokens,
			$cost,
			'success',
			'',
			$duration
		);

		// ── 8. Return OpenAI-compatible response ─────────────────────
		return new WP_REST_Response(
			[
				'id'      => 'chatcmpl-' . wp_generate_uuid4(),
				'object'  => 'chat.completion',
				'created' => time(),
				'model'   => $model_used,
				'choices' => [
					[
						'index'         => 0,
						'message'       => [
							'role'    => 'assistant',
							'content' => $reply,
						],
						'finish_reason' => 'stop',
					],
				],
				'usage'   => [
					'prompt_tokens'     => $prompt_tokens,
					'completion_tokens' => $completion_tokens,
					'total_tokens'      => $prompt_tokens + $completion_tokens,
				],
			],
			200
		);
	}

	// ─── Admin CRUD handlers ─────────────────────────────────────────

	/**
	 * GET /resale/clients — list all resale clients.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_clients( WP_REST_Request $request ): WP_REST_Response {
		$clients = ResaleApiDatabase::list_clients();
		$clients = array_map( [ $this, 'sanitize_client_for_response' ], $clients );
		return new WP_REST_Response( $clients, 200 );
	}

	/**
	 * POST /resale/clients — create a new resale client.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_client( WP_REST_Request $request ) {
		$api_key = $this->generate_api_key();

		// Set quota_reset_at to one month from now if a quota is configured.
		// @phpstan-ignore-next-line
		$quota      = absint( $request->get_param( 'monthly_token_quota' ) ?? 0 );
		$reset_date = $quota > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) : null;

		$data = [
			'name'                => $request->get_param( 'name' ),
			'description'         => $request->get_param( 'description' ) ?? '',
			'api_key'             => $api_key,
			'monthly_token_quota' => $quota,
			'quota_reset_at'      => $reset_date,
			'allowed_models'      => $request->get_param( 'allowed_models' ) ?? [],
			// @phpstan-ignore-next-line
			'markup_percent'      => (float) ( $request->get_param( 'markup_percent' ) ?? 0.0 ),
			'enabled'             => $request->get_param( 'enabled' ) ?? true,
		];

		$id = ResaleApiDatabase::create_client( $data );

		if ( false === $id ) {
			return new WP_Error(
				'resale_api_create_failed',
				__( 'Failed to create resale client.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Client not found after creation.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		// Return the API key on creation only — it is never returned again.
		$response              = $this->sanitize_client_for_response( $client );
		$response['api_key']   = $api_key;
		$response['proxy_url'] = rest_url( self::NAMESPACE . '/resale/proxy' );

		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * GET /resale/clients/{id} — get a single resale client.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_client( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$response              = $this->sanitize_client_for_response( $client );
		$response['proxy_url'] = rest_url( self::NAMESPACE . '/resale/proxy' );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * PATCH /resale/clients/{id} — update a resale client.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_client( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$allowed_fields = [
			'name',
			'description',
			'monthly_token_quota',
			'allowed_models',
			'markup_percent',
			'enabled',
		];

		$data = [];
		foreach ( $allowed_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = $value;
			}
		}

		// If quota changed and is now > 0, set a reset date if not already set.
		// @phpstan-ignore-next-line
		if ( isset( $data['monthly_token_quota'] ) && (int) $data['monthly_token_quota'] > 0 && empty( $client->quota_reset_at ) ) {
			$data['quota_reset_at'] = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'resale_api_no_data',
				__( 'No valid fields provided for update.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$updated = ResaleApiDatabase::update_client( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'resale_api_update_failed',
				__( 'Failed to update resale client.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error( 'resale_api_not_found', __( 'Client not found after update.', 'gratis-ai-agent' ), [ 'status' => 500 ] );
		}

		$response              = $this->sanitize_client_for_response( $client );
		$response['proxy_url'] = rest_url( self::NAMESPACE . '/resale/proxy' );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * DELETE /resale/clients/{id} — delete a resale client and its usage logs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_client( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		ResaleApiDatabase::delete_client( $id );

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * POST /resale/clients/{id}/rotate-key — generate a new API key for a client.
	 *
	 * The new key is returned once in the response and never again.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_rotate_key( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$new_key = $this->generate_api_key();
		ResaleApiDatabase::update_client( $id, [ 'api_key' => $new_key ] );

		return new WP_REST_Response(
			[
				'id'      => $id,
				'api_key' => $new_key,
			],
			200
		);
	}

	/**
	 * GET /resale/clients/{id}/usage — paginated usage log for a client.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_usage( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		// @phpstan-ignore-next-line
		$limit  = min( absint( $request->get_param( 'limit' ) ?? 20 ), 100 );
		// @phpstan-ignore-next-line
		$offset = absint( $request->get_param( 'offset' ) ?? 0 );

		$logs  = ResaleApiDatabase::get_usage( $id, $limit, $offset );
		$total = ResaleApiDatabase::count_usage( $id );

		return new WP_REST_Response(
			[
				'logs'   => $logs,
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			],
			200
		);
	}

	/**
	 * GET /resale/clients/{id}/usage/summary — aggregated usage totals.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_usage_summary( WP_REST_Request $request ) {
		// @phpstan-ignore-next-line
		$id     = absint( $request->get_param( 'id' ) );
		$client = ResaleApiDatabase::get_client( $id );

		if ( ! $client ) {
			return new WP_Error(
				'resale_api_not_found',
				__( 'Resale client not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		// @phpstan-ignore-next-line
		$start_date = sanitize_text_field( (string) ( $request->get_param( 'start_date' ) ?? '' ) ) ?: null;
		// @phpstan-ignore-next-line
		$end_date   = sanitize_text_field( (string) ( $request->get_param( 'end_date' ) ?? '' ) ) ?: null;

		$summary = ResaleApiDatabase::get_usage_summary( $id, $start_date, $end_date );

		return new WP_REST_Response(
			array_merge(
				$summary,
				[
					'client_id'              => $id,
					'monthly_token_quota'    => (int) $client->monthly_token_quota,
					'tokens_used_this_month' => (int) $client->tokens_used_this_month,
					'quota_reset_at'         => $client->quota_reset_at,
				]
			),
			200
		);
	}

	// ─── Private helpers ─────────────────────────────────────────────

	/**
	 * Generate a unique resale API key.
	 *
	 * Format: gaa_<32 random alphanumeric chars>
	 *
	 * @return string
	 */
	private function generate_api_key(): string {
		return 'gaa_' . wp_generate_password( 32, false );
	}

	/**
	 * Strip the API key from a client object before returning it to the admin.
	 *
	 * The key is only returned on creation and rotation — never in list/get responses.
	 *
	 * @param object|array<string, mixed> $client Raw client row from the database.
	 * @return array<string, mixed>
	 */
	private function sanitize_client_for_response( $client ): array {
		if ( is_object( $client ) ) {
			$client = (array) $client;
		}

		// Remove the raw API key — never expose it in list/get responses.
		unset( $client['api_key'] );

		// Decode allowed_models JSON back to array.
		if ( isset( $client['allowed_models'] ) && is_string( $client['allowed_models'] ) ) {
			$decoded                  = json_decode( $client['allowed_models'], true );
			$client['allowed_models'] = is_array( $decoded ) ? $decoded : [];
		}

		// Cast numeric fields.
		foreach ( [ 'id', 'monthly_token_quota', 'tokens_used_this_month', 'request_count' ] as $int_field ) {
			if ( isset( $client[ $int_field ] ) ) {
				$client[ $int_field ] = (int) $client[ $int_field ];
			}
		}

		if ( isset( $client['enabled'] ) ) {
			$client['enabled'] = (bool) $client['enabled'];
		}

		if ( isset( $client['markup_percent'] ) ) {
			$client['markup_percent'] = (float) $client['markup_percent'];
		}

		// Indicate whether a key is configured (without revealing it).
		$client['has_key'] = true;

		return $client;
	}
}
