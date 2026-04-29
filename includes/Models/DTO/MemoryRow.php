<?php

declare(strict_types=1);
/**
 * Typed DTO for a memory row returned by wpdb::get_row() / wpdb::get_results().
 *
 * @package SdAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Models\DTO;

/**
 * Immutable DTO for the sd_ai_agent_memories table row.
 */
readonly class MemoryRow {

	/**
	 * @param int    $id         Row ID (auto-increment PK).
	 * @param string $category   Memory category (e.g. 'general', 'site_info').
	 * @param string $content    Memory text content.
	 * @param string $created_at MySQL datetime string (UTC).
	 * @param string $updated_at MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public string $category,
		public string $content,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct a MemoryRow from the raw stdClass returned by wpdb::get_row() or get_results().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:         (int) $row->id,
			category:   (string) ( $row->category ?? 'general' ),
			content:    (string) ( $row->content ?? '' ),
			created_at: (string) ( $row->created_at ?? '' ),
			updated_at: (string) ( $row->updated_at ?? '' ),
		);
	}
}
