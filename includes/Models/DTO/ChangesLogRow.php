<?php

declare(strict_types=1);
/**
 * Typed DTO for a changes-log row returned by wpdb::get_row().
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_changes_log table row.
 */
readonly class ChangesLogRow {

	/**
	 * @param int         $id           Row ID (auto-increment PK).
	 * @param int         $session_id   Session ID that generated the change (0 = CLI/system).
	 * @param int         $user_id      WordPress user ID who made the change.
	 * @param string      $object_type  Object type ('post', 'option', 'user_meta', etc.).
	 * @param int         $object_id    ID of the changed object (0 for options).
	 * @param string      $object_title Human-readable title of the changed object.
	 * @param string      $ability_name Name of the ability that performed the change.
	 * @param string      $field_name   Field / meta key that changed.
	 * @param string      $before_value Previous value (serialised).
	 * @param string      $after_value  New value (serialised).
	 * @param bool        $reverted     Whether the change has been reverted.
	 * @param string|null $reverted_at  MySQL datetime string when reverted, or null.
	 * @param string      $created_at   MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public int $session_id,
		public int $user_id,
		public string $object_type,
		public int $object_id,
		public string $object_title,
		public string $ability_name,
		public string $field_name,
		public string $before_value,
		public string $after_value,
		public bool $reverted,
		public ?string $reverted_at,
		public string $created_at,
	) {}

	/**
	 * Construct a ChangesLogRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:           (int) $row->id,
			session_id:   (int) ( $row->session_id ?? 0 ),
			user_id:      (int) ( $row->user_id ?? 0 ),
			object_type:  (string) ( $row->object_type ?? '' ),
			object_id:    (int) ( $row->object_id ?? 0 ),
			object_title: (string) ( $row->object_title ?? '' ),
			ability_name: (string) ( $row->ability_name ?? '' ),
			field_name:   (string) ( $row->field_name ?? '' ),
			before_value: (string) ( $row->before_value ?? '' ),
			after_value:  (string) ( $row->after_value ?? '' ),
			reverted:     (bool) (int) ( $row->reverted ?? 0 ),
			reverted_at:  isset( $row->reverted_at ) ? (string) $row->reverted_at : null,
			created_at:   (string) ( $row->created_at ?? '' ),
		);
	}
}
