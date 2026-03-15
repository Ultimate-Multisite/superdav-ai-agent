<?php

declare(strict_types=1);
/**
 * Register memory-related WordPress abilities (tools) for the AI agent.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\Memory;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MemoryAbilities {

	/**
	 * Register memory abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the three memory abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/memory-save',
			[
				'label'         => __( 'Save Memory', 'gratis-ai-agent' ),
				'description'   => __( 'Save a piece of information to persistent memory. Use this to remember facts, preferences, or context for future conversations.', 'gratis-ai-agent' ),
				'ability_class' => MemorySaveAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/memory-list',
			[
				'label'         => __( 'List Memories', 'gratis-ai-agent' ),
				'description'   => __( 'List all stored memories, grouped by category.', 'gratis-ai-agent' ),
				'ability_class' => MemoryListAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/memory-delete',
			[
				'label'         => __( 'Delete Memory', 'gratis-ai-agent' ),
				'description'   => __( 'Delete a specific memory by its ID.', 'gratis-ai-agent' ),
				'ability_class' => MemoryDeleteAbility::class,
			]
		);
	}
}

/**
 * Save Memory ability.
 *
 * @since 1.0.0
 */
class MemorySaveAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'category' => [
					'type'        => 'string',
					'description' => 'Memory category: site_info, user_preferences, technical_notes, workflows, or general',
					'enum'        => Memory::CATEGORIES,
				],
				'content'  => [
					'type'        => 'string',
					'description' => 'The information to remember',
				],
			],
			'required'   => [ 'category', 'content' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'id'      => [ 'type' => 'integer' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$category = $input['category'] ?? 'general';
		$content  = $input['content'] ?? '';

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_param', __( 'Content is required.', 'gratis-ai-agent' ) );
		}

		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'save_failed', __( 'Failed to save memory.', 'gratis-ai-agent' ) );
		}

		return [
			'success' => true,
			'id'      => $id,
			'message' => "Memory saved (ID: $id, category: $category).",
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * List Memories ability.
 *
 * @since 1.0.0
 */
class MemoryListAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'memories' => [ 'type' => 'array' ],
				'message'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$memories = Memory::get_all();

		if ( empty( $memories ) ) {
			return [ 'message' => 'No memories stored yet.' ];
		}

		$list = [];
		foreach ( $memories as $m ) {
			$list[] = [
				'id'       => (int) $m->id,
				'category' => $m->category,
				'content'  => $m->content,
			];
		}

		return [ 'memories' => $list ];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Delete Memory ability.
 *
 * @since 1.0.0
 */
class MemoryDeleteAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => 'The memory ID to delete',
				],
			],
			'required'   => [ 'id' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$id = $input['id'] ?? 0;

		if ( empty( $id ) ) {
			return new WP_Error( 'missing_param', __( 'Memory ID is required.', 'gratis-ai-agent' ) );
		}

		$deleted = Memory::delete( (int) $id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete memory or memory not found.', 'gratis-ai-agent' ) );
		}

		return [
			'success' => true,
			'message' => "Memory $id deleted.",
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}
