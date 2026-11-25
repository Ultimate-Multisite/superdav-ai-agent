<?php

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\AiClient\Superdav;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI-compatible text generation model for the Superdav AI service.
 */
final class SuperdavAiTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/** Response metadata key consumed by AgentLoop. */
	public const CONTEXT_ADMISSION_DATA_KEY = 'superdav_context_admission';

	/** @var array{context_estimate_tokens:int,context_limit_tokens:int,output_reserve_tokens:int}|null */
	private static ?array $last_context_limit_error = null;

	/** @var string Hash identifying a conversation for provider prompt-cache reuse. */
	private string $prompt_cache_key = '';

	/** Set the provider cache key from the server-owned session identity. */
	public function set_prompt_cache_key( string $prompt_cache_key ): void {
		$this->prompt_cache_key = substr( $prompt_cache_key, 0, 64 );
	}

	/**
	 * Prepare OpenAI-compatible chat messages for the Superdav managed edge.
	 *
	 * The upstream OpenAI-compatible SDK adapter parses response
	 * `reasoning_content` into thought-channel message parts, but it deliberately
	 * omits thought parts from later requests because OpenAI's public Chat
	 * Completions API has no matching input field. Superdav's managed endpoint
	 * does accept the DeepSeek-style `reasoning_content` sibling and needs that
	 * context round-tripped on later turns, so restore it after the parent adapter
	 * prepares the normal message content/tool-call shape.
	 *
	 * @param array       $messages           Conversation messages.
	 * @param string|null $system_instruction Optional system instruction.
	 * @return list<array<string, mixed>>
	 * @phpstan-param list<Message> $messages
	 */
	protected function prepareMessagesParam( array $messages, ?string $system_instruction = null ): array {
		$messages_param = parent::prepareMessagesParam( $messages, $system_instruction );
		$offset         = $system_instruction ? 1 : 0;

		foreach ( $messages as $index => $message ) {
			$reasoning_content = self::extract_reasoning_content( $message );
			if ( '' === $reasoning_content ) {
				continue;
			}

			$param_index = $index + $offset;
			if ( ! isset( $messages_param[ $param_index ] ) ) {
				continue;
			}

			if ( 'assistant' !== ( $messages_param[ $param_index ]['role'] ?? '' ) ) {
				continue;
			}

			$messages_param[ $param_index ]['reasoning_content'] = $reasoning_content;
		}

		return array_values( $messages_param );
	}

	/**
	 * The compatible SDK passes raw schemas; the managed API requires an envelope.
	 *
	 * @param array<string, mixed>|null $output_schema Requested output schema.
	 * @return array<string, mixed>
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( null === $output_schema ) {
			return parent::prepareResponseFormatParam( $output_schema );
		}
		return array(
			'type'        => 'json_schema',
			'json_schema' => SuperdavAiProvider::output_schema_envelope( $output_schema ),
		);
	}

	/**
	 * Create an authenticated API request.
	 *
	 * @param HttpMethodEnum                     $method  HTTP method.
	 * @param string                             $path    Endpoint path.
	 * @param array<string, string|list<string>> $headers Request headers.
	 * @param string|array<string, mixed>|null   $data    Request body/query data.
	 * @return Request
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), mixed $data = null ): Request {
		if ( 'chat/completions' === trim( $path, '/' ) && is_array( $data ) && '' !== $this->prompt_cache_key ) {
			$data['prompt_cache_key'] = $this->prompt_cache_key;
		}
		if ( 'chat/completions' === trim( $path, '/' ) ) {
			self::$last_context_limit_error = null;
		}

		if ( 'chat/completions' === trim( $path, '/' ) && is_array( $data ) && ! array_key_exists( 'reasoning_effort', $data ) ) {
			$model_id = isset( $data['model'] ) && is_string( $data['model'] ) ? $data['model'] : '';
			$effort   = SuperdavAiProvider::reasoning_effort_for_model( $model_id );
			if ( '' !== $effort ) {
				$data['reasoning_effort'] = $effort;
			}
		}

		$request_headers = 'chat/completions' === trim( $path, '/' )
			? SuperdavAiProvider::with_managed_chat_attribution( $headers )
			: SuperdavAiProvider::with_session_attribution( $headers );

		return new Request( $method, SuperdavAiProvider::url( $path ), $request_headers, $data, $this->getRequestOptions() );
	}

	/** Add gateway admission headers to the SDK result's provider-specific data. */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		$result          = parent::parseResponseToGenerativeAiResult( $response );
		$additional_data = $result->getAdditionalData();
		$context_data    = self::context_admission_data( $response );
		if ( ! empty( $context_data ) ) {
			$additional_data[ self::CONTEXT_ADMISSION_DATA_KEY ] = $context_data;
		}

		return new GenerativeAiResult(
			$result->getId(),
			$result->getCandidates(),
			$result->getTokenUsage(),
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/** Capture safe scalar admission details before the SDK throws for a 400 response. */
	protected function throwIfNotSuccessful( Response $response ): void {
		if ( ! $response->isSuccessful() ) {
			self::capture_context_limit_error( $response );
		}

		parent::throwIfNotSuccessful( $response );
	}

	/**
	 * Read valid non-negative integer admission headers from a provider response.
	 *
	 * @return array<string, int>
	 */
	public static function context_admission_data( Response $response ): array {
		$headers = array(
			'context_estimate_tokens'     => 'x-superdav-context-estimate-tokens',
			'context_limit_tokens'        => 'x-superdav-context-limit-tokens',
			'output_reserve_tokens'       => 'x-superdav-output-reserve-tokens',
			'input_token_bytes_per_token' => 'x-superdav-input-token-bytes-per-token',
		);
		$data    = array();
		foreach ( $headers as $key => $header ) {
			$value = self::non_negative_integer( $response->getHeaderAsString( $header ) );
			if ( null === $value ) {
				return array();
			}
			$data[ $key ] = $value;
		}

		return $data;
	}

	/** Clear stale failure state before a native Responses request. */
	public static function clear_context_limit_error(): void {
		self::$last_context_limit_error = null;
	}

	/** Capture a max-token rejection from either managed text endpoint. */
	public static function capture_context_limit_error( Response $response ): void {
		$data  = $response->getData();
		$error = is_array( $data['error'] ?? null ) ? $data['error'] : $data;
		if ( ! is_array( $error ) || 'max_tokens_exceeded' !== (string) ( $error['code'] ?? '' ) ) {
			return;
		}

		$estimate = self::non_negative_integer( $error['context_estimate_tokens'] ?? null );
		$limit    = self::non_negative_integer( $error['context_limit_tokens'] ?? null );
		$reserve  = self::non_negative_integer( $error['output_reserve_tokens'] ?? null );
		if ( null === $estimate || null === $limit || null === $reserve ) {
			return;
		}

		self::$last_context_limit_error = array(
			'context_estimate_tokens' => $estimate,
			'context_limit_tokens'    => $limit,
			'output_reserve_tokens'   => $reserve,
		);
	}

	/** Return and clear the most recent managed max-token rejection. */
	public static function consume_context_limit_error(): ?array {
		$error                          = self::$last_context_limit_error;
		self::$last_context_limit_error = null;

		return $error;
	}

	/** Parse an integer scalar and clamp negative values to zero. */
	private static function non_negative_integer( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}
		if ( ! is_string( $value ) || ! preg_match( '/^-?\d+$/', trim( $value ) ) ) {
			return null;
		}

		return max( 0, (int) trim( $value ) );
	}

	/**
	 * Extract thought-channel text that must be sent back as reasoning context.
	 *
	 * @param Message $message Message to inspect.
	 * @return string Newline-joined thought text, or an empty string when absent.
	 */
	private static function extract_reasoning_content( Message $message ): string {
		$reasoning_parts = array();

		foreach ( $message->getParts() as $part ) {
			if ( ! self::is_thought_text_part( $part ) ) {
				continue;
			}

			$text_value = $part->getText();
			if ( ! is_string( $text_value ) ) {
				continue;
			}

			$text = trim( $text_value );
			if ( '' !== $text ) {
				$reasoning_parts[] = $text;
			}
		}

		return implode( "\n", $reasoning_parts );
	}

	/**
	 * Determine whether a message part is hidden reasoning text.
	 *
	 * @param MessagePart $part Message part.
	 * @return bool
	 */
	private static function is_thought_text_part( MessagePart $part ): bool {
		$type = $part->getType();
		if ( ! $type->isText() ) {
			return false;
		}

		if ( ! method_exists( $part, 'getChannel' ) ) {
			return false;
		}

		$channel = $part->getChannel();
		if ( ! is_object( $channel ) || ! is_callable( array( $channel, 'isThought' ) ) ) {
			return false;
		}

		return (bool) $channel->isThought();
	}
}
