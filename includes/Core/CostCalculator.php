<?php

declare(strict_types=1);
/**
 * Cost calculation for AI model usage.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Core;

class CostCalculator {

	/**
	 * Pricing per million tokens [input, output] in USD.
	 */
	private const PRICING = [
		// Claude models.
		'claude-sonnet-4-20250514' => [ 3.00, 15.00 ],
		'claude-opus-4-20250115'   => [ 15.00, 75.00 ],
		'claude-haiku-4-20250414'  => [ 0.80, 4.00 ],
		'claude-sonnet-4'          => [ 3.00, 15.00 ],
		'claude-opus-4'            => [ 15.00, 75.00 ],
		'claude-haiku-4'           => [ 0.80, 4.00 ],
		// GPT-4o models.
		'gpt-4o'                   => [ 2.50, 10.00 ],
		'gpt-4o-mini'              => [ 0.15, 0.60 ],
		'gpt-4o-2024-11-20'        => [ 2.50, 10.00 ],
		// GPT-4.1 models.
		'gpt-4.1'                  => [ 2.00, 8.00 ],
		'gpt-4.1-mini'             => [ 0.40, 1.60 ],
		'gpt-4.1-nano'             => [ 0.10, 0.40 ],
		// o-series models.
		'o3'                       => [ 10.00, 40.00 ],
		'o3-mini'                  => [ 1.10, 4.40 ],
		'o4-mini'                  => [ 1.10, 4.40 ],
	];

	/**
	 * Calculate the cost for a given model and token counts.
	 *
	 * @param string $model_id          Model identifier.
	 * @param int    $prompt_tokens     Number of input tokens.
	 * @param int    $completion_tokens Number of output tokens.
	 * @return float Cost in USD.
	 */
	public static function calculate_cost( string $model_id, int $prompt_tokens, int $completion_tokens ): float {
		$pricing = self::get_pricing( $model_id );

		if ( ! $pricing ) {
			return 0.0;
		}

		$input_cost  = ( $prompt_tokens / 1_000_000 ) * $pricing[0];
		$output_cost = ( $completion_tokens / 1_000_000 ) * $pricing[1];

		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * Get pricing for a model, matching by prefix if exact match not found.
	 *
	 * @param string $model_id Model identifier.
	 * @return array{float, float}|null [input_per_million, output_per_million] or null.
	 */
	public static function get_pricing( string $model_id ): ?array {
		if ( isset( self::PRICING[ $model_id ] ) ) {
			return self::PRICING[ $model_id ];
		}

		// Try prefix matching (e.g. 'claude-sonnet-4-20250514' matches 'claude-sonnet-4').
		foreach ( self::PRICING as $key => $pricing ) {
			if ( str_starts_with( $model_id, $key ) ) {
				return $pricing;
			}
		}

		return null;
	}

	/**
	 * Get all known model pricing.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all_pricing(): array {
		return self::PRICING;
	}
}
