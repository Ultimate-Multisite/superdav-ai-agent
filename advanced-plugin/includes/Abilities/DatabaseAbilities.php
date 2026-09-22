<?php

declare(strict_types=1);
/**
 * Database operation abilities for the AI agent.
 *
 * Provides SELECT query execution against the WordPress database.
 * Supports {prefix} placeholder for table prefix substitution.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DatabaseAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Execute a SELECT database query.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_db_query( array $input = [] ) {
		$ability = new DatabaseQueryAbility(
			'sd-ai-agent/db-query',
			[
				'label'       => __( 'Database Query', 'superdav-ai-agent' ),
				'description' => __( 'Execute a SELECT query on the WordPress database. Only SELECT queries are allowed. Use {prefix} as placeholder for the table prefix.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register database abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/db-query',
			[
				'label'         => __( 'Database Query', 'superdav-ai-agent' ),
				'description'   => __( 'Execute a SELECT query on the WordPress database. Only SELECT queries are allowed. Use {prefix} as placeholder for the table prefix.', 'superdav-ai-agent' ),
				'ability_class' => DatabaseQueryAbility::class,
			]
		);
	}
}
