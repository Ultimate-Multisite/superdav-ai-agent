<?php

declare(strict_types=1);
/**
 * Internet search abilities for the AI agent.
 *
 * Provides web search capabilities so the agent can research topics and
 * produce well-sourced content (e.g. blog posts, product descriptions).
 *
 * Search provider strategy (zero-config first):
 *   1. Brave Search API — if a Brave API key is configured in settings.
 *   2. DuckDuckGo Instant Answer API — free, no API key required, always available.
 *
 * The Brave Search API returns richer results (full snippets, news, videos)
 * and is recommended for production use. DuckDuckGo is the reliable fallback
 * that works out of the box with zero configuration.
 *
 * Configuration:
 *   - Brave API key: Settings page → "Brave Search API Key" field.
 *     Get a free key at https://brave.com/search/api/
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InternetSearchAbilities {

	/**
	 * Brave Search API endpoint.
	 */
	const BRAVE_SEARCH_URL = 'https://api.search.brave.com/res/v1/web/search';

	/**
	 * DuckDuckGo Instant Answer API endpoint.
	 */
	const DDG_API_URL = 'https://api.duckduckgo.com/';

	/**
	 * Option name for the Brave Search API key.
	 * Stored separately from general settings to avoid credential leakage.
	 */
	const BRAVE_KEY_OPTION = 'gratis_ai_agent_brave_search_key';

	/**
	 * Register the internet-search ability.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/internet-search',
			[
				'label'               => __( 'Internet Search', 'gratis-ai-agent' ),
				'description'         => __( 'Search the internet for current information. Returns a list of relevant results with titles, URLs, and snippets. Use this to research topics before writing blog posts or answering questions about recent events.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'query'     => [
							'type'        => 'string',
							'description' => 'The search query (e.g. "best WordPress SEO plugins 2025", "how to make sourdough bread")',
						],
						'count'     => [
							'type'        => 'integer',
							'description' => 'Number of results to return (1–20, default: 10)',
						],
						'freshness' => [
							'type'        => 'string',
							'description' => 'Filter results by age: "pd" (past day), "pw" (past week), "pm" (past month), "py" (past year). Omit for all time.',
							'enum'        => [ 'pd', 'pw', 'pm', 'py' ],
						],
					],
					'required'   => [ 'query' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'results'  => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'title'   => [ 'type' => 'string' ],
									'url'     => [ 'type' => 'string' ],
									'snippet' => [ 'type' => 'string' ],
								],
							],
						],
						'provider' => [ 'type' => 'string' ],
						'query'    => [ 'type' => 'string' ],
						'error'    => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_search' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Permission callback: any user who can edit posts may search the internet.
	 *
	 * @param mixed $input Unused.
	 * @return bool
	 */
	public static function check_permission( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle the internet-search ability call.
	 *
	 * Routes to Brave Search if an API key is configured, otherwise falls back
	 * to DuckDuckGo Instant Answer API (zero-config).
	 *
	 * @param array<string,mixed> $input Input with query, optional count and freshness.
	 * @return array<string,mixed> Result with results array, provider, and query.
	 */
	public static function handle_search( array $input ): array {
		// @phpstan-ignore-next-line
		$query = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		// @phpstan-ignore-next-line
		$count = (int) ( $input['count'] ?? 10 );
		// @phpstan-ignore-next-line
		$freshness = isset( $input['freshness'] ) ? sanitize_key( (string) $input['freshness'] ) : '';

		if ( '' === $query ) {
			return [ 'error' => 'query is required.' ];
		}

		$count = max( 1, min( 20, $count ) );

		$brave_key = self::get_brave_api_key();

		if ( '' !== $brave_key ) {
			return self::search_brave( $query, $count, $freshness, $brave_key );
		}

		return self::search_duckduckgo( $query, $count );
	}

	/**
	 * Search using the Brave Search API.
	 *
	 * @param string $query     Search query.
	 * @param int    $count     Number of results.
	 * @param string $freshness Optional freshness filter (pd/pw/pm/py).
	 * @param string $api_key   Brave Search API key.
	 * @return array<string,mixed> Results array.
	 */
	private static function search_brave( string $query, int $count, string $freshness, string $api_key ): array {
		$params = [
			'q'     => $query,
			'count' => $count,
		];

		if ( '' !== $freshness ) {
			$params['freshness'] = $freshness;
		}

		$url = add_query_arg( $params, self::BRAVE_SEARCH_URL );

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 15,
				'headers' => [
					'Accept'               => 'application/json',
					'Accept-Encoding'      => 'gzip',
					'X-Subscription-Token' => $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			// Fall back to DuckDuckGo on network error.
			return self::search_duckduckgo( $query, $count );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status ) {
			// Fall back to DuckDuckGo on API error (e.g. invalid key, quota exceeded).
			return self::search_duckduckgo( $query, $count );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return self::search_duckduckgo( $query, $count );
		}

		$results = [];

		// Extract web results.
		$web_results = $data['web']['results'] ?? [];
		if ( is_array( $web_results ) ) {
			foreach ( $web_results as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$results[] = [
					'title'   => (string) ( $item['title'] ?? '' ),
					'url'     => (string) ( $item['url'] ?? '' ),
					'snippet' => (string) ( $item['description'] ?? '' ),
				];
			}
		}

		return [
			'results'  => $results,
			'provider' => 'brave',
			'query'    => $query,
		];
	}

	/**
	 * Search using the DuckDuckGo Instant Answer API.
	 *
	 * DuckDuckGo's API returns instant answers and related topics. It does not
	 * return a traditional list of web results, so we extract RelatedTopics
	 * and the AbstractText to build a useful result set.
	 *
	 * Note: DuckDuckGo's API is designed for instant answers, not full web
	 * search. For comprehensive research, configure a Brave Search API key.
	 *
	 * @param string $query  Search query.
	 * @param int    $count  Maximum number of results to return.
	 * @return array<string,mixed> Results array.
	 */
	private static function search_duckduckgo( string $query, int $count ): array {
		$url = add_query_arg(
			[
				'q'             => $query,
				'format'        => 'json',
				'no_html'       => '1',
				'skip_disambig' => '1',
				'no_redirect'   => '1',
			],
			self::DDG_API_URL
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'user-agent' => 'GratisAiAgent/1.0 (WordPress plugin; +https://wordpress.org/plugins/gratis-ai-agent)',
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'results'  => [],
				'provider' => 'duckduckgo',
				'query'    => $query,
				'error'    => 'Search request failed: ' . $response->get_error_message(),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return [
				'results'  => [],
				'provider' => 'duckduckgo',
				'query'    => $query,
				'error'    => 'Invalid response from search provider.',
			];
		}

		$results = [];

		// Include the abstract (top answer) if available.
		$abstract_text = (string) ( $data['AbstractText'] ?? '' );
		$abstract_url  = (string) ( $data['AbstractURL'] ?? '' );
		$abstract_src  = (string) ( $data['AbstractSource'] ?? '' );

		if ( '' !== $abstract_text && '' !== $abstract_url ) {
			$results[] = [
				'title'   => '' !== $abstract_src ? $abstract_src : $query,
				'url'     => $abstract_url,
				'snippet' => $abstract_text,
			];
		}

		// Extract related topics.
		$related_topics = $data['RelatedTopics'] ?? [];
		if ( is_array( $related_topics ) ) {
			foreach ( $related_topics as $topic ) {
				if ( count( $results ) >= $count ) {
					break;
				}

				if ( ! is_array( $topic ) ) {
					continue;
				}

				// Skip topic groups (they have a 'Topics' key instead of 'Text').
				if ( isset( $topic['Topics'] ) ) {
					// Flatten one level of topic groups.
					foreach ( $topic['Topics'] as $sub_topic ) {
						if ( count( $results ) >= $count ) {
							break;
						}
						if ( ! is_array( $sub_topic ) ) {
							continue;
						}
						$result = self::extract_ddg_topic( $sub_topic );
						if ( null !== $result ) {
							$results[] = $result;
						}
					}
					continue;
				}

				$result = self::extract_ddg_topic( $topic );
				if ( null !== $result ) {
					$results[] = $result;
				}
			}
		}

		// If DuckDuckGo returned no useful results, provide a helpful message.
		if ( empty( $results ) ) {
			return [
				'results'  => [],
				'provider' => 'duckduckgo',
				'query'    => $query,
				'tip'      => 'DuckDuckGo returned no instant answers for this query. For comprehensive web search results, configure a Brave Search API key in Gratis AI Agent settings.',
			];
		}

		return [
			'results'  => $results,
			'provider' => 'duckduckgo',
			'query'    => $query,
		];
	}

	/**
	 * Extract a result entry from a DuckDuckGo RelatedTopics item.
	 *
	 * @param array<string,mixed> $topic DuckDuckGo topic array.
	 * @return array<string,string>|null Result array or null if the topic is unusable.
	 */
	private static function extract_ddg_topic( array $topic ): ?array {
		$text      = (string) ( $topic['Text'] ?? '' );
		$first_url = (string) ( $topic['FirstURL'] ?? '' );

		if ( '' === $text || '' === $first_url ) {
			return null;
		}

		// Extract a title from the text (first sentence or up to 80 chars).
		$title = $text;
		$dot   = strpos( $text, '. ' );
		if ( false !== $dot && $dot < 80 ) {
			$title = substr( $text, 0, $dot );
		} elseif ( strlen( $text ) > 80 ) {
			$title = substr( $text, 0, 77 ) . '...';
		}

		return [
			'title'   => $title,
			'url'     => $first_url,
			'snippet' => $text,
		];
	}

	/**
	 * Get the configured Brave Search API key.
	 *
	 * @return string Empty string when not configured.
	 */
	public static function get_brave_api_key(): string {
		$key = get_option( self::BRAVE_KEY_OPTION, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Persist the Brave Search API key.
	 *
	 * Pass an empty string to clear the key.
	 *
	 * @param string $api_key The Brave Search API key.
	 * @return bool True on success.
	 */
	public static function set_brave_api_key( string $api_key ): bool {
		if ( '' === $api_key ) {
			return delete_option( self::BRAVE_KEY_OPTION );
		}
		return update_option( self::BRAVE_KEY_OPTION, $api_key );
	}
}
