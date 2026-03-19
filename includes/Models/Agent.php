<?php

declare(strict_types=1);
/**
 * Agent model — specialized agents with custom prompts, tools, and models.
 *
 * Each agent is a named configuration that overrides the global defaults:
 * - system_prompt: custom instructions prepended to the base system prompt
 * - provider_id / model_id: override the default provider and model
 * - tool_profile: restrict available tools to a named profile
 * - temperature / max_iterations: per-agent inference settings
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

class Agent {

	/**
	 * Get the agents table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_agents';
	}

	/**
	 * Get all agents, optionally filtered by enabled status.
	 *
	 * @param bool|null $enabled Filter by enabled status (null = all).
	 * @return array<int, object>
	 */
	public static function get_all( ?bool $enabled = null ): array {
		global $wpdb;

		$table = self::table_name();

		if ( null !== $enabled ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE enabled = %d ORDER BY name ASC',
					$table,
					$enabled ? 1 : 0
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY name ASC',
					$table
				)
			);
		}

		return $results ?: [];
	}

	/**
	 * Get a single agent by ID.
	 *
	 * @param int $id Agent ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table_name(),
				$id
			)
		) ?: null;
	}

	/**
	 * Get a single agent by slug.
	 *
	 * @param string $slug Agent slug.
	 * @return object|null
	 */
	public static function get_by_slug( string $slug ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE slug = %s',
				self::table_name(),
				$slug
			)
		) ?: null;
	}

	/**
	 * Create a new agent.
	 *
	 * @param array<string, mixed> $data Agent data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				// @phpstan-ignore-next-line
				'slug'           => sanitize_title( $data['slug'] ?? '' ),
				// @phpstan-ignore-next-line
				'name'           => sanitize_text_field( $data['name'] ?? '' ),
				// @phpstan-ignore-next-line
				'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
				// @phpstan-ignore-next-line
				'system_prompt'  => sanitize_textarea_field( $data['system_prompt'] ?? '' ),
				// @phpstan-ignore-next-line
				'provider_id'    => sanitize_text_field( $data['provider_id'] ?? '' ),
				// @phpstan-ignore-next-line
				'model_id'       => sanitize_text_field( $data['model_id'] ?? '' ),
				// @phpstan-ignore-next-line
				'tool_profile'   => sanitize_text_field( $data['tool_profile'] ?? '' ),
				// @phpstan-ignore-next-line
				'temperature'    => isset( $data['temperature'] ) ? (float) $data['temperature'] : null,
				// @phpstan-ignore-next-line
				'max_iterations' => isset( $data['max_iterations'] ) ? (int) $data['max_iterations'] : null,
				// @phpstan-ignore-next-line
				'greeting'       => sanitize_textarea_field( $data['greeting'] ?? '' ),
				// @phpstan-ignore-next-line
				'avatar_icon'    => sanitize_text_field( $data['avatar_icon'] ?? '' ),
				'enabled'        => isset( $data['enabled'] ) ? ( $data['enabled'] ? 1 : 0 ) : 1,
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing agent.
	 *
	 * @param int                  $id   Agent ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [
			'name',
			'description',
			'system_prompt',
			'provider_id',
			'model_id',
			'tool_profile',
			'temperature',
			'max_iterations',
			'greeting',
			'avatar_icon',
			'enabled',
		];
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		if ( isset( $data['name'] ) ) {
			// @phpstan-ignore-next-line
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			// @phpstan-ignore-next-line
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['system_prompt'] ) ) {
			// @phpstan-ignore-next-line
			$data['system_prompt'] = sanitize_textarea_field( $data['system_prompt'] );
		}
		if ( isset( $data['provider_id'] ) ) {
			// @phpstan-ignore-next-line
			$data['provider_id'] = sanitize_text_field( $data['provider_id'] );
		}
		if ( isset( $data['model_id'] ) ) {
			// @phpstan-ignore-next-line
			$data['model_id'] = sanitize_text_field( $data['model_id'] );
		}
		if ( isset( $data['tool_profile'] ) ) {
			// @phpstan-ignore-next-line
			$data['tool_profile'] = sanitize_text_field( $data['tool_profile'] );
		}
		if ( isset( $data['temperature'] ) ) {
			// @phpstan-ignore-next-line
			$data['temperature'] = (float) $data['temperature'];
		}
		if ( isset( $data['max_iterations'] ) ) {
			// @phpstan-ignore-next-line
			$data['max_iterations'] = (int) $data['max_iterations'];
		}
		if ( isset( $data['greeting'] ) ) {
			// @phpstan-ignore-next-line
			$data['greeting'] = sanitize_textarea_field( $data['greeting'] );
		}
		if ( isset( $data['avatar_icon'] ) ) {
			// @phpstan-ignore-next-line
			$data['avatar_icon'] = sanitize_text_field( $data['avatar_icon'] );
		}
		if ( isset( $data['enabled'] ) ) {
			$data['enabled'] = $data['enabled'] ? 1 : 0;
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, [ 'enabled', 'max_iterations' ], true ) ) {
				$formats[] = '%d';
			} elseif ( $key === 'temperature' ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return is_int( $result ) && $result > 0;
	}

	/**
	 * Delete an agent by ID.
	 *
	 * @param int $id Agent ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return is_int( $result ) && $result > 0;
	}

	/**
	 * Resolve agent overrides for AgentLoop options.
	 *
	 * Returns an array of option overrides that should be merged into the
	 * AgentLoop constructor's $options parameter. Only non-empty values are
	 * included so that the loop's own defaults remain in effect for unset fields.
	 *
	 * @param int $agent_id Agent ID.
	 * @return array<string, mixed> Partial options array for AgentLoop.
	 */
	public static function get_loop_options( int $agent_id ): array {
		$agent = self::get( $agent_id );

		if ( ! $agent || ! (int) $agent->enabled ) {
			return [];
		}

		$options = [];

		if ( ! empty( $agent->system_prompt ) ) {
			$options['agent_system_prompt'] = $agent->system_prompt;
		}
		if ( ! empty( $agent->provider_id ) ) {
			$options['provider_id'] = $agent->provider_id;
		}
		if ( ! empty( $agent->model_id ) ) {
			$options['model_id'] = $agent->model_id;
		}
		if ( ! empty( $agent->tool_profile ) ) {
			$options['active_tool_profile'] = $agent->tool_profile;
		}
		if ( null !== $agent->temperature && '' !== $agent->temperature ) {
			$options['temperature'] = (float) $agent->temperature;
		}
		if ( null !== $agent->max_iterations && '' !== $agent->max_iterations ) {
			$options['max_iterations'] = (int) $agent->max_iterations;
		}

		return $options;
	}

	/**
	 * Serialize an agent row for REST API output.
	 *
	 * @param object $agent Raw DB row.
	 * @return array<string, mixed>
	 */
	public static function to_array( object $agent ): array {
		return [
			'id'             => (int) $agent->id,
			'slug'           => $agent->slug,
			'name'           => $agent->name,
			'description'    => $agent->description,
			'system_prompt'  => $agent->system_prompt,
			'provider_id'    => $agent->provider_id,
			'model_id'       => $agent->model_id,
			'tool_profile'   => $agent->tool_profile,
			'temperature'    => null !== $agent->temperature ? (float) $agent->temperature : null,
			'max_iterations' => null !== $agent->max_iterations ? (int) $agent->max_iterations : null,
			'greeting'       => $agent->greeting,
			'avatar_icon'    => $agent->avatar_icon,
			'enabled'        => (bool) $agent->enabled,
			'created_at'     => $agent->created_at,
			'updated_at'     => $agent->updated_at,
		];
	}
}
