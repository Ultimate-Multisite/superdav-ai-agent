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
	 * @param int         $id               Session ID (auto-increment PK).
	 * @param int         $userId           WordPress user ID.
	 * @param string      $title            Human-readable title (default '').
	 * @param string      $providerId       AI provider slug (default '').
	 * @param string      $modelId          Model slug (default '').
	 * @param string      $messages         JSON-encoded message array.
	 * @param string      $toolCalls        JSON-encoded tool-call log.
	 * @param int         $promptTokens     Total prompt tokens consumed.
	 * @param int         $completionTokens Total completion tokens consumed.
	 * @param string      $status           Session status (active|archived|trash).
	 * @param bool        $pinned           Whether the session is pinned.
	 * @param string      $folder           Folder name (default '').
	 * @param string|null $pausedState      JSON-encoded paused state, or null.
	 * @param string      $createdAt        MySQL datetime string (UTC).
	 * @param string      $updatedAt        MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public int $userId,
		public string $title,
		public string $providerId,
		public string $modelId,
		public string $messages,
		public string $toolCalls,
		public int $promptTokens,
		public int $completionTokens,
		public string $status,
		public bool $pinned,
		public string $folder,
		public ?string $pausedState,
		public string $createdAt,
		public string $updatedAt,
	) {}

	/**
	 * Construct a SessionRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb::get_row().
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:               (int) $row->id,
			userId:           (int) $row->user_id,
			title:            (string) ( $row->title ?? '' ),
			providerId:       (string) ( $row->provider_id ?? '' ),
			modelId:          (string) ( $row->model_id ?? '' ),
			messages:         (string) ( $row->messages ?? '[]' ),
			toolCalls:        (string) ( $row->tool_calls ?? '[]' ),
			promptTokens:     (int) ( $row->prompt_tokens ?? 0 ),
			completionTokens: (int) ( $row->completion_tokens ?? 0 ),
			status:           (string) ( $row->status ?? 'active' ),
			pinned:           (bool) (int) ( $row->pinned ?? 0 ),
			folder:           (string) ( $row->folder ?? '' ),
			pausedState:      isset( $row->paused_state ) && '' !== $row->paused_state ? (string) $row->paused_state : null,
			createdAt:        (string) ( $row->created_at ?? '' ),
			updatedAt:        (string) ( $row->updated_at ?? '' ),
		);
	}
}
