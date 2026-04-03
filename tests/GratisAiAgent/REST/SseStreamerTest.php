<?php

declare(strict_types=1);
/**
 * Unit tests for SseStreamer.
 *
 * SseStreamer produces HTTP output (headers + echo), which cannot be tested
 * in a standard PHPUnit environment without output buffering. These tests
 * focus on the observable side-effects that can be captured:
 *
 *   - start() is idempotent (calling it twice does not double-send headers).
 *   - send_token() emits a "token" event with the correct JSON payload.
 *   - send_tool_call() emits a "tool_call" event with name and args.
 *   - send_tool_result() emits a "tool_result" event with name and result.
 *   - send_confirmation_required() emits a "confirmation_required" event.
 *   - send_done() emits a "done" event with optional metadata.
 *   - send_error() emits an "error" event with code and message.
 *   - emit() auto-calls start() when not yet started.
 *   - All emitted payloads are valid JSON.
 *
 * Output is captured via ob_start() / ob_get_clean() around each call.
 * Headers cannot be asserted in CLI mode, but the output format is verified.
 *
 * @package GratisAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\REST;

use GratisAiAgent\REST\SseStreamer;
use WP_UnitTestCase;

/**
 * Unit tests for SseStreamer.
 *
 * @group sse
 * @group rest
 */
class SseStreamerTest extends WP_UnitTestCase {

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Capture output produced by a callable.
	 *
	 * Installs a temporary error handler that silences PHP warnings about
	 * headers already being sent (e.g. from SseStreamer::start() in CLI mode)
	 * so that assertions can run without the test suite failing on those
	 * warnings. All other errors are passed through to the previous handler.
	 *
	 * @param callable $fn The callable to execute.
	 * @return string Captured output.
	 */
	private function capture( callable $fn ): string {
		ob_start();
		set_error_handler(
			static function ( int $severity, string $message ): bool {
				return E_WARNING === $severity
					&& str_contains( $message, 'Cannot modify header information' );
			}
		);

		try {
			$fn();
		} finally {
			restore_error_handler();
		}

		return (string) ob_get_clean();
	}

	/**
	 * Parse SSE output into an array of [ event, data ] pairs.
	 *
	 * @param string $output Raw SSE output.
	 * @return array<int, array{event: string, data: mixed}> Parsed events.
	 */
	private function parse_sse( string $output ): array {
		$events = [];
		$blocks = preg_split( '/\n\n/', trim( $output ) );

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}

			$event = null;
			$data  = null;

			foreach ( explode( "\n", $block ) as $line ) {
				if ( str_starts_with( $line, 'event: ' ) ) {
					$event = substr( $line, 7 );
				} elseif ( str_starts_with( $line, 'data: ' ) ) {
					$data = json_decode( substr( $line, 6 ), true );
				}
			}

			if ( null !== $event ) {
				$events[] = [ 'event' => $event, 'data' => $data ];
			}
		}

		return $events;
	}

	// ─── start() ─────────────────────────────────────────────────────────────

	/**
	 * start() is idempotent — calling it twice does not produce duplicate output.
	 *
	 * In CLI mode headers are suppressed, so we verify the started flag
	 * indirectly by checking that a subsequent send_token() call produces
	 * exactly one event (not two).
	 */
	public function test_start_is_idempotent(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->start();
			$streamer->start(); // Second call should be a no-op.
			$streamer->send_token( 'hello' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events, 'Exactly one event should be emitted.' );
	}

	// ─── send_token() ────────────────────────────────────────────────────────

	/**
	 * send_token() emits an event named "token".
	 */
	public function test_send_token_emits_token_event(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'Hello world' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'token', $events[0]['event'] );
	}

	/**
	 * send_token() payload contains the token string.
	 */
	public function test_send_token_payload_contains_token(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'test-token-value' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertArrayHasKey( 'token', $events[0]['data'] );
		$this->assertSame( 'test-token-value', $events[0]['data']['token'] );
	}

	/**
	 * send_token() payload is valid JSON.
	 */
	public function test_send_token_payload_is_valid_json(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'json-check' );
		} );

		// Extract the data line.
		preg_match( '/^data: (.+)$/m', $output, $matches );
		$this->assertNotEmpty( $matches );
		$decoded = json_decode( $matches[1], true );
		$this->assertNotNull( $decoded, 'Payload must be valid JSON.' );
	}

	// ─── send_tool_call() ────────────────────────────────────────────────────

	/**
	 * send_tool_call() emits a "tool_call" event with name and args.
	 */
	public function test_send_tool_call_emits_correct_event(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_tool_call( 'memory_get', [ 'key' => 'site_name' ] );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'tool_call', $events[0]['event'] );
		$this->assertSame( 'memory_get', $events[0]['data']['name'] );
		$this->assertSame( [ 'key' => 'site_name' ], $events[0]['data']['args'] );
	}

	// ─── send_tool_result() ──────────────────────────────────────────────────

	/**
	 * send_tool_result() emits a "tool_result" event with name and result.
	 */
	public function test_send_tool_result_emits_correct_event(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_tool_result( 'memory_get', 'My Site' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'tool_result', $events[0]['event'] );
		$this->assertSame( 'memory_get', $events[0]['data']['name'] );
		$this->assertSame( 'My Site', $events[0]['data']['result'] );
	}

	/**
	 * send_tool_result() handles array results.
	 */
	public function test_send_tool_result_handles_array_result(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_tool_result( 'list_posts', [ 'posts' => [ 'Post 1', 'Post 2' ] ] );
		} );

		$events = $this->parse_sse( $output );
		$this->assertSame( 'tool_result', $events[0]['event'] );
		$this->assertIsArray( $events[0]['data']['result'] );
	}

	// ─── send_confirmation_required() ────────────────────────────────────────

	/**
	 * send_confirmation_required() emits a "confirmation_required" event.
	 */
	public function test_send_confirmation_required_emits_correct_event(): void {
		$streamer = new SseStreamer();

		$pending = [
			[ 'name' => 'delete_post', 'args' => [ 'id' => 42 ] ],
		];

		$output = $this->capture( static function () use ( $streamer, $pending ): void {
			$streamer->send_confirmation_required( 'job-abc-123', $pending );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'confirmation_required', $events[0]['event'] );
		$this->assertSame( 'job-abc-123', $events[0]['data']['job_id'] );
		$this->assertSame( $pending, $events[0]['data']['pending_tools'] );
	}

	// ─── send_done() ─────────────────────────────────────────────────────────

	/**
	 * send_done() emits a "done" event.
	 */
	public function test_send_done_emits_done_event(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_done();
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'done', $events[0]['event'] );
	}

	/**
	 * send_done() includes optional metadata in the payload.
	 */
	public function test_send_done_includes_metadata(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_done( [ 'session_id' => 42, 'token_usage' => [ 'prompt' => 10 ] ] );
		} );

		$events = $this->parse_sse( $output );
		$this->assertSame( 42, $events[0]['data']['session_id'] );
		$this->assertArrayHasKey( 'token_usage', $events[0]['data'] );
	}

	/**
	 * send_done() with empty metadata emits an empty JSON object.
	 */
	public function test_send_done_with_empty_metadata(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_done( [] );
		} );

		$events = $this->parse_sse( $output );
		$this->assertSame( 'done', $events[0]['event'] );
		$this->assertIsArray( $events[0]['data'] );
	}

	// ─── send_error() ────────────────────────────────────────────────────────

	/**
	 * send_error() emits an "error" event with code and message.
	 */
	public function test_send_error_emits_error_event(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_error( 'Something went wrong', 'test_error_code' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'error', $events[0]['event'] );
		$this->assertSame( 'test_error_code', $events[0]['data']['code'] );
		$this->assertSame( 'Something went wrong', $events[0]['data']['message'] );
	}

	/**
	 * send_error() uses default code "ai_agent_error" when none provided.
	 */
	public function test_send_error_uses_default_code(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_error( 'Default code error' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertSame( 'ai_agent_error', $events[0]['data']['code'] );
	}

	// ─── Auto-start ───────────────────────────────────────────────────────────

	/**
	 * emit() auto-calls start() when the streamer has not been started.
	 *
	 * Verified by checking that send_token() produces output even without
	 * an explicit start() call.
	 */
	public function test_emit_auto_starts_streamer(): void {
		$streamer = new SseStreamer();

		// Do NOT call start() — emit should auto-start.
		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'auto-start' );
		} );

		$this->assertNotEmpty( $output );
		$events = $this->parse_sse( $output );
		$this->assertCount( 1, $events );
		$this->assertSame( 'token', $events[0]['event'] );
	}

	// ─── Multiple events ──────────────────────────────────────────────────────

	/**
	 * Multiple send_token() calls produce multiple events in order.
	 */
	public function test_multiple_tokens_produce_multiple_events(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'first' );
			$streamer->send_token( 'second' );
			$streamer->send_token( 'third' );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 3, $events );
		$this->assertSame( 'first', $events[0]['data']['token'] );
		$this->assertSame( 'second', $events[1]['data']['token'] );
		$this->assertSame( 'third', $events[2]['data']['token'] );
	}

	/**
	 * A typical stream sequence (tokens → done) produces events in order.
	 */
	public function test_typical_stream_sequence(): void {
		$streamer = new SseStreamer();

		$output = $this->capture( static function () use ( $streamer ): void {
			$streamer->send_token( 'Hello ' );
			$streamer->send_token( 'world!' );
			$streamer->send_done( [ 'session_id' => 1 ] );
		} );

		$events = $this->parse_sse( $output );
		$this->assertCount( 3, $events );
		$this->assertSame( 'token', $events[0]['event'] );
		$this->assertSame( 'token', $events[1]['event'] );
		$this->assertSame( 'done', $events[2]['event'] );
	}
}
