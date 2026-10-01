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
class GaTrafficSummaryAbility extends AbstractAbility {

	use GaApiClient;

	protected function label(): string {
		return __( 'GA Traffic Summary', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Fetch Google Analytics 4 traffic metrics (sessions, pageviews, bounce rate, avg session duration) for a date range.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'start_date' => [
					'type'        => 'string',
					'description' => 'Start date in YYYY-MM-DD format, or a relative value like "7daysAgo", "30daysAgo", "yesterday". Defaults to "30daysAgo".',
				],
				'end_date'   => [
					'type'        => 'string',
					'description' => 'End date in YYYY-MM-DD format, or "today" or "yesterday". Defaults to "today".',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'property_id'            => [ 'type' => 'string' ],
				'start_date'             => [ 'type' => 'string' ],
				'end_date'               => [ 'type' => 'string' ],
				'sessions'               => [ 'type' => 'integer' ],
				'pageviews'              => [ 'type' => 'integer' ],
				'bounce_rate'            => [
					'type'        => 'number',
					'description' => 'Bounce rate as a percentage (0-100).',
				],
				'avg_session_duration_s' => [
					'type'        => 'number',
					'description' => 'Average session duration in seconds.',
				],
				'new_users'              => [ 'type' => 'integer' ],
				'total_users'            => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		// @phpstan-ignore-next-line
		$start_date = isset( $input['start_date'] ) ? (string) $input['start_date'] : '30daysAgo';
		// @phpstan-ignore-next-line
		$end_date = isset( $input['end_date'] ) ? (string) $input['end_date'] : 'today';

		$creds = $this->load_credentials();
		if ( is_wp_error( $creds ) ) {
			return $creds;
		}

		$property_id = $creds['property_id'];
		$token       = $creds['token'];

		$endpoint = sprintf(
			'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport',
			rawurlencode( $property_id )
		);

		$body = [
			'dateRanges' => [
				[
					'startDate' => $start_date,
					'endDate'   => $end_date,
				],
			],
			'metrics'    => [
				[ 'name' => 'sessions' ],
				[ 'name' => 'screenPageViews' ],
				[ 'name' => 'bounceRate' ],
				[ 'name' => 'averageSessionDuration' ],
				[ 'name' => 'newUsers' ],
				[ 'name' => 'totalUsers' ],
			],
		];

		$data = $this->ga_api_post( $endpoint, $body, $token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// @phpstan-ignore-next-line
		$row = $data['rows'][0] ?? null;
		if ( null === $row ) {
			return [
				'property_id'            => $property_id,
				'start_date'             => $start_date,
				'end_date'               => $end_date,
				'sessions'               => 0,
				'pageviews'              => 0,
				'bounce_rate'            => 0.0,
				'avg_session_duration_s' => 0.0,
				'new_users'              => 0,
				'total_users'            => 0,
				'note'                   => __( 'No data found for the specified date range.', 'superdav-ai-agent' ),
			];
		}

		// @phpstan-ignore-next-line
		$bounce_rate = (float) $this->extract_metric( $row, 2 );

		return [
			'property_id'            => $property_id,
			'start_date'             => $start_date,
			'end_date'               => $end_date,
			// @phpstan-ignore-next-line
			'sessions'               => (int) $this->extract_metric( $row, 0 ),
			// @phpstan-ignore-next-line
			'pageviews'              => (int) $this->extract_metric( $row, 1 ),
			'bounce_rate'            => round( $bounce_rate * 100, 2 ),
			// @phpstan-ignore-next-line
			'avg_session_duration_s' => round( (float) $this->extract_metric( $row, 3 ), 2 ),
			// @phpstan-ignore-next-line
			'new_users'              => (int) $this->extract_metric( $row, 4 ),
			// @phpstan-ignore-next-line
			'total_users'            => (int) $this->extract_metric( $row, 5 ),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
