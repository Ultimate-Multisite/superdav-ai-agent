<?php

declare(strict_types=1);
/**
 * Core agentic loop orchestration.
 *
 * Sends a prompt, checks for tool calls, executes them,
 * feeds results back, and repeats until the model is done.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Core\BudgetManager;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Knowledge\Knowledge;
use GratisAiAgent\Models\Memory;
use GratisAiAgent\Models\Skill;
use GratisAiAgent\REST\SseStreamer;
use GratisAiAgent\Tools\ToolDiscovery;
use GratisAiAgent\Tools\ToolProfiles;
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

class AgentLoop {

	/** @var string */
	private $user_message;

	/** @var string[] Ability names to enable. */
	private $abilities;

	/** @var Message[] Conversation history. */
	private $history;

	/** @var string */
	private $system_instruction;

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

	/** @var SseStreamer|null Optional SSE streamer for token-by-token output. */
	private ?SseStreamer $sse_streamer = null;

	/** @var Settings Injected settings dependency. */
	private $settings_service;

	/** @var int Session ID for change attribution (0 = no session). */
	private int $session_id = 0;

	/**
	 * Image attachments for the current user message.
	 * Each entry: [ 'name' => string, 'type' => string, 'data_url' => string, 'is_image' => bool ]
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $attachments = array();

	/**
	 * @param string               $user_message     The user's prompt.
	 * @param string[]             $abilities         Ability names to enable (empty = all).
	 * @param Message[]            $history           Prior messages for multi-turn.
	 * @param array<string, mixed> $options           Optional overrides: system_instruction, max_iterations, provider_id, model_id, temperature, max_output_tokens, page_context, attachments.
	 * @param Settings|null        $settings_service  Injected Settings service (uses static Settings::get() when null).
	 */
	public function __construct( string $user_message, array $abilities = array(), array $history = array(), array $options = array(), ?Settings $settings_service = null ) {
		$this->user_message     = $user_message;
		$this->attachments      = $options['attachments'] ?? array();
		$this->abilities        = $abilities;
		$this->history          = $history;
		$this->page_context     = $options['page_context'] ?? array();
		$this->settings_service = $settings_service ?? new Settings();

		// Merge explicit options with saved settings as fallbacks.
		$settings = $this->settings_service->get();

		$this->provider_id       = $options['provider_id'] ?? ( $settings['default_provider'] ?: '' );
		$this->model_id          = $options['model_id'] ?? ( $settings['default_model'] ?: '' );
		$this->max_iterations    = $options['max_iterations'] ?? ( $settings['max_iterations'] ?: 25 );
		$this->temperature       = $options['temperature'] ?? ( $settings['temperature'] ?? 0.7 );
		$this->max_output_tokens = $options['max_output_tokens'] ?? ( $settings['max_output_tokens'] ?? 4096 );

		// If an agent_system_prompt is provided, inject it into settings so
		// build_system_instruction() uses it as the base instead of the global prompt.
		if ( ! empty( $options['agent_system_prompt'] ) ) {
			$settings['system_prompt'] = $options['agent_system_prompt'];
		}

		// If an agent overrides the active tool profile, apply it to settings.
		if ( ! empty( $options['active_tool_profile'] ) ) {
			$settings['active_tool_profile'] = $options['active_tool_profile'];
		}

		$this->system_instruction = $options['system_instruction'] ?? $this->build_system_instruction( $settings );

		// Tool permissions, YOLO mode, and resumable state.
		$this->tool_permissions = $settings['tool_permissions'] ?? array();
		$this->yolo_mode        = (bool) ( $settings['yolo_mode'] ?? false );
		$this->tool_call_log    = $options['tool_call_log'] ?? array();
		$this->session_id       = (int) ( $options['session_id'] ?? 0 );
		$this->token_usage      = $options['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);

		// Optional SSE streamer for token-by-token output.
		if ( isset( $options['sse_streamer'] ) && $options['sse_streamer'] instanceof SseStreamer ) {
			$this->sse_streamer = $options['sse_streamer'];
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
				__( 'The AI Client SDK is not available. Please check the compatibility layer.', 'gratis-ai-agent' )
			);
		}

		// Check spending budget before making any API call.
		$budget_check = BudgetManager::check_budget();
		if ( is_wp_error( $budget_check ) ) {
			return $budget_check;
		}

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
			} finally {
				ChangeLogger::end();
			}
			// Truncate large tool results before adding to history.
			$truncated_message = self::truncate_tool_results( $response_message );
			$this->history[]   = $truncated_message;
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
	 * Inner loop: send prompts, handle tool calls, repeat.
	 *
	 * @param int $iterations Max iterations remaining.
	 * @return array<string, mixed>|WP_Error
	 */
	private function run_loop( int $iterations ) {
		$last_was_tool_call = false;

		while ( $iterations > 0 ) {
			--$iterations;
			++$this->iterations_used;

			// Smart conversation trimming before each LLM call.
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

			// Emit tool_call events via SSE if streaming.
			if ( null !== $this->sse_streamer ) {
				foreach ( $assistant_message->getParts() as $part ) {
					$call = $part->getFunctionCall();
					if ( $call ) {
						$this->sse_streamer->send_tool_call( (string) $call->getName(), $call->getArgs() ?: array() );
					}
				}
			}

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
			} finally {
				ChangeLogger::end();
			}
			// Truncate large tool results before adding to history.
			$truncated_message = self::truncate_tool_results( $response_message );
			$this->history[]   = $truncated_message;
			$this->log_tool_responses( $response_message );

			// Emit tool_result events via SSE if streaming.
			if ( null !== $this->sse_streamer ) {
				foreach ( $response_message->getParts() as $part ) {
					$fr = $part->getFunctionResponse();
					if ( $fr ) {
						$this->sse_streamer->send_tool_result( $fr->getName(), $fr->getResponse() );
					}
				}
			}
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
	 * For the OpenAI-compatible provider (which routes through the local Claude
	 * proxy), we bypass the WordPress AI SDK entirely and call the proxy
	 * directly via wp_remote_post(). This avoids a fatal autoloader conflict
	 * where the plugin-check plugin's broken copy of HttpTransporterFactory
	 * wins the PHP class-loading race, causing every SDK model request to throw
	 * "HttpTransporterInterface instance not set".
	 *
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|\GratisAiAgent\Core\SimpleAiResult|WP_Error
	 */
	private function send_prompt() {
		$provider_id = $this->provider_id ?: 'ai-provider-for-any-openai-compatible';

		// Route to direct provider implementations first (no WP SDK dependency).
		if ( 'openai' === $provider_id ) {
			return $this->send_prompt_openai();
		}

		if ( 'anthropic' === $provider_id ) {
			return $this->send_prompt_anthropic();
		}

		if ( 'google' === $provider_id ) {
			return $this->send_prompt_google();
		}

		if ( 'ai-provider-for-any-openai-compatible' === $provider_id ) {
			return $this->send_prompt_direct();
		}

		// If the requested provider isn't registered, fall back to direct
		// OpenAI-compatible endpoint if configured, otherwise return an error.
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $provider_id ) ) {
				if ( CredentialResolver::isOpenAiCompatConfigured() ) {
					return $this->send_prompt_direct();
				}
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
			// Registry unavailable — try direct path as last resort.
			return $this->send_prompt_direct();
		}

		$builder = wp_ai_client_prompt();

		$builder->using_system_instruction( $this->system_instruction );
		$this->configure_model( $builder );

		if ( method_exists( $builder, 'using_temperature' ) ) {
			/** @phpstan-ignore-next-line */
			$builder->using_temperature( (float) $this->temperature );
		}

		if ( method_exists( $builder, 'using_max_tokens' ) ) {
			/** @phpstan-ignore-next-line */
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
	 * Build an OpenAI-format messages array from the current history.
	 *
	 * Shared by send_prompt_direct(), send_prompt_openai(), and
	 * send_prompt_google() (which uses Google's OpenAI-compatible endpoint).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_openai_messages(): array {
		$messages = array();

		if ( ! empty( $this->system_instruction ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $this->system_instruction,
			);
		}

		foreach ( $this->history as $msg ) {
			/** @var \WordPress\AiClient\Messages\DTO\Message $msg */
			$role = 'user';

			try {
				$role_enum = $msg->getRole();
				if ( method_exists( $role_enum, 'value' ) ) {
					$role = $role_enum->value;
				} elseif ( method_exists( $role_enum, 'getValue' ) ) {
					$role = $role_enum->getValue();
				} else {
					$role = (string) $role_enum;
				}
				$role = ( 'model' === $role || 'assistant' === $role ) ? 'assistant' : 'user';
			} catch ( \Throwable $e ) {
				$role = 'user';
			}

			try {
				$parts          = $msg->getParts();
				$texts          = array();
				$msg_tool_calls = array();
				$fn_responses   = array();

				foreach ( $parts as $part ) {
					if ( method_exists( $part, 'getText' ) ) {
						$t = $part->getText();
						if ( is_string( $t ) && '' !== $t ) {
							$texts[] = $t;
						}
					}

					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionCall() ) {
						$fc = $part->getFunctionCall();
						if ( $fc ) {
							$msg_tool_calls[] = array(
								'id'       => $fc->getId() ?: ( 'call_' . wp_generate_uuid4() ),
								'type'     => 'function',
								'function' => array(
									'name'      => $fc->getName(),
									'arguments' => wp_json_encode( $fc->getArgs() ?: new \stdClass() ),
								),
							);
						}
					}

					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionResponse() ) {
						$fr = $part->getFunctionResponse();
						if ( $fr ) {
							$fn_responses[] = array(
								'tool_call_id' => $fr->getId() ?: '',
								'role'         => 'tool',
								'content'      => wp_json_encode( $fr->getResponse() ),
							);
						}
					}
				}

				if ( ! empty( $msg_tool_calls ) ) {
					$assistant_msg            = array(
						'role'       => 'assistant',
						'tool_calls' => $msg_tool_calls,
					);
					$text_content             = implode( '', $texts );
					$assistant_msg['content'] = '' !== $text_content ? $text_content : null;
					$messages[]               = $assistant_msg;

					foreach ( $fn_responses as $fr_msg ) {
						$messages[] = $fr_msg;
					}
				} elseif ( ! empty( $fn_responses ) ) {
					foreach ( $fn_responses as $fr_msg ) {
						$messages[] = $fr_msg;
					}
				} else {
					$content = implode( '', $texts );
					if ( '' !== $content ) {
						$messages[] = array(
							'role'    => $role,
							'content' => $content,
						);
					}
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		// Inject image attachments into the last user message for vision models.
		// The last message in history is always the current user turn (appended in run()).
		if ( ! empty( $this->attachments ) ) {
			$last_idx = count( $messages ) - 1;
			for ( $i = $last_idx; $i >= 0; $i-- ) {
				if ( isset( $messages[ $i ]['role'] ) && 'user' === $messages[ $i ]['role'] ) {
					$text_content = $messages[ $i ]['content'];
					$parts        = array();
					if ( is_string( $text_content ) && '' !== $text_content ) {
						$parts[] = array(
							'type' => 'text',
							'text' => $text_content,
						);
					}
					foreach ( $this->attachments as $att ) {
						if ( ! empty( $att['is_image'] ) && ! empty( $att['data_url'] ) ) {
							$parts[] = array(
								'type'      => 'image_url',
								'image_url' => array( 'url' => $att['data_url'] ),
							);
						}
					}
					if ( ! empty( $parts ) ) {
						$messages[ $i ]['content'] = $parts;
					}
					break;
				}
			}
		}

		return $messages;
	}

	/**
	 * Build an OpenAI-format tools array from the current resolved abilities.
	 *
	 * Shared by send_prompt_direct(), send_prompt_openai(), and
	 * send_prompt_google().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_openai_tools(): array {
		$tools     = array();
		$abilities = $this->resolve_abilities();

		/** @var int $max_tools Maximum number of tools to include. */
		$max_tools = (int) apply_filters( 'gratis_ai_agent_max_tools', 64, $abilities );
		if ( $max_tools > 0 && count( $abilities ) > $max_tools ) {
			$abilities = array_slice( $abilities, 0, $max_tools );
		}

		foreach ( $abilities as $ability ) {
			$fn_name      = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability->get_name() );
			$input_schema = $ability->get_input_schema();
			$description  = $ability->get_description();

			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$tool = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $fn_name,
					'description' => $description,
				),
			);

			if ( ! empty( $input_schema ) ) {
				if ( isset( $input_schema['properties'] ) && $input_schema['properties'] === array() ) {
					$input_schema['properties'] = new \stdClass();
				}
				if ( isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) && empty( $input_schema['required'] ) ) {
					unset( $input_schema['required'] );
				}
				$tool['function']['parameters'] = $input_schema;
			} else {
				$tool['function']['parameters'] = array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				);
			}

			$tools[] = $tool;
		}

		// Sanitize and strip non-standard fields.
		$tools = array_map( array( $this, 'sanitize_tool_schema' ), $tools );
		foreach ( $tools as &$tool_ref ) {
			$params = &$tool_ref['function']['parameters'];
			unset( $params['default'] );
			if ( isset( $params['properties'] ) && is_array( $params['properties'] ) ) {
				foreach ( $params['properties'] as &$prop_ref ) {
					if ( is_array( $prop_ref ) && isset( $prop_ref['required'] ) && is_bool( $prop_ref['required'] ) ) {
						unset( $prop_ref['required'] );
					}
				}
				unset( $prop_ref );
			}
			unset( $params );
		}
		unset( $tool_ref );

		return $tools;
	}

	/**
	 * Send a prompt directly to the OpenAI API.
	 *
	 * Uses the API key stored via Settings::set_provider_key('openai', ...).
	 *
	 * @return SimpleAiResult|WP_Error
	 */
	private function send_prompt_openai() {
		$api_key = Settings::get_provider_key( 'openai' );
		if ( '' === $api_key ) {
			return new WP_Error(
				'gratis_ai_agent_no_openai_key',
				__( 'OpenAI API key is not configured. Add it in Settings > Providers.', 'gratis-ai-agent' )
			);
		}

		$model_id = $this->model_id ?: Settings::DIRECT_PROVIDERS['openai']['default_model'];
		$messages = $this->build_openai_messages();
		$tools    = $this->build_openai_tools();

		$request_body = array(
			'model'       => $model_id,
			'messages'    => $messages,
			'temperature' => (float) $this->temperature,
			'max_tokens'  => (int) $this->max_output_tokens,
			'stream'      => false,
		);

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$encoded_body = (string) wp_json_encode( $request_body );
		$encoded_body = str_replace( '"properties":[]', '"properties":{}', $encoded_body ?: '' );

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 600,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $encoded_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from OpenAI";
			return new WP_Error( 'gratis_ai_agent_openai_error', $msg );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';
		return new SimpleAiResult( $text, $data );
	}

	/**
	 * Send a prompt directly to the Anthropic API.
	 *
	 * Uses the API key stored via Settings::set_provider_key('anthropic', ...).
	 * Anthropic uses a different message format: system is a top-level field,
	 * and tool calls use content blocks (tool_use / tool_result).
	 *
	 * @return SimpleAiResult|WP_Error
	 */
	private function send_prompt_anthropic() {
		$api_key = Settings::get_provider_key( 'anthropic' );
		if ( '' === $api_key ) {
			return new WP_Error(
				'gratis_ai_agent_no_anthropic_key',
				__( 'Anthropic API key is not configured. Add it in Settings > Providers.', 'gratis-ai-agent' )
			);
		}

		$model_id = $this->model_id ?: Settings::DIRECT_PROVIDERS['anthropic']['default_model'];

		// Build Anthropic-format messages (no system role in messages array).
		$messages = array();
		foreach ( $this->history as $msg ) {
			$role = 'user';
			try {
				$role_enum = $msg->getRole();
				if ( method_exists( $role_enum, 'value' ) ) {
					$role = $role_enum->value;
				} elseif ( method_exists( $role_enum, 'getValue' ) ) {
					$role = $role_enum->getValue();
				} else {
					$role = (string) $role_enum;
				}
				$role = ( 'model' === $role || 'assistant' === $role ) ? 'assistant' : 'user';
			} catch ( \Throwable $e ) {
				$role = 'user';
			}

			try {
				$parts          = $msg->getParts();
				$content_blocks = array();
				$tool_results   = array();

				foreach ( $parts as $part ) {
					if ( method_exists( $part, 'getText' ) ) {
						$t = $part->getText();
						if ( is_string( $t ) && '' !== $t ) {
							$content_blocks[] = array(
								'type' => 'text',
								'text' => $t,
							);
						}
					}

					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionCall() ) {
						$fc = $part->getFunctionCall();
						if ( $fc ) {
							$content_blocks[] = array(
								'type'  => 'tool_use',
								'id'    => $fc->getId() ?: ( 'toolu_' . wp_generate_uuid4() ),
								'name'  => $fc->getName(),
								'input' => $fc->getArgs() ?: new \stdClass(),
							);
						}
					}

					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionResponse() ) {
						$fr = $part->getFunctionResponse();
						if ( $fr ) {
							$tool_results[] = array(
								'type'        => 'tool_result',
								'tool_use_id' => $fr->getId() ?: '',
								'content'     => wp_json_encode( $fr->getResponse() ),
							);
						}
					}
				}

				if ( ! empty( $tool_results ) ) {
					// Tool results go in a user message.
					$messages[] = array(
						'role'    => 'user',
						'content' => $tool_results,
					);
				} elseif ( ! empty( $content_blocks ) ) {
					$messages[] = array(
						'role'    => $role,
						'content' => $content_blocks,
					);
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		// Build Anthropic-format tools.
		$tools     = array();
		$abilities = $this->resolve_abilities();
		$max_tools = (int) apply_filters( 'gratis_ai_agent_max_tools', 64, $abilities );
		if ( $max_tools > 0 && count( $abilities ) > $max_tools ) {
			$abilities = array_slice( $abilities, 0, $max_tools );
		}

		foreach ( $abilities as $ability ) {
			$fn_name      = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability->get_name() );
			$input_schema = $ability->get_input_schema();
			$description  = $ability->get_description();

			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$schema = ! empty( $input_schema ) ? $input_schema : array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			);
			if ( isset( $schema['properties'] ) && $schema['properties'] === array() ) {
				$schema['properties'] = new \stdClass();
			}
			if ( isset( $schema['required'] ) && is_array( $schema['required'] ) && empty( $schema['required'] ) ) {
				unset( $schema['required'] );
			}

			$tools[] = array(
				'name'         => $fn_name,
				'description'  => $description,
				'input_schema' => $schema,
			);
		}

		$request_body = array(
			'model'      => $model_id,
			'max_tokens' => (int) $this->max_output_tokens,
			'messages'   => $messages,
		);

		if ( ! empty( $this->system_instruction ) ) {
			$request_body['system'] = $this->system_instruction;
		}

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$encoded_body = (string) wp_json_encode( $request_body );
		$encoded_body = str_replace( '"properties":[]', '"properties":{}', $encoded_body ?: '' );

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 600,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => $encoded_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from Anthropic";
			return new WP_Error( 'gratis_ai_agent_anthropic_error', $msg );
		}

		// Extract text from Anthropic's content blocks.
		$text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}

		return new SimpleAiResult( $text, $data );
	}

	/**
	 * Send a prompt to Google AI via its OpenAI-compatible endpoint.
	 *
	 * Uses the API key stored via Settings::set_provider_key('google', ...).
	 * Google's OpenAI-compatible endpoint is at:
	 * https://generativelanguage.googleapis.com/v1beta/openai/
	 *
	 * @return SimpleAiResult|WP_Error
	 */
	private function send_prompt_google() {
		$api_key = Settings::get_provider_key( 'google' );
		if ( '' === $api_key ) {
			return new WP_Error(
				'gratis_ai_agent_no_google_key',
				__( 'Google AI API key is not configured. Add it in Settings > Providers.', 'gratis-ai-agent' )
			);
		}

		$model_id = $this->model_id ?: Settings::DIRECT_PROVIDERS['google']['default_model'];
		$messages = $this->build_openai_messages();
		$tools    = $this->build_openai_tools();

		$request_body = array(
			'model'       => $model_id,
			'messages'    => $messages,
			'temperature' => (float) $this->temperature,
			'max_tokens'  => (int) $this->max_output_tokens,
			'stream'      => false,
		);

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$encoded_body = (string) wp_json_encode( $request_body );
		$encoded_body = str_replace( '"properties":[]', '"properties":{}', $encoded_body ?: '' );

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			array(
				'timeout' => 600,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $encoded_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from Google AI";
			return new WP_Error( 'gratis_ai_agent_google_error', $msg );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';
		return new SimpleAiResult( $text, $data );
	}

	/**
	 * Send a prompt directly to the OpenAI-compatible proxy endpoint.
	 *
	 * Converts the current conversation history to an OpenAI messages array,
	 * POSTs to the configured endpoint URL, and returns either a simple
	 * result object or a WP_Error.
	 *
	 * @return SimpleAiResult|WP_Error
	 */
	private function send_prompt_direct() {
		$endpoint_url = CredentialResolver::getOpenAiCompatEndpointUrl();
		if ( '' === $endpoint_url ) {
			return new WP_Error( 'gratis_ai_agent_no_endpoint', __( 'OpenAI-compatible endpoint URL is not configured.', 'gratis-ai-agent' ) );
		}

		// Resolve model for the OpenAI-compatible endpoint.
		// Priority: explicit selection → connector default → configurable plugin default.
		$model_id = $this->model_id;
		if ( empty( $model_id ) && function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
			$model_id = \OpenAiCompatibleConnector\get_default_model();
		}
		if ( empty( $model_id ) ) {
			$model_id = Settings::get_default_model();
		}

		$messages = $this->build_openai_messages();
		$tools    = $this->build_openai_tools();

		$api_key = CredentialResolver::getOpenAiCompatApiKey();
		$timeout = CredentialResolver::getOpenAiCompatTimeout();

		$use_streaming = null !== $this->sse_streamer;

		$request_body = array(
			'model'       => $model_id,
			'messages'    => $messages,
			'temperature' => (float) $this->temperature,
			'max_tokens'  => (int) $this->max_output_tokens,
			'stream'      => $use_streaming,
		);

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$encoded_body = wp_json_encode( $request_body );

		// Final safety net: replace any remaining "properties":[] with "properties":{}
		// in the JSON string. This catches edge cases where PHP's type juggling
		// converts stdClass back to an empty array during serialization.
		$encoded_body = str_replace( '"properties":[]', '"properties":{}', $encoded_body ?: '' );

		// When streaming, use PHP stream context to read the SSE response line-by-line.
		if ( $use_streaming ) {
			return $this->send_prompt_direct_streaming( $endpoint_url, $api_key, $encoded_body, $timeout );
		}

		$response = wp_remote_post(
			$endpoint_url . '/chat/completions',
			array(
				'timeout'   => $timeout,
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . ( $api_key ?: 'no-key' ),
				),
				'body'      => $encoded_body,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from proxy";
			return new WP_Error( 'gratis_ai_agent_proxy_error', $msg );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		return new SimpleAiResult( $text, $data );
	}

	/**
	 * Send a streaming prompt to the OpenAI-compatible endpoint.
	 *
	 * Opens a persistent HTTP connection, reads the SSE stream line-by-line,
	 * emits each text delta token via the SseStreamer, and returns a
	 * SimpleAiResult with the fully-assembled text once the stream ends.
	 *
	 * @param string $endpoint_url  Base URL of the OpenAI-compatible endpoint.
	 * @param string $api_key       API key (may be 'no-key').
	 * @param string $encoded_body  JSON-encoded request body (stream=true already set).
	 * @param int    $timeout       Request timeout in seconds.
	 * @return SimpleAiResult|WP_Error
	 */
	private function send_prompt_direct_streaming( string $endpoint_url, string $api_key, string $encoded_body, int $timeout ) {
		$url = $endpoint_url . '/chat/completions';

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'POST',
					'header'        => implode(
						"\r\n",
						array(
							'Content-Type: application/json',
							'Authorization: Bearer ' . ( $api_key ?: 'no-key' ),
							'Accept: text/event-stream',
						)
					),
					'content'       => $encoded_body,
					'timeout'       => $timeout,
					'ignore_errors' => true,
				),
				'ssl'  => array(
					'verify_peer'      => false,
					'verify_peer_name' => false,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming HTTP requires fopen
		$stream = fopen( $url, 'r', false, $context );

		if ( false === $stream ) {
			return new WP_Error( 'ai_agent_stream_open_failed', __( 'Failed to open streaming connection to AI endpoint.', 'gratis-ai-agent' ) );
		}

		$full_text        = '';
		$tool_calls_delta = array();

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- standard stream-reading pattern
		while ( ! feof( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets -- streaming HTTP requires fgets
			$line = fgets( $stream );

			if ( false === $line ) {
				break;
			}

			$line = rtrim( $line );

			// SSE lines start with "data: ".
			if ( strpos( $line, 'data: ' ) !== 0 ) {
				continue;
			}

			$json_str = substr( $line, 6 );

			if ( '[DONE]' === $json_str ) {
				break;
			}

			$chunk = json_decode( $json_str, true );

			if ( ! is_array( $chunk ) ) {
				continue;
			}

			$delta = $chunk['choices'][0]['delta'] ?? array();

			// Text token delta.
			if ( isset( $delta['content'] ) && is_string( $delta['content'] ) && '' !== $delta['content'] ) {
				$full_text .= $delta['content'];
				if ( null !== $this->sse_streamer ) {
					$this->sse_streamer->send_token( $delta['content'] );
				}
			}

			// Tool call deltas — accumulate across chunks.
			if ( ! empty( $delta['tool_calls'] ) ) {
				foreach ( $delta['tool_calls'] as $tc_delta ) {
					$idx = $tc_delta['index'] ?? 0;

					if ( ! isset( $tool_calls_delta[ $idx ] ) ) {
						$tool_calls_delta[ $idx ] = array(
							'id'       => '',
							'type'     => 'function',
							'function' => array(
								'name'      => '',
								'arguments' => '',
							),
						);
					}

					if ( ! empty( $tc_delta['id'] ) ) {
						$tool_calls_delta[ $idx ]['id'] = $tc_delta['id'];
					}
					if ( ! empty( $tc_delta['function']['name'] ) ) {
						$tool_calls_delta[ $idx ]['function']['name'] .= $tc_delta['function']['name'];
					}
					if ( isset( $tc_delta['function']['arguments'] ) ) {
						$tool_calls_delta[ $idx ]['function']['arguments'] .= $tc_delta['function']['arguments'];
					}
				}
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with fopen above
		fclose( $stream );

		// Build a synthetic full-response array for SimpleAiResult.
		$synthetic_data = array(
			'choices' => array(
				array(
					'message' => array(
						'role'    => 'assistant',
						'content' => $full_text,
					),
				),
			),
		);

		// Attach assembled tool calls if any.
		if ( ! empty( $tool_calls_delta ) ) {
			$assembled = array();
			foreach ( $tool_calls_delta as $tc ) {
				$assembled[] = $tc;
			}
			$synthetic_data['choices'][0]['message']['tool_calls'] = $assembled;
		}

		return new SimpleAiResult( $full_text, $synthetic_data );
	}

	/**
	 * Recursively sanitize a tool schema for OpenAI compatibility.
	 *
	 * Ensures that 'properties' fields are JSON objects (not arrays),
	 * removes empty 'required' arrays, and handles nested schemas.
	 *
	 * @param array<string, mixed> $tool The tool definition.
	 * @return array<string, mixed> The sanitized tool definition.
	 */
	private function sanitize_tool_schema( array $tool ): array {
		if ( isset( $tool['function']['parameters'] ) ) {
			$tool['function']['parameters'] = $this->sanitize_schema_properties( $tool['function']['parameters'] );
		}
		return $tool;
	}

	/**
	 * Recursively fix empty arrays that should be objects in JSON schema.
	 *
	 * @param mixed $schema The schema or sub-schema.
	 * @return mixed The sanitized schema.
	 */
	private function sanitize_schema_properties( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		// Fix 'properties' — must be an object, not an empty array.
		if ( array_key_exists( 'properties', $schema ) ) {
			if ( is_array( $schema['properties'] ) && empty( $schema['properties'] ) ) {
				$schema['properties'] = new \stdClass();
			} elseif ( is_array( $schema['properties'] ) ) {
				// Recurse into each property.
				foreach ( $schema['properties'] as $key => $prop ) {
					$schema['properties'][ $key ] = $this->sanitize_schema_properties( $prop );
				}
			}
		}

		// Remove empty 'required' arrays (some providers reject them).
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) && empty( $schema['required'] ) ) {
			unset( $schema['required'] );
		}

		// Recurse into 'items' for array types.
		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = $this->sanitize_schema_properties( $schema['items'] );
		}

		return $schema;
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

		// Source 3: OpenAI-compatible connector plugin.
		$compat_provider = 'ai-provider-for-any-openai-compatible';

		if ( $registry->hasProvider( $compat_provider ) && null === $registry->getProviderRequestAuthentication( $compat_provider ) ) {
			$api_key = CredentialResolver::getOpenAiCompatApiKey();

			$registry->setProviderRequestAuthentication(
				$compat_provider,
				new $auth_class( $api_key )
			);
		}
	}

	/**
	 * Check which tool calls in an assistant message require user confirmation.
	 *
	 * @param Message $message The assistant's tool-call message.
	 * @return list<array<string, mixed>> Array of tool details needing confirmation (empty if none).
	 */
	private function get_tools_needing_confirmation( Message $message ): array {
		// YOLO mode: skip all confirmations and execute immediately.
		if ( $this->yolo_mode ) {
			return array();
		}

		if ( empty( $this->tool_permissions ) ) {
			return array();
		}

		$confirm = array();

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
			$fn_name = (string) $call->getName();

			// The function call name uses the wpab__ format (e.g. wpab__gratis-ai-agent__memory-save)
			// while tool_permissions uses ability name format (e.g. gratis-ai-agent/memory-save).
			// Convert function name to ability name for the lookup.
			$ability_name = $fn_name;
			if ( str_starts_with( $fn_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
					$ability_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $fn_name );
				}

				$permission = $this->tool_permissions[ $ability_name ] ?? 'auto';

				if ( 'confirm' === $permission ) {
					$confirm[] = array(
						'id'   => $call->getId(),
						'name' => $fn_name,
						'args' => $call->getArgs(),
					);
				}
			}
		}

		return $confirm;
	}

	/**
	 * Resolve ability names to WP_Ability objects.
	 *
	 * Respects both the new `tool_permissions` setting and the legacy
	 * `disabled_abilities` array for backward compatibility.
	 *
	 * @return \WP_Ability[]
	 */
	private function resolve_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$all = wp_get_abilities();

		// Hide abilities marked as ai_hidden from the model's tool list.
		$all = array_filter(
			$all,
			function ( $ability ) {
				$meta = $ability->get_meta();
				return empty( $meta['ai_hidden'] );
			}
		);

		// Use tool_permissions if set, otherwise fall back to disabled_abilities.
		if ( ! empty( $this->tool_permissions ) ) {
			$perms = $this->tool_permissions;
			$all   = array_filter(
				$all,
				function ( $ability ) use ( $perms ) {
					$perm = $perms[ $ability->get_name() ] ?? 'auto';
					return 'disabled' !== $perm;
				}
			);
		} else {
			$disabled = $this->settings_service->get( 'disabled_abilities' );
			if ( ! empty( $disabled ) && is_array( $disabled ) ) {
				$all = array_filter(
					$all,
					function ( $ability ) use ( $disabled ) {
						return ! in_array( $ability->get_name(), $disabled, true );
					}
				);
			}
		}

		// Apply role-based ability restrictions for the current user.
		// Administrators are unrestricted (get_allowed_abilities_for_current_user returns null).
		$role_allowed = RolePermissions::get_allowed_abilities_for_current_user();
		if ( null !== $role_allowed ) {
			$all = array_filter(
				$all,
				function ( $ability ) use ( $role_allowed ) {
					return in_array( $ability->get_name(), $role_allowed, true );
				}
			);
		}

		if ( ! empty( $this->abilities ) ) {
			$resolved = array();
			foreach ( $this->abilities as $name ) {
				if ( isset( $all[ $name ] ) ) {
					$resolved[] = $all[ $name ];
				}
			}
			return $resolved;
		}

		// Apply tool profile filter.
		$active_profile = $this->settings_service->get( 'active_tool_profile' );
		if ( ! empty( $active_profile ) && 'all' !== $active_profile ) {
			$all = ToolProfiles::filter_abilities( $all, $active_profile );
		}

		// Discovery mode: only load priority-category and priority-named tools.
		if ( ToolDiscovery::should_use_discovery_mode() ) {
			$priority_cats  = ToolDiscovery::get_priority_categories();
			$priority_tools = ToolDiscovery::get_priority_tools();
			$priority       = array_filter(
				$all,
				function ( $ability ) use ( $priority_cats, $priority_tools ) {
					if ( in_array( $ability->get_category(), $priority_cats, true ) ) {
						return true;
					}

					// Check if this ability matches a priority tool name.
					$name = $ability->get_name();
					foreach ( $priority_tools as $tool ) {
						if ( $name === $tool ) {
							return true;
						}
						$suffix = substr( $tool, strpos( $tool, '/' ) + 1 );
						if ( str_ends_with( $name, '/' . $suffix ) ) {
							return true;
						}
					}

					return false;
				}
			);
			return array_values( $priority );
		}

		return array_values( $all );
	}

	/**
	 * Get or create the ability function resolver instance.
	 *
	 * @return WP_AI_Client_Ability_Function_Resolver
	 */
	private function get_ability_resolver(): WP_AI_Client_Ability_Function_Resolver {
		if ( null === $this->ability_resolver ) {
			$abilities              = $this->resolve_abilities();
			$this->ability_resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
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
	 * Serialize conversation history to transportable arrays.
	 *
	 * @return array<string, mixed>
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
	 * @param array<string, mixed> $data Serialized history arrays.
	 * @return Message[]
	 */
	public static function deserialize_history( array $data ): array {
		return array_map(
			function ( $item ) {
				return Message::fromArray( $item );
			},
			$data
		);
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
			$base .= "\n\n" . $memory_text;
		}

		// Append skill index if skills are available.
		$skill_index = Skill::get_index_for_prompt();
		if ( ! empty( $skill_index ) ) {
			$base .= "\n\n" . $skill_index;
		}

		// If auto-memory is enabled, tell the agent about memory abilities.
		$auto_memory = $settings['auto_memory'] ?? true;
		if ( $auto_memory ) {
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
				$base .= "\n\n" . $formatted_context;
			}
		}

		// Append tool discovery instructions when discovery mode is active.
		if ( ToolDiscovery::should_use_discovery_mode() ) {
			$discovery_section = ToolDiscovery::get_system_prompt_section();
			if ( ! empty( $discovery_section ) ) {
				$base .= "\n\n" . $discovery_section;
			}
		}

		// Suggestion chips: instruct the AI to append follow-up suggestions.
		$suggestion_count = (int) ( $settings['suggestion_count'] ?? 3 );
		if ( $suggestion_count > 0 ) {
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
	 * Site builder system prompt.
	 *
	 * Used when site_builder_mode is active. The agent interviews the user
	 * about their business and then generates a complete site autonomously.
	 *
	 * @return string
	 */
	public static function get_site_builder_system_prompt(): string {
		$wp_path  = ABSPATH;
		$site_url = get_site_url();

		return "You are a WordPress site builder assistant. Your job is to interview the user about their business and then build their complete website automatically.\n\n"
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
			. "Once you have answers to all questions, say: \"Great! I have everything I need. I'll now build your site — this will take about 2-3 minutes.\"\n\n"
			. "### Phase 2 — Build (execute immediately, no further questions)\n"
			. "Build the complete site in this order:\n\n"
			. "1. **Update site identity**\n"
			. "   - Set site title: `option/update blogname '<business name>'`\n"
			. "   - Set tagline: `option/update blogdescription '<tagline>'`\n\n"
			. "2. **Create all pages** (use `post/create --post_type=page --post_status=publish --porcelain`)\n"
			. "   - Write substantial, realistic content for each page (3+ paragraphs minimum)\n"
			. "   - Home page: hero section, value proposition, call to action\n"
			. "   - About page: story, mission, team (if applicable)\n"
			. "   - Services/Products page: detailed descriptions\n"
			. "   - Contact page: contact info, form instructions\n"
			. "   - Any additional pages the user requested\n\n"
			. "3. **Set homepage** — Set the Home page as the static front page:\n"
			. "   - `option/update show_on_front page`\n"
			. "   - `option/update page_on_front <home_page_id>`\n\n"
			. "4. **Create navigation menu**\n"
			. "   - Create a menu named 'Main Menu'\n"
			. "   - Add all pages to the menu in logical order\n"
			. "   - Assign to the primary menu location\n\n"
			. "5. **Import hero image** (optional but recommended)\n"
			. "   - Use `gratis-ai-agent/import-stock-image` with a keyword matching the business type\n"
			. "   - Set as featured image on the home page\n\n"
			. "6. **Save site info to memory**\n"
			. "   - Use `gratis-ai-agent/memory-save` to store: business name, type, goals, and page IDs\n\n"
			. "7. **Mark site builder complete**\n"
			. "   - Call `gratis-ai-agent/complete-site-builder` to disable site builder mode\n\n"
			. "### Phase 3 — Summary\n"
			. "After building, provide a summary with:\n"
			. "- List of all pages created with their URLs\n"
			. "- What was configured (title, tagline, menu, homepage)\n"
			. "- Next steps the user might want to take\n\n"
			. "## Important Rules\n"
			. "- **Never use placeholder text.** Write real, specific content based on what the user told you.\n"
			. "- **One question at a time** during the interview phase.\n"
			. "- **No confirmation needed** during the build phase — just build it.\n"
			. "- **If a tool fails**, try an alternative approach and continue.\n"
			. "- **Target: 5-page site built in under 3 minutes.**\n\n"
			. "## Error Handling\n"
			. "- If a tool call fails, try a different approach or skip it and continue.\n"
			. "- Never stop after a single error — complete as many steps as possible.\n"
			. "- If you've retried the same tool 2 times, move on.";
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
