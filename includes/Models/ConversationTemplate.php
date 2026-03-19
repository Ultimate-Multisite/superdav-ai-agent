<?php

declare(strict_types=1);
/**
 * Conversation template model — pre-built prompts for common tasks.
 *
 * Built-in templates are seeded on install and cannot be deleted (only
 * hidden). User-created templates are fully editable and deletable.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

class ConversationTemplate {

	/**
	 * Get the templates table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_conversation_templates';
	}

	/**
	 * Built-in templates seeded on install.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function get_builtins(): array {
		return [
			[
				'slug'        => 'summarise-page',
				'name'        => 'Summarise this page',
				'description' => 'Get a concise summary of the current page content.',
				'prompt'      => 'Please summarise the content of this page in 3–5 bullet points.',
				'category'    => 'content',
				'icon'        => 'editor-ul',
			],
			[
				'slug'        => 'improve-writing',
				'name'        => 'Improve my writing',
				'description' => 'Rewrite selected text to be clearer and more professional.',
				'prompt'      => 'Please improve the following text to make it clearer, more concise, and professional:\n\n',
				'category'    => 'writing',
				'icon'        => 'edit',
			],
			[
				'slug'        => 'fix-grammar',
				'name'        => 'Fix grammar & spelling',
				'description' => 'Correct grammar and spelling errors in your text.',
				'prompt'      => 'Please fix any grammar and spelling errors in the following text, keeping the original meaning:\n\n',
				'category'    => 'writing',
				'icon'        => 'editor-spellcheck',
			],
			[
				'slug'        => 'translate-text',
				'name'        => 'Translate text',
				'description' => 'Translate text into another language.',
				'prompt'      => 'Please translate the following text to English (or specify a target language):\n\n',
				'category'    => 'writing',
				'icon'        => 'translation',
			],
			[
				'slug'        => 'explain-code',
				'name'        => 'Explain this code',
				'description' => 'Get a plain-English explanation of a code snippet.',
				'prompt'      => 'Please explain what the following code does in plain English:\n\n```\n\n```',
				'category'    => 'development',
				'icon'        => 'editor-code',
			],
			[
				'slug'        => 'debug-code',
				'name'        => 'Debug my code',
				'description' => 'Find and fix bugs in a code snippet.',
				'prompt'      => 'Please review the following code, identify any bugs or issues, and suggest fixes:\n\n```\n\n```',
				'category'    => 'development',
				'icon'        => 'warning',
			],
			[
				'slug'        => 'write-post',
				'name'        => 'Write a blog post',
				'description' => 'Draft a blog post on a topic.',
				'prompt'      => 'Please write a blog post about the following topic. Include an engaging introduction, 3–4 main sections with subheadings, and a conclusion:\n\n',
				'category'    => 'content',
				'icon'        => 'admin-post',
			],
			[
				'slug'        => 'seo-meta',
				'name'        => 'Generate SEO meta',
				'description' => 'Create an SEO title and meta description for a page.',
				'prompt'      => 'Please generate an SEO-optimised title (under 60 characters) and meta description (under 160 characters) for a page about:\n\n',
				'category'    => 'seo',
				'icon'        => 'search',
			],
			[
				'slug'        => 'brainstorm-ideas',
				'name'        => 'Brainstorm ideas',
				'description' => 'Generate a list of creative ideas on a topic.',
				'prompt'      => 'Please brainstorm 10 creative ideas for:\n\n',
				'category'    => 'content',
				'icon'        => 'lightbulb',
			],
			[
				'slug'        => 'answer-question',
				'name'        => 'Answer a question',
				'description' => 'Get a detailed answer to any question.',
				'prompt'      => 'Please provide a detailed and accurate answer to the following question:\n\n',
				'category'    => 'general',
				'icon'        => 'editor-help',
			],
		];
	}

	/**
	 * Seed built-in templates into the database.
	 * Skips templates that already exist (by slug).
	 */
	public static function seed_builtins(): void {
		global $wpdb;

		$table = self::table_name();
		$now   = current_time( 'mysql' );

		foreach ( self::get_builtins() as $template ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE slug = %s',
					$table,
					$template['slug']
				)
			);

			if ( $exists ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$wpdb->insert(
				$table,
				[
					'slug'        => $template['slug'],
					'name'        => $template['name'],
					'description' => $template['description'],
					'prompt'      => $template['prompt'],
					'category'    => $template['category'],
					'icon'        => $template['icon'],
					'is_builtin'  => 1,
					'sort_order'  => 0,
					'created_at'  => $now,
					'updated_at'  => $now,
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
			);
		}
	}

	/**
	 * Get all templates, optionally filtered by category.
	 *
	 * @param string|null $category Filter by category (null = all).
	 * @return array<int, object>
	 */
	public static function get_all( ?string $category = null ): array {
		global $wpdb;

		$table = self::table_name();

		if ( null !== $category && '' !== $category ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE category = %s ORDER BY sort_order ASC, name ASC',
					$table,
					$category
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY sort_order ASC, name ASC',
				$table
			)
		);
	}

	/**
	 * Get a single template by ID.
	 *
	 * @param int $id Template ID.
	 * @return object|null
	 */
	public static function get( int $id ) {
		global $wpdb;

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
	 * Create a new template.
	 *
	 * @param array<string, mixed> $data Template data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'slug'        => $data['slug'] ?? self::generate_slug( $data['name'] ?? '' ),
				'name'        => $data['name'] ?? '',
				'description' => $data['description'] ?? '',
				'prompt'      => $data['prompt'] ?? '',
				'category'    => $data['category'] ?? 'general',
				'icon'        => $data['icon'] ?? 'admin-comments',
				'is_builtin'  => 0,
				'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing template.
	 *
	 * @param int                  $id   Template ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True on success.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [ 'name', 'description', 'prompt', 'category', 'icon', 'sort_order' ];
		$update  = [];
		$formats = [];

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			$update[ $field ] = $data[ $field ];
			$formats[]        = 'sort_order' === $field ? '%d' : '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		$update['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update(
			self::table_name(),
			$update,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a template. Built-in templates cannot be deleted.
	 *
	 * @param int $id Template ID.
	 * @return bool True on success.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$template = self::get( $id );
		if ( ! $template ) {
			return false;
		}

		// Prevent deletion of built-in templates.
		if ( (int) $template->is_builtin === 1 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Get all distinct categories.
	 *
	 * @return string[]
	 */
	public static function get_categories(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT category FROM %i ORDER BY category ASC',
				self::table_name()
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Generate a URL-safe slug from a name.
	 *
	 * @param string $name Template name.
	 * @return string
	 */
	private static function generate_slug( string $name ): string {
		return sanitize_title( $name ) . '-' . wp_generate_password( 4, false );
	}
}
