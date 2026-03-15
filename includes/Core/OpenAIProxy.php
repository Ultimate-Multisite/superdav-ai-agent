<?php

declare(strict_types=1);
/**
 * OpenAI-compatible proxy client.
 *
 * Handles direct API calls to an OpenAI-compatible endpoint, bypassing the
 * WordPress AI SDK. This is necessary to avoid a fatal autoloader conflict
 * where the plugin-check plugin's broken copy of HttpTransporterFactory wins
 * the PHP class-loading race, causing every SDK model request to throw
 * "HttpTransporterInterface instance not set".
 *
 * Responsibilities:
 * - Request formatting: abilities → OpenAI tools, history → messages array
 * - Direct HTTP call via wp_remote_post()
 * - Response parsing into SimpleAiResult
 * - Error handling returning WP_Error on failure
 *
 * @package GratisAiAgent\Core
 */

namespace GratisAiAgent\Core;

use WP_AI_Client_Ability_Function_Resolver;
use WP_Error;
use WordPress\AiClient\Messages\DTO\Message;

class OpenAIProxy {

	/** @var string The OpenAI-compatible endpoint base URL (no trailing slash). */
	private string $endpoint_url;

	/** @var string The API key for Authorization header. */
	private string $api_key;

	/** @var int HTTP request timeout in seconds. */
	private int $timeout;

	/** @var string The model ID to use. */
	private string $model_id;

	/** @var float Sampling temperature. */
	private float $temperature;

	/** @var int Maximum output tokens. */
	private int $max_output_tokens;

	/** @var string System instruction prepended as a system message. */
	private string $system_instruction;

	/** @var Message[] Conversation history. */
	private array $history;

	/** @var array<int, mixed> Resolved ability objects. */
	private array $abilities;

	/**
	 * @param string            $endpoint_url      Base URL of the OpenAI-compatible endpoint.
	 * @param string            $api_key           API key for Authorization header.
	 * @param int               $timeout           HTTP timeout in seconds.
	 * @param string            $model_id          Model identifier.
	 * @param float             $temperature       Sampling temperature.
	 * @param int               $max_output_tokens Maximum tokens in the response.
	 * @param string            $system_instruction System prompt text.
	 * @param Message[]         $history           Conversation history.
	 * @param array<int, mixed> $abilities         Resolved ability objects.
	 */
	public function __construct(
		string $endpoint_url,
		string $api_key,
		int $timeout,
		string $model_id,
		float $temperature,
		int $max_output_tokens,
		string $system_instruction,
		array $history,
		array $abilities
	) {
		$this->endpoint_url       = rtrim( $endpoint_url, '/' );
		$this->api_key            = $api_key;
		$this->timeout            = $timeout;
		$this->model_id           = $model_id;
		$this->temperature        = $temperature;
		$this->max_output_tokens  = $max_output_tokens;
		$this->system_instruction = $system_instruction;
		$this->history            = $history;
		$this->abilities          = $abilities;
	}

	/**
	 * Send the prompt to the OpenAI-compatible endpoint and return the result.
	 *
	 * @return SimpleAiResult|WP_Error
	 */
	public function send() {
		if ( empty( $this->endpoint_url ) ) {
			return new WP_Error(
				'ai_agent_no_endpoint',
				__( 'OpenAI-compatible endpoint URL is not configured.', 'gratis-ai-agent' )
			);
		}

		$tools        = $this->build_tools();
		$messages     = $this->build_messages();
		$encoded_body = $this->build_request_body( $messages, $tools );

		$response = wp_remote_post(
			$this->endpoint_url . '/chat/completions',
			[
				'timeout'   => $this->timeout,
				'headers'   => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . ( $this->api_key ?: 'no-key' ),
				],
				'body'      => $encoded_body,
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Convert resolved abilities to the OpenAI tools array format.
	 *
	 * Applies the ai_agent_max_tools filter to cap the number of tools sent,
	 * sanitizes schemas for OpenAI compatibility, and removes non-standard fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_tools(): array {
		$abilities = $this->abilities;

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

		$tools = [];

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
				// Also ensure 'required' is not an empty array (some providers reject it).
				if ( isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) && empty( $input_schema['required'] ) ) {
					unset( $input_schema['required'] );
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

		if ( ! empty( $tools ) ) {
			// Sanitize tool schemas: ensure 'properties' fields are JSON objects, not arrays.
			$tools = array_map( [ $this, 'sanitize_tool_schema' ], $tools );

			// Remove non-standard fields that some providers reject.
			foreach ( $tools as &$tool_ref ) {
				$params = &$tool_ref['function']['parameters'];
				// Remove 'default' at the parameters level (non-standard).
				unset( $params['default'] );
				// Remove 'required' booleans inside individual properties (should be array at schema level).
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
		}

		return $tools;
	}

	/**
	 * Build the OpenAI-format messages array from the system instruction and history.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_messages(): array {
		$messages = [];

		if ( ! empty( $this->system_instruction ) ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $this->system_instruction,
			];
		}

		foreach ( $this->history as $msg ) {
			/** @var Message $msg */
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
					$text_content  = implode( '', $texts );
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
						$messages[] = [
							'role'    => $role,
							'content' => $content,
						];
					}
				}
			} catch ( \Throwable $e ) {
				// Skip malformed messages.
				continue;
			}
		}

		return $messages;
	}

	/**
	 * Encode the request body as JSON, applying a final safety-net fix for
	 * empty properties arrays that should be objects.
	 *
	 * @param array<int, array<string, mixed>> $messages The messages array.
	 * @param array<int, array<string, mixed>> $tools    The tools array.
	 * @return string JSON-encoded request body.
	 */
	private function build_request_body( array $messages, array $tools ): string {
		$request_body = [
			'model'       => $this->model_id,
			'messages'    => $messages,
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_output_tokens,
			'stream'      => false,
		];

		if ( ! empty( $tools ) ) {
			$request_body['tools'] = $tools;
		}

		$encoded = (string) wp_json_encode( $request_body );

		// Final safety net: replace any remaining "properties":[] with "properties":{}
		// in the JSON string. This catches edge cases where PHP's type juggling
		// converts stdClass back to an empty array during serialization.
		return str_replace( '"properties":[]', '"properties":{}', $encoded );
	}

	/**
	 * Parse the HTTP response into a SimpleAiResult or WP_Error.
	 *
	 * @param array<string, mixed>|\WP_HTTP_Requests_Response $response The wp_remote_post() response.
	 * @return SimpleAiResult|WP_Error
	 */
	private function parse_response( $response ) {
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code from proxy";
			return new WP_Error( 'ai_agent_proxy_error', $msg );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		return new SimpleAiResult( (string) $text, (array) $data );
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
}
