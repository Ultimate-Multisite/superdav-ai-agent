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
 *   - ToolController       — custom-tools, abilities
 *   - ChangesController    — changes, modified-plugins, download
 *   - AgentController      — agents, conversation-templates
 *   - FeedbackController   — feedback/preview, feedback/send
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
use GratisAiAgent\Core\ConversationTrimmer;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Models\Agent;
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
 * Core REST controller — holds the shared NAMESPACE constant and the
 * /chat/tool-result endpoint. All domain controllers are now DI-managed
 * and self-register via their own #[Handler] / #[REST_Handler] attributes.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class RestController {

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
	 * Register the /chat/tool-result endpoint.
	 *
	 * All domain controllers are now DI-managed and register their own
	 * routes via #[REST_Handler] or #[Handler] + #[Action(rest_api_init)].
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {

		// Client tool result endpoint — resumes the agent loop after the browser
		// has executed client-side tool calls and POSTs the results back.
		register_rest_route(
			self::NAMESPACE,
			'/chat/tool-result',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_tool_result' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => array(
					'session_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'tool_results' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'     => array( 'type' => 'string' ),
								'name'   => array( 'type' => 'string' ),
								'result' => array(),
								'error'  => array( 'type' => 'string' ),
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
	public static function upload_attachments_to_media_library( array $attachments ): array {
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
	 * Handle POST /chat — REMOVED.
	 *
	 * All chat messages now go through /run (async job + poll).
	 * This stub exists only to return a helpful error if anything
	 * still calls the old endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		return new WP_Error(
			'gratis_ai_agent_endpoint_removed',
			__( 'The /chat endpoint has been removed. Use /run instead.', 'gratis-ai-agent' ),
			array( 'status' => 410 )
		);
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
				$settings = Settings::instance()->get();
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

			$result = $builder->generate_text_result();
			if ( is_wp_error( $result ) ) {
				return $fallback;
			}
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

	/**
	 * Handle POST /chat/tool-result — resume the agent loop after the browser
	 * has executed client-side tool calls and POSTs the results back.
	 *
	 * Loads the paused loop state from the session row, reconstructs an
	 * AgentLoop with the persisted history, and calls resume_after_client_tools().
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_tool_result( WP_REST_Request $request ) {
		$session_id   = self::get_int_param( $request, 'session_id' );
		$tool_results = $request->get_param( 'tool_results' );

		if ( ! $session_id ) {
			return new WP_Error(
				'gratis_ai_agent_missing_session',
				__( 'session_id is required.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $tool_results ) || empty( $tool_results ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_results',
				__( 'tool_results must be a non-empty array.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		// Load and clear the paused state from the session row.
		$paused_state = Database::load_and_clear_paused_state( $session_id );

		if ( null === $paused_state ) {
			return new WP_Error(
				'gratis_ai_agent_no_paused_state',
				__( 'No paused agent state found for this session. The session may have already been resumed or expired.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		// Reconstruct history from the paused state.
		$history = array();
		try {
			$raw_history = $paused_state['history'] ?? array();
			if ( ! empty( $raw_history ) && is_array( $raw_history ) ) {
				/** @var list<array<string, mixed>> $raw_history_typed */
				$raw_history_typed = array_values( $raw_history );
				$history           = AgentLoop::deserialize_history( $raw_history_typed );
				// Strip any orphaned tool_use blocks (no matching tool_result) that
				// may have been saved to the paused state mid-cycle.
				$history = ConversationTrimmer::validate_tool_pairs( $history );
			}
		} catch ( \Exception $e ) {
			$history = array();
		}

		$iterations_remaining = (int) ( $paused_state['iterations_remaining'] ?? 5 );

		// Reconstruct the AgentLoop with the persisted state.
		$options = array(
			'provider_id'      => (string) ( $paused_state['provider_id'] ?? '' ),
			'model_id'         => (string) ( $paused_state['model_id'] ?? '' ),
			'tool_call_log'    => $paused_state['tool_call_log'] ?? array(),
			'token_usage'      => $paused_state['token_usage'] ?? array(
				'prompt'     => 0,
				'completion' => 0,
			),
			'session_id'       => $session_id,
			'client_abilities' => $paused_state['client_abilities'] ?? array(),
		);

		// Use an empty user message — the loop resumes from history.
		$loop = new AgentLoop( '', array(), $history, $options );
		/** @var list<array{id: string, name: string, result?: mixed, error?: string}> $tool_results_typed */
		$tool_results_typed = array_values( $tool_results );
		$result             = $loop->resume_after_client_tools( $tool_results_typed, $iterations_remaining );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/** @var array<string, mixed> $result */

		// Handle another client-side pause (chained JS tool calls).
		if ( ! empty( $result['pending_client_tool_calls'] ) ) {
			return new WP_REST_Response(
				array(
					'pending_client_tool_calls' => $result['pending_client_tool_calls'],
					'session_id'                => $session_id,
					'token_usage'               => $result['token_usage'] ?? array(
						'prompt'     => 0,
						'completion' => 0,
					),
					'iterations_used'           => $result['iterations_used'] ?? 0,
					'model_id'                  => $result['model_id'] ?? '',
				),
				200
			);
		}

		// Persist the completed conversation to the session.
		$session = Database::get_session( $session_id );
		if ( $session ) {
			$existing_messages = json_decode( $session->messages, true ) ?: array();
			$existing_count    = count( $existing_messages );
			$full_history      = $result['history'] ?? array();
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
		}

		$token_usage = $result['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);
		$model_id    = $result['model_id'] ?? '';

		$response = array(
			'reply'           => $result['reply'] ?? '',
			'history'         => $result['history'] ?? array(),
			'tool_calls'      => $result['tool_calls'] ?? array(),
			'token_usage'     => $token_usage,
			'iterations_used' => $result['iterations_used'] ?? 0,
			'model_id'        => $model_id,
			'session_id'      => $session_id,
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

		if ( ! empty( $result['inability_reported'] ) ) {
			$response['inability_reported'] = $result['inability_reported'];
		}

		return new WP_REST_Response( $response, 200 );
	}
}
