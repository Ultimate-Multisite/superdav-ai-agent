<?php

declare(strict_types=1);
/**
 * Lightweight result wrapper for direct OpenAI-compatible proxy calls.
 *
 * The AgentLoop expects a result object with toMessage(), toText(), and
 * getUsage(). When we call the OpenAI-compat proxy directly via wp_remote_post()
 * instead of through the WordPress AI SDK (to avoid a fatal autoloader conflict),
 * we wrap the raw response in this class so the loop can handle it uniformly.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Tools\DTO\FunctionCall;

class SimpleAiResult {

	/** @var string */
	private string $text;

	/** @var array<string, mixed> */
	private array $raw;

	/** @var Message|null */
	private ?Message $message = null;

	/**
	 * @param string               $text The text content from the response.
	 * @param array<string, mixed> $raw  The raw API response data.
	 */
	public function __construct( string $text, array $raw = [] ) {
		$this->text = $text;
		$this->raw  = $raw;
	}

	/**
	 * Get the text content of the response.
	 *
	 * @return string The response text.
	 */
	public function toText(): string {
		return $this->text;
	}

	/**
	 * Convert the response to a Message object for the conversation history.
	 *
	 * Parses OpenAI-format tool_calls from the raw response and creates
	 * appropriate MessagePart objects for text and function calls.
	 *
	 * @return Message The message representation.
	 */
	public function toMessage(): Message {
		if ( null === $this->message ) {
			$parts = [];

			// Add text part if present.
			if ( '' !== $this->text ) {
				$parts[] = new MessagePart( $this->text );
			}

			// Parse OpenAI-format tool_calls from the raw response.
			// @phpstan-ignore-next-line
			$tool_calls = $this->raw['choices'][0]['message']['tool_calls'] ?? [];
			// @phpstan-ignore-next-line
			foreach ( $tool_calls as $tc ) {
				// @phpstan-ignore-next-line
				$fn_name = $tc['function']['name'] ?? '';
				// @phpstan-ignore-next-line
				$fn_id = $tc['id'] ?? $fn_name;
				// @phpstan-ignore-next-line
				$fn_args = $tc['function']['arguments'] ?? '{}';

				if ( is_string( $fn_args ) ) {
					$fn_args = json_decode( $fn_args, true ) ?: [];
				}

				$parts[] = new MessagePart(
					// @phpstan-ignore-next-line
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

	/**
	 * Get token usage information from the response.
	 *
	 * @return object|null Usage object with getPromptTokens() and getCompletionTokens() methods, or null.
	 */
	public function getUsage() {
		$usage = $this->raw['usage'] ?? null;
		if ( ! is_array( $usage ) ) {
			return null;
		}
		// @phpstan-ignore-next-line
		$prompt     = (int) ( $usage['prompt_tokens'] ?? 0 );
		$completion = (int) ( $usage['completion_tokens'] ?? 0 );
		return new class( $prompt, $completion ) {
			private int $prompt;
			private int $completion;

			/**
			 * @param int $prompt     Number of prompt tokens.
			 * @param int $completion Number of completion tokens.
			 */
			public function __construct( int $prompt, int $completion ) {
				$this->prompt     = $prompt;
				$this->completion = $completion;
			}

			/**
			 * Get the number of prompt (input) tokens used.
			 *
			 * @return int
			 */
			public function getPromptTokens(): int {
				return $this->prompt;
			}

			/**
			 * Get the number of completion (output) tokens used.
			 *
			 * @return int
			 */
			public function getCompletionTokens(): int {
				return $this->completion;
			}
		};
	}
}
