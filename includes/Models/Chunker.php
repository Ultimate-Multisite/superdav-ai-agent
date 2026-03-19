<?php

declare(strict_types=1);
/**
 * Text chunker for knowledge base indexing.
 *
 * Recursively splits text into overlapping chunks suitable for
 * FULLTEXT search and future embedding generation.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Models;

class Chunker {

	/**
	 * Split text into overlapping chunks.
	 *
	 * Splits on paragraph breaks first, then sentences, then words.
	 * Token count is estimated as strlen / 4.
	 *
	 * @param string $text       The text to chunk.
	 * @param int    $max_tokens Maximum tokens per chunk (estimated).
	 * @param int    $overlap    Overlap tokens between adjacent chunks.
	 * @return list<array<string, mixed>> Array of chunk arrays: {text, index, char_start, char_end}.
	 */
	public static function chunk( string $text, int $max_tokens = 500, int $overlap = 50 ): array {
		$text = trim( $text );

		if ( empty( $text ) ) {
			return [];
		}

		$max_chars     = $max_tokens * 4;
		$overlap_chars = $overlap * 4;

		// If the text fits in one chunk, return it directly.
		if ( strlen( $text ) <= $max_chars ) {
			return [
				[
					'text'       => $text,
					'index'      => 0,
					'char_start' => 0,
					'char_end'   => strlen( $text ),
				],
			];
		}

		// Split into paragraphs first.
		$paragraphs = preg_split( '/\n\s*\n/', $text );
		$segments   = [];

		foreach ( $paragraphs ?: [] as $para ) {
			$para = trim( $para );
			if ( empty( $para ) ) {
				continue;
			}

			// If a paragraph is too large, split it into sentences.
			if ( strlen( $para ) > $max_chars ) {
				$sentences = self::split_sentences( $para );
				foreach ( $sentences as $sentence ) {
					$sentence = trim( $sentence );
					if ( empty( $sentence ) ) {
						continue;
					}

					// If a sentence is still too large, split on words.
					if ( strlen( $sentence ) > $max_chars ) {
						$words       = explode( ' ', $sentence );
						$word_buffer = '';
						foreach ( $words as $word ) {
							if ( strlen( $word_buffer ) + strlen( $word ) + 1 > $max_chars && ! empty( $word_buffer ) ) {
								$segments[]  = trim( $word_buffer );
								$word_buffer = '';
							}
							$word_buffer .= ( empty( $word_buffer ) ? '' : ' ' ) . $word;
						}
						if ( ! empty( $word_buffer ) ) {
							$segments[] = trim( $word_buffer );
						}
					} else {
						$segments[] = $sentence;
					}
				}
			} else {
				$segments[] = $para;
			}
		}

		// Now combine segments into chunks respecting max_chars with overlap.
		$chunks       = [];
		$current_text = '';
		$chunk_index  = 0;

		foreach ( $segments as $segment ) {
			$candidate = empty( $current_text ) ? $segment : $current_text . "\n\n" . $segment;

			if ( strlen( $candidate ) > $max_chars && ! empty( $current_text ) ) {
				// Finalize the current chunk.
				$char_start = self::find_char_start( $text, $current_text );
				$chunks[]   = [
					'text'       => $current_text,
					'index'      => $chunk_index++,
					'char_start' => $char_start,
					'char_end'   => $char_start + strlen( $current_text ),
				];

				// Start next chunk with overlap from the end of the current one.
				$current_text = self::get_overlap_text( $current_text, $overlap_chars ) . "\n\n" . $segment;
			} else {
				$current_text = $candidate;
			}
		}

		// Don't forget the last chunk.
		if ( ! empty( trim( $current_text ) ) ) {
			$char_start = self::find_char_start( $text, $current_text );
			$chunks[]   = [
				'text'       => trim( $current_text ),
				'index'      => $chunk_index,
				'char_start' => $char_start,
				'char_end'   => $char_start + strlen( trim( $current_text ) ),
			];
		}

		return $chunks;
	}

	/**
	 * Split text into sentences.
	 *
	 * @param string $text Input text.
	 * @return list<string> Array of sentence strings.
	 */
	private static function split_sentences( string $text ): array {
		// Split on sentence-ending punctuation followed by whitespace.
		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		return $sentences ?: [ $text ];
	}

	/**
	 * Get the trailing overlap text from a chunk.
	 *
	 * @param string $text          The chunk text.
	 * @param int    $overlap_chars Number of characters of overlap.
	 * @return string The overlap portion.
	 */
	private static function get_overlap_text( string $text, int $overlap_chars ): string {
		if ( strlen( $text ) <= $overlap_chars ) {
			return $text;
		}

		$tail = substr( $text, -$overlap_chars );

		// Try to start at a word boundary.
		$space_pos = strpos( $tail, ' ' );
		if ( false !== $space_pos && $space_pos < strlen( $tail ) / 2 ) {
			$tail = substr( $tail, $space_pos + 1 );
		}

		return $tail;
	}

	/**
	 * Find the approximate start position of a chunk in the original text.
	 *
	 * @param string $full_text  The original full text.
	 * @param string $chunk_text The chunk text to locate.
	 * @return int Character offset.
	 */
	private static function find_char_start( string $full_text, string $chunk_text ): int {
		// Use the first 100 chars of the chunk to find position.
		$needle = substr( trim( $chunk_text ), 0, 100 );
		$pos    = strpos( $full_text, $needle );
		return false !== $pos ? $pos : 0;
	}
}
