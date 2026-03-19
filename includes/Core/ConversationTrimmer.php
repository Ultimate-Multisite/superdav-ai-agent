<?php

declare(strict_types=1);
/**
 * Smart Conversation Trimmer.
 *
 * Trims conversation history at safe boundaries to prevent context overflow.
 * Never cuts mid-tool-cycle (assistant tool call + tool response are kept together).
 * Always trims before a user message boundary.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use WordPress\AiClient\Messages\DTO\Message;

class ConversationTrimmer {

	/**
	 * Default max history turns (a turn = one user message + one assistant response).
	 */
	const DEFAULT_MAX_TURNS = 20;

	/**
	 * Trim conversation history if it exceeds the configured max turns.
	 *
	 * A "turn" is counted as a user message followed by any number of assistant
	 * messages, tool calls, and tool responses until the next user message.
	 *
	 * The first user message is always preserved (it may contain crucial context).
	 * When trimming, we keep a summary placeholder to indicate content was removed.
	 *
	 * @param Message[] $history   The full conversation history.
	 * @param int       $max_turns Maximum turns to keep. 0 = no trimming.
	 * @return Message[]
	 */
	public static function trim( array $history, int $max_turns = 0 ): array {
		if ( $max_turns <= 0 ) {
			$max_turns = (int) Settings::get( 'max_history_turns' );
		}

		if ( $max_turns <= 0 ) {
			return $history;
		}

		// Find turn boundaries (indices where user messages start).
		$turn_starts = self::find_turn_boundaries( $history );

		// If within limits, no trimming needed.
		if ( count( $turn_starts ) <= $max_turns ) {
			return $history;
		}

		// How many turns to remove from the front (keep last $max_turns).
		// Always keep the first turn (index 0) for context.
		$total_turns = count( $turn_starts );
		$keep_from   = $total_turns - $max_turns;

		// Clamp — always keep at least the first turn.
		if ( $keep_from <= 1 ) {
			return $history;
		}

		// Get the index in $history where we start keeping.
		$cut_at = $turn_starts[ $keep_from ];

		// Build trimmed history:
		// 1. Keep the first turn (messages from index 0 to turn_starts[1]-1).
		// 2. Insert a trimming marker.
		// 3. Keep everything from $cut_at onwards.
		$first_turn_end = isset( $turn_starts[1] ) ? $turn_starts[1] : count( $history );
		$first_turn     = array_slice( $history, 0, $first_turn_end );
		$kept_history   = array_slice( $history, $cut_at );

		// Create a summary marker message.
		$removed_turns = $keep_from - 1; // Minus the first turn we're keeping.
		$marker        = new \WordPress\AiClient\Messages\DTO\UserMessage(
			[
				new \WordPress\AiClient\Messages\DTO\MessagePart(
					sprintf(
						'[%d earlier conversation turns were trimmed to save context. The conversation continues below.]',
						$removed_turns
					)
				),
			]
		);

		return array_merge( $first_turn, [ $marker ], $kept_history );
	}

	/**
	 * Find indices in the history array where user messages start a new turn.
	 *
	 * @param Message[] $history Conversation history.
	 * @return int[] Array of indices.
	 */
	private static function find_turn_boundaries( array $history ): array {
		$boundaries = [];

		foreach ( $history as $i => $message ) {
			try {
				$role     = $message->getRole();
				$role_str = '';

				if ( method_exists( $role, 'value' ) ) {
					$role_str = $role->value;
				} elseif ( method_exists( $role, 'getValue' ) ) {
					$role_str = $role->getValue();
				} else {
					$role_str = (string) $role;
				}

				if ( 'user' === $role_str ) {
					$boundaries[] = $i;
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return $boundaries;
	}

	/**
	 * Estimate the token count of a message (rough heuristic).
	 *
	 * Uses a simple word-based approximation (1 token ~= 0.75 words).
	 * For more accurate counts, the actual tokenizer would be needed.
	 *
	 * @param Message $message A conversation message.
	 * @return int Estimated token count.
	 */
	public static function estimate_tokens( Message $message ): int {
		$text = '';

		try {
			foreach ( $message->getParts() as $part ) {
				if ( method_exists( $part, 'getText' ) ) {
					$text .= $part->getText() . ' ';
				}
				if ( method_exists( $part, 'getFunctionCall' ) ) {
					$fc = $part->getFunctionCall();
					if ( $fc ) {
						$text .= wp_json_encode( $fc->getArgs() ) . ' ';
					}
				}
				if ( method_exists( $part, 'getFunctionResponse' ) ) {
					$fr = $part->getFunctionResponse();
					if ( $fr ) {
						$text .= wp_json_encode( $fr->getResponse() ) . ' ';
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Best effort.
		}

		// Rough estimate: 1 token ~= 4 characters.
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Estimate total tokens in a history array.
	 *
	 * @param Message[] $history Conversation history.
	 * @return int
	 */
	public static function estimate_total_tokens( array $history ): int {
		$total = 0;
		foreach ( $history as $message ) {
			$total += self::estimate_tokens( $message );
		}
		return $total;
	}
}
