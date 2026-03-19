<?php

declare(strict_types=1);
/**
 * Register knowledge-related WordPress abilities (tools) for the AI agent.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Knowledge\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KnowledgeAbilities {

	/**
	 * Register knowledge abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the knowledge search ability.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/knowledge-search',
			[
				'label'               => __( 'Search Knowledge Base', 'gratis-ai-agent' ),
				'description'         => __( 'Search the knowledge base for relevant information. Use this to find indexed documents, posts, and uploaded files.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'query'      => [
							'type'        => 'string',
							'description' => 'The search query to find relevant knowledge.',
						],
						'collection' => [
							'type'        => 'string',
							'description' => 'Optional collection slug to search within. Leave empty to search all collections.',
						],
					],
					'required'   => [ 'query' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'results' => [ 'type' => 'array' ],
						'count'   => [ 'type' => 'integer' ],
						'message' => [ 'type' => 'string' ],
						'error'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => [ __CLASS__, 'handle_knowledge_search' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);
	}

	/**
	 * Handle the knowledge-search ability call.
	 *
	 * @param array<string,mixed> $input Input with query and optional collection.
	 * @return array<string,mixed>|\WP_Error Result.
	 */
	public static function handle_knowledge_search( array $input ) {
		$query = $input['query'] ?? '';

		if ( empty( $query ) ) {
			return new \WP_Error( 'missing_query', 'Search query is required.' );
		}

		$options = [ 'limit' => 8 ];

		if ( ! empty( $input['collection'] ) ) {
			$options['collection'] = $input['collection'];
		}

		$results = Knowledge::search( $query, $options );

		if ( empty( $results ) ) {
			return [ 'message' => 'No relevant knowledge found for that query.' ];
		}

		$formatted = [];
		foreach ( $results as $result ) {
			$entry = [
				'text'       => $result['chunk_text'],
				'source'     => $result['source_title'],
				'collection' => $result['collection_name'],
			];

			if ( ! empty( $result['source_url'] ) ) {
				$entry['url'] = $result['source_url'];
			}

			$formatted[] = $entry;
		}

		return [
			'results' => $formatted,
			'count'   => count( $formatted ),
		];
	}
}
