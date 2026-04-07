<?php

declare(strict_types=1);
/**
 * REST API controller for the AI Agent.
 *
 * This is now a thin orchestrator. Domain-specific routes are handled by:
 *   - SessionController    — sessions, jobs, process, run, site-builder
 *   - SettingsController   — settings, providers, budget, usage, roles, alerts
 *   - MemoryController     — memories
 *   - SkillController      — skills
 *   - AutomationController — automations, event-automations, logs, triggers
 *   - KnowledgeController  — knowledge collections, sources, search, stats
 *   - ToolController       — custom-tools, tool-profiles, abilities
 *   - ChangesController    — changes, modified-plugins, download
 *   - AgentController      — agents, conversation-templates
 *
 * This class retains:
 *   - The /chat endpoint that runs the agent loop and returns JSON
 *   - Session title generation helper (used by SessionController)
 *   - Shared constants (NAMESPACE, JOB_PREFIX, JOB_TTL)
 *   - sanitize_page_context() (used by route args in SessionController)
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Models\Agent;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Transient prefix for job data.
	 */
	const JOB_PREFIX = 'gratis_ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;



	/**
	 * Register REST routes.
	 *
	 * Delegates to domain controllers and registers the /stream endpoint here.
	 */
	public static function register_routes(): void {
		// MCP (Model Context Protocol) endpoint.
		McpController::register_routes();

		// Webhook API endpoints.
		WebhookController::register_routes();

		// Resale API endpoints.
		ResaleApiController::register_routes();

		// Domain controllers.
		SessionController::register_routes();
		SettingsController::register_routes();
		MemoryController::register_routes();
		SkillController::register_routes();
		AutomationController::register_routes();
		KnowledgeController::register_routes();
		ToolController::register_routes();
		ChangesController::register_routes();
		AgentController::register_routes();

		$instance = new self();

		// Synchronous chat endpoint — runs the agent loop and returns JSON.
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_chat' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'message'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
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
						'sanitize_callback' => array( __CLASS__, 'sanitize_page_context' ),
					),
					'agent_id'           => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'attachments'        => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'name'     => array( 'type' => 'string' ),
								'type'     => array( 'type' => 'string' ),
								'data_url' => array( 'type' => 'string' ),
								'is_image' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize the page_context parameter.
	 *
	 * Accepts either an object (associative array) or a string and always
	 * returns an associative array.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return array<string, mixed> Normalised page context.
	 */
	public static function sanitize_page_context( $value ): array {
		if ( is_array( $value ) ) {
			/** @var array<string, mixed> $value */
			return $value;
		}

		if ( is_string( $value ) && $value !== '' ) {
			return array( 'summary' => sanitize_textarea_field( $value ) );
		}

		return array();
	}

	/**
	 * Upload base64-encoded image attachments to the WordPress media library.
	 *
	 * @param array<int, array{name: string, type: string, data_url: string, is_image: bool}> $attachments Raw attachment objects from the REST request.
	 * @return array<int, array{name: string, type: string, data_url: string, is_image: bool, attachment_id?: int, url?: string}> Enriched attachment objects.
	 */
	private static function upload_attachments_to_media_library( array $attachments ): array {
		if ( empty( $attachments ) ) {
			return array();
		}

		// Ensure media-handling functions are available outside the admin context.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$processed = array();

		foreach ( $attachments as $att ) {
			$name     = sanitize_file_name( $att['name'] ?? 'upload' );
			$type     = $att['type'] ?? '';
			$data_url = $att['data_url'] ?? '';
			$is_image = ! empty( $att['is_image'] );

			// Only upload images to the media library; pass other files through.
			if ( ! $is_image || empty( $data_url ) ) {
				$processed[] = $att;
				continue;
			}

			// Decode the base64 data URL.
			if ( ! preg_match( '/^data:([^;]+);base64,(.+)$/s', $data_url, $matches ) ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding image data URLs from user uploads, not obfuscating code.
			$decoded = base64_decode( $matches[2], true );
			if ( false === $decoded ) {
				$processed[] = $att;
				continue;
			}

			// Write to a temporary file.
			$tmp_file = wp_tempnam( $name );
			if ( ! $tmp_file ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $tmp_file, $decoded ) ) {
				wp_delete_file( $tmp_file );
				$processed[] = $att;
				continue;
			}

			$file_array = array(
				'name'     => $name,
				'type'     => $type,
				'tmp_name' => $tmp_file,
				'error'    => '0',
				'size'     => (string) strlen( $decoded ),
			);

			$attachment_id = media_handle_sideload( $file_array, 0, null );

			wp_delete_file( $tmp_file );

			if ( is_wp_error( $attachment_id ) ) {
				// Fall back to passing the raw data URL on upload failure.
				$processed[] = $att;
				continue;
			}

			$url = wp_get_attachment_url( $attachment_id );

			$processed[] = array_merge(
				$att,
				array(
					'attachment_id' => $attachment_id,
					'url'           => ( $url ? $url : $data_url ),
				)
			);
		}

		return $processed;
	}

	/**
	 * Handle POST /chat — run the agent loop and return the result as JSON.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		$session_id      = self::get_int_param( $request, 'session_id' );
		$raw_attachments = $request->get_param( 'attachments' ) ?? array();
		/** @var array<int, array{name: string, type: string, data_url: string, is_image: bool}> $raw_attachments_typed */
		$raw_attachments_typed = is_array( $raw_attachments ) ? $raw_attachments : array();
		$attachments           = self::upload_attachments_to_media_library( $raw_attachments_typed );

		$params = array(
			// @phpstan-ignore-next-line
			'message'            => (string) $request->get_param( 'message' ),
			'abilities'          => $request->get_param( 'abilities' ) ?? array(),
			'system_instruction' => $request->get_param( 'system_instruction' ),
			'max_iterations'     => $request->get_param( 'max_iterations' ) ?? 10,
			'provider_id'        => $request->get_param( 'provider_id' ),
			'model_id'           => $request->get_param( 'model_id' ),
			'page_context'       => $request->get_param( 'page_context' ) ?? array(),
			'agent_id'           => $request->get_param( 'agent_id' ),
			'attachments'        => $attachments,
		);

		// Load conversation history from session.
		$history = array();
		if ( $session_id ) {
			$session = Database::get_session( $session_id );
			if ( $session ) {
				/** @var list<array<string, mixed>> $session_messages */
				$session_messages = json_decode( $session->messages, true ) ?: array();
				if ( ! empty( $session_messages ) ) {
					try {
						$history = AgentLoop::deserialize_history( array_values( $session_messages ) );
					} catch ( \Exception $e ) {
						$history = array();
					}
				}
			}
		}

		$options = array(
			'max_iterations' => $params['max_iterations'],
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

		// Apply agent overrides (agent_id takes precedence over individual params).
		if ( ! empty( $params['agent_id'] ) ) {
			// @phpstan-ignore-next-line
			$agent_options = Agent::get_loop_options( (int) $params['agent_id'] );
			$options       = array_merge( $options, $agent_options );
		}

		// Pass image attachments to AgentLoop for vision model support.
		if ( ! empty( $params['attachments'] ) ) {
			$options['attachments'] = $params['attachments'];
		}

		$abilities_param = $params['abilities'];
		// @phpstan-ignore-next-line
		$loop   = new AgentLoop( $params['message'], is_array( $abilities_param ) ? $abilities_param : array(), $history, $options );
		$result = $loop->run();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/** @var array<string, mixed> $result */

		// Handle tool confirmation pause.
		if ( ! empty( $result['awaiting_confirmation'] ) ) {
			$job_id = wp_generate_uuid4();
			$token  = wp_generate_password( 40, false );

			// Enrich pending tools with classification info for the frontend.
			$pending_tools = $result['pending_tools'] ?? array();
			$all_abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
			foreach ( $pending_tools as &$pt ) {
				$pt_name = (string) ( $pt['name'] ?? '' );
				if ( str_starts_with( $pt_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
					$pt_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $pt_name );
				}
				$pt['ability_name']     = $pt_name;
				$pt['classification']   = 'write'; // Default — these are all write tools.
				$pt['can_always_allow'] = true;    // Frontend can show "Always Allow" button.
			}
			unset( $pt );

			$job = array(
				'status'             => 'awaiting_confirmation',
				'token'              => $token,
				'user_id'            => get_current_user_id(),
				'pending_tools'      => $pending_tools,
				'confirmation_state' => array(
					'history'              => $result['history'] ?? array(),
					'tool_call_log'        => $result['tool_call_log'] ?? array(),
					'token_usage'          => $result['token_usage'] ?? array(
						'prompt'     => 0,
						'completion' => 0,
					),
					'iterations_remaining' => $result['iterations_remaining'] ?? 5,
				),
				'params'             => $params,
			);

			set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

			return new WP_REST_Response(
				array(
					'awaiting_confirmation' => true,
					'job_id'                => $job_id,
					'pending_tools'         => $pending_tools,
				),
				200
			);
		}

		// Persist to session.
		$generated_title = null;
		if ( $session_id && ! empty( $result ) ) {
			$session        = Database::get_session( $session_id );
			$existing_count = 0;
			if ( $session ) {
				$existing_messages = json_decode( $session->messages, true ) ?: array();
				// @phpstan-ignore-next-line
				$existing_count = count( $existing_messages );
			}

			$full_history = $result['history'] ?? array();
			/** @var array<mixed> $full_history */
			$appended = array_slice( $full_history, $existing_count );
			/** @var list<array<string, mixed>> $tool_calls_stream */
			$tool_calls_stream = $result['tool_calls'] ?? array();
			Database::append_to_session( $session_id, array_values( $appended ), $tool_calls_stream );

			$token_usage = $result['token_usage'] ?? array();
			/** @var array<string, mixed> $token_usage */
			if ( ! empty( $token_usage ) ) {
				Database::update_session_tokens(
					$session_id,
					// @phpstan-ignore-next-line
					(int) ( $token_usage['prompt'] ?? 0 ),
					// @phpstan-ignore-next-line
					(int) ( $token_usage['completion'] ?? 0 )
				);
			}

			// Log usage.
			// @phpstan-ignore-next-line
			$prompt_t = (int) ( $token_usage['prompt'] ?? 0 );
			// @phpstan-ignore-next-line
			$completion_t = (int) ( $token_usage['completion'] ?? 0 );
			if ( $prompt_t > 0 || $completion_t > 0 ) {
				// @phpstan-ignore-next-line
				$model_id = (string) ( $options['model_id'] ?? $params['model_id'] ?? '' );
				$cost     = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
				Database::log_usage(
					array(
						'user_id'           => get_current_user_id(),
						'session_id'        => $session_id,
						// @phpstan-ignore-next-line
						'provider_id'       => (string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' ),
						'model_id'          => $model_id,
						'prompt_tokens'     => $prompt_t,
						'completion_tokens' => $completion_t,
						'cost_usd'          => $cost,
					)
				);
			}

			// Auto-generate title.
			$generated_title = null;
			if ( $session && empty( $session->title ) ) {
				$generated_title = self::generate_session_title(
					$params['message'],
					// @phpstan-ignore-next-line
					(string) ( $result['reply'] ?? '' ),
					// @phpstan-ignore-next-line
					(string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' ),
					// @phpstan-ignore-next-line
					(string) ( $options['model_id'] ?? $params['model_id'] ?? '' )
				);
				Database::update_session( $session_id, array( 'title' => $generated_title ) );
			}
		}

		$token_usage = $result['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);
		$model_id    = $result['model_id'] ?? ( $params['model_id'] ?? '' );

		$response = array(
			'reply'           => $result['reply'] ?? '',
			'history'         => $result['history'] ?? array(),
			'tool_calls'      => $result['tool_calls'] ?? array(),
			'token_usage'     => $token_usage,
			'iterations_used' => $result['iterations_used'] ?? 0,
			'model_id'        => $model_id,
			'session_id'      => $session_id ?: null,
			'cost_estimate'   => CostCalculator::calculate_cost(
				// @phpstan-ignore-next-line
				$model_id,
				// @phpstan-ignore-next-line
				(int) ( $token_usage['prompt'] ?? 0 ),
				// @phpstan-ignore-next-line
				(int) ( $token_usage['completion'] ?? 0 )
			),
		);

		if ( ! empty( $result['exit_reason'] ) ) {
			$response['exit_reason'] = $result['exit_reason'];
		}

		if ( null !== $generated_title ) {
			$response['generated_title'] = $generated_title;
		}

		return new WP_REST_Response( $response, 200 );
	}

	// ─── Session Title Generation ─────────────────────────────────────────────

	/**
	 * Generate a short 3-5 word session title from the first user message and AI reply.
	 *
	 * Routes the request through the WP AI Client SDK so the same provider/model
	 * resolution as the main chat loop applies.
	 *
	 * @param string $user_message The first user message.
	 * @param string $ai_reply     The first AI reply.
	 * @param string $provider_id  Provider identifier.
	 * @param string $model_id     Model identifier.
	 * @return string A short title (3-5 words, no quotes, no punctuation at end).
	 */
	public static function generate_session_title( string $user_message, string $ai_reply, string $provider_id, string $model_id ): string {
		$fallback = self::title_fallback( $user_message );

		if ( empty( $user_message ) || ! function_exists( 'wp_ai_client_prompt' ) ) {
			return $fallback;
		}

		$prompt_text = sprintf(
			'Generate a short 3-5 word title for this conversation. Reply with ONLY the title — no quotes, no punctuation at the end, no explanation.

User: %s
Assistant: %s',
			mb_substr( $user_message, 0, 500 ),
			mb_substr( $ai_reply, 0, 500 )
		);

		try {
			$builder = wp_ai_client_prompt( $prompt_text );
			/** @var \WP_AI_Client_Prompt_Builder $builder */

			$effective_provider = $provider_id;
			if ( empty( $effective_provider ) ) {
				$settings = Settings::get();
				// @phpstan-ignore-next-line
				$effective_provider = (string) ( $settings['default_provider'] ?? '' );
			}

			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! empty( $effective_provider ) && $registry->hasProvider( $effective_provider ) ) {
				if ( ! empty( $model_id ) ) {
					$builder->using_model( $registry->getProviderModel( $effective_provider, $model_id ) );
				} else {
					$builder->using_provider( $effective_provider );
				}
			}

			if ( method_exists( $builder, 'using_max_tokens' ) ) {
				$builder->using_max_tokens( 20 );
			}

			$result    = $builder->generate_text_result();
			$raw_title = $result->toText();
		} catch ( \Throwable $e ) {
			return $fallback;
		}

		$title = trim( (string) $raw_title, " \t\n\r\0\x0B\"'" );
		$title = wp_strip_all_tags( $title );
		$title = mb_substr( $title, 0, 100 );

		return '' !== $title ? $title : $fallback;
	}
	/**
	 * Fallback title: truncate the user message to 60 characters.
	 *
	 * @param string $user_message The user message.
	 * @return string Truncated title.
	 */
	private static function title_fallback( string $user_message ): string {
		$title = mb_substr( $user_message, 0, 60 );
		if ( mb_strlen( $user_message ) > 60 ) {
			$title .= '...';
		}
		return $title;
	}
}
