<?php

declare(strict_types=1);
/**
 * Google Analytics 4 traffic analysis abilities for the AI agent.
 *
 * Connects to the Google Analytics Data API v1 using a service account JSON
 * key stored in WordPress options. Provides three abilities:
 *
 *   - sd-ai-agent/ga-traffic-summary  — sessions, pageviews, bounce rate,
 *                                           avg session duration for a date range
 *   - sd-ai-agent/ga-top-pages        — top N pages by pageviews
 *   - sd-ai-agent/ga-realtime         — active users right now
 *
 * Authentication: Google service account JSON key (downloaded from Google Cloud
 * Console). The key is stored in a dedicated WordPress option and never exposed
 * through the general GET /settings endpoint.
 *
 * @package SdAiAgent
 * @since 1.0.0
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Analytics abilities facade.
 *
 * Registers three GA4 Data API abilities and provides static proxy methods
 * for backwards-compatible test access.
 */
class GoogleAnalyticsAbilities {

	/**
	 * WordPress option name for GA credentials.
	 * Stored separately from general settings to avoid credential leakage.
	 */
	const CREDENTIALS_OPTION = 'sd_ai_agent_ga_credentials';

	// ─── Static proxy methods ────────────────────────────────────────────────

	/**
	 * Get traffic summary for a date range.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function handle_traffic_summary( array $input = [] ) {
		$ability = new GaTrafficSummaryAbility(
			'sd-ai-agent/ga-traffic-summary',
			[
				'label'       => __( 'GA Traffic Summary', 'superdav-ai-agent' ),
				'description' => __( 'Fetch Google Analytics 4 traffic metrics (sessions, pageviews, bounce rate, avg session duration) for a date range.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get top pages by pageviews.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function handle_top_pages( array $input = [] ) {
		$ability = new GaTopPagesAbility(
			'sd-ai-agent/ga-top-pages',
			[
				'label'       => __( 'GA Top Pages', 'superdav-ai-agent' ),
				'description' => __( 'Fetch the top pages by pageviews from Google Analytics 4 for a date range.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get realtime active users.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function handle_realtime( array $input = [] ) {
		$ability = new GaRealtimeAbility(
			'sd-ai-agent/ga-realtime',
			[
				'label'       => __( 'GA Realtime Users', 'superdav-ai-agent' ),
				'description' => __( 'Fetch the number of active users on the site right now from Google Analytics 4.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register Google Analytics abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/ga-traffic-summary',
			[
				'label'         => __( 'GA Traffic Summary', 'superdav-ai-agent' ),
				'description'   => __( 'Fetch Google Analytics 4 traffic metrics (sessions, pageviews, bounce rate, avg session duration) for a date range.', 'superdav-ai-agent' ),
				'ability_class' => GaTrafficSummaryAbility::class,
				'show_in_rest'  => true,
			]
		);

		wp_register_ability(
			'sd-ai-agent/ga-top-pages',
			[
				'label'         => __( 'GA Top Pages', 'superdav-ai-agent' ),
				'description'   => __( 'Fetch the top pages by pageviews from Google Analytics 4 for a date range.', 'superdav-ai-agent' ),
				'ability_class' => GaTopPagesAbility::class,
				'show_in_rest'  => true,
			]
		);

		wp_register_ability(
			'sd-ai-agent/ga-realtime',
			[
				'label'         => __( 'GA Realtime Users', 'superdav-ai-agent' ),
				'description'   => __( 'Fetch the number of active users on the site right now from Google Analytics 4.', 'superdav-ai-agent' ),
				'ability_class' => GaRealtimeAbility::class,
				'show_in_rest'  => true,
			]
		);
	}

	// ─── Credential helpers ──────────────────────────────────────────────────

	/**
	 * Get stored GA credentials.
	 *
	 * @return array{property_id: string, service_account_json: string}
	 */
	public static function get_credentials(): array {
		$stored = get_option( self::CREDENTIALS_OPTION, [] );
		return [
			// @phpstan-ignore-next-line
			'property_id'          => isset( $stored['property_id'] ) ? (string) $stored['property_id'] : '',
			// @phpstan-ignore-next-line
			'service_account_json' => isset( $stored['service_account_json'] ) ? (string) $stored['service_account_json'] : '',
		];
	}

	/**
	 * Persist GA credentials.
	 *
	 * @param string $property_id          GA4 property ID (e.g. "123456789").
	 * @param string $service_account_json Service account JSON key contents.
	 * @return bool True on success.
	 */
	public static function set_credentials( string $property_id, string $service_account_json ): bool {
		return (bool) update_option(
			self::CREDENTIALS_OPTION,
			[
				'property_id'          => $property_id,
				'service_account_json' => $service_account_json,
			]
		);
	}

	/**
	 * Clear stored GA credentials.
	 *
	 * @return bool True on success.
	 */
	public static function clear_credentials(): bool {
		return (bool) delete_option( self::CREDENTIALS_OPTION );
	}
}
