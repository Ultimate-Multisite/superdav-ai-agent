<?php

declare(strict_types=1);
/**
 * Webhook API controller.
 *
 * Exposes two groups of endpoints:
 *
 * 1. Admin CRUD (requires manage_options):
 *    GET    /gratis-ai-agent/v1/webhooks
 *    POST   /gratis-ai-agent/v1/webhooks
 *    GET    /gratis-ai-agent/v1/webhooks/{id}
 *    PATCH  /gratis-ai-agent/v1/webhooks/{id}
 *    DELETE /gratis-ai-agent/v1/webhooks/{id}
 *    GET    /gratis-ai-agent/v1/webhooks/{id}/logs
 *    POST   /gratis-ai-agent/v1/webhooks/{id}/rotate-secret
 *
 * 2. Public trigger (authenticated by webhook secret):
 *    POST   /gratis-ai-agent/v1/webhook/trigger
 *
 * The trigger endpoint accepts a JSON body, validates the X-Webhook-Secret
 * header against the stored secret, then dispatches an async AgentLoop job
 * using the same job/process pattern as the main /run endpoint.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\REST\WebhookDatabase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WebhookController {

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Transient prefix for webhook jobs (reuses the main job pattern).
	 */
	const JOB_PREFIX = 'gratis_ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;

	/**
	 * Register all webhook REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// ─── Public trigger endpoint ────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/webhook/trigger',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $instance, 'handle_trigger' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'webhook_id'         => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'message'            => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'system_instruction' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'max_iterations'     => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
					'provider_id'        => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'model_id'           => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'context'            => [
						'required' => false,
						'type'     => 'object',
						'default'  => [],
					],
					'async'              => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => true,
					],
				],
			]
		);

		// ─── Admin CRUD endpoints ────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/webhooks',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $instance, 'handle_list' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $instance, 'handle_create' ],
					'permission_callback' => [ $instance, 'check_admin_permission' ],
					'args'                => [
						'name'               => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description'        => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'prompt_template'    => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'system_instruction' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'provider_id'        => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'model_id'           => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'max_iterations'     => [
							'required'          => false,
							'type'              => 'integer',
							'default'           => 10,
							'sanitize_callback' => 'absint',
						],
						'enabled'            => [
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
			'/webhooks/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $instance, 'handle_get' ],
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
					'callback'            => [ $instance, 'handle_update' ],
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
					'callback'            => [ $instance, 'handle_delete' ],
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
			'/webhooks/(?P<id>\d+)/logs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_logs' ],
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
			'/webhooks/(?P<id>\d+)/rotate-secret',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $instance, 'handle_rotate_secret' ],
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
	}

	/**
	 * Permission check — admin only.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ─── Admin CRUD handlers ─────────────────────────────────────────

	/**
	 * GET /webhooks — list all webhooks.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list( WP_REST_Request $request ): WP_REST_Response {
		$webhooks = WebhookDatabase::list_webhooks();

		// Strip secrets from list response.
		$webhooks = array_map( [ $this, 'sanitize_webhook_for_response' ], $webhooks );

		return new WP_REST_Response( $webhooks, 200 );
	}

	/**
	 * POST /webhooks — create a new webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create( WP_REST_Request $request ) {
		$secret = wp_generate_password( 32, false );

		$data = [
			'name'               => $request->get_param( 'name' ),
			'description'        => $request->get_param( 'description' ) ?? '',
			'secret'             => $secret,
			'prompt_template'    => $request->get_param( 'prompt_template' ) ?? '',
			'system_instruction' => $request->get_param( 'system_instruction' ) ?? '',
			'provider_id'        => $request->get_param( 'provider_id' ) ?? '',
			'model_id'           => $request->get_param( 'model_id' ) ?? '',
			'max_iterations'     => $request->get_param( 'max_iterations' ) ?? 10,
			'enabled'            => $request->get_param( 'enabled' ) ?? true,
		];

		$id = WebhookDatabase::create_webhook( $data );

		if ( false === $id ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_create_failed',
				__( 'Failed to create webhook.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found after creation.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		// Return the secret on creation only — it is never returned again.
		$response                = $this->sanitize_webhook_for_response( $webhook );
		$response['secret']      = $secret;
		$response['trigger_url'] = rest_url( self::NAMESPACE . '/webhook/trigger' );

		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * GET /webhooks/{id} — get a single webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$response                = $this->sanitize_webhook_for_response( $webhook );
		$response['trigger_url'] = rest_url( self::NAMESPACE . '/webhook/trigger' );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * PATCH /webhooks/{id} — update a webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$allowed_fields = [
			'name',
			'description',
			'prompt_template',
			'system_instruction',
			'provider_id',
			'model_id',
			'max_iterations',
			'enabled',
		];

		$data = [];
		foreach ( $allowed_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = $value;
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_no_data',
				__( 'No valid fields provided for update.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$updated = WebhookDatabase::update_webhook( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_update_failed',
				__( 'Failed to update webhook.', 'gratis-ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$webhook                 = WebhookDatabase::get_webhook( $id );
		$response                = $this->sanitize_webhook_for_response( $webhook );
		$response['trigger_url'] = rest_url( self::NAMESPACE . '/webhook/trigger' );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * DELETE /webhooks/{id} — delete a webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		WebhookDatabase::delete_webhook( $id );

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * GET /webhooks/{id}/logs — get execution logs for a webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_logs( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$limit  = min( absint( $request->get_param( 'limit' ) ?? 20 ), 100 );
		$offset = absint( $request->get_param( 'offset' ) ?? 0 );

		$logs  = WebhookDatabase::get_logs( $id, $limit, $offset );
		$total = WebhookDatabase::count_logs( $id );

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
	 * POST /webhooks/{id}/rotate-secret — generate a new secret for a webhook.
	 *
	 * The new secret is returned once in the response and never again.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_rotate_secret( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = WebhookDatabase::get_webhook( $id );

		if ( ! $webhook ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_not_found',
				__( 'Webhook not found.', 'gratis-ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$new_secret = wp_generate_password( 32, false );
		WebhookDatabase::update_webhook( $id, [ 'secret' => $new_secret ] );

		return new WP_REST_Response(
			[
				'id'     => $id,
				'secret' => $new_secret,
			],
			200
		);
	}

	// ─── Public trigger handler ──────────────────────────────────────

	/**
	 * POST /webhook/trigger — trigger an AI conversation from an external system.
	 *
	 * Authentication: X-Webhook-Secret header must match the stored secret for
	 * the webhook identified by the webhook_id body parameter.
	 *
	 * Async mode (default): dispatches a background job and returns immediately
	 * with job_id + status=processing (202).
	 *
	 * Sync mode (async=false): runs the agent loop inline and returns the full
	 * result (reply, tool_calls, token_usage). Use only for short tasks.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_trigger( WP_REST_Request $request ) {
		// ── 1. Identify the webhook ──────────────────────────────────
		$webhook_id = absint( $request->get_param( 'webhook_id' ) );

		if ( ! $webhook_id ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_id_required',
				__( 'webhook_id is required.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$webhook = WebhookDatabase::get_webhook( $webhook_id );

		if ( ! $webhook ) {
			// Return 401 rather than 404 to avoid leaking webhook existence.
			return new WP_Error(
				'gratis_ai_agent_webhook_unauthorized',
				__( 'Invalid webhook credentials.', 'gratis-ai-agent' ),
				[ 'status' => 401 ]
			);
		}

		// ── 2. Validate the secret ───────────────────────────────────
		$provided_secret = $request->get_header( 'X-Webhook-Secret' );

		if ( empty( $provided_secret ) || ! hash_equals( (string) $webhook->secret, $provided_secret ) ) {
			WebhookDatabase::log_execution(
				$webhook_id,
				'error',
				'',
				[],
				0,
				0,
				0,
				'Invalid or missing X-Webhook-Secret header.'
			);

			return new WP_Error(
				'gratis_ai_agent_webhook_unauthorized',
				__( 'Invalid webhook credentials.', 'gratis-ai-agent' ),
				[ 'status' => 401 ]
			);
		}

		// ── 3. Check enabled state ───────────────────────────────────
		if ( ! (bool) $webhook->enabled ) {
			return new WP_Error(
				'gratis_ai_agent_webhook_disabled',
				__( 'This webhook is disabled.', 'gratis-ai-agent' ),
				[ 'status' => 403 ]
			);
		}

		// ── 4. Build the prompt ──────────────────────────────────────
		$raw_message     = (string) ( $request->get_param( 'message' ) ?? '' );
		$context         = $request->get_param( 'context' ) ?? [];
		$prompt_template = (string) ( $webhook->prompt_template ?? '' );

		// If the webhook has a prompt template, use it; otherwise fall back to
		// the raw message from the request body.
		if ( ! empty( $prompt_template ) ) {
			$message = $this->interpolate_template( $prompt_template, $raw_message, $context );
		} elseif ( ! empty( $raw_message ) ) {
			$message = $raw_message;
		} else {
			return new WP_Error(
				'gratis_ai_agent_webhook_no_message',
				__( 'No message provided and webhook has no prompt template.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		// ── 5. Resolve options (request overrides webhook defaults) ──
		$system_instruction = (string) ( $request->get_param( 'system_instruction' ) ?? $webhook->system_instruction ?? '' );
		$provider_id        = (string) ( $request->get_param( 'provider_id' ) ?? $webhook->provider_id ?? '' );
		$model_id           = (string) ( $request->get_param( 'model_id' ) ?? $webhook->model_id ?? '' );
		$max_iterations     = absint( $request->get_param( 'max_iterations' ) ?? $webhook->max_iterations ?? 10 );
		$is_async           = (bool) ( $request->get_param( 'async' ) ?? true );

		// ── 6. Dispatch ──────────────────────────────────────────────
		if ( $is_async ) {
			return $this->dispatch_async( $webhook_id, $message, $system_instruction, $provider_id, $model_id, $max_iterations );
		}

		return $this->dispatch_sync( $webhook_id, $message, $system_instruction, $provider_id, $model_id, $max_iterations );
	}

	// ─── Private helpers ─────────────────────────────────────────────

	/**
	 * Dispatch an async webhook job.
	 *
	 * Creates a job transient and fires a non-blocking loopback to /process.
	 *
	 * @param int    $webhook_id         Webhook record ID.
	 * @param string $message            Resolved prompt message.
	 * @param string $system_instruction System instruction override.
	 * @param string $provider_id        Provider ID override.
	 * @param string $model_id           Model ID override.
	 * @param int    $max_iterations     Max agent loop iterations.
	 * @return WP_REST_Response
	 */
	private function dispatch_async(
		int $webhook_id,
		string $message,
		string $system_instruction,
		string $provider_id,
		string $model_id,
		int $max_iterations
	): WP_REST_Response {
		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );

		$job = [
			'status'     => 'processing',
			'token'      => $token,
			'user_id'    => 0,
			'webhook_id' => $webhook_id,
			'params'     => [
				'message'            => $message,
				'history'            => [],
				'abilities'          => [],
				'system_instruction' => $system_instruction,
				'max_iterations'     => $max_iterations,
				'session_id'         => 0,
				'provider_id'        => $provider_id,
				'model_id'           => $model_id,
				'page_context'       => [],
			],
		];

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => (string) wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'   => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return new WP_REST_Response(
			[
				'job_id'     => $job_id,
				'status'     => 'processing',
				'webhook_id' => $webhook_id,
			],
			202
		);
	}

	/**
	 * Dispatch a synchronous webhook job.
	 *
	 * Runs the AgentLoop inline and returns the full result.
	 * Logs the execution to the webhook_logs table.
	 *
	 * @param int    $webhook_id         Webhook record ID.
	 * @param string $message            Resolved prompt message.
	 * @param string $system_instruction System instruction override.
	 * @param string $provider_id        Provider ID override.
	 * @param string $model_id           Model ID override.
	 * @param int    $max_iterations     Max agent loop iterations.
	 * @return WP_REST_Response
	 */
	private function dispatch_sync(
		int $webhook_id,
		string $message,
		string $system_instruction,
		string $provider_id,
		string $model_id,
		int $max_iterations
	) {
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Webhook sync runs need extended execution time.
		set_time_limit( 600 );

		$options = [
			'max_iterations' => $max_iterations,
		];

		if ( ! empty( $system_instruction ) ) {
			$options['system_instruction'] = $system_instruction;
		}
		if ( ! empty( $provider_id ) ) {
			$options['provider_id'] = $provider_id;
		}
		if ( ! empty( $model_id ) ) {
			$options['model_id'] = $model_id;
		}

		$start_ms = (int) round( microtime( true ) * 1000 );

		AgentLoop::ensure_provider_credentials_static();

		$loop   = new AgentLoop( $message, [], [], $options );
		$result = $loop->run();

		$duration_ms = (int) round( microtime( true ) * 1000 ) - $start_ms;

		if ( is_wp_error( $result ) ) {
			WebhookDatabase::log_execution(
				$webhook_id,
				'error',
				'',
				[],
				0,
				0,
				$duration_ms,
				$result->get_error_message()
			);

			return new WP_REST_Response(
				[
					'status'  => 'error',
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				],
				500
			);
		}

		$reply       = $result['reply'] ?? '';
		$tool_calls  = $result['tool_calls'] ?? [];
		$token_usage = $result['token_usage'] ?? [
			'prompt'     => 0,
			'completion' => 0,
		];

		WebhookDatabase::log_execution(
			$webhook_id,
			'success',
			$reply,
			$tool_calls,
			$token_usage['prompt'] ?? 0,
			$token_usage['completion'] ?? 0,
			$duration_ms,
			''
		);

		$model = $result['model_id'] ?? $model_id;
		$cost  = CostCalculator::calculate_cost(
			$model,
			(int) ( $token_usage['prompt'] ?? 0 ),
			(int) ( $token_usage['completion'] ?? 0 )
		);

		return new WP_REST_Response(
			[
				'status'          => 'complete',
				'reply'           => $reply,
				'tool_calls'      => $tool_calls,
				'token_usage'     => $token_usage,
				'cost_estimate'   => $cost,
				'iterations_used' => $result['iterations_used'] ?? 0,
				'model_id'        => $model,
				'webhook_id'      => $webhook_id,
			],
			200
		);
	}

	/**
	 * Interpolate a prompt template with the incoming message and context.
	 *
	 * Supported placeholders:
	 *   {{message}}        — the raw message from the request body
	 *   {{context.KEY}}    — a key from the context object
	 *
	 * @param string               $template   The prompt template string.
	 * @param string               $message    The raw message from the request.
	 * @param array<string, mixed> $context    The context object from the request.
	 * @return string The interpolated prompt.
	 */
	private function interpolate_template( string $template, string $message, array $context ): string {
		$result = str_replace( '{{message}}', $message, $template );

		foreach ( $context as $key => $value ) {
			$safe_key = preg_replace( '/[^a-zA-Z0-9_.-]/', '', (string) $key );
			if ( '' === $safe_key ) {
				continue;
			}
			$scalar = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
			$result = str_replace( '{{context.' . $safe_key . '}}', $scalar, $result );
		}

		return $result;
	}

	/**
	 * Strip the secret hash from a webhook object before returning it to the client.
	 *
	 * @param object|array<string, mixed> $webhook Raw webhook row from the database.
	 * @return array<string, mixed>
	 */
	private function sanitize_webhook_for_response( $webhook ): array {
		if ( is_object( $webhook ) ) {
			$webhook = (array) $webhook;
		}

		unset( $webhook['secret'] );

		// Cast numeric fields.
		if ( isset( $webhook['id'] ) ) {
			$webhook['id'] = (int) $webhook['id'];
		}
		if ( isset( $webhook['max_iterations'] ) ) {
			$webhook['max_iterations'] = (int) $webhook['max_iterations'];
		}
		if ( isset( $webhook['enabled'] ) ) {
			$webhook['enabled'] = (bool) $webhook['enabled'];
		}
		if ( isset( $webhook['run_count'] ) ) {
			$webhook['run_count'] = (int) $webhook['run_count'];
		}

		return $webhook;
	}
}
