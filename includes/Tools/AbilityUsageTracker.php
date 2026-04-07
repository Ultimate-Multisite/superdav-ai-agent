<?php

declare(strict_types=1);
/**
 * AbilityUsageTracker
 *
 * Records every successful ability invocation so the auto-discovery layer
 * can promote the most-used abilities into the always-loaded Tier 1 set.
 *
 * Storage: a single wp_option (`gratis_ai_agent_ability_usage`) holding a
 * map of ability name => { count, last_used }. The map is LRU-pruned at a
 * hard cap so it never grows unbounded.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbilityUsageTracker {

	/**
	 * Option name for the persisted usage map.
	 */
	public const OPTION_NAME = 'gratis_ai_agent_ability_usage';

	/**
	 * Maximum number of distinct abilities tracked. Old entries (lowest
	 * `last_used` timestamp) are pruned when the cap is exceeded.
	 */
	public const MAX_ENTRIES = 200;

	/**
	 * Record one successful invocation of an ability.
	 *
	 * Increments the counter and updates the timestamp. Prunes the map to
	 * MAX_ENTRIES if it overflows.
	 *
	 * @param string $ability_name Fully qualified ability name (e.g. "gratis-ai-agent/get-plugins").
	 * @return void
	 */
	public static function record( string $ability_name ): void {
		if ( '' === $ability_name ) {
			return;
		}

		$map = self::load();
		$now = time();

		if ( isset( $map[ $ability_name ] ) ) {
			$map[ $ability_name ]['count']     = (int) $map[ $ability_name ]['count'] + 1;
			$map[ $ability_name ]['last_used'] = $now;
		} else {
			$map[ $ability_name ] = array(
				'count'     => 1,
				'last_used' => $now,
			);
		}

		if ( count( $map ) > self::MAX_ENTRIES ) {
			$map = self::prune_internal( $map, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_NAME, $map, false );
	}

	/**
	 * Get the top-N most-used ability names.
	 *
	 * Sort key: descending count, then descending last_used as tiebreaker.
	 *
	 * @param int $n Maximum number of names to return.
	 * @return string[]
	 */
	public static function top( int $n ): array {
		if ( $n <= 0 ) {
			return array();
		}

		$map = self::load();
		if ( empty( $map ) ) {
			return array();
		}

		uasort(
			$map,
			static function ( $a, $b ) {
				$cmp = (int) $b['count'] - (int) $a['count'];
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return (int) $b['last_used'] - (int) $a['last_used'];
			}
		);

		return array_slice( array_keys( $map ), 0, $n );
	}

	/**
	 * Reset all usage data.
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Load the persisted map, normalising any malformed entries.
	 *
	 * @return array<string, array{count:int,last_used:int}>
	 */
	private static function load(): array {
		$raw = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $name => $entry ) {
			if ( ! is_string( $name ) || '' === $name || ! is_array( $entry ) ) {
				continue;
			}
			$out[ $name ] = array(
				'count'     => isset( $entry['count'] ) ? (int) $entry['count'] : 0,
				'last_used' => isset( $entry['last_used'] ) ? (int) $entry['last_used'] : 0,
			);
		}

		return $out;
	}

	/**
	 * Prune the map down to the given size, dropping the least-recently-used
	 * entries first.
	 *
	 * @param array<string, array{count:int,last_used:int}> $map The current map.
	 * @param int                                           $max Maximum number of entries to keep.
	 * @return array<string, array{count:int,last_used:int}>
	 */
	private static function prune_internal( array $map, int $max ): array {
		if ( count( $map ) <= $max ) {
			return $map;
		}

		uasort(
			$map,
			static function ( $a, $b ) {
				return (int) $b['last_used'] - (int) $a['last_used'];
			}
		);

		return array_slice( $map, 0, $max, true );
	}
}
