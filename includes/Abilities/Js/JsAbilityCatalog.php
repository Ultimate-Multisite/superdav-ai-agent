<?php

declare(strict_types=1);
/**
 * Catalog of client-side (browser-executed) abilities in the gratis-ai-agent-js namespace.
 *
 * This class is the single source of truth for the metadata of abilities that
 * run in the browser. The JS registry (src/abilities/registry.js) mirrors these
 * definitions. AgentLoop uses this catalog to validate client-posted descriptors
 * and reject any name not in the catalog.
 *
 * @package GratisAiAgent\Abilities\Js
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\Js;

class JsAbilityCatalog {

	/**
	 * Return all registered client-side ability descriptors.
	 *
	 * Each descriptor matches the shape expected by AgentLoop::resolve_abilities()
	 * when building synthetic WP_Ability objects from client-posted descriptors.
	 *
	 * @return list<array{
	 *   name: string,
	 *   label: string,
	 *   description: string,
	 *   category: string,
	 *   input_schema: array<string, mixed>,
	 *   output_schema: array<string, mixed>,
	 *   annotations: array<string, mixed>,
	 *   screens: string[]
	 * }>
	 */
	public static function get_descriptors(): array {
		return array(
			array(
				'name'          => 'gratis-ai-agent-js/navigate-to',
				'label'         => 'Navigate to Admin Page',
				'description'   => 'Navigate to a WordPress admin page without a full page reload when inside the admin SPA.',
				'category'      => 'gratis-ai-agent-js',
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'path' => array(
							'type'        => 'string',
							'description' => 'wp-admin-relative path, e.g. "plugins.php" or "edit.php?post_type=page".',
						),
					),
					'required'   => array( 'path' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'navigated' => array( 'type' => 'boolean' ),
						'path'      => array( 'type' => 'string' ),
					),
				),
				'annotations'   => array(
					'readonly' => true,
				),
				'screens'       => array( 'all' ),
			),
			array(
				'name'          => 'gratis-ai-agent-js/insert-block',
				'label'         => 'Insert Block',
				'description'   => 'Insert a Gutenberg block into the active block editor. Only available on editor screens.',
				'category'      => 'gratis-ai-agent-js',
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'blockName'  => array(
							'type'        => 'string',
							'description' => 'Block name, e.g. "core/paragraph".',
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'Block attributes.',
						),
						'innerHTML'  => array(
							'type'        => 'string',
							'description' => 'Optional inner HTML for the block.',
						),
					),
					'required'   => array( 'blockName' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'inserted'  => array( 'type' => 'boolean' ),
						'clientId'  => array( 'type' => 'string' ),
						'blockName' => array( 'type' => 'string' ),
					),
				),
				'annotations'   => array(
					'readonly' => false,
				),
				'screens'       => array( 'editor' ),
			),
		);
	}

	/**
	 * Return a map of ability name → descriptor for fast lookup.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_descriptors_by_name(): array {
		$map = array();
		foreach ( self::get_descriptors() as $descriptor ) {
			$map[ $descriptor['name'] ] = $descriptor;
		}
		return $map;
	}

	/**
	 * Check whether a given ability name is in the catalog.
	 *
	 * @param string $name Ability name to check.
	 * @return bool
	 */
	public static function has( string $name ): bool {
		$map = self::get_descriptors_by_name();
		return isset( $map[ $name ] );
	}
}
