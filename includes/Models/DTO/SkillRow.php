<?php

declare(strict_types=1);
/**
 * Typed DTO for a skill row returned by wpdb::get_row() / wpdb::get_results().
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_skills table row.
 */
readonly class SkillRow {

	/**
	 * @param int    $id            Row ID (auto-increment PK).
	 * @param string $slug          URL-safe unique slug.
	 * @param string $name          Human-readable name.
	 * @param string $description   Short description for the agent index.
	 * @param string $content       Full skill guide content (markdown).
	 * @param bool   $is_builtin    Whether this is a framework-bundled built-in skill.
	 * @param bool   $enabled       Whether the skill is active.
	 * @param string $version       Semver string of the current skill content (e.g. "1.0.0").
	 * @param string $content_hash  SHA-256 hash of the canonical content for change detection.
	 * @param string $source_url    Remote URL from which the skill content originates (empty for user-created skills).
	 * @param bool   $user_modified Whether an admin has edited a built-in skill (blocks auto-updates).
	 * @param string $created_at    MySQL datetime string (UTC).
	 * @param string $updated_at    MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public string $slug,
		public string $name,
		public string $description,
		public string $content,
		public bool $is_builtin,
		public bool $enabled,
		public string $version,
		public string $content_hash,
		public string $source_url,
		public bool $user_modified,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct a SkillRow from the raw stdClass returned by wpdb::get_row() or get_results().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:            (int) $row->id,
			slug:          (string) ( $row->slug ?? '' ),
			name:          (string) ( $row->name ?? '' ),
			description:   (string) ( $row->description ?? '' ),
			content:       (string) ( $row->content ?? '' ),
			is_builtin:    (bool) (int) ( $row->is_builtin ?? 0 ),
			enabled:       (bool) (int) ( $row->enabled ?? 1 ),
			version:       (string) ( $row->version ?? '' ),
			content_hash:  (string) ( $row->content_hash ?? '' ),
			source_url:    (string) ( $row->source_url ?? '' ),
			user_modified: (bool) (int) ( $row->user_modified ?? 0 ),
			created_at:    (string) ( $row->created_at ?? '' ),
			updated_at:    (string) ( $row->updated_at ?? '' ),
		);
	}
}
