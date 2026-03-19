<?php

declare(strict_types=1);
/**
 * Server-Sent Events (SSE) streaming helper.
 *
 * Handles the low-level HTTP plumbing for SSE: sets the correct headers,
 * flushes output buffers, and emits individual events in the
 * `data: <json>\n\n` format required by the EventSource API.
 *
 * Usage:
 *   $streamer = new SseStreamer();
 *   $streamer->start();
 *   $streamer->send_token( 'Hello ' );
 *   $streamer->send_token( 'world!' );
 *   $streamer->send_done( [ 'session_id' => 42 ] );
 *
 * @package GratisAiAgent\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

class SseStreamer {

	/**
	 * Whether the SSE headers have been sent.
	 *
	 * @var bool
	 */
	private bool $started = false;

	/**
	 * Send SSE headers and disable output buffering.
	 *
	 * Must be called before any output is produced.
	 */
	public function start(): void {
		if ( $this->started ) {
			return;
		}

		$this->started = true;

		// Prevent WordPress / PHP from buffering the response.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// SSE headers — must not be escaped.
		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx proxy buffering.
		header( 'Connection: keep-alive' );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		// Disable PHP time limit for long-running streams.
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE streams need extended execution time.
		set_time_limit( 600 );
		ignore_user_abort( true );
	}

	/**
	 * Emit a token delta event.
	 *
	 * @param string $token The text token to stream.
	 */
	public function send_token( string $token ): void {
		$this->emit( 'token', [ 'token' => $token ] );
	}

	/**
	 * Emit a tool-call event (agent is executing a tool).
	 *
	 * @param string               $name Tool name.
	 * @param array<string, mixed> $args Tool arguments.
	 */
	public function send_tool_call( string $name, array $args ): void {
		$this->emit(
			'tool_call',
			[
				'name' => $name,
				'args' => $args,
			]
		);
	}

	/**
	 * Emit a tool-result event.
	 *
	 * @param string $name   Tool name.
	 * @param mixed  $result Tool result.
	 */
	public function send_tool_result( string $name, $result ): void {
		$this->emit(
			'tool_result',
			[
				'name'   => $name,
				'result' => $result,
			]
		);
	}

	/**
	 * Emit a confirmation-required event (user must approve a tool call).
	 *
	 * @param string                     $job_id        Job identifier for the confirm/reject endpoints.
	 * @param list<array<string, mixed>> $pending_tools Tools awaiting confirmation.
	 */
	public function send_confirmation_required( string $job_id, array $pending_tools ): void {
		$this->emit(
			'confirmation_required',
			[
				'job_id'        => $job_id,
				'pending_tools' => $pending_tools,
			]
		);
	}

	/**
	 * Emit the final `done` event and close the stream.
	 *
	 * @param array<string, mixed> $metadata Optional metadata (session_id, token_usage, etc.).
	 */
	public function send_done( array $metadata = [] ): void {
		$this->emit( 'done', $metadata );
		$this->flush();
	}

	/**
	 * Emit an error event and close the stream.
	 *
	 * @param string $message Human-readable error message.
	 * @param string $code    Machine-readable error code.
	 */
	public function send_error( string $message, string $code = 'ai_agent_error' ): void {
		$this->emit(
			'error',
			[
				'code'    => $code,
				'message' => $message,
			]
		);
		$this->flush();
	}

	/**
	 * Emit a single SSE event.
	 *
	 * @param string               $event Event name.
	 * @param array<string, mixed> $data  Event payload (will be JSON-encoded).
	 */
	private function emit( string $event, array $data ): void {
		if ( ! $this->started ) {
			$this->start();
		}

		$json = wp_json_encode( $data );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// SSE protocol requires raw output — escaping would corrupt the stream.
		echo "event: {$event}\n";
		echo "data: {$json}\n\n";
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->flush();
	}

	/**
	 * Flush all output buffers to the client.
	 */
	private function flush(): void {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// FastCGI: flush without closing the connection.
			flush();
		} else {
			flush();
		}
	}
}
