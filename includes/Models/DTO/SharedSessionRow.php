<?php

declare(strict_types=1);
/**
 * Typed DTO for a shared-session row returned by wpdb::get_row().
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_shared_sessions table row.
 */
readonly class SharedSessionRow {

	/**
	 * @param int    $id         Row ID (auto-increment PK).
	 * @param int    $session_id Session being shared.
	 * @param int    $shared_by  WordPress user ID of the admin who shared the session.
	 * @param string $shared_at  MySQL datetime string (UTC) when sharing was created.
	 */
	public function __construct(
		public int $id,
		public int $session_id,
		public int $shared_by,
		public string $shared_at,
	) {}

	/**
	 * Construct a SharedSessionRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb::get_row().
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:         (int) $row->id,
			session_id: (int) $row->session_id,
			shared_by:  (int) $row->shared_by,
			shared_at:  (string) ( $row->shared_at ?? '' ),
		);
	}
}
