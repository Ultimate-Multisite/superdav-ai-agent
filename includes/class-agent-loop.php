<?php
/**
 * Core agentic loop orchestration.
 *
 * Sends a prompt, checks for tool calls, executes them,
 * feeds results back, and repeats until the model is done.
 *
 * @package AiAgent
 */

namespace AiAgent;

use WP_AI_Client_Ability_Function_Resolver;
use WP_Error;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;

/**
 * Lightweight result wrapper for direct proxy calls.
 *
 * The main run() loop expects a result object with toMessage(), toText(), and
 * getUsage(). When we call the OpenAI-compat proxy directly via wp_remote_post()
 * instead of through the WordPress AI SDK (to avoid a fatal autoloader conflict),
 * we wrap the raw response in this class so the loop can handle it uniformly.
 */
class Simple_AI_Result {

	/** @var string */
	private $text;

	/** @var array */
	private $raw;

	/** @var Message|null */
	private $message = null;

	public function __construct( string $text, array $raw = [] ) {
		$this->text = $text;
		$this->raw  = $raw;
	}

	public function toText(): string {
		return $this->text;
	}

	public function toMessage(): Message {
		if ( null === $this->message ) {
			$parts = [];

			// Add text part if present.
			if ( '' !== $this->text ) {
				$parts[] = new MessagePart( $this->text );
			}

			// Parse OpenAI-format tool_calls from the raw response.
			$tool_calls = $this->raw['choices'][0]['message']['tool_calls'] ?? [];
			foreach ( $tool_calls as $tc ) {
				$fn_name = $tc['function']['name'] ?? '';
				$fn_id   = $tc['id'] ?? $fn_name;
				$fn_args = $tc['function']['arguments'] ?? '{}';

				if ( is_string( $fn_args ) ) {
					$fn_args = json_decode( $fn_args, true ) ?: [];
				}

				$parts[] = new MessagePart(
					new FunctionCall( $fn_id, $fn_name, $fn_args )
				);
			}

			// Fallback: if no parts at all, add empty text.
			if ( empty( $parts ) ) {
				$parts[] = new MessagePart( '' );
			}

			$this->message = new ModelMessage( $parts );
		}
		return $this->message;
	}

	public function getUsage() {
		$usage = $this->raw['usage'] ?? null;
		if ( ! is_array( $usage ) ) {
			return null;
		}
		$prompt     = (int) ( $usage['prompt_tokens'] ?? 0 );
		$completion = (int) ( $usage['completion_tokens'] ?? 0 );
		return new class( $prompt, $completion ) {
			private int $prompt;
			private int $completion;
			public function __construct( int $prompt, int $completion ) {
				$this->prompt     = $prompt;
				$this->completion = $completion;
			}
			public function getPromptTokens(): int {
				return $this->prompt;
			}
			public function getCompletionTokens(): int {
				return $this->completion;
			}
		};
	}
}

class Agent_Loop {

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

	/** @var array Logged tool call activity. */
	private $tool_call_log = [];

	/** @var float */
	private $temperature;

	/** @var int */
	private $max_output_tokens;

	/** @var int Number of loop iterations used. */
	private $iterations_used = 0;

	/** @var array Token usage accumulator. */
	private $token_usage = [
		'prompt'     => 0,
		'completion' => 0,
	];

	/** @var array Tool permission levels from settings. */
	private $tool_permissions = [];

	/** @var array Page context from the widget. */
	private $page_context = [];

	/**
	 * @param string   $user_message The user's prompt.
	 * @param string[] $abilities    Ability names to enable (empty = all).
	 * @param Message[] $history     Prior messages for multi-turn.
	 * @param array    $options      Optional overrides: system_instruction, max_iterations, provider_id, model_id, temperature, max_output_tokens, page_context.
	 */
	public function __construct( string $user_message, array $abilities = [], array $history = [], array $options = [] ) {
		$this->user_message = $user_message;
		$this->abilities    = $abilities;
		$this->history      = $history;
		$this->page_context = $options['page_context'] ?? [];

		// Merge explicit options with saved settings as fallbacks.
		$settings = Settings::get();

		$this->provider_id        = $options['provider_id'] ?? ( $settings['default_provider'] ?: '' );
		$this->model_id           = $options['model_id'] ?? ( $settings['default_model'] ?: '' );
		$this->max_iterations     = $options['max_iterations'] ?? ( $settings['max_iterations'] ?: 25 );
		$this->temperature        = $options['temperature'] ?? ( $settings['temperature'] ?? 0.7 );
		$this->max_output_tokens  = $options['max_output_tokens'] ?? ( $settings['max_output_tokens'] ?? 4096 );

		$this->system_instruction = $options['system_instruction'] ?? $this->build_system_instruction( $settings );

		// Tool permissions and resumable state.
		$this->tool_permissions = $settings['tool_permissions'] ?? [];
		$this->tool_call_log   = $options['tool_call_log'] ?? [];
		$this->token_usage     = $options['token_usage'] ?? [ 'prompt' => 0, 'completion' => 0 ];
	}

	/**
	 * Run the agentic loop.
	 *
	 * @return array{reply: string, history: array, tool_calls: array}|WP_Error
	 */
	public function run() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'ai_agent_missing_client',
				__( 'The AI Client SDK is not available. Please check the compatibility layer.', 'ai-agent' )
			);
		}

		// Ensure provider auth is available (critical for loopback requests).
		self::ensure_provider_credentials_static();

		// Append the new user message to history.
		$this->history[] = new UserMessage( [ new MessagePart( $this->user_message ) ] );

		return $this->run_loop( $this->max_iterations );
	}

	/**
	 * Resume after a tool confirmation or rejection.
	 *
	 * @param bool $confirmed Whether the user approved the tool call.
	 * @param int  $remaining_iterations Remaining loop iterations.
	 * @return array|WP_Error
	 */
	public function resume_after_confirmation( bool $confirmed, int $remaining_iterations ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'ai_agent_missing_client',
				__( 'wp_ai_client_prompt() is not available.', 'ai-agent' )
			);
		}

		self::ensure_provider_credentials_static();

		if ( $confirmed ) {
			// The last message in history is the model's tool call message.
			$assistant_message = end( $this->history );
			$response_message  = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $assistant_message );
			$this->history[]   = $response_message;
			$this->log_tool_responses( $response_message );
		} else {
			// Remove the model's tool call message and tell the model the call was rejected.
			array_pop( $this->history );
			$this->history[] = new UserMessage( [
				new MessagePart(
					'The user declined the requested tool calls. Please respond directly without using those tools.'
				),
			] );
		}

		return $this->run_loop( $remaining_iterations );
	}

	/**
	 * Inner loop: send prompts, handle tool calls, repeat.
	 *
	 * @param int $iterations Max iterations remaining.
	 * @return array|WP_Error
	 */
	private function run_loop( int $iterations ) {
		while ( $iterations > 0 ) {
			$iterations--;
			$this->iterations_used++;

			// Smart conversation trimming before each LLM call.
			$max_turns = (int) Settings::get( 'max_history_turns' );
			if ( $max_turns > 0 ) {
				$this->history = Conversation_Trimmer::trim( $this->history, $max_turns );
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
			if ( ! WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $assistant_message ) ) {
				// No tool calls — we're done.
				$reply = '';

				try {
					$reply = $result->toText();
				} catch ( \RuntimeException $e ) {
					$reply = '';
				}

				return [
					'reply'       => $reply,
					'history'     => $this->serialize_history(),
					'tool_calls'  => $this->tool_call_log,
					'token_usage'     => $this->token_usage,
					'iterations_used' => $this->iterations_used,
					'model_id'        => $this->model_id,
				];
			}

			// Log tool calls and check for confirmation requirement.
			$this->log_tool_calls( $assistant_message );
			$confirm_needed = $this->get_tools_needing_confirmation( $assistant_message );

			if ( ! empty( $confirm_needed ) ) {
				return [
					'awaiting_confirmation' => true,
					'pending_tools'         => $confirm_needed,
					'history'               => $this->serialize_history(),
					'tool_call_log'         => $this->tool_call_log,
					'token_usage'           => $this->token_usage,
					'iterations_remaining'  => $iterations,
					'iterations_used'      => $this->iterations_used,
					'model_id'             => $this->model_id,
				];
			}

			// Execute the ability calls and get the function response message.
			$response_message = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $assistant_message );
			$this->history[]  = $response_message;
			$this->log_tool_responses( $response_message );
		}

		// Exhausted iterations — return what we have so callers can inspect the log.
		return new WP_Error(
			'ai_agent_max_iterations',
			sprintf(
				/* translators: %d: max iterations */
				__( 'Agent reached the maximum of %d iterations without completing.', 'ai-agent' ),
				$this->max_iterations
			),
			[
				'tool_calls'      => $this->tool_call_log,
				'token_usage'     => $this->token_usage,
				'iterations_used' => $this->iterations_used,
				'model_id'        => $this->model_id,
				'history'         => $this->serialize_history(),
			]
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
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|WP_Error
	 */
	private function send_prompt() {
		$provider_id = $this->provider_id ?: 'ai-provider-for-any-openai-compatible';

		if ( 'ai-provider-for-any-openai-compatible' === $provider_id ) {
			return $this->send_prompt_direct();
		}

		// If the requested provider isn't registered, fall back to direct
		// OpenAI-compatible endpoint if configured, otherwise return an error.
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $provider_id ) ) {
				$endpoint_url = get_option( 'openai_compat_endpoint_url', '' );
				if ( ! empty( $endpoint_url ) ) {
					return $this->send_prompt_direct();
				}
				return new WP_Error(
					'ai_agent_provider_unavailable',
					sprintf(
						/* translators: %s: provider ID */
						__( 'Provider "%s" is not available. Please select a different provider in the chat header.', 'ai-agent' ),
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
			$builder->using_temperature( (float) $this->temperature );
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
	 * Send a prompt directly to the OpenAI-compatible proxy endpoint.
	 *
	 * Converts the current conversation history to an OpenAI messages array,
	 * POSTs to the configured endpoint URL, and returns either a simple
	 * result object or a WP_Error.
	 *
	 * @return Simple_AI_Result|WP_Error
	 */
	private function send_prompt_direct() {
		$endpoint_url = rtrim( (string) get_option( 'openai_compat_endpoint_url', '' ), '/' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'ai_agent_no_endpoint', __( 'OpenAI-compatible endpoint URL is not configured.', 'ai-agent' ) );
		}

		// Resolve model for the OpenAI-compatible endpoint.
		// Priority: explicit selection → connector default → hardcoded fallback.
		$model_id = $this->model_id;
		if ( empty( $model_id ) && function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
			$model_id = \OpenAiCompatibleConnector\get_default_model();
		}
		if ( empty( $model_id ) ) {
			$model_id = 'claude-sonnet-4';
		}

		// Resolve abilities and convert to OpenAI tools format.
		$tools          = [];
		$abilities      = $this->resolve_abilities();

		/**
		 * Cap the number of tools sent to the model to avoid overwhelming
		 * smaller models with hundreds of function definitions.
		 *
		 * @param int   $max_tools Maximum number of tools to include.
		 * @param array $abilities The full list of resolved abilities.
		 */
		$max_tools = (int) apply_filters( 'ai_agent_max_tools', 64, $abilities );
		if ( $max_tools > 0 && count( $abilities ) > $max_tools ) {
			$abilities = array_slice( $abilities, 0, $max_tools );
		}

		foreach ( $abilities as $ability ) {
			$fn_name      = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability->get_name() );
			$input_schema = $ability->get_input_schema();
			$description  = $ability->get_description();

			// Truncate long descriptions (e.g. WP-CLI help text) to save tokens.
			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$tool = [
				'type'     => 'function',
				'function' => [
					'name'        => $fn_name,
					'description' => $description,
				],
			];

			if ( ! empty( $input_schema ) ) {
				// Ensure 'properties' is an object, not an empty array.
				// PHP's json_encode([]) produces "[]" but OpenAI requires "{}".
				if ( isset( $input_schema['properties'] ) && $input_schema['properties'] === [] ) {
					$input_schema['properties'] = new \stdClass();
				}
				$tool['function']['parameters'] = $input_schema;
			} else {
				$tool['function']['parameters'] = [
					'type'       => 'object',
					'properties' => new \stdClass(),
				];
			}

			$tools[] = $tool;
		}

		// Build OpenAI-format messages array from history.
		$messages = [];

		if ( ! empty( $this->system_instruction ) ) {
			$messages[] = [ 'role' => 'system', 'content' => $this->system_instruction ];
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
				// Normalize to OpenAI roles.
				$role = ( 'model' === $role || 'assistant' === $role ) ? 'assistant' : 'user';
			} catch ( \Throwable $e ) {
				$role = 'user';
			}

			try {
				$parts          = $msg->getParts();
				$texts          = [];
				$msg_tool_calls = [];
				$fn_responses   = [];

				foreach ( $parts as $part ) {
					// Text parts.
					if ( method_exists( $part, 'getText' ) ) {
						$t = $part->getText();
						if ( is_string( $t ) && '' !== $t ) {
							$texts[] = $t;
						}
					}

					// FunctionCall parts (assistant requesting tool use).
					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionCall() ) {
						$fc = $part->getFunctionCall();
						if ( $fc ) {
							$msg_tool_calls[] = [
								'id'       => $fc->getId() ?: ( 'call_' . wp_generate_uuid4() ),
								'type'     => 'function',
								'function' => [
									'name'      => $fc->getName(),
									'arguments' => wp_json_encode( $fc->getArgs() ?: new \stdClass() ),
								],
							];
						}
					}

					// FunctionResponse parts (tool results).
					if ( method_exists( $part, 'getType' ) && $part->getType()->isFunctionResponse() ) {
						$fr = $part->getFunctionResponse();
						if ( $fr ) {
							$fn_responses[] = [
								'tool_call_id' => $fr->getId() ?: '',
								'role'         => 'tool',
								'content'      => wp_json_encode( $fr->getResponse() ),
							];
						}
					}
				}

				// Build the message(s) for OpenAI format.
				if ( ! empty( $msg_tool_calls ) ) {
					// Assistant message with tool calls.
					$assistant_msg = [
						'role'       => 'assistant',
						'tool_calls' => $msg_tool_calls,
					];
					$text_content = implode( '', $texts );
					if ( '' !== $text_content ) {
						$assistant_msg['content'] = $text_content;
					} else {
						$assistant_msg['content'] = null;
					}
					$messages[] = $assistant_msg;

					// Followed by tool response messages.
					foreach ( $fn_responses as $fr_msg ) {
						$messages[] = $fr_msg;
					}
				} elseif ( ! empty( $fn_responses ) ) {
					// Standalone tool responses (shouldn't normally happen).
					foreach ( $fn_responses as $fr_msg ) {
						$messages[] = $fr_msg;
					}
				} else {
					// Regular text message.
					$content = implode( '', $texts );
					if ( '' !== $content ) {
						$messages[] = [ 'role' => $role, 'content' => $content ];
					}
				}
			} catch ( \Throwable $e ) {
				// Skip malformed messages.
				continue;
			}
		}

		$api_key = (string) get_option( 'openai_compat_api_key', 'no-key' );
		$timeout = (int) get_option( 'openai_compat_timeout', 600 );

		$request_body = [
			'model'       => $model_id,
			'messages'    => $messages,
			'temperature' => (float) $this->temperature,
			'max_tokens'  => (int) $this->max_output_tokens,
			'stream'      => false,
		];

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$response = wp_remote_post(
			$endpoint_url . '/chat/completions',
			[
				'timeout'    => $timeout,
				'headers'    => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . ( $api_key ?: 'no-key' ),
				],
				'body'       => wp_json_encode( $request_body ),
				'sslverify'  => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from proxy";
			return new WP_Error( 'ai_agent_proxy_error', $msg );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		return new Simple_AI_Result( $text, $data );
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
		$credentials = get_option( 'wp_ai_client_provider_credentials', [] );

		if ( is_array( $credentials ) && ! empty( $credentials ) ) {
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
			$api_key = get_option( 'openai_compat_api_key', '' );

			if ( empty( $api_key ) ) {
				$api_key = 'no-key';
			}

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
	 * @return array Array of tool details needing confirmation (empty if none).
	 */
	private function get_tools_needing_confirmation( Message $message ): array {
		if ( empty( $this->tool_permissions ) ) {
			return [];
		}

		$confirm = [];

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$fn_name = $call->getName();

				// The function call name uses the wpab__ format (e.g. wpab__ai-agent__memory-save)
				// while tool_permissions uses ability name format (e.g. ai-agent/memory-save).
				// Convert function name to ability name for the lookup.
				$ability_name = $fn_name;
				if ( str_starts_with( $fn_name, 'wpab__' ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
					$ability_name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $fn_name );
				}

				$permission = $this->tool_permissions[ $ability_name ] ?? 'auto';

				if ( 'confirm' === $permission ) {
					$confirm[] = [
						'id'   => $call->getId(),
						'name' => $fn_name,
						'args' => $call->getArgs(),
					];
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
			return [];
		}

		$all = wp_get_abilities();

		// Use tool_permissions if set, otherwise fall back to disabled_abilities.
		if ( ! empty( $this->tool_permissions ) ) {
			$perms = $this->tool_permissions;
			$all   = array_filter( $all, function ( $ability ) use ( $perms ) {
				$perm = $perms[ $ability->get_name() ] ?? 'auto';
				return 'disabled' !== $perm;
			} );
		} else {
			$disabled = Settings::get( 'disabled_abilities' );
			if ( ! empty( $disabled ) && is_array( $disabled ) ) {
				$all = array_filter( $all, function ( $ability ) use ( $disabled ) {
					return ! in_array( $ability->get_name(), $disabled, true );
				} );
			}
		}

		if ( ! empty( $this->abilities ) ) {
			$resolved = [];
			foreach ( $this->abilities as $name ) {
				if ( isset( $all[ $name ] ) ) {
					$resolved[] = $all[ $name ];
				}
			}
			return $resolved;
		}

		// Apply tool profile filter.
		$active_profile = Settings::get( 'active_tool_profile' );
		if ( ! empty( $active_profile ) && 'all' !== $active_profile ) {
			$all = Tool_Profiles::filter_abilities( $all, $active_profile );
		}

		// Discovery mode: only load priority-category and priority-named tools.
		if ( Tool_Discovery::should_use_discovery_mode() ) {
			$priority_cats  = Tool_Discovery::get_priority_categories();
			$priority_tools = Tool_Discovery::get_priority_tools();
			$priority       = array_filter( $all, function ( $ability ) use ( $priority_cats, $priority_tools ) {
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
			} );
			return array_values( $priority );
		}

		return array_values( $all );
	}

	/**
	 * Log tool calls from an assistant message for transparency.
	 */
	private function log_tool_calls( Message $message ): void {
		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$this->tool_call_log[] = [
					'type' => 'call',
					'id'   => $call->getId(),
					'name' => $call->getName(),
					'args' => $call->getArgs(),
				];
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
				$this->tool_call_log[] = [
					'type'     => 'response',
					'id'       => $response->getId(),
					'name'     => $response->getName(),
					'response' => $response->getResponse(),
				];
			}
		}
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
	 * @param array $data Serialized history arrays.
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
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function build_system_instruction( array $settings ): string {
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
				. "- Use **ai-agent/memory-save** to remember important information the user tells you (preferences, site details, workflows).\n"
				. "- Use **ai-agent/memory-list** to recall what you've previously stored.\n"
				. "- Use **ai-agent/memory-delete** to remove outdated memories.\n"
				. "- Use **ai-agent/knowledge-search** to search the knowledge base for relevant documents and information.\n"
				. "Save memories when the user shares reusable facts, preferences, or context that would be valuable in future conversations.";
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
					. "Cite the source when using specific facts from the knowledge base.";
			}
		}

		// Inject structured context from providers.
		$context_data = Context_Providers::gather( $this->page_context );
		if ( ! empty( $context_data ) ) {
			$formatted_context = Context_Providers::format_for_prompt( $context_data );
			if ( ! empty( $formatted_context ) ) {
				$base .= "\n\n" . $formatted_context;
			}
		}

		// Append tool discovery instructions when discovery mode is active.
		if ( Tool_Discovery::should_use_discovery_mode() ) {
			$discovery_section = Tool_Discovery::get_system_prompt_section();
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
			. "- Site URL: {$site_url}\n"
			. "- WP-CLI is available at: wp\n\n"
			. "## Core Principles\n"
			. "1. **Act, don't ask.** Execute the task right away. Don't ask \"shall I proceed?\" or request confirmation unless the task is destructive (deleting data, dropping tables).\n"
			. "2. **Generate real content.** When creating pages or posts, write substantial, realistic content (3+ paragraphs). Never use placeholder text like \"Lorem ipsum\" or \"Content goes here\".\n"
			. "3. **Use tools directly.** Your loaded tools cover site management, content, media, and more. Call them immediately — don't describe what you would do.\n\n"
			. "## Common Workflows\n"
			. "- **Create a subsite:** Use `site/create` (or `wpcli/site/create`), then target it with `--url=<subsite_url>` in subsequent WP-CLI commands.\n"
			. "- **Target a subsite:** Pass `url` (the site URL) on any WP-CLI command to target a specific subsite. The URL carries over — once you pass `url` on any command, subsequent commands will automatically target the same site without needing to pass it again.\n"
			. "- **Create pages with content:** Use `post/create` with `--post_type=page --post_status=publish --post_content='<html content>'`.\n"
			. "- **Import images:** Use `ai-agent/import-stock-image` with a keyword and optional site_url. Returns attachment_id and url.\n"
			. "- **Set a homepage:** Use `option/update` to set `show_on_front=page` and `page_on_front=<page_id>` with `--url=<site>`.\n"
			. "- **Get IDs from create commands:** Add `--porcelain` (as boolean true, not a string) to WP-CLI commands to return just the ID.\n\n"
			. "## Tips\n"
			. "- Target a subsite: pass `url` to any WP-CLI tool. It persists for subsequent calls.\n"
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
				$usage = $result->getUsage();
				if ( $usage ) {
					if ( method_exists( $usage, 'getPromptTokens' ) ) {
						$this->token_usage['prompt'] += (int) $usage->getPromptTokens();
					}
					if ( method_exists( $usage, 'getCompletionTokens' ) ) {
						$this->token_usage['completion'] += (int) $usage->getCompletionTokens();
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Token tracking is best-effort.
		}
	}
}
