<?php

declare(strict_types=1);
/**
 * Register knowledge-related WordPress abilities (tools) for the AI agent.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Knowledge\Knowledge;
use WP_Error;

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
			'gratis-ai-agent/knowledge-search',
			[
				'label'         => __( 'Search Knowledge Base', 'gratis-ai-agent' ),
				'description'   => __( 'Search the knowledge base for relevant information. Use this to find indexed documents, posts, and uploaded files.', 'gratis-ai-agent' ),
				'ability_class' => KnowledgeSearchAbility::class,
			]
		);
	}
}

/**
 * Knowledge Search ability.
 *
 * @since 1.0.0
 */
class KnowledgeSearchAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
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
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'results' => [ 'type' => 'array' ],
				'count'   => [ 'type' => 'integer' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$query = $input['query'] ?? '';

		if ( empty( $query ) ) {
			return new WP_Error( 'missing_param', __( 'Search query is required.', 'gratis-ai-agent' ) );
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

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}
