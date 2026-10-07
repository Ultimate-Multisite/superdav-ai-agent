<?php

declare(strict_types=1);
/**
 * Expiring, compare-and-delete locks for bounded MCP option mutations.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpLock {

	/** @return string|WP_Error Owner token, or a safe busy error. */
	public static function acquire( string $key, int $seconds = 60 ): string|WP_Error {
		$name  = self::name( $key );
		$value = wp_json_encode(
			array(
				'owner'   => wp_generate_uuid4(),
				'expires' => time() + $seconds,
			)
		);
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_busy', __( 'This connection is busy. Try again shortly.', 'superdav-ai-agent' ) );
		}
		$existing = get_option( $name );
		$decoded  = is_string( $existing ) ? json_decode( $existing, true ) : null;
		if ( is_array( $decoded ) && (int) ( $decoded['expires'] ?? 0 ) < time() ) {
			self::release( $key, $existing );
		}
		global $wpdb;
		if ( ! is_string( $wpdb->options ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_busy', __( 'This connection is busy. Try again shortly.', 'superdav-ai-agent' ) );
		}
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $value ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( 1 !== $inserted ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_busy', __( 'This connection is busy. Try again shortly.', 'superdav-ai-agent' ) );
		}
		return $value;
	}

	public static function release( string $key, string $owner ): void {
		global $wpdb;
		if ( ! is_string( $wpdb->options ) ) {
			return;
		}
		$name = self::name( $key );
		// Compare the complete lease so an expired owner cannot delete its successor.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $owner ) );
		if ( $deleted ) {
			wp_cache_delete( $name, 'options' );
		}
	}

	/**
	 * Commit an option only while the exact lease still owns the write.
	 *
	 * @param string               $key Lease key.
	 * @param string               $owner Exact lease token.
	 * @param string               $option Private MCP option name.
	 * @param mixed                $value New option value.
	 * @param array<string,string> $parent_fence Optional parent lease for network work.
	 */
	public static function commit( string $key, string $owner, string $option, mixed $value, array $parent_fence = array() ): bool {
		global $wpdb;
		if ( ! is_string( $wpdb->options ) ) {
			return false;
		}
		$lease = json_decode( $owner, true );
		if ( ! is_array( $lease ) ) {
			return false;
		}
		$old            = get_option( $option, null );
		$new            = maybe_serialize( $value );
		$parent_name    = '';
		$parent_owner   = '';
		$parent_expires = 0;
		if ( ! empty( $parent_fence ) ) {
			$parent_lease = json_decode( $parent_fence['owner'] ?? '', true );
			if ( ! is_array( $parent_lease ) ) {
				return false;
			}
			$parent_name    = self::name( $parent_fence['key'] );
			$parent_owner   = $parent_fence['owner'];
			$parent_expires = (int) ( $parent_lease['expires'] ?? 0 );
		}
		if ( null === $old ) {
			$sql  = "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) SELECT %s, %s, 'no' FROM {$wpdb->options} AS lease LEFT JOIN {$wpdb->options} AS parent_lease ON parent_lease.option_name = %s WHERE lease.option_name = %s AND lease.option_value = %s AND %d >= UNIX_TIMESTAMP() AND (%s = '' OR (parent_lease.option_value = %s AND %d >= UNIX_TIMESTAMP()))";
			$args = array( $option, $new, $parent_name, self::name( $key ), $owner, (int) ( $lease['expires'] ?? 0 ), $parent_owner, $parent_owner, $parent_expires );
		} else {
			$sql  = "UPDATE {$wpdb->options} AS data JOIN {$wpdb->options} AS lease ON lease.option_name = %s LEFT JOIN {$wpdb->options} AS parent_lease ON parent_lease.option_name = %s SET data.option_value = %s WHERE data.option_name = %s AND data.option_value = %s AND lease.option_value = %s AND %d >= UNIX_TIMESTAMP() AND (%s = '' OR (parent_lease.option_value = %s AND %d >= UNIX_TIMESTAMP()))";
			$args = array( self::name( $key ), $parent_name, $new, $option, maybe_serialize( $old ), $owner, (int) ( $lease['expires'] ?? 0 ), $parent_owner, $parent_owner, $parent_expires );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Only fixed SQL/validated table identifiers are composed; every value is bound by prepare.
		$written = $wpdb->query( $wpdb->prepare( $sql, $args ) );
		if ( false === $written ) {
			return false;
		}
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		if ( $written > 0 ) {
			return true;
		}
		// An unchanged value is an idempotent success only for a still-live owner.
		return $old === $value && get_option( self::name( $key ) ) === $owner && (int) ( $lease['expires'] ?? 0 ) > time() && ( empty( $parent_fence ) || ( get_option( self::name( $parent_fence['key'] ) ) === $parent_fence['owner'] && $parent_expires > time() ) );
	}

	private static function name( string $key ): string {
		return '_sd_ai_agent_mcp_lock_' . hash( 'sha256', $key );
	}
}
