<?php
/**
 * Bounded, recoverable skill guidance shared by prompt and tool delivery.
 *
 * @package SdAiAgent
 */

declare(strict_types=1);

namespace SdAiAgent\Core;

final class SkillContextDelivery {

	/** Dedicated skill budget; ordinary tool results retain their smaller cap. */
	public const MAX_BYTES = 6144;

	/** @var array<string, array<string, int>> Original byte lengths, keyed by content version/chunk. */
	private static array $delivered = [];

	/** Reset between agent runs, never across users through persistent storage. */
	public static function reset(): void {
		self::$delivered = [];
	}

	/** Check whether this version has already supplied guidance in the run. */
	public static function has_skill( string $slug, string $content ): bool {
		return isset( self::$delivered[ $slug . ':' . hash( 'sha256', $content ) ] );
	}

	/** Record a full prompt-level injection. */
	public static function mark_full( string $slug, string $content ): void {
		self::$delivered[ $slug . ':' . hash( 'sha256', $content ) ]['*'] = strlen( $content );
	}

	/**
	 * Build a bounded page with exact section and byte-offset continuation.
	 *
	 * @param string               $slug Skill identity.
	 * @param string               $name Display name.
	 * @param string               $content Complete current skill source.
	 * @param array<string, mixed> $input Section, offset and optional content version.
	 * @return array<string, mixed>|\WP_Error Recoverable context receipt.
	 */
	public static function build( string $slug, string $name, string $content, array $input = [] ): array|\WP_Error {
		$version = hash( 'sha256', $content );
		if ( ! empty( $input['content_version'] ) && $input['content_version'] !== $version ) {
			return new \WP_Error( 'sd_ai_agent_skill_content_changed', 'The skill changed. Reload its section index before continuing.' );
		}
		$section = trim( (string) ( $input['section'] ?? '' ) );
		$offset  = (int) ( $input['offset'] ?? 0 );
		$source  = $content;
		$found   = '' === $section;
		preg_match_all( '/^(#{1,3})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$sections    = [];
		$match_count = count( $matches );
		foreach ( $matches as $index => $match ) {
			$title = trim( $match[2][0] );
			if ( count( $sections ) < 48 && strlen( $title ) <= 100 ) {
				$sections[] = $title;
			}
			if ( ! $found && 0 === strcasecmp( $section, $title ) ) {
				$found = true;
				$end   = strlen( $content );
				for ( $next = $index + 1; $next < $match_count; ++$next ) {
					if ( strlen( $matches[ $next ][1][0] ) <= strlen( $match[1][0] ) ) {
						$end = $matches[ $next ][0][1];
						break;
					}
				}
				$source = substr( $content, $match[0][1], $end - $match[0][1] );
			}
		}
		if ( ! $found ) {
			return new \WP_Error( 'sd_ai_agent_skill_section_not_found', 'Unknown skill section. Use an exact heading from the section index.' );
		}
		if ( $offset < 0 || $offset > strlen( $source ) || 1 !== preg_match( '//u', substr( $source, $offset ) ) ) {
			return new \WP_Error( 'sd_ai_agent_skill_invalid_offset', 'Use a valid next_offset from the previous skill result.' );
		}
		$key    = $slug . ':' . $version;
		$chunk  = strtolower( $section ) . ':' . $offset;
		$seen   = empty( $input['reload'] ) && ( isset( self::$delivered[ $key ]['*'] ) || isset( self::$delivered[ $key ][ $chunk ] ) );
		$result = [
			'name'            => $name,
			'slug'            => $slug,
			'content'         => '',
			'content_version' => $version,
			'section'         => $section,
			'offset'          => $offset,
			'total_bytes'     => strlen( $source ),
			'next_offset'     => null,
			'truncated'       => false,
			'already_loaded'  => $seen,
			'sections'        => $sections,
			'hint'            => 'Use guidance already loaded. For missing details, request an exact section or next_offset with this content_version. Use reload=true only if compaction removed the previous guidance; do not fetch the whole guide repeatedly.',
		];
		// Leave at least half the page for actionable guidance rather than its index.
		$metadata_bytes = strlen( (string) wp_json_encode( $result ) );
		while ( $metadata_bytes > intdiv( self::MAX_BYTES, 2 ) && $result['sections'] ) {
			array_pop( $result['sections'] );
			$metadata_bytes = strlen( (string) wp_json_encode( $result ) );
		}
		$remaining = substr( $source, $offset );
		$low       = 0;
		$high      = min( strlen( $remaining ), self::MAX_BYTES );
		$best      = '';
		while ( $low <= $high ) {
			$middle = intdiv( $low + $high, 2 );
			$prefix = substr( $remaining, 0, $middle );
			while ( '' !== $prefix && 1 !== preg_match( '//u', $prefix ) ) {
				$prefix = substr( $prefix, 0, -1 );
			}
			$candidate                = $result;
			$candidate['content']     = $prefix;
			$candidate['truncated']   = strlen( $prefix ) < strlen( $remaining );
			$candidate['next_offset'] = $candidate['truncated'] ? $offset + strlen( $prefix ) : null;
			if ( strlen( (string) wp_json_encode( $candidate ) ) <= self::MAX_BYTES ) {
				$best = $prefix;
				$low  = $middle + 1;
			} else {
				$high = $middle - 1;
			}
		}
		$delivered_bytes                   = $seen ? ( self::$delivered[ $key ][ $chunk ] ?? strlen( $best ) ) : strlen( $best );
		$result['content']                 = $seen ? '' : $best;
		$result['truncated']               = $delivered_bytes < strlen( $remaining );
		$result['next_offset']             = $result['truncated'] ? $offset + $delivered_bytes : null;
		self::$delivered[ $key ][ $chunk ] = $delivered_bytes;
		return $result;
	}
}
