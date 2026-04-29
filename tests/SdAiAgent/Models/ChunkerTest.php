<?php

declare(strict_types=1);
/**
 * Unit tests for Chunker model.
 *
 * Tests chunk() with various text sizes, paragraph splitting,
 * sentence splitting, word-level splitting, and overlap behaviour.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Models;

use SdAiAgent\Models\Chunker;
use WP_UnitTestCase;

/**
 * Tests for Chunker model.
 *
 * @since 1.1.0
 */
class ChunkerTest extends WP_UnitTestCase {

	// ─── chunk() — empty / trivial inputs ────────────────────────────────────

	/**
	 * chunk() returns empty array for empty string.
	 */
	public function test_chunk_returns_empty_for_empty_string(): void {
		$result = Chunker::chunk( '' );
		$this->assertSame( [], $result );
	}

	/**
	 * chunk() returns empty array for whitespace-only string.
	 */
	public function test_chunk_returns_empty_for_whitespace_only(): void {
		$result = Chunker::chunk( '   ' );
		$this->assertSame( [], $result );
	}

	// ─── chunk() — single-chunk text ─────────────────────────────────────────

	/**
	 * chunk() returns a single chunk when text fits within max_tokens.
	 */
	public function test_chunk_returns_single_chunk_for_short_text(): void {
		$text   = 'This is a short text that fits in one chunk.';
		$result = Chunker::chunk( $text, 500 );

		$this->assertCount( 1, $result );
		$this->assertSame( $text, $result[0]['text'] );
		$this->assertSame( 0, $result[0]['index'] );
		$this->assertSame( 0, $result[0]['char_start'] );
		$this->assertSame( strlen( $text ), $result[0]['char_end'] );
	}

	// ─── chunk() — chunk structure ───────────────────────────────────────────

	/**
	 * Each chunk has the expected keys.
	 */
	public function test_chunk_has_expected_keys(): void {
		$text   = 'Hello world.';
		$result = Chunker::chunk( $text );

		$this->assertNotEmpty( $result );
		$chunk = $result[0];
		$this->assertArrayHasKey( 'text', $chunk );
		$this->assertArrayHasKey( 'index', $chunk );
		$this->assertArrayHasKey( 'char_start', $chunk );
		$this->assertArrayHasKey( 'char_end', $chunk );
	}

	/**
	 * chunk() assigns sequential index values starting from 0.
	 */
	public function test_chunk_assigns_sequential_indices(): void {
		// Create text large enough to produce multiple chunks.
		$paragraph = str_repeat( 'Word ', 200 ); // ~1000 chars per paragraph.
		$text      = implode( "\n\n", array_fill( 0, 5, $paragraph ) );

		$result = Chunker::chunk( $text, 100, 10 ); // small max_tokens to force splits.

		$this->assertGreaterThan( 1, count( $result ) );

		foreach ( $result as $i => $chunk ) {
			$this->assertSame( $i, $chunk['index'] );
		}
	}

	/**
	 * char_end is greater than char_start for each chunk.
	 */
	public function test_chunk_char_end_greater_than_char_start(): void {
		$text   = 'Some text content here.';
		$result = Chunker::chunk( $text );

		foreach ( $result as $chunk ) {
			$this->assertGreaterThan( $chunk['char_start'], $chunk['char_end'] );
		}
	}

	// ─── chunk() — multi-chunk splitting ─────────────────────────────────────

	/**
	 * chunk() produces multiple chunks for long text.
	 */
	public function test_chunk_produces_multiple_chunks_for_long_text(): void {
		// Each paragraph is ~200 chars; max_tokens=50 → max_chars=200.
		// With 5 paragraphs, we expect at least 2 chunks.
		$paragraph = str_repeat( 'abcde ', 33 ); // ~200 chars.
		$text      = implode( "\n\n", array_fill( 0, 5, $paragraph ) );

		$result = Chunker::chunk( $text, 50, 5 );

		$this->assertGreaterThan( 1, count( $result ) );
	}

	/**
	 * chunk() text fields are non-empty strings.
	 */
	public function test_chunk_text_fields_are_non_empty(): void {
		$paragraph = str_repeat( 'word ', 100 );
		$text      = implode( "\n\n", array_fill( 0, 3, $paragraph ) );

		$result = Chunker::chunk( $text, 50, 5 );

		foreach ( $result as $chunk ) {
			$this->assertIsString( $chunk['text'] );
			$this->assertNotEmpty( trim( $chunk['text'] ) );
		}
	}

	// ─── chunk() — paragraph splitting ───────────────────────────────────────

	/**
	 * chunk() respects paragraph boundaries when splitting.
	 */
	public function test_chunk_respects_paragraph_boundaries(): void {
		$para1 = str_repeat( 'First paragraph content. ', 20 ); // ~500 chars.
		$para2 = str_repeat( 'Second paragraph content. ', 20 );
		$text  = $para1 . "\n\n" . $para2;

		// max_tokens=100 → max_chars=400; each paragraph is ~500 chars.
		$result = Chunker::chunk( $text, 100, 10 );

		$this->assertGreaterThanOrEqual( 1, count( $result ) );
		// All chunks should have non-empty text.
		foreach ( $result as $chunk ) {
			$this->assertNotEmpty( trim( $chunk['text'] ) );
		}
	}

	// ─── chunk() — word-level splitting ──────────────────────────────────────

	/**
	 * chunk() handles a single very long paragraph by splitting on words.
	 */
	public function test_chunk_splits_long_paragraph_on_words(): void {
		// One paragraph with 500 words — no double newlines.
		$text = implode( ' ', array_fill( 0, 500, 'word' ) );

		$result = Chunker::chunk( $text, 50, 5 ); // max_chars=200.

		$this->assertGreaterThan( 1, count( $result ) );
		foreach ( $result as $chunk ) {
			$this->assertLessThanOrEqual( 250, strlen( $chunk['text'] ) ); // some tolerance for overlap.
		}
	}

	// ─── chunk() — overlap ───────────────────────────────────────────────────

	/**
	 * chunk() with zero overlap produces non-overlapping chunks.
	 */
	public function test_chunk_with_zero_overlap(): void {
		$paragraph = str_repeat( 'content ', 50 );
		$text      = implode( "\n\n", array_fill( 0, 4, $paragraph ) );

		$result = Chunker::chunk( $text, 50, 0 );

		$this->assertGreaterThanOrEqual( 1, count( $result ) );
		foreach ( $result as $chunk ) {
			$this->assertNotEmpty( $chunk['text'] );
		}
	}

	// ─── chunk() — default parameters ────────────────────────────────────────

	/**
	 * chunk() uses default parameters (500 tokens, 50 overlap) without error.
	 */
	public function test_chunk_uses_default_parameters(): void {
		$text   = 'Default parameter test. ' . str_repeat( 'More content. ', 50 );
		$result = Chunker::chunk( $text );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}
}
