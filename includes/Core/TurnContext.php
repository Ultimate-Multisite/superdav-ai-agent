<?php

declare(strict_types=1);
/**
 * Provider-only per-turn context.
 *
 * Fresh page, user, site, skill and knowledge context is appended to the
 * current user turn so the session instructions stay byte-stable for prompt
 * caching. That block is model input, not something the user typed: it must
 * never be saved in the conversation transcript, shown in the chat UI, or
 * exported, and it is dropped from completed turns so stale context is not
 * replayed on every later request.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TurnContext {

	/** Matches a trailing block produced by SystemInstructionBuilder::build_turn_context(). */
	private const TRAILING_BLOCK = '/\n\n<context>\n[\s\S]*\n<\/context>\z/';

	/**
	 * Append a context block to the user's message for a provider request.
	 *
	 * @param string $message User-authored message.
	 * @param string $context Context block, or an empty string.
	 */
	public static function append( string $message, string $context ): string {
		return '' === $context ? $message : $message . "\n\n" . $context;
	}

	/**
	 * Remove a trailing provider context block from message text.
	 *
	 * @param string $text Message text.
	 */
	public static function strip( string $text ): string {
		if ( ! str_contains( $text, '<context>' ) ) {
			return $text;
		}

		return (string) preg_replace( self::TRAILING_BLOCK, '', $text );
	}

	/**
	 * Remove provider context blocks from Responses user input items.
	 *
	 * @param list<array<string, mixed>> $input  Responses input items.
	 * @param int|null                   $before Only strip items before this index; null strips all.
	 * @return list<array<string, mixed>>
	 */
	public static function strip_input_items( array $input, ?int $before = null ): array {
		foreach ( $input as $index => &$item ) {
			if ( null !== $before && $index >= $before ) {
				break;
			}
			if ( 'user' === ( $item['role'] ?? '' ) && is_string( $item['content'] ?? null ) ) {
				$item['content'] = self::strip( $item['content'] );
			}
		}
		unset( $item );

		return $input;
	}
}
