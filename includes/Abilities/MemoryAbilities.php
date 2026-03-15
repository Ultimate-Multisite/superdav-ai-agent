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
				'label'               => __( 'Save Memory', 'gratis-ai-agent' ),
				'description'         => __( 'Save a piece of information to persistent memory. Use this to remember facts, preferences, or context for future conversations.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
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
				'execute_callback'    => [ __CLASS__, 'handle_memory_save' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'gratis-ai-agent/memory-list',
			[
				'label'               => __( 'List Memories', 'gratis-ai-agent' ),
				'description'         => __( 'List all stored memories, grouped by category.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'execute_callback'    => [ __CLASS__, 'handle_memory_list' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);

		wp_register_ability(
			'gratis-ai-agent/memory-delete',
			[
				'label'               => __( 'Delete Memory', 'gratis-ai-agent' ),
				'description'         => __( 'Delete a specific memory by its ID.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
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
				'execute_callback'    => [ __CLASS__, 'handle_memory_delete' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ); },
			]
		);
	}

	/**
	 * Handle the memory-save ability call.
	 *
	 * @param array<string, mixed> $input Input with category and content.
	 * @return array<string, mixed>|\WP_Error Result or WP_Error on failure.
	 */
	public static function handle_memory_save( array $input ): array|\WP_Error {
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

	/**
	 * Handle the memory-list ability call.
	 *
	 * @return array<string, mixed> Result.
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
	 * @param array<string, mixed> $input Input with id.
	 * @return array<string, mixed>|\WP_Error Result or WP_Error on failure.
	 */
	public static function handle_memory_delete( array $input ): array|\WP_Error {
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
}
