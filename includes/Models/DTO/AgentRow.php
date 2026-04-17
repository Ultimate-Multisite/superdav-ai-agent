<?php

declare(strict_types=1);
/**
 * Typed DTO for an agent row returned by wpdb::get_row() / wpdb::get_results().
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_agents table row.
 */
readonly class AgentRow {

	/**
	 * @param int        $id             Row ID (auto-increment PK).
	 * @param string     $slug           URL-safe unique slug.
	 * @param string     $name           Human-readable name.
	 * @param string     $description    Short description.
	 * @param string     $system_prompt  Custom system instruction override (default '').
	 * @param string     $provider_id    AI provider slug override (default '').
	 * @param string     $model_id       Model slug override (default '').
	 * @param string     $tool_profile   Tool profile slug (default '').
	 * @param float|null $temperature    Sampling temperature override, or null to use default.
	 * @param int|null   $max_iterations Iteration cap override, or null to use default.
	 * @param string     $greeting       Agent greeting message (default '').
	 * @param string     $avatar_icon    Dashicons slug or empty string.
	 * @param bool       $enabled        Whether the agent is active.
	 * @param string     $created_at     MySQL datetime string (UTC).
	 * @param string     $updated_at     MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public string $slug,
		public string $name,
		public string $description,
		public string $system_prompt,
		public string $provider_id,
		public string $model_id,
		public string $tool_profile,
		public ?float $temperature,
		public ?int $max_iterations,
		public string $greeting,
		public string $avatar_icon,
		public bool $enabled,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct an AgentRow from the raw stdClass returned by wpdb::get_row() or get_results().
	 *
	 * @param object $row Raw row from wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$temp = isset( $row->temperature ) && '' !== $row->temperature
			? (float) $row->temperature
			: null;

		$max_iter = isset( $row->max_iterations ) && '' !== $row->max_iterations
			? (int) $row->max_iterations
			: null;

		return new self(
			id:             (int) $row->id,
			slug:           (string) ( $row->slug ?? '' ),
			name:           (string) ( $row->name ?? '' ),
			description:    (string) ( $row->description ?? '' ),
			system_prompt:  (string) ( $row->system_prompt ?? '' ),
			provider_id:    (string) ( $row->provider_id ?? '' ),
			model_id:       (string) ( $row->model_id ?? '' ),
			tool_profile:   (string) ( $row->tool_profile ?? '' ),
			temperature:    $temp,
			max_iterations: $max_iter,
			greeting:       (string) ( $row->greeting ?? '' ),
			avatar_icon:    (string) ( $row->avatar_icon ?? '' ),
			enabled:        (bool) (int) ( $row->enabled ?? 1 ),
			created_at:     (string) ( $row->created_at ?? '' ),
			updated_at:     (string) ( $row->updated_at ?? '' ),
		);
	}
}
