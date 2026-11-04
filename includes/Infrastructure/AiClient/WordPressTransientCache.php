<?php

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\AiClient;

use DateInterval;
use DateTimeImmutable;
use WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistent PSR-16 cache backed by site-scoped WordPress transients.
 *
 * The AI Client SDK supplies a 24-hour model-directory TTL. Provider catalogs
 * can change after account or connector updates, so this adapter deliberately
 * caps every entry at five minutes while still sharing SDK results across PHP
 * requests.
 */
final class WordPressTransientCache implements CacheInterface {

	public const MAX_TTL = 5 * MINUTE_IN_SECONDS;

	private const GENERATION_OPTION = 'sd_ai_agent_ai_client_cache_generation';
	private const KEY_PREFIX        = 'sd_ai_agent_ai_client_';

	/**
	 * @param string $key Cache key.
	 * @param mixed  $default Default on a miss.
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Matches the PSR-16 interface for named arguments.
	public function get( $key, $default = null ): mixed {
		$cached = get_transient( $this->transient_key( $this->key_string( $key ) ) );
		if ( ! is_array( $cached ) || ! array_key_exists( 'value', $cached ) ) {
			return $default;
		}

		return $cached['value'];
	}

	/**
	 * @param string                $key Cache key.
	 * @param mixed                 $value Serializable value.
	 * @param null|int|DateInterval $ttl Requested TTL.
	 */
	public function set( $key, $value, $ttl = null ): bool {
		$key     = $this->key_string( $key );
		$seconds = $this->ttl_seconds( $ttl );
		if ( $seconds <= 0 ) {
			return $this->delete( $key );
		}

		$transient_key = $this->transient_key( $key );
		$cached        = array( 'value' => $value );

		return set_transient( $transient_key, $cached, $seconds )
			|| $cached === get_transient( $transient_key );
	}

	/**
	 * @param string $key Cache key.
	 */
	public function delete( $key ): bool {
		$transient_key = $this->transient_key( $this->key_string( $key ) );
		if ( false === get_transient( $transient_key ) ) {
			return true;
		}

		return delete_transient( $transient_key );
	}

	public function clear(): bool {
		$generation = $this->generation() + 1;
		return update_option( self::GENERATION_OPTION, $generation, false )
			|| $generation === (int) get_option( self::GENERATION_OPTION, 1 );
	}

	/**
	 * @param iterable<string> $keys Cache keys.
	 * @param mixed            $default Default on a miss.
	 * @return iterable<string, mixed>
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Matches the PSR-16 interface for named arguments.
	public function getMultiple( $keys, $default = null ): iterable {
		if ( ! is_iterable( $keys ) ) {
			throw new CacheInvalidArgumentException( 'Cache keys must be iterable.' );
		}
		$values = array();
		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) ) {
				throw new CacheInvalidArgumentException( 'Cache keys must be strings.' );
			}
			$values[ $key ] = $this->get( $key, $default );
		}

		return $values;
	}

	/**
	 * @param iterable<string, mixed> $values Values keyed by cache key.
	 * @param null|int|DateInterval   $ttl Requested TTL.
	 */
	public function setMultiple( $values, $ttl = null ): bool {
		if ( ! is_iterable( $values ) ) {
			throw new CacheInvalidArgumentException( 'Cache values must be iterable.' );
		}
		$success = true;
		foreach ( $values as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new CacheInvalidArgumentException( 'Cache keys must be strings.' );
			}
			$success = $this->set( $key, $value, $ttl ) && $success;
		}

		return $success;
	}

	/**
	 * @param iterable<string> $keys Cache keys.
	 */
	public function deleteMultiple( $keys ): bool {
		if ( ! is_iterable( $keys ) ) {
			throw new CacheInvalidArgumentException( 'Cache keys must be iterable.' );
		}
		$success = true;
		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) ) {
				throw new CacheInvalidArgumentException( 'Cache keys must be strings.' );
			}
			$success = $this->delete( $key ) && $success;
		}

		return $success;
	}

	/**
	 * @param string $key Cache key.
	 */
	public function has( $key ): bool {
		$cached = get_transient( $this->transient_key( $this->key_string( $key ) ) );
		return is_array( $cached ) && array_key_exists( 'value', $cached );
	}

	/** Return a bounded positive TTL in seconds. */
	private function ttl_seconds( mixed $ttl ): int {
		if ( null === $ttl ) {
			return self::MAX_TTL;
		}

		if ( $ttl instanceof DateInterval ) {
			$now = new DateTimeImmutable();
			$ttl = $now->add( $ttl )->getTimestamp() - $now->getTimestamp();
		}
		if ( ! is_int( $ttl ) ) {
			throw new CacheInvalidArgumentException( 'Cache TTL values must be integers or DateInterval objects.' );
		}

		return min( self::MAX_TTL, max( 0, $ttl ) );
	}

	private function key_string( mixed $key ): string {
		if ( ! is_string( $key ) ) {
			throw new CacheInvalidArgumentException( 'Cache keys must be strings.' );
		}

		return $key;
	}

	/** Build a fixed-length, site-scoped transient name. */
	private function transient_key( string $key ): string {
		$this->validate_key( $key );
		return self::KEY_PREFIX . $this->generation() . '_' . hash( 'sha256', $key );
	}

	private function generation(): int {
		return max( 1, (int) get_option( self::GENERATION_OPTION, 1 ) );
	}

	private function validate_key( string $key ): void {
		if ( '' === $key || preg_match( '/[{}()\\/\\\\@:]/', $key ) ) {
			throw new CacheInvalidArgumentException( 'The cache key contains reserved characters.' );
		}
	}
}
