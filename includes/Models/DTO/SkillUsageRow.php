<?php

declare(strict_types=1);
/**
 * Typed DTO for a skill usage row returned by wpdb::get_row() / wpdb::get_results().
 *
 * @package SdAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Models\DTO;

/**
 * Immutable DTO for the sd_ai_agent_skill_usage table row.
 */
readonly class SkillUsageRow {

	/**
	 * @param int    $id              Row ID (auto-increment PK).
	 * @param int    $skill_id        FK to sd_ai_agent_skills.id.
	 * @param int    $session_id      FK to sd_ai_agent_sessions.id (0 = no session).
	 * @param string $trigger_type    How the skill was loaded: 'auto', 'manual', 'tool_call'.
	 * @param int    $injected_tokens Estimated token cost of the injected content.
	 * @param string $outcome         Heuristic quality signal: 'helpful', 'neutral', 'negative', 'unknown'.
	 * @param string $model_id        Model ID that received the skill injection.
	 * @param string $created_at      MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public int $skill_id,
		public int $session_id,
		public string $trigger_type,
		public int $injected_tokens,
		public string $outcome,
		public string $model_id,
		public string $created_at,
	) {}

	/**
	 * Construct a SkillUsageRow from the raw stdClass returned by wpdb::get_row() or get_results().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:              (int) $row->id,
			skill_id:        (int) ( $row->skill_id ?? 0 ),
			session_id:      (int) ( $row->session_id ?? 0 ),
			trigger_type:    (string) ( $row->trigger_type ?? 'auto' ),
			injected_tokens: (int) ( $row->injected_tokens ?? 0 ),
			outcome:         (string) ( $row->outcome ?? 'unknown' ),
			model_id:        (string) ( $row->model_id ?? '' ),
			created_at:      (string) ( $row->created_at ?? '' ),
		);
	}
}
