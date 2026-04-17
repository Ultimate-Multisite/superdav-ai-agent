<?php

declare(strict_types=1);
/**
 * Typed DTO for a conversation-template row returned by wpdb::get_row() / wpdb::get_results().
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_conversation_templates table row.
 */
readonly class ConversationTemplateRow {

	/**
	 * @param int    $id         Row ID (auto-increment PK).
	 * @param string $slug       URL-safe unique slug.
	 * @param string $name       Human-readable name.
	 * @param string $description Short description.
	 * @param string $prompt     Full prompt content.
	 * @param string $category   Category slug (default 'general').
	 * @param string $icon       Dashicons slug (default 'admin-comments').
	 * @param bool   $is_builtin Whether this is a framework-bundled built-in template.
	 * @param int    $sort_order Sort order integer.
	 * @param string $created_at MySQL datetime string (UTC).
	 * @param string $updated_at MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public string $slug,
		public string $name,
		public string $description,
		public string $prompt,
		public string $category,
		public string $icon,
		public bool $is_builtin,
		public int $sort_order,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct a ConversationTemplateRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:          (int) $row->id,
			slug:        (string) ( $row->slug ?? '' ),
			name:        (string) ( $row->name ?? '' ),
			description: (string) ( $row->description ?? '' ),
			prompt:      (string) ( $row->prompt ?? '' ),
			category:    (string) ( $row->category ?? 'general' ),
			icon:        (string) ( $row->icon ?? 'admin-comments' ),
			is_builtin:  (bool) (int) ( $row->is_builtin ?? 0 ),
			sort_order:  (int) ( $row->sort_order ?? 0 ),
			created_at:  (string) ( $row->created_at ?? '' ),
			updated_at:  (string) ( $row->updated_at ?? '' ),
		);
	}
}
