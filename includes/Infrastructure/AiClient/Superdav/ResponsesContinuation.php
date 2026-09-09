<?php

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\AiClient\Superdav;

/**
 * Stores a short-lived server conversation cursor, never the prompt or credentials.
 *
 * A matching prefix proves which local input the server has already acknowledged.
 * WordPress transients survive PHP requests but may be evicted; a miss must use the
 * caller's full-history fallback rather than submitting orphaned tool results.
 */
final class ResponsesContinuation {

	/** @var string Site/session/user-scoped transient key. */
	private string $key;

	/** Construct a cursor for an authenticated agent session. */
	public function __construct( int $session_id, int $user_id ) {
		$this->key = $session_id > 0 && $user_id > 0
			? 'sd_ai_agent_responses_' . get_current_blog_id() . '_' . $session_id . '_' . $user_id
			: '';
	}

	/**
	 * Return only input not already held by the server, if the cursor still matches.
	 *
	 * @param list<array<string, mixed>> $input Full locally reconstructed input.
	 * @param string                     $scope Model, endpoint and tool-catalog fingerprint.
	 * @return array{previous_response_id:string,input:list<array<string,mixed>>}|null
	 */
	public function resume( array $input, string $scope ): ?array {
		$state = '' !== $this->key ? get_transient( $this->key ) : false;
		if ( ! is_array( $state ) ) {
			return null;
		}
		$count = $state['count'] ?? null;
		if ( ! is_int( $count ) || $count < 1 || $count >= count( $input )
			|| ! is_string( $state['response_id'] ?? null )
			|| ! self::valid_response_id( $state['response_id'] )
			|| ( $state['scope'] ?? null ) !== $scope
			|| ! is_string( $state['hash'] ?? null )
			|| ! hash_equals( $state['hash'], self::fingerprint( array_slice( $input, 0, $count ) ) )
		) {
			$this->clear();
			return null;
		}

		return array(
			'previous_response_id' => $state['response_id'],
			'input'                => array_values( array_slice( $input, $count ) ),
		);
	}

	/**
	 * Persist the response ID and the local history prefix represented by that response.
	 *
	 * @param string                     $response_id Accepted Responses API ID.
	 * @param list<array<string, mixed>> $input       Expected acknowledged history, including model output.
	 * @param string                     $scope       Model, endpoint and tool-catalog fingerprint.
	 */
	public function acknowledge( string $response_id, array $input, string $scope ): void {
		if ( '' === $this->key || ! self::valid_response_id( $response_id ) || empty( $input ) ) {
			return;
		}
		set_transient(
			$this->key,
			array(
				'response_id' => $response_id,
				'count'       => count( $input ),
				'hash'        => self::fingerprint( $input ),
				'scope'       => $scope,
			),
			DAY_IN_SECONDS
		);
	}

	/** Remove a cursor after fallback or incompatible history changes. */
	public function clear(): void {
		if ( '' !== $this->key ) {
			delete_transient( $this->key );
		}
	}

	/**
	 * Hash local structures without persisting their potentially sensitive content.
	 *
	 * @param array<mixed> $data Local structures to fingerprint.
	 */
	public static function fingerprint( array $data ): string {
		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	/** Reject malformed or unbounded opaque response IDs. */
	private static function valid_response_id( string $response_id ): bool {
		// The SD edge may wrap an upstream ID in an authenticated site-bound cursor.
		return strlen( $response_id ) <= 2048 && 1 === preg_match( '/^resp_[a-zA-Z0-9_-]+$/D', $response_id );
	}
}
