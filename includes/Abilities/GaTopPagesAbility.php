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
class GaTopPagesAbility extends AbstractAbility {

	use GaApiClient;

	protected function label(): string {
		return __( 'GA Top Pages', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Fetch the top pages by pageviews from Google Analytics 4 for a date range.', 'superdav-ai-agent' );
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
				'limit'      => [
					'type'        => 'integer',
					'description' => 'Number of top pages to return. Defaults to 10, max 50.',
					'minimum'     => 1,
					'maximum'     => 50,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'property_id' => [ 'type' => 'string' ],
				'start_date'  => [ 'type' => 'string' ],
				'end_date'    => [ 'type' => 'string' ],
				'pages'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'page_path'   => [ 'type' => 'string' ],
							'page_title'  => [ 'type' => 'string' ],
							'pageviews'   => [ 'type' => 'integer' ],
							'sessions'    => [ 'type' => 'integer' ],
							'bounce_rate' => [ 'type' => 'number' ],
						],
					],
				],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		// @phpstan-ignore-next-line
		$start_date = isset( $input['start_date'] ) ? (string) $input['start_date'] : '30daysAgo';
		// @phpstan-ignore-next-line
		$end_date = isset( $input['end_date'] ) ? (string) $input['end_date'] : 'today';
		// @phpstan-ignore-next-line
		$limit = isset( $input['limit'] ) ? min( 50, max( 1, (int) $input['limit'] ) ) : 10;

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
			'dimensions' => [
				[ 'name' => 'pagePath' ],
				[ 'name' => 'pageTitle' ],
			],
			'metrics'    => [
				[ 'name' => 'screenPageViews' ],
				[ 'name' => 'sessions' ],
				[ 'name' => 'bounceRate' ],
			],
			'orderBys'   => [
				[
					'metric' => [ 'metricName' => 'screenPageViews' ],
					'desc'   => true,
				],
			],
			'limit'      => $limit,
		];

		$data = $this->ga_api_post( $endpoint, $body, $token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$pages = [];
		// @phpstan-ignore-next-line
		foreach ( $data['rows'] ?? [] as $row ) {
			// @phpstan-ignore-next-line
			$bounce_rate = (float) $this->extract_metric( $row, 2 );
			$pages[]     = [
				// @phpstan-ignore-next-line
				'page_path'   => $this->extract_dimension( $row, 0 ),
				// @phpstan-ignore-next-line
				'page_title'  => $this->extract_dimension( $row, 1 ),
				// @phpstan-ignore-next-line
				'pageviews'   => (int) $this->extract_metric( $row, 0 ),
				// @phpstan-ignore-next-line
				'sessions'    => (int) $this->extract_metric( $row, 1 ),
				'bounce_rate' => round( $bounce_rate * 100, 2 ),
			];
		}

		return [
			'property_id' => $property_id,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'pages'       => $pages,
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
