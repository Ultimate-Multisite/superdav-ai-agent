<?php

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\AiClient\Superdav;

/**
 * Stores encrypted, short-lived native conversation state for OAuth replay.
 *
 * A matching prefix links ordinary SDK history to its complete native snapshot.
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
	 * Return complete native history followed by new local input when the prefix matches.
	 *
	 * @param list<array<string, mixed>> $input Full locally reconstructed input.
	 * @param string                     $scope Model, endpoint and tool-catalog fingerprint.
	 * @return array{input:list<array<string,mixed>>}|null
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

		$native = $this->decrypt_history( $state['native'] ?? null, $scope . ':' . $state['hash'] . ':' . $count );
		if ( null === $native ) {
			$this->clear();
			return null;
		}
		return array( 'input' => array_merge( $native, array_values( array_slice( $input, $count ) ) ) );
	}

	/**
	 * Persist the response ID and the local history prefix represented by that response.
	 *
	 * @param string                          $response_id Accepted Responses API ID.
	 * @param list<array<string, mixed>>      $input       Expected acknowledged history, including model output.
	 * @param string                          $scope       Model, endpoint and tool-catalog fingerprint.
	 * @param list<array<string, mixed>>|null $native_input Complete native input and output; null uses local input.
	 */
	public function acknowledge( string $response_id, array $input, string $scope, ?array $native_input = null ): void {
		if ( '' === $this->key || ! self::valid_response_id( $response_id ) || empty( $input ) ) {
			$this->clear();
			return;
		}
		$native = $this->encrypt_history( $native_input ?? $input, $scope . ':' . self::fingerprint( $input ) . ':' . count( $input ) );
		if ( null === $native ) {
			$this->clear();
			return;
		}
		set_transient(
			$this->key,
			array(
				'response_id' => $response_id,
				'count'       => count( $input ),
				'hash'        => self::fingerprint( $input ),
				'scope'       => $scope,
				'native'      => $native,
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Encrypt at rest and bound retention; unavailable crypto disables persisted replay.
	 *
	 * @param list<array<string, mixed>> $input Complete native history.
	 */
	private function encrypt_history( array $input, string $context ): ?string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return null;
		}
		$json = wp_json_encode( $input );
		if ( false === $json || strlen( $json ) > 1048576 ) {
			return null;
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $json, 'aes-256-gcm', hash( 'sha256', 'sd-ai-native-replay:' . wp_salt( 'auth' ), true ), 0, $iv, $tag, $this->key . ':' . $context );
		return false !== $cipher ? bin2hex( $iv . $tag ) . $cipher : null;
	}

	/** @return list<array<string, mixed>>|null */
	private function decrypt_history( mixed $encrypted, string $context ): ?array {
		if ( ! is_string( $encrypted ) || strlen( $encrypted ) > 1400000 || ! function_exists( 'openssl_decrypt' ) ) {
			return null;
		}
		if ( strlen( $encrypted ) <= 56 || ! ctype_xdigit( substr( $encrypted, 0, 56 ) ) ) {
			return null;
		}
		$bytes = hex2bin( substr( $encrypted, 0, 56 ) );
		if ( false === $bytes ) {
			return null;
		}
		$json = openssl_decrypt( substr( $encrypted, 56 ), 'aes-256-gcm', hash( 'sha256', 'sd-ai-native-replay:' . wp_salt( 'auth' ), true ), 0, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ), $this->key . ':' . $context );
		$data = false !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || ! array_is_list( $data ) ) {
			return null;
		}
		$validated = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				return null;
			}
			$row = array();
			foreach ( $item as $key => $value ) {
				if ( ! is_string( $key ) ) {
					return null;
				}
				$row[ $key ] = $value;
			}
			$validated[] = $row;
		}
		return $validated;
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
		// IDs are bounded diagnostic identifiers, never dereferenced during OAuth replay.
		return strlen( $response_id ) <= 2048 && 1 === preg_match( '/^resp_[a-zA-Z0-9_-]+$/D', $response_id );
	}
}
