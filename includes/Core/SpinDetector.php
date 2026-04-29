<?php

declare(strict_types=1);
/**
 * Detects spin loops in the agent loop.
 *
 * Extracted from AgentLoop so the spin-detection concern — tracking
 * consecutive identical tool-call rounds — lives in one focused class.
 *
 * @package SdAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

use WordPress\AiClient\Messages\DTO\Message;

class SpinDetector {

	/** @var int Consecutive rounds with identical tool signatures. */
	private int $idle_rounds = 0;

	/** @var string Hash of the previous round's tool calls for spin detection. */
	private string $last_tool_signature = '';

	/**
	 * Record a round's tool calls and detect whether the loop is spinning.
	 *
	 * Returns true when the model has called the same tools with the same args
	 * for MAX_IDLE_ROUNDS consecutive rounds and should be stopped.
	 *
	 * @param Message $message    The assistant message for the current round.
	 * @param int     $max_rounds Threshold before declaring a spin (default 3).
	 * @return bool True if spinning, false otherwise.
	 */
	public function record( Message $message, int $max_rounds = 3 ): bool {
		$current_signature = $this->build_tool_signature( $message );

		if ( '' !== $current_signature && $current_signature === $this->last_tool_signature ) {
			++$this->idle_rounds;
			if ( $this->idle_rounds >= $max_rounds ) {
				return true;
			}
		} else {
			$this->idle_rounds = 0;
		}

		$this->last_tool_signature = $current_signature;
		return false;
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
	public function build_tool_signature( Message $message ): string {
		$parts = array();

		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$parts[] = (string) $call->getName() . ':' . wp_json_encode( $call->getArgs() ?: array() ); // phpcs:ignore
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		sort( $parts );
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Reset the spin detector state (e.g. at the start of a new run).
	 */
	public function reset(): void {
		$this->idle_rounds         = 0;
		$this->last_tool_signature = '';
	}

	/**
	 * Return the current idle-round count (for testing/inspection).
	 *
	 * @return int
	 */
	public function get_idle_rounds(): int {
		return $this->idle_rounds;
	}
}
