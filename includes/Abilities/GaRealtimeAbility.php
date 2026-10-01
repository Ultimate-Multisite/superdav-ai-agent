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
class GaRealtimeAbility extends AbstractAbility {

	use GaApiClient;

	protected function label(): string {
		return __( 'GA Realtime Users', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Fetch the number of active users on the site right now from Google Analytics 4.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'minutes_ago' => [
					'type'        => 'integer',
					'description' => 'Look back window in minutes (1-60). Defaults to 30.',
					'minimum'     => 1,
					'maximum'     => 60,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'property_id'  => [ 'type' => 'string' ],
				'active_users' => [
					'type'        => 'integer',
					'description' => 'Number of active users in the last N minutes.',
				],
				'minutes_ago'  => [ 'type' => 'integer' ],
				'top_pages'    => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'page_path'    => [ 'type' => 'string' ],
							'active_users' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		// @phpstan-ignore-next-line
		$minutes_ago = isset( $input['minutes_ago'] ) ? min( 60, max( 1, (int) $input['minutes_ago'] ) ) : 30;

		$creds = $this->load_credentials();
		if ( is_wp_error( $creds ) ) {
			return $creds;
		}

		$property_id = $creds['property_id'];
		$token       = $creds['token'];

		$endpoint = sprintf(
			'https://analyticsdata.googleapis.com/v1beta/properties/%s:runRealtimeReport',
			rawurlencode( $property_id )
		);

		$body = [
			'dimensions'   => [
				[ 'name' => 'pagePath' ],
			],
			'metrics'      => [
				[ 'name' => 'activeUsers' ],
			],
			'minuteRanges' => [
				[
					'name'            => 'last_n_minutes',
					'startMinutesAgo' => $minutes_ago,
					'endMinutesAgo'   => 0,
				],
			],
			'orderBys'     => [
				[
					'metric' => [ 'metricName' => 'activeUsers' ],
					'desc'   => true,
				],
			],
			'limit'        => 10,
		];

		$data = $this->ga_api_post( $endpoint, $body, $token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Sum active users across all pages.
		$total_active = 0;
		$top_pages    = [];
		// @phpstan-ignore-next-line
		foreach ( $data['rows'] ?? [] as $row ) {
			// @phpstan-ignore-next-line
			$users         = (int) $this->extract_metric( $row, 0 );
			$total_active += $users;
			$top_pages[]   = [
				// @phpstan-ignore-next-line
				'page_path'    => $this->extract_dimension( $row, 0 ),
				'active_users' => $users,
			];
		}

		// Fallback: use totals from response if rows are empty.
		if ( 0 === $total_active && ! empty( $data['totals'] ) ) {
			// @phpstan-ignore-next-line
			$total_active = (int) ( $data['totals'][0]['metricValues'][0]['value'] ?? 0 );
		}

		return [
			'property_id'  => $property_id,
			'active_users' => $total_active,
			'minutes_ago'  => $minutes_ago,
			'top_pages'    => $top_pages,
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
