<?php

declare(strict_types=1);
/**
 * Register memory-related WordPress abilities (tools) for the AI agent.
 *
 * @package AiAgent
 */

namespace AiAgent\Abilities;

use AiAgent\Models\Memory;

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
			'ai-agent/memory-save',
			[
				'label'               => __( 'Save Memory', 'ai-agent' ),
				'description'         => __( 'Save a piece of information to persistent memory. Use this to remember facts, preferences, or context for future conversations.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
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
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_memory_save' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'ai-agent/memory-list',
			[
				'label'               => __( 'List Memories', 'ai-agent' ),
				'description'         => __( 'List all stored memories, grouped by category.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_memory_list' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'ai-agent/memory-delete',
			[
				'label'               => __( 'Delete Memory', 'ai-agent' ),
				'description'         => __( 'Delete a specific memory by its ID.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'The memory ID to delete',
						],
					],
					'required'   => [ 'id' ],
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_memory_delete' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);
	}

	/**
	 * Handle the memory-save ability call.
	 *
	 * @param array $input Input with category and content.
	 * @return array Result.
	 */
	public static function handle_memory_save( array $input ): array {
		$category = $input['category'] ?? 'general';
		$content  = $input['content'] ?? '';

		if ( empty( $content ) ) {
			return [ 'error' => 'Content is required.' ];
		}

		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return [ 'error' => 'Failed to save memory.' ];
		}

		return [
			'success' => true,
			'id'      => $id,
			'message' => "Memory saved (ID: $id, category: $category).",
		];
	}

	/**
	 * Handle the memory-list ability call.
	 *
	 * @return array Result.
	 */
	public static function handle_memory_list(): array {
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

	/**
	 * Handle the memory-delete ability call.
	 *
	 * @param array $input Input with id.
	 * @return array Result.
	 */
	public static function handle_memory_delete( array $input ): array {
		$id = $input['id'] ?? 0;

		if ( empty( $id ) ) {
			return [ 'error' => 'Memory ID is required.' ];
		}

		$deleted = Memory::delete( (int) $id );

		if ( ! $deleted ) {
			return [ 'error' => 'Failed to delete memory or memory not found.' ];
		}

		return [
			'success' => true,
			'message' => "Memory $id deleted.",
		];
	}
}
