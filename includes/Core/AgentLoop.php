<?php

declare(strict_types=1);
/**
 * Core agentic loop orchestration.
 *
 * Sends a prompt, checks for tool calls, executes them,
 * feeds results back, and repeats until the model is done.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Abilities\Js\JsAbilityCatalog;
use GratisAiAgent\Core\BudgetManager;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Knowledge\Knowledge;
use GratisAiAgent\Models\Memory;
use GratisAiAgent\Models\Skill;
use GratisAiAgent\Tools\ModelHealthTracker;
use GratisAiAgent\Tools\ToolDiscovery;
use GratisAiAgent\Core\RolePermissions;
use WP_AI_Client_Ability_Function_Resolver;
use WP_Error;
use GratisAiAgent\Core\CredentialResolver;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

class AgentLoop {

	// ── Production Hardening Constants ────────────────────────────────────

	/**
	 * Wall-clock timeout in seconds. Prevents runaway loops from burning
	 * tokens indefinitely when round/token limits are not hit.
	 */
	const LOOP_TIMEOUT_SECONDS = 120;

	/**
	 * Consecutive no-progress rounds before forced exit.
	 * If the model calls the exact same tools with the same args N times
	 * in a row, it's spinning and we bail out.
	 */
	const MAX_IDLE_ROUNDS = 3;

	/**
	 * Maximum token-estimated size (in characters) for a single tool result
	 * fed back into the loop. Results exceeding this are truncated.
	 * ~40K chars ≈ 10K tokens — generous but bounded.
	 */
	const MAX_TOOL_RESULT_CHARS = 40000;

	/** @var string */
	private $user_message;

	/** @var string[] Ability names to enable. */
	private $abilities;

	/** @var Message[] Conversation history. */
	private $history;

	/** @var string */
	private $system_instruction;

	/** @var array<string,mixed> Cached settings for per-turn system-prompt rebuilds. */
	private array $settings_for_prompt = array();

	/** @var bool When true the constructor was given an explicit system_instruction override and we should NOT rebuild it per turn. */
	private bool $system_instruction_locked = false;

	/** @var int */
	private $max_iterations;

	/** @var string AI provider ID. */
	private $provider_id;

	/** @var string AI model ID. */
	private $model_id;

	/** @var list<array<string, mixed>> Logged tool call activity. */
	private $tool_call_log = array();

	/** @var float */
	private $temperature;

	/** @var int */
	private $max_output_tokens;

	/** @var int Number of loop iterations used. */
	private $iterations_used = 0;

	/** @var array<string, int> Token usage accumulator. */
	private $token_usage = array(
		'prompt'     => 0,
		'completion' => 0,
	);

	/** @var array<string, string> Tool permission levels from settings. */
	private $tool_permissions = array();

	/** @var bool When true, skip all tool confirmations (YOLO mode). */
	private $yolo_mode = false;

	/** @var array<string, mixed> Page context from the widget. */
	private $page_context = array();

	/** @var WP_AI_Client_Ability_Function_Resolver|null */
	private $ability_resolver = null;

	/** @var Settings Injected settings dependency. */
	private $settings_service;

	/** @var int Session ID for change attribution (0 = no session). */
	private int $session_id = 0;

	/**
	 * Client-side ability descriptors validated against JsAbilityCatalog.
	 * These are abilities the browser can execute; the loop pauses and returns
	 * them as pending_client_tool_calls when the model invokes one.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $client_abilities = array();

	// ── Spin Detection State ─────────────────────────────────────────────

	/** @var int Consecutive rounds with identical tool signatures. */
	private int $idle_rounds = 0;

	/** @var string Hash of the previous round's tool calls for spin detection. */
	private string $last_tool_signature = '';

	/**
	 * @param string               $user_message     The user's prompt.
	 * @param string[]             $abilities         Ability names to enable (empty = all).
	 * @param Message[]            $history           Prior messages for multi-turn.
	 * @param array<string, mixed> $options           Optional overrides: system_instruction, max_iterations, provider_id, model_id, temperature, max_output_tokens, page_context.
	 * @param Settings|null        $settings_service  Injected Settings service (uses static Settings::get() when null).
	 */
	public function __construct( string $user_message, array $abilities = array(), array $history = array(), array $options = array(), ?Settings $settings_service = null ) {
		$this->user_message = $user_message;
		$this->abilities    = $abilities;
		$this->history      = $history;
		// @phpstan-ignore-next-line
		$this->page_context     = $options['page_context'] ?? array();
		$this->settings_service = $settings_service ?? new Settings();

		// Merge explicit options with saved settings as fallbacks.
		$settings = $this->settings_service->get();

		// @phpstan-ignore-next-line
		$this->provider_id = $options['provider_id'] ?? ( $settings['default_provider'] ?: '' );
		// @phpstan-ignore-next-line
		$this->model_id = $options['model_id'] ?? ( $settings['default_model'] ?: '' );
		// @phpstan-ignore-next-line
		$this->max_iterations = $options['max_iterations'] ?? ( $settings['max_iterations'] ?: 25 );

		// Cap iterations harder for known-weak models — they burn through
		// rounds on dead-end paths, so failing fast surfaces a model
		// limitation to the user instead of timing out at 2 minutes.
		if ( ModelHealthTracker::is_weak( (string) ( $options['model_id'] ?? ( $settings['default_model'] ?? '' ) ) ) ) {
			$this->max_iterations = min( (int) $this->max_iterations, 10 );
		}
		// @phpstan-ignore-next-line
		$this->temperature = $options['temperature'] ?? ( $settings['temperature'] ?? 0.7 );
		// @phpstan-ignore-next-line
		$this->max_output_tokens = $options['max_output_tokens'] ?? ( $settings['max_output_tokens'] ?? 4096 );

		// If an agent_system_prompt is provided, inject it into settings so
		// build_system_instruction() uses it as the base instead of the global prompt.
		if ( ! empty( $options['agent_system_prompt'] ) ) {
			// @phpstan-ignore-next-line
			$settings['system_prompt'] = $options['agent_system_prompt'];
		}

		// Store settings so send_prompt() can rebuild the system instruction
		// before each model call — this lets the recently_fetched_section
		// (and any other dynamic blocks) reach the model on subsequent turns.
		// @phpstan-ignore-next-line
		$this->settings_for_prompt = $settings;
		if ( isset( $options['system_instruction'] ) ) {
			// @phpstan-ignore-next-line
			$this->system_instruction        = $options['system_instruction'];
			$this->system_instruction_locked = true;
		} else {
			$this->system_instruction = $this->build_system_instruction( $settings );
		}

		// Tool permissions, YOLO mode, and resumable state.
		// Options override settings for tool_permissions and yolo_mode so
		// callers (e.g. CLI, automations) can inject per-run overrides.
		// @phpstan-ignore-next-line
		$this->tool_permissions = $options['tool_permissions'] ?? ( $settings['tool_permissions'] ?? array() );
		// @phpstan-ignore-next-line
		$this->yolo_mode = (bool) ( $options['yolo_mode'] ?? ( $settings['yolo_mode'] ?? false ) );
		// @phpstan-ignore-next-line
		$this->tool_call_log = $options['tool_call_log'] ?? array();
		// @phpstan-ignore-next-line
		$this->session_id = (int) ( $options['session_id'] ?? 0 );
		// @phpstan-ignore-next-line
		$this->token_usage = $options['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);

		// Validate and store client-side ability descriptors.
		// Only accept names that exist in JsAbilityCatalog to prevent the
		// client from injecting arbitrary ability names into the model's tool list.
		// @phpstan-ignore-next-line
		$raw_client_abilities = $options['client_abilities'] ?? array();
		if ( is_array( $raw_client_abilities ) ) {
			$catalog = JsAbilityCatalog::get_descriptors_by_name();
			foreach ( $raw_client_abilities as $descriptor ) {
				if ( ! is_array( $descriptor ) ) {
					continue;
				}
				$name = (string) ( $descriptor['name'] ?? '' );
				if ( '' !== $name && isset( $catalog[ $name ] ) ) {
					/** @var array<string, mixed> $descriptor */
					$this->client_abilities[] = $descriptor;
				}
			}
		}
	}

	/**
	 * Run the agentic loop.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function run() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_client',
				__( 'The AI Client SDK is not available. WordPress 7.0+ is required.', 'gratis-ai-agent' )
			);
		}

		// Check spending budget before making any API call.
		$budget_check = BudgetManager::check_budget();
		if ( is_wp_error( $budget_check ) ) {
			return $budget_check;
		}

		// Clear per-call failure history so spin detection is per-run, and
		// attribute subsequent telemetry to the configured model.
		IdenticalFailureTracker::reset();
		ModelHealthTracker::set_current_model( $this->model_id );

		// Ensure provider auth is available (critical for loopback requests).
		self::ensure_provider_credentials_static();

		// Append the new user message to history.
		$this->history[] = new UserMessage( array( new MessagePart( $this->user_message ) ) );

		return $this->run_loop( $this->max_iterations );
	}

	/**
	 * Resume after a tool confirmation or rejection.
	 *
	 * @param bool $confirmed Whether the user approved the tool call.
	 * @param int  $remaining_iterations Remaining loop iterations.
	 * @return array<string, mixed>|WP_Error
	 */
	public function resume_after_confirmation( bool $confirmed, int $remaining_iterations ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_client',
				__( 'wp_ai_client_prompt() is not available.', 'gratis-ai-agent' )
			);
		}

		self::ensure_provider_credentials_static();

		if ( $confirmed ) {
			// The last message in history is the model's tool call message.
			$assistant_message = end( $this->history );
			ChangeLogger::begin( $this->session_id, 'confirmed-tool' );
			try {
				$response_message = $this->get_ability_resolver()->execute_abilities( $assistant_message );
				/** @var \WordPress\AiClient\Messages\DTO\Message $response_message */
			} finally {
				ChangeLogger::end();
			}
			// Truncate then split for OpenAI-compatible providers.
			$truncated_message = self::truncate_tool_results( $response_message );
			$this->append_tool_response_to_history( $truncated_message );
			$this->log_tool_responses( $response_message );
		} else {
			// Remove the model's tool call message and tell the model the call was rejected.
			array_pop( $this->history );
			$this->history[] = new UserMessage(
				array(
					new MessagePart(
						'The user declined the requested tool calls. Please respond directly without using those tools.'
					),
				)
			);
		}

		return $this->run_loop( $remaining_iterations );
	}

	/**
	 * Resume the agent loop after the browser has executed client-side tool calls.
	 *
	 * Called by the /chat/tool-result REST endpoint. Reconstructs a tool-response
	 * Message from the client results, appends it to history, and continues the loop.
	 * Mirrors resume_after_confirmation() in shape.
	 *
	 * @param list<array{id: string, name: string, result?: mixed, error?: string}> $results Client tool results.
	 * @param int                                                                   $remaining_iterations Remaining loop iterations from the paused state.
	 * @return array<string, mixed>|WP_Error
	 */
	public function resume_after_client_tools( array $results, int $remaining_iterations ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_client',
				__( 'wp_ai_client_prompt() is not available.', 'gratis-ai-agent' )
			);
		}

		self::ensure_provider_credentials_static();

		// Build a tool-response message from the client results.
		$parts = array();
		foreach ( $results as $result ) {
			$id   = (string) ( $result['id'] ?? '' );
			$name = (string) ( $result['name'] ?? '' );

			if ( '' === $id || '' === $name ) {
				continue;
			}

			// Encode the result payload as a JSON string for the response.
			$response_payload = isset( $result['error'] )
				? wp_json_encode( array( 'error' => $result['error'] ) )
				: wp_json_encode( $result['result'] ?? array() );

			$parts[] = new MessagePart(
				new FunctionResponse(
					$id,
					$name,
					(string) $response_payload
				)
			);
		}

		if ( ! empty( $parts ) ) {
			$response_message = new UserMessage( $parts );
			$this->append_tool_response_to_history( $response_message );

			// Log the client tool responses for transparency.
			foreach ( $results as $result ) {
				$this->tool_call_log[] = array(
					'type'     => 'response',
					'id'       => (string) ( $result['id'] ?? '' ),
					'name'     => (string) ( $result['name'] ?? '' ),
					'response' => $result['result'] ?? $result['error'] ?? null,
					'source'   => 'client',
				);
			}
		}

		return $this->run_loop( $remaining_iterations );
	}

	/**
	 * Inner loop: send prompts, handle tool calls, repeat.
	 *
	 * @param int $iterations Max iterations remaining.
	 * @return array<string, mixed>|WP_Error
	 */
	private function run_loop( int $iterations ) {
		$last_was_tool_call = false;

		// Wall-clock deadline prevents runaway loops even when round count
		// and token budget are within limits (e.g. cheap read-only tool
		// calls in a spin cycle).
		$deadline = microtime( true ) + self::LOOP_TIMEOUT_SECONDS;

		while ( $iterations > 0 ) {
			--$iterations;
			++$this->iterations_used;

			// Wall-clock timeout check.
			if ( microtime( true ) >= $deadline ) {
				return array(
					'reply'           => __(
						'This request took longer than expected and was stopped to protect your usage budget. You can continue the conversation to pick up where it left off.',
						'gratis-ai-agent'
					),
					'history'         => $this->serialize_history(),
					'tool_calls'      => $this->tool_call_log,
					'token_usage'     => $this->token_usage,
					'iterations_used' => $this->iterations_used,
					'model_id'        => $this->model_id,
					'exit_reason'     => 'timeout',
				);
			}

			// Smart conversation trimming before each LLM call.
			// @phpstan-ignore-next-line
			$max_turns = (int) $this->settings_service->get( 'max_history_turns' );
			if ( $max_turns > 0 ) {
				$this->history = ConversationTrimmer::trim( $this->history, $max_turns );
			}

			$result = $this->send_prompt();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			/** @var \WordPress\AiClient\Results\DTO\GenerativeAiResult $result */
			$assistant_message = $result->toMessage();
			$this->history[]   = $assistant_message;

			// Accumulate token usage if available.
			$this->accumulate_tokens( $result );

			// Check if the model wants to call tools.
			if ( ! $this->get_ability_resolver()->has_ability_calls( $assistant_message ) ) {
				// No tool calls — we're done.
				$last_was_tool_call = false;
				$reply              = '';

				try {
					$reply = $result->toText();
				} catch ( \RuntimeException $e ) {
					$reply = '';
				}

				// If the response is empty or whitespace-only after tool results,
				// inject a follow-up user message asking the AI to summarize.
				// This handles models that silently return an empty text turn
				// after processing tool results instead of providing a summary.
				// Guard: only attempt if we have at least one iteration remaining
				// to avoid consuming the last slot and returning empty anyway.
				if ( '' === trim( $reply ) && $iterations > 0 ) {
					$this->history[] = new UserMessage(
						[
							new MessagePart(
								__(
									'Please summarize the tool results for the user and provide your final response.',
									'gratis-ai-agent'
								)
							),
						]
					);

					++$this->iterations_used;
					$followup_result = $this->send_prompt();

					if ( ! is_wp_error( $followup_result ) ) {
						$followup_message = $followup_result->toMessage();
						$this->history[]  = $followup_message;
						$this->accumulate_tokens( $followup_result );

						try {
							$reply = $followup_result->toText();
						} catch ( \RuntimeException $e ) {
							$reply = '';
						}
					}
				}

				return array(
					'reply'           => $reply,
					'history'         => $this->serialize_history(),
					'tool_calls'      => $this->tool_call_log,
					'token_usage'     => $this->token_usage,
					'iterations_used' => $this->iterations_used,
					'model_id'        => $this->model_id,
				);
			}

			$last_was_tool_call = true;

			// Log tool calls and check for confirmation requirement.
			$this->log_tool_calls( $assistant_message );

			// ── Client-side ability routing ───────────────────────────────
			// Partition tool calls into PHP-executable and JS-pending sets.
			// PHP calls execute inline; JS calls are returned as pending so
			// the browser can dispatch them and POST results back.
			$client_names = $this->get_client_ability_names();
			if ( ! empty( $client_names ) ) {
				$partition = $this->partition_tool_calls( $assistant_message, $client_names );

				if ( ! empty( $partition['client'] ) ) {
					// Execute any PHP-side calls inline first.
					if ( ! empty( $partition['php'] ) ) {
						$php_message = $this->build_message_from_parts( $assistant_message, $partition['php'] );
						ChangeLogger::begin( $this->session_id );
						try {
							$php_response = $this->get_ability_resolver()->execute_abilities( $php_message );
							/** @var \WordPress\AiClient\Messages\DTO\Message $php_response */
						} finally {
							ChangeLogger::end();
						}
						$truncated_php = self::truncate_tool_results( $php_response );
						$this->append_tool_response_to_history( $truncated_php );
						$this->log_tool_responses( $php_response );
					}

					// Persist loop state so the resume endpoint can reconstruct it.
					if ( $this->session_id > 0 ) {
						$paused_state = array(
							'history'              => $this->serialize_history(),
							'tool_call_log'        => $this->tool_call_log,
							'token_usage'          => $this->token_usage,
							'iterations_remaining' => $iterations,
							'model_id'             => $this->model_id,
							'provider_id'          => $this->provider_id,
							'client_abilities'     => $this->client_abilities,
						);
						Database::save_paused_state( $this->session_id, $paused_state );
					}

					// Return pending client tool calls to the browser.
					return array(
						'pending_client_tool_calls' => $partition['client'],
						'history'                   => $this->serialize_history(),
						'tool_call_log'             => $this->tool_call_log,
						'token_usage'               => $this->token_usage,
						'iterations_remaining'      => $iterations,
						'iterations_used'           => $this->iterations_used,
						'model_id'                  => $this->model_id,
					);
				}
			}
			// ── End client-side routing ───────────────────────────────────

			$confirm_needed = $this->get_tools_needing_confirmation( $assistant_message );

			if ( ! empty( $confirm_needed ) ) {
				return array(
					'awaiting_confirmation' => true,
					'pending_tools'         => $confirm_needed,
					'history'               => $this->serialize_history(),
					'tool_call_log'         => $this->tool_call_log,
					'token_usage'           => $this->token_usage,
					'iterations_remaining'  => $iterations,
					'iterations_used'       => $this->iterations_used,
					'model_id'              => $this->model_id,
				);
			}

			// Execute the ability calls and get the function response message.
			ChangeLogger::begin( $this->session_id );
			try {
				$response_message = $this->get_ability_resolver()->execute_abilities( $assistant_message );
				/** @var \WordPress\AiClient\Messages\DTO\Message $response_message */
			} finally {
				ChangeLogger::end();
			}
			// Truncate large tool results before adding to history, then
			// append (splitting multi-part responses for OpenAI-compatible
			// providers that only accept one tool result per message).
			$truncated_message = self::truncate_tool_results( $response_message );
			$this->append_tool_response_to_history( $truncated_message );
			$this->log_tool_responses( $response_message );

			// Spin detection: if this round's tool calls are identical to the
			// previous round's, the model is looping without making progress.
			$current_signature = $this->build_tool_signature( $assistant_message );
			if ( '' !== $current_signature && $current_signature === $this->last_tool_signature ) {
				++$this->idle_rounds;
				if ( $this->idle_rounds >= self::MAX_IDLE_ROUNDS ) {
					return array(
						'reply'           => __(
							'I\'ve been repeating the same operations without making progress. Here\'s what I found so far. Try rephrasing your request or providing more specifics.',
							'gratis-ai-agent'
						),
						'history'         => $this->serialize_history(),
						'tool_calls'      => $this->tool_call_log,
						'token_usage'     => $this->token_usage,
						'iterations_used' => $this->iterations_used,
						'model_id'        => $this->model_id,
						'exit_reason'     => 'spin_detected',
					);
				}
			} else {
				$this->idle_rounds = 0;
			}
			$this->last_tool_signature = $current_signature;
		}

		// Exhausted iterations. If the last AI turn was a tool call (not text),
		// the user would see an empty response. Inject one final summarization
		// prompt so the AI can explain what it accomplished and what failed.
		if ( $last_was_tool_call ) {
			$this->history[] = new UserMessage(
				[
					new MessagePart(
						__(
							'You have reached the maximum number of tool calls. Please summarize what you accomplished and what failed, and provide your final response to the user.',
							'gratis-ai-agent'
						)
					),
				]
			);

			++$this->iterations_used;
			$fallback_result = $this->send_prompt();

			if ( ! is_wp_error( $fallback_result ) ) {
				$fallback_message = $fallback_result->toMessage();
				$this->history[]  = $fallback_message;
				$this->accumulate_tokens( $fallback_result );

				$reply = '';
				try {
					$reply = $fallback_result->toText();
				} catch ( \RuntimeException $e ) {
					$reply = '';
				}

				return [
					'reply'           => $reply,
					'history'         => $this->serialize_history(),
					'tool_calls'      => $this->tool_call_log,
					'token_usage'     => $this->token_usage,
					'iterations_used' => $this->iterations_used,
					'model_id'        => $this->model_id,
				];
			}
		}

		// Exhausted iterations — return what we have so callers can inspect the log.
		return new WP_Error(
			'gratis_ai_agent_max_iterations',
			sprintf(
				/* translators: %d: max iterations */
				__( 'Agent reached the maximum of %d iterations without completing.', 'gratis-ai-agent' ),
				$this->max_iterations
			),
			array(
				'tool_calls'      => $this->tool_call_log,
				'token_usage'     => $this->token_usage,
				'iterations_used' => $this->iterations_used,
				'model_id'        => $this->model_id,
				'history'         => $this->serialize_history(),
			)
		);
	}

	/**
	 * Build and send a single prompt with the current history.
	 *
	 * Always routes through the WordPress AI Client SDK. Per-vendor direct
	 * paths and the OpenAI-compatible HTTP fallback have been removed —
	 * provider auth, model resolution, and request transport are entirely
	 * the SDK's responsibility now.
	 *
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|WP_Error
	 */
	private function send_prompt() {
		$provider_id = $this->provider_id ?: 'ai-provider-for-any-openai-compatible';

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $provider_id ) ) {
				return new WP_Error(
					'gratis_ai_agent_provider_unavailable',
					sprintf(
						/* translators: %s: provider ID */
						__( 'Provider "%s" is not available. Please select a different provider in the chat header.', 'gratis-ai-agent' ),
						$provider_id
					)
				);
			}
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'gratis_ai_agent_registry_unavailable',
				__( 'AI Client SDK registry is not available.', 'gratis-ai-agent' )
			);
		}

		$builder = wp_ai_client_prompt();
		/** @var \WP_AI_Client_Prompt_Builder $builder */

		// Rebuild the system instruction unless the caller pinned a static
		// override. This lets the manifest's "recently fetched ability
		// schemas" block reach the model on subsequent turns.
		if ( ! $this->system_instruction_locked ) {
			$this->system_instruction = $this->build_system_instruction( $this->settings_for_prompt );
		}
		$builder->using_system_instruction( $this->system_instruction );
		$this->configure_model( $builder );

		// For known-weak models, force temperature 0 (less hallucination
		// of arg shapes) and disable parallel tool calls (single-track
		// models lose track of which result corresponds to which call).
		$is_weak     = ModelHealthTracker::is_weak( $this->model_id );
		$temperature = $is_weak ? 0.0 : (float) $this->temperature;

		if ( method_exists( $builder, 'using_temperature' ) ) {
			$builder->using_temperature( $temperature );
		}

		if ( $is_weak && method_exists( $builder, 'using_custom_options' ) ) {
			// @phpstan-ignore-next-line — using_custom_options() exists at runtime in WP 7.0.
			$builder->using_custom_options( array( 'parallel_tool_calls' => false ) );
		}

		if ( method_exists( $builder, 'using_max_tokens' ) ) {
			$builder->using_max_tokens( (int) $this->max_output_tokens );
		}

		$abilities = $this->resolve_abilities();
		if ( ! empty( $abilities ) ) {
			$builder->using_abilities( ...$abilities );
		}

		if ( ! empty( $this->history ) ) {
			$builder->with_history( ...$this->history );
		}

		return $builder->generate_text_result();
	}

	/**
	 * Configure the PromptBuilder with the correct provider and model.
	 *
	 * Uses the builder's own provider/preference API so that the SDK
	 * handles model creation and dependency injection (auth, transporter)
	 * through ProviderRegistry::getProviderModel(). This avoids creating
	 * model instances outside the registry which can miss auth binding.
	 *
	 * @param \WP_AI_Client_Prompt_Builder $builder The prompt builder.
	 */
	private function configure_model( $builder ): void {
		$provider_id = $this->provider_id;
		$model_id    = $this->model_id;

		// Resolve provider — fall back to the OpenAI-compatible connector.
		if ( empty( $provider_id ) ) {
			$provider_id = 'ai-provider-for-any-openai-compatible';
		}

		// Resolve model — fall back to the connector's configured default.
		if ( empty( $model_id ) && function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
			$model_id = \OpenAiCompatibleConnector\get_default_model();
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			if ( ! $registry->hasProvider( $provider_id ) ) {
				return;
			}

			if ( ! empty( $model_id ) ) {
				// Directly create the model instance via the registry.
				// This bypasses the SDK's model-listing HTTP call which
				// can fail for OpenAI-compatible endpoints.
				$model = $registry->getProviderModel( $provider_id, $model_id );
				$builder->using_model( $model );
			} else {
				$builder->using_provider( $provider_id );
			}
		} catch ( \Throwable $e ) {
			// Last resort: just set the provider and hope for the best.
			try {
				$builder->using_provider( $provider_id );
			} catch ( \Throwable $e2 ) {
				// Both approaches failed — builder will use default.
			}
		}
	}

	/**
	 * Ensure AI provider credentials are loaded from the database.
	 *
	 * In loopback/background requests the AI Experiments plugin's init
	 * chain may not fully pass credentials to the registry. This method
	 * reads the stored credentials option and sets auth on any provider
	 * that doesn't already have it configured.
	 */
	public static function ensure_provider_credentials_static(): void {
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		} catch ( \Throwable $e ) {
			return;
		}

		$auth_class = '\\WordPress\\AiClient\\Providers\\Http\\DTO\\ApiKeyRequestAuthentication';

		if ( ! class_exists( $auth_class ) ) {
			return;
		}

		// Source 1: WordPress 7.0 Connectors API (connectors_ai_*_api_key options).
		if ( function_exists( '_wp_connectors_get_provider_settings' ) ) {
			foreach ( _wp_connectors_get_provider_settings() as $setting_name => $config ) {
				$api_key = _wp_connectors_get_real_api_key( $setting_name, $config['mask'] );

				if ( '' === $api_key || ! $registry->hasProvider( $config['provider'] ) ) {
					continue;
				}

				$registry->setProviderRequestAuthentication(
					$config['provider'],
					new $auth_class( $api_key )
				);
			}
		}

		// Source 2: AI Experiments plugin credentials option.
		$credentials = CredentialResolver::getAiExperimentsCredentials();

		if ( ! empty( $credentials ) ) {
			foreach ( $credentials as $provider_id => $api_key ) {
				if ( ! is_string( $api_key ) || '' === $api_key ) {
					continue;
				}

				if ( ! $registry->hasProvider( $provider_id ) ) {
					continue;
				}

				$registry->setProviderRequestAuthentication(
					$provider_id,
					new $auth_class( $api_key )
				);
			}
		}
	}

	/**
	 * Check which tool calls in an assistant message require user confirmation.
	 *
	 * Permission resolution order (first match wins):
	 * 1. YOLO mode → skip all confirmations.
	 * 2. Explicit tool_permissions setting ('auto'|'confirm'|'disabled'|'always_allow') → use it.
	 * 3. Annotation-based classification:
	 *    - readonly=true  → auto-execute (read-only, safe).
	 *    - readonly=false or null → require confirmation (write operation).
	 *
	 * This means by default (no tool_permissions configured), read-only tools
	 * execute automatically and write tools pause for user approval — matching
	 * the PressArk-style "Preview → Approve → Execute" pattern.
	 *
	 * @param Message $message The assistant's tool-call message.
	 * @return list<array<string, mixed>> Array of tool details needing confirmation (empty if none).
	 */
	private function get_tools_needing_confirmation( Message $message ): array {
		// YOLO mode: skip all confirmations and execute immediately.
		if ( $this->yolo_mode ) {
			return array();
		}

		$confirm       = array();
		$all_abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( ! $call ) {
				continue;
			}

			$fn_name = (string) $call->getName();

			// Convert function name to ability name for lookups.
			$ability_name = $fn_name;
			if ( str_starts_with( $fn_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
				$ability_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $fn_name );
			}

			// 1. Check explicit tool_permissions setting first.
			if ( ! empty( $this->tool_permissions ) ) {
				$permission = $this->tool_permissions[ $ability_name ] ?? null;

				if ( null !== $permission ) {
					// Explicit permission set for this tool.
					if ( 'confirm' === $permission ) {
						$confirm[] = array(
							'id'   => $call->getId(),
							'name' => $fn_name,
							'args' => $call->getArgs(),
						);
					}
					// 'auto', 'always_allow', 'disabled' → no confirmation needed.
					continue;
				}
			}

			// 2. No explicit permission — use annotation-based classification.
			// Look up the ability's readonly annotation.
			$ability = $all_abilities[ $ability_name ] ?? null;

			if ( null !== $ability ) {
				$classification = self::classify_ability( $ability );

				if ( 'write' === $classification ) {
					$confirm[] = array(
						'id'   => $call->getId(),
						'name' => $fn_name,
						'args' => $call->getArgs(),
					);
				}
				// 'read' → auto-execute.
			} elseif ( null === $ability ) {
				// If ability not found in registry (e.g. custom tool), default to
				// requiring confirmation for safety.
				$confirm[] = array(
					'id'   => $call->getId(),
					'name' => $fn_name,
					'args' => $call->getArgs(),
				);
			}
		}

		return $confirm;
	}

	/**
	 * Persist an "always allow" permission for a specific ability.
	 *
	 * Called when the user approves a write tool and chooses "Always Allow".
	 * Stores the permission in the tool_permissions setting so future calls
	 * to this ability skip confirmation.
	 *
	 * @param string $ability_name The ability name (e.g. 'gratis-ai-agent/memory-save').
	 */
	public static function set_always_allow( string $ability_name ): void {
		$all   = Settings::get();
		$perms = $all['tool_permissions'] ?? array();

		// @phpstan-ignore-next-line
		$perms[ $ability_name ] = 'always_allow';

		Settings::update( array( 'tool_permissions' => $perms ) );
	}

	/**
	 * Get the list of abilities that have been set to "always allow".
	 *
	 * @return string[] Ability names with always_allow permission.
	 */
	public static function get_always_allowed(): array {
		$perms = Settings::get( 'tool_permissions' );

		if ( ! is_array( $perms ) ) {
			return array();
		}

		$always = array();
		foreach ( $perms as $name => $level ) {
			if ( 'always_allow' === $level ) {
				$always[] = $name;
			}
		}

		return $always;
	}

	/**
	 * Resolve which abilities should be loaded as direct (Tier-1) tools for
	 * this run. Returns the WP_Ability objects matching {@see ToolDiscovery::tier_1_for_run()}
	 * (curated cold-start list ∪ top-N most-used ∪ meta-tools), filtered
	 * through tool_permissions, the `ai_hidden` meta flag and any role-based
	 * restrictions.
	 *
	 * When client_abilities are present, synthetic WP_Ability stubs for the
	 * validated JS descriptors are appended so the model sees them in its
	 * tool list. The loop intercepts calls to these names and returns them
	 * as pending_client_tool_calls instead of executing them server-side.
	 *
	 * Tier-2 abilities are NOT returned here — the model sees them as a
	 * name-only manifest in the system prompt and reaches them via
	 * gratis-ai-agent/ability-search + ability-call.
	 *
	 * @return \WP_Ability[]
	 */
	private function resolve_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		// Explicit per-instance override (e.g. from tests or CLI --abilities).
		// When set, bypass the auto-discovery layer and return exactly what was asked for.
		if ( ! empty( $this->abilities ) ) {
			$resolved = array();
			foreach ( $this->abilities as $name ) {
				// @phpstan-ignore-next-line
				$ability = wp_get_ability( $name );
				if ( $ability instanceof \WP_Ability ) {
					$resolved[] = $ability;
				}
			}
			// Append client ability stubs even in explicit-abilities mode.
			return array_merge( $resolved, $this->build_client_ability_stubs() );
		}

		$tier_1 = ToolDiscovery::tier_1_for_run();

		$role_allowed = RolePermissions::get_allowed_abilities_for_current_user();
		$perms        = $this->tool_permissions;

		$resolved = array();
		foreach ( $tier_1 as $name ) {
			if ( null !== $role_allowed && ! in_array( $name, $role_allowed, true ) ) {
				continue;
			}
			if ( 'disabled' === ( $perms[ $name ] ?? 'auto' ) ) {
				continue;
			}
			// @phpstan-ignore-next-line
			$ability = wp_get_ability( $name );
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}
			$meta = $ability->get_meta();
			if ( ! empty( $meta['ai_hidden'] ) ) {
				continue;
			}
			$resolved[] = $ability;
		}

		// Append synthetic stubs for validated client-side abilities.
		return array_merge( $resolved, $this->build_client_ability_stubs() );
	}

	/**
	 * Build synthetic WP_Ability stubs for validated client-side descriptors.
	 *
	 * These stubs expose the client ability schemas to the model's tool list.
	 * The loop intercepts calls to these names and returns them as
	 * pending_client_tool_calls instead of executing them server-side.
	 *
	 * @return \WP_Ability[]
	 */
	private function build_client_ability_stubs(): array {
		if ( empty( $this->client_abilities ) ) {
			return array();
		}

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return array();
		}

		$stubs = array();
		foreach ( $this->client_abilities as $descriptor ) {
			$name = (string) ( $descriptor['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			// Check if already registered in the global registry.
			// @phpstan-ignore-next-line
			$existing = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
			if ( $existing instanceof \WP_Ability ) {
				$stubs[] = $existing;
				continue;
			}

			// Register a transient stub for this request only.
			// The stub has a no-op callback — the loop never actually calls it.
			// @phpstan-ignore-next-line
			wp_register_ability(
				$name,
				array(
					'label'        => (string) ( $descriptor['label'] ?? $name ),
					'description'  => (string) ( $descriptor['description'] ?? '' ),
					'category'     => 'gratis-ai-agent-js',
					'callback'     => static function ( array $args ): array {
						// No-op: client-side abilities are never executed server-side.
						return array( 'error' => 'Client-side ability cannot be executed server-side.' );
					},
					'input_schema' => $descriptor['input_schema'] ?? array(),
					'annotations'  => array(
						'readonly' => (bool) ( $descriptor['annotations']['readonly'] ?? true ),
					),
				)
			);

			// @phpstan-ignore-next-line
			$stub = wp_get_ability( $name );
			if ( $stub instanceof \WP_Ability ) {
				$stubs[] = $stub;
			}
		}

		return $stubs;
	}

	/**
	 * Return the set of client ability names validated for this run.
	 *
	 * @return string[]
	 */
	private function get_client_ability_names(): array {
		return array_map(
			static function ( array $d ): string {
				return (string) ( $d['name'] ?? '' );
			},
			$this->client_abilities
		);
	}

	/**
	 * Partition the tool calls in an assistant message into PHP-executable
	 * and client-side (JS) sets.
	 *
	 * Returns an array with two keys:
	 * - 'php':    list of MessagePart objects for PHP-executable calls.
	 * - 'client': list of pending call descriptors for JS execution.
	 *
	 * @param Message  $message      The assistant message containing tool calls.
	 * @param string[] $client_names Names of client-side abilities.
	 * @return array{php: list<\WordPress\AiClient\Messages\DTO\MessagePart>, client: list<array<string, mixed>>}
	 */
	private function partition_tool_calls( Message $message, array $client_names ): array {
		$php_parts = array();
		$client    = array();

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( ! $call ) {
				$php_parts[] = $part;
				continue;
			}

			$fn_name      = (string) $call->getName();
			$ability_name = $fn_name;
			if ( str_starts_with( $fn_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
				$ability_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $fn_name );
			}

			if ( in_array( $ability_name, $client_names, true ) ) {
				$client[] = array(
					'id'   => (string) $call->getId(),
					'name' => $ability_name,
					'args' => $call->getArgs() ?: array(),
				);
			} else {
				$php_parts[] = $part;
			}
		}

		return array(
			'php'    => $php_parts,
			'client' => $client,
		);
	}

	/**
	 * Build a new Message containing only the given MessagePart objects.
	 *
	 * Used to construct a PHP-only sub-message when a mixed assistant message
	 * contains both PHP and JS tool calls.
	 *
	 * @param Message                                            $original Original message (for role/type).
	 * @param list<\WordPress\AiClient\Messages\DTO\MessagePart> $parts    Parts to include.
	 * @return Message
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Generic list<T> not supported by PHPCS.
	private function build_message_from_parts( Message $original, array $parts ): Message {
		// Reconstruct as a ModelMessage with the filtered parts.
		return new ModelMessage( $parts );
	}

	/**
	 * Get or create the ability function resolver instance.
	 *
	 * @return WP_AI_Client_Ability_Function_Resolver
	 */
	private function get_ability_resolver(): WP_AI_Client_Ability_Function_Resolver {
		if ( null === $this->ability_resolver ) {
			$abilities              = $this->resolve_abilities();
			$this->ability_resolver = new AbilityFunctionResolver( ...$abilities );
		}
		return $this->ability_resolver;
	}

	/**
	 * Log tool calls from an assistant message for transparency.
	 */
	private function log_tool_calls( Message $message ): void {
		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$this->tool_call_log[] = array(
					'type' => 'call',
					'id'   => $call->getId(),
					'name' => $call->getName(),
					'args' => $call->getArgs(),
				);
			}
		}
	}

	/**
	 * Log tool responses for transparency.
	 */
	private function log_tool_responses( Message $message ): void {
		foreach ( $message->getParts() as $part ) {
			$response = $part->getFunctionResponse();
			if ( $response ) {
				$this->tool_call_log[] = array(
					'type'     => 'response',
					'id'       => $response->getId(),
					'name'     => $response->getName(),
					'response' => $response->getResponse(),
				);
			}
		}
	}

	/**
	 * Build a deterministic signature of the tool calls in a message.
	 *
	 * Used for spin detection: if two consecutive rounds produce the same
	 * signature, the model is calling the same tools with the same args
	 * and making no progress.
	 *
	 * @param Message $message The assistant message containing tool calls.
	 * @return string A hash signature, or empty string if no tool calls.
	 */
	private function build_tool_signature( Message $message ): string {
		$parts = array();

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$parts[] = (string) $call->getName() . ':' . wp_json_encode( $call->getArgs() ?: array() );
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		sort( $parts );
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Classify an ability as 'read' or 'write' based on its meta annotations.
	 *
	 * Uses the WordPress Abilities API `readonly` annotation:
	 * - readonly=true  → 'read' (auto-execute, no confirmation needed)
	 * - readonly=false → 'write' (needs confirmation unless always-allowed)
	 * - readonly=null  → 'write' (default to safe — require confirmation)
	 *
	 * @param \WP_Ability $ability The ability to classify.
	 * @return string 'read' or 'write'.
	 */
	public static function classify_ability( \WP_Ability $ability ): string {
		$meta = $ability->get_meta();

		// Check the annotations.readonly field.
		if ( isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ) {
			if ( true === ( $meta['annotations']['readonly'] ?? null ) ) {
				return 'read';
			}
		}

		// Default to 'write' — safe by default.
		return 'write';
	}

	/**
	 * Serialize conversation history to transportable arrays.
	 *
	 * @return array
	 */
	private function serialize_history(): array {
		return array_map(
			function ( Message $msg ) {
				return $msg->toArray();
			},
			$this->history
		);
	}

	/**
	 * Deserialize conversation history from arrays back to Message objects.
	 *
	 * @param list<array<string, mixed>> $data Serialized history arrays.
	 * @return list<Message>
	 */
	public static function deserialize_history( array $data ): array {
		$messages = [];
		foreach ( $data as $item ) {
			$messages[] = Message::fromArray( $item );
		}
		return $messages;
	}

	/**
	 * Build the system instruction, incorporating custom prompt and memories.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	private function build_system_instruction( array $settings ): string {
		// Site builder mode: use the site builder interview prompt instead of the default.
		if ( ! empty( $settings['site_builder_mode'] ) ) {
			$base = self::get_site_builder_system_prompt();

			// Still append memories so the agent knows what was collected in prior turns.
			$memory_text = Memory::get_formatted_for_prompt();
			if ( ! empty( $memory_text ) ) {
				$base .= "\n\n" . $memory_text;
			}

			return $base;
		}

		// Use custom system prompt if set, otherwise the built-in default.
		$custom = $settings['system_prompt'] ?? '';
		$base   = ! empty( $custom ) ? $custom : self::default_system_instruction();

		// Append memory section if memories exist.
		$memory_text = Memory::get_formatted_for_prompt();
		if ( ! empty( $memory_text ) ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n" . $memory_text;
		}

		// Append skill index if skills are available.
		$skill_index = Skill::get_index_for_prompt();
		if ( ! empty( $skill_index ) ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n" . $skill_index;
		}

		// If auto-memory is enabled, tell the agent about memory abilities.
		$auto_memory = $settings['auto_memory'] ?? true;
		if ( $auto_memory ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n## Memory Instructions\n"
				. "You have access to persistent memory tools. Use them proactively:\n"
				. "- Use **gratis-ai-agent/memory-save** to remember important information the user tells you (preferences, site details, workflows).\n"
				. "- Use **gratis-ai-agent/memory-list** to recall what you've previously stored.\n"
				. "- Use **gratis-ai-agent/memory-delete** to remove outdated memories.\n"
				. "- Use **gratis-ai-agent/knowledge-search** to search the knowledge base for relevant documents and information.\n"
				. 'Save memories when the user shares reusable facts, preferences, or context that would be valuable in future conversations.';
		}

		// Inject knowledge context if enabled and user message is available.
		$knowledge_enabled = $settings['knowledge_enabled'] ?? true;
		if ( $knowledge_enabled && ! empty( $this->user_message ) ) {
			$context = Knowledge::get_context_for_query( $this->user_message );
			if ( ! empty( $context ) ) {
				// @phpstan-ignore-next-line
				$base .= "\n\n## Relevant Knowledge\n"
					. "The following information was retrieved from the knowledge base and may be relevant:\n\n"
					. $context
					. "\n\nUse this information to provide accurate, contextual responses. "
					. 'Cite the source when using specific facts from the knowledge base.';
			}
		}

		// Inject structured context from providers.
		$context_data = ContextProviders::gather( $this->page_context );
		if ( ! empty( $context_data ) ) {
			$formatted_context = ContextProviders::format_for_prompt( $context_data );
			if ( ! empty( $formatted_context ) ) {
				// @phpstan-ignore-next-line
				$base .= "\n\n" . $formatted_context;
			}
		}

		// Append the Tier-2 ability manifest so the model knows what's
		// reachable via ability-search / ability-call. This is the heart of
		// the auto-discovery layer.
		$manifest = ToolDiscovery::build_manifest_section();
		if ( '' !== $manifest ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n" . $manifest;
		}

		// If the configured model is known to be weak at tool use (either
		// by name heuristic or by accumulated telemetry), append explicit
		// guidance about reading schemas and not retrying with the same
		// arguments. Strong models don't get this — keeps their context lean.
		if ( ModelHealthTracker::is_weak( $this->model_id ) ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n" . ModelHealthTracker::weak_model_prompt_nudge();
		}

		// Suggestion chips: instruct the AI to append follow-up suggestions.
		// @phpstan-ignore-next-line
		$suggestion_count = (int) ( $settings['suggestion_count'] ?? 3 );
		if ( $suggestion_count > 0 ) {
			// @phpstan-ignore-next-line
			$base .= "\n\n## Follow-up Suggestions\n"
				. sprintf(
					'After each response, include exactly %d brief follow-up suggestions the user might want to ask next. '
					. "Format them on the LAST lines of your response, one per line, each prefixed with `[suggestion]`. Example:\n"
					. "[suggestion] Show me recent posts\n"
					. "[suggestion] Check plugin updates\n"
					. "[suggestion] Optimize the database\n"
					. 'Keep suggestions relevant, actionable, and under 60 characters each. '
					. 'Do NOT include suggestions when you are asking the user a question or waiting for input.',
					$suggestion_count
				);
		}

		// @phpstan-ignore-next-line
		return $base;
	}

	/**
	 * Default system instruction for the agent.
	 *
	 * @return string
	 */
	public static function get_default_system_prompt(): string {
		return self::default_system_instruction();
	}

	/**
	 * Site builder system prompt v2.
	 *
	 * Used when site_builder_mode is active. The agent interviews the user,
	 * generates a structured plan for confirmation, then builds the complete
	 * site using all available abilities — including plugin discovery for
	 * capabilities not built in.
	 *
	 * @return string
	 */
	public static function get_site_builder_system_prompt(): string {
		$wp_path  = ABSPATH;
		$site_url = get_site_url();

		return "You are a WordPress site builder assistant. Your job is to interview the user, generate a build plan for their approval, then build their complete website automatically using all available tools.\n\n"
			. "## WordPress Environment\n"
			. "- WordPress path: {$wp_path}\n"
			. "- Site URL: {$site_url}\n\n"
			. "## Site Builder Workflow\n\n"
			. "### Phase 1 — Interview (ask ONE question at a time)\n"
			. "Collect the following information through a friendly, conversational interview. Ask one question at a time and wait for the answer before proceeding:\n\n"
			. "1. **Business name** — What is the name of your business or website?\n"
			. "2. **Business type** — What kind of business or website is this? (e.g. restaurant, portfolio, blog, e-commerce, service business, non-profit)\n"
			. "3. **Target audience** — Who are your customers or visitors?\n"
			. "4. **Key goals** — What do you want visitors to do on your site? (e.g. contact you, buy products, read your blog, book appointments)\n"
			. "5. **Pages needed** — Which pages do you need? (suggest: Home, About, Services/Products, Contact — ask if they want more)\n"
			. "6. **Tone and style** — How would you describe the tone? (e.g. professional, friendly, creative, minimal, bold)\n"
			. "7. **Any specific content** — Do you have a tagline, description, or any specific text you want included?\n\n"
			. "### Phase 2 — Plan Generation (present before building)\n"
			. "Once you have all interview answers, generate a structured build plan and present it to the user for confirmation before executing.\n\n"
			. "**Format the plan as:**\n"
			. "```\n"
			. "## Your Site Build Plan\n\n"
			. "**Site:** [Business Name] — [Business Type]\n"
			. "**Tagline:** [Generated tagline]\n\n"
			. "### Pages to Create\n"
			. "1. Home — [brief description]\n"
			. "2. About — [brief description]\n"
			. "... (all pages)\n\n"
			. "### Plugins to Install (if needed)\n"
			. "- [Plugin name] — [reason, e.g. \"contact forms\"]\n"
			. "  (or: \"No additional plugins needed\")\n\n"
			. "### Configuration\n"
			. "- Site title, tagline, homepage, navigation menu\n"
			. "- [Any CPTs, taxonomies, or special features]\n\n"
			. "**Ready to build? This will take about 2-3 minutes.**\n"
			. "```\n\n"
			. "Wait for the user to confirm (\"yes\", \"go ahead\", \"build it\", etc.) before proceeding to Phase 3.\n\n"
			. "### Phase 3 — Plugin Discovery (before building)\n"
			. "Before building, check whether any needed capabilities require plugins:\n\n"
			. "1. **Check available abilities** — Use `gratis-ai-agent/ability-search` to find abilities for each needed feature.\n"
			. "   - Search for: \"menu\", \"nav\", \"options\", \"custom post type\", \"form\", \"seo\", etc.\n"
			. "2. **Identify gaps** — If a needed capability has no ability, check installed plugins:\n"
			. "   - Use `gratis-ai-agent/get-plugins` to list installed and active plugins.\n"
			. "3. **Recommend plugins for gaps** — If a plugin can fill the gap:\n"
			. "   - Use `gratis-ai-agent/recommend-plugin` (if available) to get ranked recommendations.\n"
			. "   - Or use `gratis-ai-agent/search-plugin-directory` (if available) to search WordPress.org.\n"
			. "   - Prefer plugins with Abilities API support > block-based plugins > popular plugins.\n"
			. "4. **Install needed plugins** — Use `gratis-ai-agent/install-plugin` to install and activate.\n"
			. "   - Only install plugins that are genuinely needed for the site type.\n"
			. "   - Common needs by site type:\n"
			. "     - Contact forms: WPForms Lite (slug: wpforms-lite)\n"
			. "     - SEO: Yoast SEO (slug: wordpress-seo) or Rank Math (slug: seo-by-rank-math)\n"
			. "     - E-commerce: WooCommerce (slug: woocommerce)\n"
			. "     - Booking/appointments: Amelia (slug: ameliabooking)\n\n"
			. "### Phase 4 — Build (execute with progress updates)\n"
			. "Build the complete site in this order. After each major step, output a progress update:\n"
			. "\"**Progress:** [step description] ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 1 — Site identity**\n"
			. "- If `gratis-ai-agent/manage-options` is available: use it to set blogname and blogdescription.\n"
			. "- Otherwise use `gratis-ai-agent/run-php` with `update_option('blogname', '...')` and `update_option('blogdescription', '...')`.\n"
			. "- Output: \"**Progress:** Site identity set ✓ (1/[total] steps done)\"\n\n"
			. "**Step 2 — Create all pages**\n"
			. "- Use `ai-agent/create-post` with `post_type: page`, `status: publish`.\n"
			. "- Write substantial, realistic content for each page (3+ paragraphs minimum).\n"
			. "- Home page: hero section, value proposition, call to action.\n"
			. "- About page: story, mission, team (if applicable).\n"
			. "- Services/Products page: detailed descriptions.\n"
			. "- Contact page: contact info, form instructions or embedded form block.\n"
			. "- Any additional pages the user requested.\n"
			. "- After each page: \"**Progress:** [Page name] page created ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 3 — Set static homepage**\n"
			. "- If `gratis-ai-agent/manage-options` is available: set show_on_front=page and page_on_front=[home_page_id].\n"
			. "- Otherwise use `gratis-ai-agent/run-php` with `update_option('show_on_front', 'page')` and `update_option('page_on_front', [id])`.\n"
			. "- Output: \"**Progress:** Homepage configured ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 4 — Navigation menu**\n"
			. "- If `gratis-ai-agent/manage-nav-menu` is available: use it to create menu, add pages, assign to primary location.\n"
			. "- Otherwise use `gratis-ai-agent/run-php` with `wp_create_nav_menu('Main Menu')`, `wp_update_nav_menu_item()` for each page, and `set_theme_mod('nav_menu_locations', ...)`.\n"
			. "- Output: \"**Progress:** Navigation menu created ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 5 — Hero image** (optional but recommended)\n"
			. "- Use `gratis-ai-agent/import-stock-image` with a keyword matching the business type.\n"
			. "- Set as featured image on the home page using `ai-agent/update-post`.\n"
			. "- Output: \"**Progress:** Hero image imported ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 6 — Custom post types / taxonomies** (if needed for the site type)\n"
			. "- If `gratis-ai-agent/register-custom-post-type` is available and the site needs CPTs (e.g. restaurant menu items, portfolio projects, team members): register them.\n"
			. "- If `gratis-ai-agent/register-custom-taxonomy` is available and needed: register taxonomies.\n"
			. "- Output: \"**Progress:** Custom post types registered ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 7 — Global styles** (if available)\n"
			. "- If `gratis-ai-agent/manage-global-styles` is available: apply a color palette and typography matching the site tone.\n"
			. "- Output: \"**Progress:** Global styles applied ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 8 — Save site info to memory**\n"
			. "- Use `gratis-ai-agent/memory-save` to store: business name, type, goals, page IDs, installed plugins.\n"
			. "- Output: \"**Progress:** Site info saved to memory ✓ ([N]/[total] steps done)\"\n\n"
			. "**Step 9 — Mark site builder complete**\n"
			. "- Call `gratis-ai-agent/complete-site-builder` to disable site builder mode.\n"
			. "- Output: \"**Progress:** Site builder complete ✓ ([total]/[total] steps done)\"\n\n"
			. "### Phase 5 — Summary\n"
			. "After building, provide a complete summary:\n\n"
			. "```\n"
			. "## Your Site is Ready! 🎉\n\n"
			. "**[Business Name]** — [Site URL]\n\n"
			. "### Pages Created\n"
			. "- [Page name]: [URL]\n"
			. "... (all pages with links)\n\n"
			. "### What Was Configured\n"
			. "- Site title and tagline\n"
			. "- Static homepage\n"
			. "- Navigation menu\n"
			. "- [Any plugins installed]\n"
			. "- [Any CPTs/taxonomies registered]\n\n"
			. "### What Needs Attention\n"
			. "- [Any steps that failed or were skipped]\n"
			. "- [Any manual steps needed]\n\n"
			. "### Suggested Next Steps\n"
			. "- [Relevant follow-up actions]\n"
			. "```\n\n"
			. "## Error Recovery Rules\n"
			. "- **Never stop on a single error.** Log the failure and continue with the next step.\n"
			. "- **Retry once** with a different approach before skipping a step.\n"
			. "- **Track failures** — keep a mental list of what failed to include in the summary.\n"
			. "- **Fallback chain for options:** manage-options → run-php with update_option → skip with note.\n"
			. "- **Fallback chain for menus:** manage-nav-menu → run-php with wp_create_nav_menu → skip with note.\n"
			. "- **Fallback chain for pages:** ai-agent/create-post → gratis-ai-agent/run-php with wp_insert_post → skip with note.\n"
			. "- If you've retried the same tool twice with similar arguments, move on.\n\n"
			. "## Important Rules\n"
			. "- **Never use placeholder text.** Write real, specific content based on what the user told you.\n"
			. "- **One question at a time** during the interview phase.\n"
			. "- **Wait for plan confirmation** before starting Phase 4.\n"
			. "- **No further questions** during the build phase — just build it.\n"
			. "- **Use ability-search first** when you need a capability — don't assume a tool doesn't exist.\n"
			. '- **Target: 5-page site built in under 3 minutes** after plan confirmation.';
	}

	/**
	 * Internal default system instruction builder.
	 *
	 * @return string
	 */
	private static function default_system_instruction(): string {
		$wp_path  = ABSPATH;
		$site_url = get_site_url();

		return "You are a WordPress assistant that ACTS — you execute tasks immediately using your tools.\n\n"
			. "## WordPress Environment\n"
			. "- WordPress path: {$wp_path}\n"
			. "- Site URL: {$site_url}\n\n"
			. "## Core Principles\n"
			. "1. **Act, don't ask.** Execute the task right away. Don't ask \"shall I proceed?\" or request confirmation unless the task is destructive (deleting data, dropping tables).\n"
			. "2. **Generate real content.** When creating pages or posts, write substantial, realistic content (3+ paragraphs). Never use placeholder text like \"Lorem ipsum\" or \"Content goes here\".\n"
			. "3. **Use tools directly.** Call tools immediately — don't describe what you would do.\n"
			. "4. **Call all needed tools in one response.** When a task requires multiple tools (e.g. create a post AND find an image), call them all at once.\n"
			. "5. **After receiving tool results, ALWAYS provide a text response summarizing the results for the user.** Never return an empty response after tool calls.\n\n"
			. "## Content Creation (IMPORTANT)\n"
			. "To create any page or blog post, use `ai-agent/create-post`. This is the ONLY tool you need.\n"
			. "- For pages: set `post_type` to `page`.\n"
			. "- For blog posts: set `post_type` to `post`.\n"
			. "- Write content directly in the `content` field using markdown (## headings, **bold**, - lists) or HTML. Markdown is automatically converted to Gutenberg blocks.\n"
			. "- Set `status` to `publish` to make it live, or `draft` to save without publishing.\n"
			. "- Include `categories` and `tags` arrays for blog posts.\n"
			. "- Include `excerpt` for SEO meta descriptions.\n"
			. "- To create a post WITH a stock image in one step, use `ai-agent/create-post-with-image` instead.\n"
			. "- For WooCommerce products, use `gratis-ai-agent/woo-create-product` instead.\n\n"
			. "## Tips\n"
			. "- Chain operations: create content first, then configure settings.\n"
			. "- After completing all steps, summarize what was done with links to the created resources.\n\n"
			. "## Error Handling\n"
			. "- If a tool call fails, try a different approach or skip it and continue with the next step.\n"
			. "- Never stop after a single error — complete as many steps as possible.\n"
			. "- If you've retried the same tool 2 times with similar args, move on.";
	}

	/**
	 * Accumulate token usage from an AI result.
	 *
	 * @param mixed $result The AI result object.
	 */
	private function accumulate_tokens( $result ): void {
		try {
			// @phpstan-ignore-next-line
			if ( method_exists( $result, 'getUsage' ) ) {
				/** @phpstan-ignore-next-line */
				$usage = $result->getUsage();
				if ( $usage ) {
					if ( method_exists( $usage, 'getPromptTokens' ) ) {
						/** @phpstan-ignore-next-line */
						$this->token_usage['prompt'] += (int) $usage->getPromptTokens();
					}
					if ( method_exists( $usage, 'getCompletionTokens' ) ) {
						/** @phpstan-ignore-next-line */
						$this->token_usage['completion'] += (int) $usage->getCompletionTokens();
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Token tracking is best-effort.
		}
	}

	/**
	 * Append a tool-response message to history, splitting multi-part
	 * function-response messages into one UserMessage per part.
	 *
	 * Anthropic accepts a single user message containing N function_response
	 * parts; OpenAI-compatible providers (synthetic.new, Ollama, LM Studio,
	 * etc.) require one `tool` role message per `tool_call_id`. The SDK's
	 * OpenAI adapter only special-cases the single-part shape, so we have
	 * to split here for portability.
	 *
	 * @param Message $message Tool-response message returned by the resolver.
	 * @return void
	 */
	private function append_tool_response_to_history( Message $message ): void {
		$parts = $message->getParts();

		$has_function_response = false;
		foreach ( $parts as $part ) {
			$fr = method_exists( $part, 'getFunctionResponse' ) ? $part->getFunctionResponse() : null;
			if ( $fr ) {
				$has_function_response = true;
				break;
			}
		}

		if ( ! $has_function_response || count( $parts ) <= 1 ) {
			$this->history[] = $message;
			return;
		}

		foreach ( $parts as $part ) {
			$this->history[] = new UserMessage( array( $part ) );
		}
	}

	/**
	 * Truncate large tool results in a response message.
	 *
	 * @param Message $message The tool response message.
	 * @return Message A new message with truncated results.
	 */
	private static function truncate_tool_results( Message $message ): Message {
		$new_parts = array();
		$modified  = false;

		foreach ( $message->getParts() as $part ) {
			$fr = method_exists( $part, 'getFunctionResponse' ) ? $part->getFunctionResponse() : null;
			if ( ! $fr ) {
				$new_parts[] = $part;
				continue;
			}

			$original_result = $fr->getResponse();
			$tool_name       = (string) $fr->getName();
			$ability_name    = $tool_name;
			if ( str_starts_with( $tool_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
				$ability_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $tool_name );
			}

			$truncated = ToolResultTruncator::truncate( $original_result, $ability_name );

			if ( $truncated !== $original_result ) {
				$modified    = true;
				$new_parts[] = new MessagePart(
					new \WordPress\AiClient\Tools\DTO\FunctionResponse(
						(string) $fr->getId(),
						(string) $fr->getName(),
						$truncated
					)
				);
			} else {
				$new_parts[] = $part;
			}
		}

		if ( ! $modified ) {
			return $message;
		}

		return new UserMessage( $new_parts );
	}
}
