<?php

declare(strict_types=1);
/**
 * Completed-turn tool payload compaction.
 *
 * Tool results and tool-call arguments from earlier user turns are replayed on
 * every later provider request. A single theme or styles read can be 10-30 KB,
 * so a few completed turns can fill most of a model's context. The current turn
 * keeps full fidelity; completed turns keep a bounded excerpt that preserves the
 * leading identifiers and tells the model how to recover full data.
 *
 * This is a provider-view transform only. Durable session history, checkpoints
 * and the UI transcript keep the original payloads.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompletedTurnCompactor {

	/** Completed-turn payloads at or below this encoded size are replayed unchanged. */
	public const MAX_PAYLOAD_BYTES = 2048;

	/** Leading bytes retained from a compacted completed-turn tool result. */
	private const RESULT_EXCERPT_BYTES = 1024;

	/** Longest scalar string retained in a compacted completed-turn argument object. */
	private const ARGUMENT_STRING_BYTES = 160;

	/** Stable marker so already-compacted payloads are never compacted again. */
	public const MARKER = '[Earlier-turn tool result compacted';

	/**
	 * Compact tool payloads in the completed-turn prefix of SDK history.
	 *
	 * @param Message[] $history         Provider history.
	 * @param int       $completed_count Number of leading messages that belong to completed user turns.
	 * @return Message[] History with bounded completed-turn tool payloads.
	 */
	public static function compact_history( array $history, int $completed_count ): array {
		if ( $completed_count <= 0 ) {
			return $history;
		}

		$index = 0;
		foreach ( $history as $key => $message ) {
			if ( $index++ >= $completed_count ) {
				break;
			}
			if ( $message instanceof Message ) {
				$history[ $key ] = self::compact_message( $message );
			}
		}

		return $history;
	}

	/**
	 * Return tool call IDs that appear in the completed-turn prefix of SDK history.
	 *
	 * @param Message[] $history         Provider history.
	 * @param int       $completed_count Number of leading completed-turn messages.
	 * @return array<string, true>
	 */
	public static function completed_call_ids( array $history, int $completed_count ): array {
		$ids   = array();
		$index = 0;
		foreach ( $history as $message ) {
			if ( $index++ >= $completed_count ) {
				break;
			}
			if ( ! $message instanceof Message ) {
				continue;
			}
			foreach ( $message->getParts() as $part ) {
				$id = $part->getFunctionCall()?->getId() ?? $part->getFunctionResponse()?->getId();
				if ( is_string( $id ) && '' !== $id ) {
					$ids[ $id ] = true;
				}
			}
		}

		return $ids;
	}

	/**
	 * Compact a completed-turn tool result string.
	 *
	 * @param string $output Encoded tool result.
	 */
	public static function compact_result_text( string $output ): string {
		if ( strlen( $output ) <= self::MAX_PAYLOAD_BYTES || str_starts_with( $output, self::MARKER ) ) {
			return $output;
		}

		return sprintf(
			'%1$s from %2$d bytes. Leading excerpt follows; call the tool again if the complete current data is needed.] %3$s',
			self::MARKER,
			strlen( $output ),
			self::utf8_prefix( $output, self::RESULT_EXCERPT_BYTES )
		);
	}

	/**
	 * Compact completed-turn tool-call arguments while keeping a valid JSON object.
	 *
	 * Short top-level scalars (IDs, slugs, titles) are retained; long strings and
	 * nested structures are replaced by a size note.
	 *
	 * @param mixed $args Decoded tool-call arguments.
	 * @return mixed Original arguments when small, otherwise a compact object.
	 */
	public static function compact_arguments( mixed $args ): mixed {
		$encoded = wp_json_encode( $args );
		if ( ! is_array( $args ) || ! is_string( $encoded ) || strlen( $encoded ) <= self::MAX_PAYLOAD_BYTES ) {
			return $args;
		}

		$compact = array();
		foreach ( $args as $key => $value ) {
			$key = (string) $key;
			if ( is_string( $value ) && strlen( $value ) > self::ARGUMENT_STRING_BYTES ) {
				$compact[ $key ] = sprintf( '[%d characters omitted from an earlier turn]', strlen( $value ) );
			} elseif ( is_array( $value ) ) {
				$value_json      = (string) wp_json_encode( $value );
				$compact[ $key ] = strlen( $value_json ) > self::ARGUMENT_STRING_BYTES
					? sprintf( '[%d-byte value omitted from an earlier turn]', strlen( $value_json ) )
					: $value;
			} else {
				$compact[ $key ] = $value;
			}
		}

		return $compact;
	}

	/**
	 * Compact a JSON-encoded argument string, preserving it when it is not an object.
	 *
	 * @param string $arguments Encoded arguments.
	 */
	public static function compact_argument_text( string $arguments ): string {
		if ( strlen( $arguments ) <= self::MAX_PAYLOAD_BYTES ) {
			return $arguments;
		}
		$decoded = json_decode( $arguments, true );
		if ( ! is_array( $decoded ) ) {
			return $arguments;
		}
		$encoded = wp_json_encode( self::compact_arguments( $decoded ) );

		return is_string( $encoded ) ? $encoded : $arguments;
	}

	/** Compact the tool parts of one completed-turn message. */
	private static function compact_message( Message $message ): Message {
		$parts   = array();
		$changed = false;
		foreach ( $message->getParts() as $part ) {
			$replacement = self::compact_part( $part );
			$changed     = $changed || $replacement !== $part;
			$parts[]     = $replacement;
		}

		if ( ! $changed ) {
			return $message;
		}

		return $message->getRole()->isModel() ? new ModelMessage( $parts ) : new UserMessage( $parts );
	}

	/** Return a compacted copy of a tool part, or the original part. */
	private static function compact_part( MessagePart $part ): MessagePart {
		$call = $part->getFunctionCall();
		if ( $call instanceof FunctionCall ) {
			$args    = $call->getArgs();
			$compact = self::compact_arguments( $args );
			if ( $compact === $args ) {
				return $part;
			}
			// Matches ConversationSerializer: tool parts are rebuilt on the content channel.
			return new MessagePart( new FunctionCall( $call->getId(), $call->getName(), $compact ) );
		}

		$response = $part->getFunctionResponse();
		if ( $response instanceof FunctionResponse ) {
			$payload = $response->getResponse();
			$encoded = is_string( $payload ) ? $payload : wp_json_encode( $payload );
			if ( ! is_string( $encoded ) ) {
				return $part;
			}
			$compact = self::compact_result_text( $encoded );
			if ( $compact === $encoded ) {
				return $part;
			}
			return new MessagePart( new FunctionResponse( $response->getId(), $response->getName(), $compact ) );
		}

		return $part;
	}

	/** Return at most `$bytes` leading bytes without splitting a UTF-8 character. */
	private static function utf8_prefix( string $text, int $bytes ): string {
		if ( function_exists( 'mb_strcut' ) ) {
			return mb_strcut( $text, 0, $bytes, 'UTF-8' );
		}

		return substr( $text, 0, $bytes );
	}
}
