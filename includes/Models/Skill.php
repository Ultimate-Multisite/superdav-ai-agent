<?php

declare(strict_types=1);
/**
 * Skill model — on-demand instruction guides for the AI agent.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

class Skill {

	/**
	 * Get the skills table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		/** @var \wpdb $wpdb */
		return $wpdb->prefix . 'gratis_ai_agent_skills';
	}

	/**
	 * Get all skills, optionally filtered by enabled status.
	 *
	 * @param bool|null $enabled Filter by enabled status (null = all).
	 * @return list<object>|null
	 */
	public static function get_all( ?bool $enabled = null ): ?array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = self::table_name();

		if ( null !== $enabled ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE enabled = %d ORDER BY name ASC',
					$table,
					$enabled ? 1 : 0
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY name ASC',
				$table
			)
		);
	}

	/**
	 * Get a single skill by ID.
	 *
	 * @param int $id Skill ID.
	 * @return object|null
	 */
	public static function get( int $id ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table_name(),
				$id
			)
		);
	}

	/**
	 * Get a single skill by slug.
	 *
	 * @param string $slug Skill slug.
	 * @return object|null
	 */
	public static function get_by_slug( string $slug ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE slug = %s',
				self::table_name(),
				$slug
			)
		);
	}

	/**
	 * Create a new skill.
	 *
	 * @param array<string, mixed> $data Skill data: slug, name, description, content, is_builtin, enabled.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				// @phpstan-ignore-next-line
				'slug'        => sanitize_title( $data['slug'] ?? '' ),
				// @phpstan-ignore-next-line
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				// @phpstan-ignore-next-line
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				// Skill content is markdown for AI consumption, not HTML for browser rendering.
				// It is stored verbatim; SQL injection is prevented by $wpdb->insert() parameterisation.
				// @phpstan-ignore-next-line
				'content'     => $data['content'] ?? '',
				'is_builtin'  => ! empty( $data['is_builtin'] ) ? 1 : 0,
				'enabled'     => isset( $data['enabled'] ) ? ( $data['enabled'] ? 1 : 0 ) : 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing skill.
	 *
	 * @param int                  $id   Skill ID.
	 * @param array<string, mixed> $data Fields to update (name, description, content, enabled).
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$allowed = [ 'name', 'description', 'content', 'enabled' ];
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		if ( isset( $data['name'] ) ) {
			// @phpstan-ignore-next-line
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			// @phpstan-ignore-next-line
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}
		// Skill content is markdown for AI consumption, not HTML for browser rendering.
		// Stored verbatim; SQL injection is prevented by $wpdb->update() parameterisation.
		// No sanitization needed: content only flows through REST/admin, never rendered as raw HTML.

		if ( isset( $data['enabled'] ) ) {
			$data['enabled'] = $data['enabled'] ? 1 : 0;
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( $key === 'enabled' ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a skill by ID (refuses built-in skills).
	 *
	 * @param int $id Skill ID.
	 * @return bool|string True on success, error message string if built-in.
	 */
	public static function delete( int $id ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$skill = self::get( $id );

		if ( ! $skill ) {
			return false;
		}

		if ( (int) $skill->is_builtin === 1 ) {
			return 'builtin';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Reset a built-in skill to its original content.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function reset_builtin( int $id ): bool {
		$skill = self::get( $id );

		if ( ! $skill || (int) $skill->is_builtin !== 1 ) {
			return false;
		}

		$builtins = self::get_builtin_definitions();

		if ( ! isset( $builtins[ $skill->slug ] ) ) {
			return false;
		}

		$definition = $builtins[ $skill->slug ];

		return self::update(
			$id,
			[
				// @phpstan-ignore-next-line
				'name'        => $definition['name'],
				// @phpstan-ignore-next-line
				'description' => $definition['description'],
				// @phpstan-ignore-next-line
				'content'     => $definition['content'],
			]
		);
	}

	/**
	 * Get a compact skill index for the system prompt (enabled skills only).
	 *
	 * @return string Formatted index or empty string if no skills enabled.
	 */
	public static function get_index_for_prompt(): string {
		$skills = self::get_all( true );

		if ( empty( $skills ) ) {
			return '';
		}

		$lines = [];
		foreach ( $skills as $skill ) {
			// @phpstan-ignore-next-line
			$lines[] = "- {$skill->slug}: {$skill->description}";
		}

		return "## Available Skills\n"
			. "You have access to specialized skill guides. When a user's request matches a skill topic,\n"
			. "use the gratis-ai-agent/skill-load tool to load the full instructions before proceeding.\n\n"
			. "Available skills:\n"
			. implode( "\n", $lines );
	}

	/**
	 * Idempotent seeding of built-in skills (skips if slug exists).
	 */
	public static function seed_builtins(): void {
		foreach ( self::get_builtin_definitions() as $slug => $definition ) {
			$existing = self::get_by_slug( $slug );

			if ( $existing ) {
				continue;
			}

			self::create(
				[
					'slug'        => $slug,
					// @phpstan-ignore-next-line
					'name'        => $definition['name'],
					// @phpstan-ignore-next-line
					'description' => $definition['description'],
					// @phpstan-ignore-next-line
					'content'     => $definition['content'],
					'is_builtin'  => true,
					// @phpstan-ignore-next-line
					'enabled'     => $definition['enabled'],
				]
			);
		}
	}

	/**
	 * Directory containing built-in skill markdown files.
	 */
	const SKILLS_DIR = __DIR__ . '/skills';

	/**
	 * Built-in skill metadata: slug => [name, description, enabled].
	 *
	 * Content is loaded from markdown files in the skills/ directory.
	 * Each file is named {slug}.md and contains the full skill instructions.
	 *
	 * @var array<string, array{name: string, description: string, enabled: bool}>
	 */
	private const BUILTIN_META = [
		'wordpress-admin'      => [
			'name'        => 'WordPress Administration',
			'description' => 'General WordPress administration (settings, updates, users, options)',
			'enabled'     => true,
		],
		'content-management'   => [
			'name'        => 'Content Management',
			'description' => 'Managing posts, pages, media, taxonomies',
			'enabled'     => true,
		],
		'woocommerce'          => [
			'name'        => 'WooCommerce Store Management',
			'description' => 'WooCommerce store management (products, orders, coupons)',
			'enabled'     => false,
		],
		'site-troubleshooting' => [
			'name'        => 'Site Troubleshooting',
			'description' => 'Debugging errors, site health, performance diagnosis',
			'enabled'     => true,
		],
		'multisite-management' => [
			'name'        => 'Multisite Network Management',
			'description' => 'WordPress Multisite network administration',
			'enabled'     => false,
		],
		'seo-optimization'     => [
			'name'        => 'SEO Optimization',
			'description' => 'SEO auditing, on-page optimization, meta tags, technical SEO checks',
			'enabled'     => true,
		],
		'content-marketing'    => [
			'name'        => 'Content Marketing',
			'description' => 'Content strategy, editorial workflows, content audits, publishing analysis',
			'enabled'     => true,
		],
		'competitive-analysis' => [
			'name'        => 'Competitive Analysis',
			'description' => 'Analyzing competitor sites, tech stack discovery, content gap analysis',
			'enabled'     => false,
		],
		'analytics-reporting'  => [
			'name'        => 'Analytics & Reporting',
			'description' => 'Content performance reports, site growth metrics, publishing analytics',
			'enabled'     => true,
		],
		'gutenberg-blocks'     => [
			'name'        => 'Gutenberg Blocks',
			'description' => 'Creating content with Gutenberg blocks, converting markdown, building layouts',
			'enabled'     => true,
		],
		'full-site-editing'    => [
			'name'        => 'Full Site Editing',
			'description' => 'Block theme templates, template parts, site-wide layout customization',
			'enabled'     => false,
		],
	];

	/**
	 * Return the built-in skill definitions.
	 *
	 * Metadata is defined in BUILTIN_META. Content is loaded from
	 * markdown files in the skills/ directory ({slug}.md).
	 *
	 * @return array<string, array{name: string, description: string, enabled: bool, content: string}> Keyed by slug.
	 */
	public static function get_builtin_definitions(): array {
		$definitions = [];

		foreach ( self::BUILTIN_META as $slug => $meta ) {
			$definitions[ $slug ] = array_merge(
				$meta,
				[ 'content' => self::load_skill_file( $slug ) ]
			);
		}

		return $definitions;
	}

	/**
	 * Load a built-in skill's markdown content from disk.
	 *
	 * @param string $slug The skill slug (matches the .md filename).
	 * @return string The markdown content, or an empty string if the file is missing.
	 */
	private static function load_skill_file( string $slug ): string {
		$path = self::SKILLS_DIR . '/' . $slug . '.md';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local bundled plugin file

		return false !== $content ? trim( $content ) : '';
	}
}
