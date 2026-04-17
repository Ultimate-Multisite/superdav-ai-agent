<?php

declare(strict_types=1);
/**
 * Typed DTO for a session row returned by wpdb::get_row().
 *
 * Eliminates `object::$` property-access phpstan errors throughout callers
 * by providing explicit, typed property declarations for every column in the
 * gratis_ai_agent_sessions table.
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_sessions table row.
 */
readonly class SessionRow {

	/**
	 * @param int         $id                Session ID (auto-increment PK).
	 * @param int         $user_id           WordPress user ID.
	 * @param string      $title             Human-readable title (default '').
	 * @param string      $provider_id       AI provider slug (default '').
	 * @param string      $model_id          Model slug (default '').
	 * @param string      $messages          JSON-encoded message array.
	 * @param string      $tool_calls        JSON-encoded tool-call log.
	 * @param int         $prompt_tokens     Total prompt tokens consumed.
	 * @param int         $completion_tokens Total completion tokens consumed.
	 * @param string      $status            Session status (active|archived|trash).
	 * @param bool        $pinned            Whether the session is pinned.
	 * @param string      $folder            Folder name (default '').
	 * @param string|null $paused_state      JSON-encoded paused state, or null.
	 * @param string      $created_at        MySQL datetime string (UTC).
	 * @param string      $updated_at        MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public int $user_id,
		public string $title,
		public string $provider_id,
		public string $model_id,
		public string $messages,
		public string $tool_calls,
		public int $prompt_tokens,
		public int $completion_tokens,
		public string $status,
		public bool $pinned,
		public string $folder,
		public ?string $paused_state,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct a SessionRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb::get_row().
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:                (int) $row->id,
			user_id:           (int) $row->user_id,
			title:             (string) ( $row->title ?? '' ),
			provider_id:       (string) ( $row->provider_id ?? '' ),
			model_id:          (string) ( $row->model_id ?? '' ),
			messages:          (string) ( $row->messages ?? '[]' ),
			tool_calls:        (string) ( $row->tool_calls ?? '[]' ),
			prompt_tokens:     (int) ( $row->prompt_tokens ?? 0 ),
			completion_tokens: (int) ( $row->completion_tokens ?? 0 ),
			status:            (string) ( $row->status ?? 'active' ),
			pinned:            (bool) (int) ( $row->pinned ?? 0 ),
			folder:            (string) ( $row->folder ?? '' ),
			paused_state:      isset( $row->paused_state ) && '' !== $row->paused_state ? (string) $row->paused_state : null,
			created_at:        (string) ( $row->created_at ?? '' ),
			updated_at:        (string) ( $row->updated_at ?? '' ),
		);
	}
}
