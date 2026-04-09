<?php

declare(strict_types=1);
/**
 * Benchmark suite management.
 *
 * Loads and provides access to test questions from suite files.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Benchmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BenchmarkSuite {

	/**
	 * Get all available benchmark suites.
	 *
	 * @return array
	 */
	public static function list_suites(): array {
		return array(
			array(
				'slug'           => 'wp-core-v1',
				'name'           => __( 'WordPress Core v1', 'gratis-ai-agent' ),
				'description'    => __( 'Tests knowledge of WordPress core APIs, hooks, coding standards, and best practices.', 'gratis-ai-agent' ),
				'question_count' => count( self::get_wp_core_questions() ),
			),
			array(
				'slug'           => 'wp-quick',
				'name'           => __( 'WordPress Quick Test', 'gratis-ai-agent' ),
				'description'    => __( 'A quick 5-question test for rapid model evaluation.', 'gratis-ai-agent' ),
				'question_count' => 5,
			),
			array(
				'slug'           => 'agent-capabilities-v1',
				'name'           => __( 'Agent Capabilities v1', 'gratis-ai-agent' ),
				'description'    => __( 'Tests complex reasoning, code generation, debugging, multi-step problem solving, and architecture decisions. Designed to differentiate capable models like Opus from simpler ones.', 'gratis-ai-agent' ),
				'question_count' => count( self::get_agent_capabilities_questions() ),
			),
		);
	}

	/**
	 * Get a specific suite with all questions.
	 *
	 * @param string $slug Suite slug.
	 * @return array|null
	 */
	public static function get_suite( string $slug ): ?array {
		switch ( $slug ) {
			case 'wp-core-v1':
				return array(
					'slug'        => 'wp-core-v1',
					'name'        => __( 'WordPress Core v1', 'gratis-ai-agent' ),
					'description' => __( 'Tests knowledge of WordPress core APIs, hooks, coding standards, and best practices.', 'gratis-ai-agent' ),
					'questions'   => self::get_wp_core_questions(),
				);

			case 'wp-quick':
				$all = self::get_wp_core_questions();
				return array(
					'slug'        => 'wp-quick',
					'name'        => __( 'WordPress Quick Test', 'gratis-ai-agent' ),
					'description' => __( 'A quick 5-question test for rapid model evaluation.', 'gratis-ai-agent' ),
					'questions'   => array_slice( $all, 0, 5 ),
				);

			case 'agent-capabilities-v1':
				return array(
					'slug'        => 'agent-capabilities-v1',
					'name'        => __( 'Agent Capabilities v1', 'gratis-ai-agent' ),
					'description' => __( 'Tests complex reasoning, code generation, debugging, multi-step problem solving, and architecture decisions. Designed to differentiate capable models like Opus from simpler ones.', 'gratis-ai-agent' ),
					'questions'   => self::get_agent_capabilities_questions(),
				);

			default:
				return null;
		}
	}

	/**
	 * Get questions for a suite.
	 *
	 * @param string $slug Suite slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_questions( string $slug ): array {
		$suite = self::get_suite( $slug );
		if ( ! $suite ) {
			return array();
		}
		/** @var array<int, array<string, mixed>> $questions */
		$questions = $suite['questions'];
		return $questions;
	}

	/**
	 * Get WordPress Core v1 test questions.
	 *
	 * Inspired by wp-bench dataset format.
	 *
	 * @return array
	 */
	private static function get_wp_core_questions(): array {
		return array(
			// General WordPress Knowledge
			array(
				'id'             => 'wp-001',
				'category'       => 'general',
				'type'           => 'knowledge',
				'question'       => 'What is the correct way to enqueue a JavaScript file in WordPress?',
				'options'        => array(
					'A' => 'wp_script_enqueue( "my-script", "/path/to/script.js" );',
					'B' => 'wp_enqueue_script( "my-script", "/path/to/script.js", array(), "1.0", true );',
					'C' => 'add_script( "my-script", "/path/to/script.js" );',
					'D' => 'enqueue_script( "/path/to/script.js" );',
				),
				'correct_answer' => 'B',
				'explanation'    => 'wp_enqueue_script() is the correct function, with parameters for handle, source, dependencies, version, and footer placement.',
			),
			array(
				'id'             => 'wp-002',
				'category'       => 'general',
				'type'           => 'knowledge',
				'question'       => 'Which hook should you use to add functionality that runs after WordPress has finished loading but before any headers are sent?',
				'options'        => array(
					'A' => 'wp_loaded',
					'B' => 'init',
					'C' => 'wp_head',
					'D' => 'template_redirect',
				),
				'correct_answer' => 'B',
				'explanation'    => 'The init hook fires after WordPress has finished loading but before any headers are sent. Most of WP is set up at this stage.',
			),
			array(
				'id'             => 'wp-003',
				'category'       => 'general',
				'type'           => 'knowledge',
				'question'       => 'What is the recommended way to check if the current user has a specific capability?',
				'options'        => array(
					'A' => 'user_can( "edit_posts" )',
					'B' => 'current_user_can( "edit_posts" )',
					'C' => 'has_cap( "edit_posts" )',
					'D' => 'wp_user_can( "edit_posts" )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'current_user_can() is the proper function to check if the current user has a specific capability or role.',
			),
			array(
				'id'             => 'wp-004',
				'category'       => 'general',
				'type'           => 'knowledge',
				'question'       => 'What is the correct prefix for all WordPress database tables by default?',
				'options'        => array(
					'A' => 'wp_',
					'B' => 'wordpress_',
					'C' => 'db_',
					'D' => 'tbl_',
				),
				'correct_answer' => 'A',
				'explanation'    => 'The default table prefix is wp_, but this can be changed during installation for security purposes.',
			),
			array(
				'id'             => 'wp-005',
				'category'       => 'general',
				'type'           => 'knowledge',
				'question'       => 'Which function is used to create a nonce in WordPress for security purposes?',
				'options'        => array(
					'A' => 'create_nonce()',
					'B' => 'wp_nonce()',
					'C' => 'wp_create_nonce()',
					'D' => 'generate_nonce()',
				),
				'correct_answer' => 'C',
				'explanation'    => 'wp_create_nonce() generates a cryptographic nonce for use in forms and URLs to prevent CSRF attacks.',
			),

			// Database and Queries
			array(
				'id'             => 'wp-006',
				'category'       => 'database',
				'type'           => 'knowledge',
				'question'       => 'Which class should be used for custom database queries in WordPress?',
				'options'        => array(
					'A' => 'WP_Query',
					'B' => 'wpdb',
					'C' => 'WP_Database',
					'D' => 'Query_Builder',
				),
				'correct_answer' => 'B',
				'explanation'    => 'The $wpdb class is the WordPress database access abstraction object used for custom queries.',
			),
			array(
				'id'             => 'wp-007',
				'category'       => 'database',
				'type'           => 'knowledge',
				'question'       => 'How do you properly escape a string for a SQL query in WordPress?',
				'options'        => array(
					'A' => 'mysql_real_escape_string( $string )',
					'B' => '$wpdb->escape( $string )',
					'C' => 'esc_sql( $string )',
					'D' => '$wpdb->prepare() with placeholders',
				),
				'correct_answer' => 'D',
				'explanation'    => '$wpdb->prepare() is the correct method as it prepares a SQL query with proper escaping of placeholders.',
			),
			array(
				'id'             => 'wp-008',
				'category'       => 'database',
				'type'           => 'knowledge',
				'question'       => 'Which function retrieves a single post by its ID?',
				'options'        => array(
					'A' => 'get_post_by_id( $id )',
					'B' => 'get_post( $id )',
					'C' => 'wp_get_post( $id )',
					'D' => 'fetch_post( $id )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'get_post() retrieves post data given a post ID or post object.',
			),
			array(
				'id'             => 'wp-009',
				'category'       => 'database',
				'type'           => 'knowledge',
				'question'       => 'What is the correct way to update a post meta value?',
				'options'        => array(
					'A' => 'update_meta( $post_id, "key", "value" )',
					'B' => 'update_post_meta( $post_id, "key", "value" )',
					'C' => 'set_post_meta( $post_id, "key", "value" )',
					'D' => 'save_post_meta( $post_id, "key", "value" )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'update_post_meta() updates the value of an existing meta key for the specified post.',
			),
			array(
				'id'             => 'wp-010',
				'category'       => 'database',
				'type'           => 'knowledge',
				'question'       => 'Which hook runs when a post is saved or updated?',
				'options'        => array(
					'A' => 'post_update',
					'B' => 'save_post',
					'C' => 'wp_insert_post',
					'D' => 'update_post',
				),
				'correct_answer' => 'B',
				'explanation'    => 'save_post fires once a post has been saved. It can be used for both creating and updating posts.',
			),

			// Hooks and Filters
			array(
				'id'             => 'wp-011',
				'category'       => 'hooks',
				'type'           => 'knowledge',
				'question'       => 'What is the difference between do_action() and apply_filters()?',
				'options'        => array(
					'A' => 'They are synonyms with no difference',
					'B' => 'do_action() is for actions, apply_filters() modifies and returns data',
					'C' => 'apply_filters() is for actions, do_action() modifies data',
					'D' => 'do_action() runs once, apply_filters() runs multiple times',
				),
				'correct_answer' => 'B',
				'explanation'    => 'do_action() executes hooked functions without returning a value. apply_filters() passes data through functions and returns the modified value.',
			),
			array(
				'id'             => 'wp-012',
				'category'       => 'hooks',
				'type'           => 'knowledge',
				'question'       => 'Which function is used to add a filter hook?',
				'options'        => array(
					'A' => 'add_hook()',
					'B' => 'add_action()',
					'C' => 'add_filter()',
					'D' => 'register_filter()',
				),
				'correct_answer' => 'C',
				'explanation'    => 'add_filter() hooks a function to a specific filter action, allowing modification of data.',
			),
			array(
				'id'             => 'wp-013',
				'category'       => 'hooks',
				'type'           => 'knowledge',
				'question'       => 'What is the default priority for hooks in WordPress?',
				'options'        => array(
					'A' => '0',
					'B' => '1',
					'C' => '10',
					'D' => '100',
				),
				'correct_answer' => 'C',
				'explanation'    => 'The default priority is 10. Lower numbers run earlier, higher numbers run later.',
			),
			array(
				'id'             => 'wp-014',
				'category'       => 'hooks',
				'type'           => 'knowledge',
				'question'       => 'Which hook fires when WordPress is determining which template to load?',
				'options'        => array(
					'A' => 'template_include',
					'B' => 'template_redirect',
					'C' => 'get_template_part',
					'D' => 'load_template',
				),
				'correct_answer' => 'A',
				'explanation'    => 'template_include is the filter hook used to return the template file path before it\'s loaded.',
			),
			array(
				'id'             => 'wp-015',
				'category'       => 'hooks',
				'type'           => 'knowledge',
				'question'       => 'How do you remove an action that was added by a plugin?',
				'options'        => array(
					'A' => 'delete_action()',
					'B' => 'unregister_action()',
					'C' => 'remove_action()',
					'D' => 'clear_action()',
				),
				'correct_answer' => 'C',
				'explanation'    => 'remove_action() removes a function from a specified action hook.',
			),

			// Security
			array(
				'id'             => 'wp-016',
				'category'       => 'security',
				'type'           => 'knowledge',
				'question'       => 'Which function should be used to sanitize text input from users?',
				'options'        => array(
					'A' => 'clean_text()',
					'B' => 'sanitize_text_field()',
					'C' => 'esc_text()',
					'D' => 'strip_tags()',
				),
				'correct_answer' => 'B',
				'explanation'    => 'sanitize_text_field() sanitizes a string from user input or from the database.',
			),
			array(
				'id'             => 'wp-017',
				'category'       => 'security',
				'type'           => 'knowledge',
				'question'       => 'What does wp_kses_post() do?',
				'options'        => array(
					'A' => 'Validates a post object',
					'B' => 'Sanitizes content for allowed HTML tags in posts',
					'C' => 'Checks if post data is secure',
					'D' => 'Encrypts post content',
				),
				'correct_answer' => 'B',
				'explanation'    => 'wp_kses_post() returns content with only allowed HTML tags and attributes for post content.',
			),
			array(
				'id'             => 'wp-018',
				'category'       => 'security',
				'type'           => 'knowledge',
				'question'       => 'Which constant should be defined to disable file editing in the WordPress admin?',
				'options'        => array(
					'A' => 'DISABLE_FILE_EDIT',
					'B' => 'DISALLOW_FILE_EDIT',
					'C' => 'WP_NO_FILE_EDIT',
					'D' => 'SECURE_FILE_EDIT',
				),
				'correct_answer' => 'B',
				'explanation'    => 'DISALLOW_FILE_EDIT disables the file editor in the WordPress admin for themes and plugins.',
			),
			array(
				'id'             => 'wp-019',
				'category'       => 'security',
				'type'           => 'knowledge',
				'question'       => 'What is the purpose of check_admin_referer()?',
				'options'        => array(
					'A' => 'Checks if user is an admin',
					'B' => 'Verifies the admin referrer URL',
					'C' => 'Validates nonce for admin requests',
					'D' => 'Redirects to admin page',
				),
				'correct_answer' => 'C',
				'explanation'    => 'check_admin_referer() validates the nonce on admin forms to protect against CSRF attacks.',
			),
			array(
				'id'             => 'wp-020',
				'category'       => 'security',
				'type'           => 'knowledge',
				'question'       => 'Which function escapes HTML attributes?',
				'options'        => array(
					'A' => 'esc_attr()',
					'B' => 'esc_html()',
					'C' => 'esc_url()',
					'D' => 'sanitize_attr()',
				),
				'correct_answer' => 'A',
				'explanation'    => 'esc_attr() escapes an HTML attribute value, making it safe for output in attributes.',
			),

			// Coding Standards
			array(
				'id'             => 'wp-021',
				'category'       => 'coding',
				'type'           => 'knowledge',
				'question'       => 'What is the WordPress coding standard for naming hooks?',
				'options'        => array(
					'A' => 'camelCase',
					'B' => 'PascalCase',
					'C' => 'snake_case',
					'D' => 'kebab-case',
				),
				'correct_answer' => 'C',
				'explanation'    => 'WordPress uses snake_case (lowercase with underscores) for hook names.',
			),
			array(
				'id'             => 'wp-022',
				'category'       => 'coding',
				'type'           => 'knowledge',
				'question'       => 'Which PHP version is the minimum required for WordPress 6.9?',
				'options'        => array(
					'A' => '7.0',
					'B' => '7.4',
					'C' => '8.0',
					'D' => '8.2',
				),
				'correct_answer' => 'B',
				'explanation'    => 'WordPress 6.9 requires PHP 7.4 or higher, though 8.0+ is recommended.',
			),
			array(
				'id'             => 'wp-023',
				'category'       => 'coding',
				'type'           => 'knowledge',
				'question'       => 'What should you use instead of direct SQL queries when possible?',
				'options'        => array(
					'A' => 'Raw PHP arrays',
					'B' => 'WordPress APIs (WP_Query, get_posts, etc.)',
					'C' => 'External database libraries',
					'D' => 'JSON files',
				),
				'correct_answer' => 'B',
				'explanation'    => 'WordPress APIs like WP_Query, get_posts(), and built-in functions should be preferred over direct SQL.',
			),
			array(
				'id'             => 'wp-024',
				'category'       => 'coding',
				'type'           => 'knowledge',
				'question'       => 'What is the correct way to check if a plugin is active?',
				'options'        => array(
					'A' => 'is_plugin_active( "plugin/plugin.php" )',
					'B' => 'plugin_is_active( "plugin/plugin.php" )',
					'C' => 'active_plugins[ "plugin/plugin.php" ]',
					'D' => 'is_active_plugin( "plugin/plugin.php" )',
				),
				'correct_answer' => 'A',
				'explanation'    => 'is_plugin_active() checks if a plugin is active. Must be called from admin context or include plugin.php.',
			),
			array(
				'id'             => 'wp-025',
				'category'       => 'coding',
				'type'           => 'knowledge',
				'question'       => 'Which function is used to get the current theme directory path?',
				'options'        => array(
					'A' => 'get_theme_path()',
					'B' => 'get_template_directory()',
					'C' => 'get_current_theme_dir()',
					'D' => 'theme_directory()',
				),
				'correct_answer' => 'B',
				'explanation'    => 'get_template_directory() retrieves the absolute path to the current theme directory.',
			),
		);
	}

	/**
	 * Get Agent Capabilities v1 test questions.
	 *
	 * Tests complex reasoning, code generation, debugging, multi-step
	 * problem solving, and architecture decisions. These open-ended
	 * questions use keyword-based partial scoring (0-100) rather than
	 * binary correct/incorrect, making them effective at differentiating
	 * capable models from simpler ones.
	 *
	 * Question types:
	 * - code_generation: Write WordPress-compliant PHP code
	 * - debugging:       Identify and fix bugs in provided code
	 * - reasoning:       Multi-step logical analysis
	 * - architecture:    System design and trade-off decisions
	 * - multi_step:      Tasks requiring sequential reasoning
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_agent_capabilities_questions(): array {
		return array(
			// ── Code Generation ──────────────────────────────────────────

			array(
				'id'               => 'ac-001',
				'category'         => 'code_generation',
				'type'             => 'open_ended',
				'question'         => 'Write a WordPress REST API endpoint that accepts a POST request to create a custom "event" post type entry. The endpoint should: validate a nonce, check that the user has the "publish_posts" capability, sanitize the "title" and "date" fields from the request body, create the post, and return the new post ID. Include proper error handling with WP_Error.',
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to generate production-quality WordPress REST API code with security best practices.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'register_rest_route',
						'weight'      => 10,
						'description' => 'Uses register_rest_route to register the endpoint',
					),
					array(
						'keyword'     => 'permission_callback',
						'weight'      => 10,
						'description' => 'Includes permission_callback in route registration',
					),
					array(
						'keyword'     => 'current_user_can',
						'weight'      => 10,
						'description' => 'Checks user capabilities with current_user_can',
					),
					array(
						'keyword'     => 'sanitize_text_field',
						'weight'      => 10,
						'description' => 'Sanitizes text input with sanitize_text_field',
					),
					array(
						'keyword'     => 'wp_insert_post',
						'weight'      => 15,
						'description' => 'Uses wp_insert_post to create the post',
					),
					array(
						'keyword'     => 'WP_Error',
						'weight'      => 10,
						'description' => 'Returns WP_Error for error cases',
					),
					array(
						'keyword'     => 'WP_REST_Response',
						'weight'      => 10,
						'description' => 'Returns WP_REST_Response for success',
					),
					array(
						'keyword'     => 'wp_verify_nonce|check_ajax_referer|nonce',
						'weight'      => 10,
						'description' => 'Validates nonce for CSRF protection',
					),
					array(
						'keyword'     => 'publish_posts',
						'weight'      => 5,
						'description' => 'References the publish_posts capability',
					),
					array(
						'keyword'     => 'sanitize_text_field|sanitize_title|absint',
						'weight'      => 10,
						'description' => 'Uses appropriate sanitization functions',
					),
				),
			),

			array(
				'id'               => 'ac-002',
				'category'         => 'code_generation',
				'type'             => 'open_ended',
				'question'         => 'Write a WordPress shortcode handler that displays a filterable grid of posts. The shortcode [post_grid category="news" columns="3" limit="9"] should: query posts by category slug, support pagination via a "paged" query var, output semantic HTML with CSS class hooks, and escape all output. Include the shortcode registration call.',
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to generate complex shortcode with WP_Query, pagination, output escaping, and clean HTML structure.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'add_shortcode',
						'weight'      => 10,
						'description' => 'Registers the shortcode with add_shortcode',
					),
					array(
						'keyword'     => 'WP_Query|new WP_Query',
						'weight'      => 15,
						'description' => 'Uses WP_Query for the post query',
					),
					array(
						'keyword'     => 'category_name|tax_query',
						'weight'      => 10,
						'description' => 'Filters by category slug',
					),
					array(
						'keyword'     => 'paged|get_query_var',
						'weight'      => 10,
						'description' => 'Handles pagination with paged parameter',
					),
					array(
						'keyword'     => 'esc_html|esc_attr|esc_url',
						'weight'      => 15,
						'description' => 'Escapes output properly',
					),
					array(
						'keyword'     => 'wp_reset_postdata',
						'weight'      => 10,
						'description' => 'Resets post data after custom query',
					),
					array(
						'keyword'     => 'shortcode_atts',
						'weight'      => 10,
						'description' => 'Uses shortcode_atts for default values',
					),
					array(
						'keyword'     => 'ob_start|ob_get_clean',
						'weight'      => 10,
						'description' => 'Uses output buffering for shortcode return',
					),
					array(
						'keyword'     => 'posts_per_page',
						'weight'      => 5,
						'description' => 'Sets posts_per_page in query args',
					),
					array(
						'keyword'     => 'the_title|get_the_title',
						'weight'      => 5,
						'description' => 'Retrieves post title correctly',
					),
				),
			),

			array(
				'id'               => 'ac-003',
				'category'         => 'code_generation',
				'type'             => 'open_ended',
				'question'         => 'Write a WordPress plugin activation hook that creates a custom database table for storing API request logs. The table should have columns: id (auto-increment primary key), user_id (bigint), endpoint (varchar 255), method (varchar 10), status_code (int), response_time_ms (int), created_at (datetime). Use dbDelta() and the correct charset/collation. Include the deactivation hook to clean up the scheduled event (but NOT drop the table).',
				'correct_answer'   => '',
				'explanation'      => 'Tests knowledge of WordPress database table creation, dbDelta quirks, and activation/deactivation lifecycle.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'register_activation_hook',
						'weight'      => 10,
						'description' => 'Uses register_activation_hook',
					),
					array(
						'keyword'     => 'dbDelta',
						'weight'      => 15,
						'description' => 'Uses dbDelta for table creation',
					),
					array(
						'keyword'     => 'get_charset_collate|charset_collate',
						'weight'      => 10,
						'description' => 'Uses proper charset/collation',
					),
					array(
						'keyword'     => '\$wpdb->prefix',
						'weight'      => 10,
						'description' => 'Uses $wpdb->prefix for table name',
					),
					array(
						'keyword'     => 'AUTO_INCREMENT',
						'weight'      => 5,
						'description' => 'Sets auto-increment on primary key',
					),
					array(
						'keyword'     => 'PRIMARY KEY',
						'weight'      => 5,
						'description' => 'Defines primary key correctly',
					),
					array(
						'keyword'     => 'upgrade.php|wp-admin/includes/upgrade.php',
						'weight'      => 10,
						'description' => 'Includes upgrade.php for dbDelta',
					),
					array(
						'keyword'     => 'register_deactivation_hook',
						'weight'      => 10,
						'description' => 'Uses register_deactivation_hook',
					),
					array(
						'keyword'     => 'wp_clear_scheduled_hook|wp_unschedule_event',
						'weight'      => 10,
						'description' => 'Clears scheduled events on deactivation',
					),
					array(
						'keyword'     => 'bigint|varchar|datetime|int',
						'weight'      => 5,
						'description' => 'Uses correct SQL column types',
					),
					array(
						'keyword'     => 'NOT NULL',
						'weight'      => 5,
						'description' => 'Specifies NOT NULL constraints',
					),
					array(
						'keyword'     => 'global \$wpdb',
						'weight'      => 5,
						'description' => 'Declares global $wpdb',
					),
				),
			),

			// ── Debugging ────────────────────────────────────────────────

			array(
				'id'               => 'ac-004',
				'category'         => 'debugging',
				'type'             => 'open_ended',
				'question'         => "Find all the bugs in this WordPress code and explain each fix:\n\n```php\nfunction save_custom_meta( \$post_id ) {\n    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;\n    \n    update_post_meta( \$post_id, '_custom_field', \$_POST['custom_field'] );\n    \n    \$terms = get_the_terms( \$post_id, 'custom_tax' );\n    foreach ( \$terms as \$term ) {\n        update_term_meta( \$term->term_id, 'post_count', count( \$terms ) );\n    }\n}\nadd_action( 'save_post', 'save_custom_meta' );\n```",
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to identify multiple security and robustness bugs: missing nonce verification, missing capability check, unsanitized $_POST, missing isset check on $_POST, get_the_terms can return false/WP_Error (no type check before foreach).',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'nonce|wp_verify_nonce|check_admin_referer',
						'weight'      => 20,
						'description' => 'Identifies missing nonce verification',
					),
					array(
						'keyword'     => 'current_user_can|capability|permission',
						'weight'      => 15,
						'description' => 'Identifies missing capability check',
					),
					array(
						'keyword'     => 'sanitize|sanitize_text_field|esc_|unsanitized',
						'weight'      => 15,
						'description' => 'Identifies unsanitized $_POST data',
					),
					array(
						'keyword'     => 'isset|empty|array_key_exists|\$_POST',
						'weight'      => 10,
						'description' => 'Identifies missing isset check on $_POST key',
					),
					array(
						'keyword'     => 'false|WP_Error|is_array|is_wp_error|empty',
						'weight'      => 15,
						'description' => 'Identifies that get_the_terms can return false/WP_Error',
					),
					array(
						'keyword'     => 'wp_unslash',
						'weight'      => 5,
						'description' => 'Mentions wp_unslash for $_POST data',
					),
					array(
						'keyword'     => 'revision|wp_is_post_revision',
						'weight'      => 5,
						'description' => 'Mentions checking for post revisions',
					),
					array(
						'keyword'     => 'post_type|get_post_type',
						'weight'      => 5,
						'description' => 'Suggests checking post type before saving',
					),
				),
			),

			array(
				'id'               => 'ac-005',
				'category'         => 'debugging',
				'type'             => 'open_ended',
				'question'         => "This REST API endpoint has a critical security vulnerability and a performance problem. Identify both and provide the corrected code:\n\n```php\nfunction get_user_data( WP_REST_Request \$request ) {\n    global \$wpdb;\n    \$user_id = \$request->get_param( 'id' );\n    \$query = \"SELECT * FROM {\$wpdb->users} WHERE ID = \$user_id\";\n    \$user = \$wpdb->get_row( \$query );\n    \n    if ( ! \$user ) {\n        return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );\n    }\n    \n    \$meta = \$wpdb->get_results(\n        \"SELECT * FROM {\$wpdb->usermeta} WHERE user_id = {\$user->ID}\"\n    );\n    \n    return new WP_REST_Response( array(\n        'user' => \$user,\n        'meta' => \$meta,\n    ) );\n}\n```",
				'correct_answer'   => '',
				'explanation'      => 'SQL injection via unescaped $user_id in query string. Performance: querying all usermeta rows instead of using get_user_meta. Also exposes sensitive data (password hashes) by selecting all columns.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'SQL injection|injection|inject',
						'weight'      => 20,
						'description' => 'Identifies SQL injection vulnerability',
					),
					array(
						'keyword'     => '\$wpdb->prepare|prepare|placeholder|%d',
						'weight'      => 15,
						'description' => 'Recommends $wpdb->prepare with placeholders',
					),
					array(
						'keyword'     => 'password|user_pass|sensitive|hash',
						'weight'      => 10,
						'description' => 'Identifies exposure of sensitive user data',
					),
					array(
						'keyword'     => 'get_user_meta|get_userdata|get_user_by',
						'weight'      => 15,
						'description' => 'Recommends WordPress API instead of raw SQL',
					),
					array(
						'keyword'     => 'absint|intval|sanitize',
						'weight'      => 10,
						'description' => 'Suggests input sanitization/casting',
					),
					array(
						'keyword'     => 'performance|N\+1|all.*meta|SELECT \*',
						'weight'      => 10,
						'description' => 'Identifies the performance problem',
					),
					array(
						'keyword'     => 'permission|capability|current_user_can',
						'weight'      => 10,
						'description' => 'Notes missing permission check',
					),
					array(
						'keyword'     => 'WP_Error',
						'weight'      => 5,
						'description' => 'Suggests using WP_Error for error responses',
					),
				),
			),

			// ── Reasoning ────────────────────────────────────────────────

			array(
				'id'               => 'ac-006',
				'category'         => 'reasoning',
				'type'             => 'open_ended',
				'question'         => 'A WordPress site has 50,000 posts. The admin reports that the "All Posts" page in wp-admin takes 30 seconds to load. The theme uses a pre_get_posts filter. Walk through your diagnostic process step by step: what would you check first, second, third? What are the most likely causes? How would you confirm each hypothesis? What specific WordPress/MySQL tools or queries would you use?',
				'correct_answer'   => '',
				'explanation'      => 'Tests systematic debugging methodology, knowledge of WordPress performance bottlenecks, and familiarity with diagnostic tools.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'QUERY_MONITOR|Query Monitor|query monitor|Debug Bar',
						'weight'      => 10,
						'description' => 'Mentions Query Monitor or Debug Bar for diagnosis',
					),
					array(
						'keyword'     => 'pre_get_posts|posts_per_page|nopaging',
						'weight'      => 15,
						'description' => 'Investigates the pre_get_posts filter as likely cause',
					),
					array(
						'keyword'     => 'EXPLAIN|slow.*query|query.*log|SHOW FULL PROCESSLIST',
						'weight'      => 10,
						'description' => 'Uses MySQL diagnostic tools',
					),
					array(
						'keyword'     => 'index|INDEX|missing.*index|add.*index',
						'weight'      => 10,
						'description' => 'Considers missing database indexes',
					),
					array(
						'keyword'     => 'meta_query|postmeta|meta.*join|JOIN',
						'weight'      => 10,
						'description' => 'Considers expensive meta queries',
					),
					array(
						'keyword'     => 'object.*cache|transient|cache|Redis|Memcache',
						'weight'      => 10,
						'description' => 'Considers caching solutions',
					),
					array(
						'keyword'     => 'posts_per_page|pagination|LIMIT',
						'weight'      => 10,
						'description' => 'Checks if pagination is being bypassed',
					),
					array(
						'keyword'     => 'WP_DEBUG|debug|error.*log',
						'weight'      => 5,
						'description' => 'Mentions enabling WP_DEBUG for diagnostics',
					),
					array(
						'keyword'     => 'plugin|deactivate.*plugin|conflict',
						'weight'      => 5,
						'description' => 'Suggests plugin conflict testing',
					),
					array(
						'keyword'     => 'step|first|second|then|next|systematic',
						'weight'      => 10,
						'description' => 'Provides a structured step-by-step approach',
					),
				),
			),

			array(
				'id'               => 'ac-007',
				'category'         => 'reasoning',
				'type'             => 'open_ended',
				'question'         => 'A client wants to build a WordPress site where users can submit articles for review. Editors should be able to approve, reject, or request changes. Authors should only see their own submissions and the review status. Explain the complete implementation approach: what WordPress features would you use, what custom code is needed, and what are the security considerations? Consider roles, capabilities, post statuses, and notifications.',
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to design a complete feature using WordPress primitives, considering security, UX, and extensibility.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'custom.*post.*status|register_post_status|post_status',
						'weight'      => 15,
						'description' => 'Proposes custom post statuses for workflow states',
					),
					array(
						'keyword'     => 'role|capability|add_role|add_cap|WP_Role',
						'weight'      => 15,
						'description' => 'Addresses roles and capabilities',
					),
					array(
						'keyword'     => 'pre_get_posts|posts_where|author|current_user',
						'weight'      => 10,
						'description' => 'Restricts authors to viewing only their own posts',
					),
					array(
						'keyword'     => 'wp_mail|notification|email|notify',
						'weight'      => 10,
						'description' => 'Includes notification system for status changes',
					),
					array(
						'keyword'     => 'nonce|sanitize|capability.*check|permission',
						'weight'      => 10,
						'description' => 'Addresses security considerations',
					),
					array(
						'keyword'     => 'meta.*box|add_meta_box|admin.*column',
						'weight'      => 10,
						'description' => 'Suggests admin UI elements for the workflow',
					),
					array(
						'keyword'     => 'transition_post_status|save_post|post.*status.*change',
						'weight'      => 10,
						'description' => 'Uses status transition hooks for workflow logic',
					),
					array(
						'keyword'     => 'pending|draft|review|approved|rejected',
						'weight'      => 5,
						'description' => 'Defines clear workflow states',
					),
					array(
						'keyword'     => 'audit|log|history|revision',
						'weight'      => 5,
						'description' => 'Considers audit trail or revision history',
					),
				),
			),

			// ── Architecture ─────────────────────────────────────────────

			array(
				'id'               => 'ac-008',
				'category'         => 'architecture',
				'type'             => 'open_ended',
				'question'         => 'You need to build a WordPress plugin that syncs WooCommerce orders to an external CRM via a REST API. The CRM API has a rate limit of 60 requests per minute and occasionally returns 503 errors. Orders must be synced within 5 minutes of creation. Design the architecture: what WordPress mechanisms would you use for queuing, retry logic, error handling, and monitoring? Consider what happens during high-traffic sales events.',
				'correct_answer'   => '',
				'explanation'      => 'Tests system design thinking: async processing, rate limiting, retry with backoff, failure handling, and observability.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'WP_Cron|wp_schedule|Action Scheduler|queue|async',
						'weight'      => 15,
						'description' => 'Uses async processing (WP-Cron, Action Scheduler, or custom queue)',
					),
					array(
						'keyword'     => 'retry|backoff|exponential|attempt',
						'weight'      => 15,
						'description' => 'Implements retry logic with backoff strategy',
					),
					array(
						'keyword'     => 'rate.*limit|throttle|60.*per.*minute|token.*bucket|leaky.*bucket',
						'weight'      => 10,
						'description' => 'Handles the CRM rate limit',
					),
					array(
						'keyword'     => '503|error.*handling|fallback|dead.*letter|failed.*queue',
						'weight'      => 10,
						'description' => 'Handles 503 errors and failed syncs',
					),
					array(
						'keyword'     => 'woocommerce_new_order|woocommerce_checkout_order_processed|order.*created',
						'weight'      => 10,
						'description' => 'Hooks into WooCommerce order creation',
					),
					array(
						'keyword'     => 'log|monitor|alert|notification|admin.*notice|health.*check',
						'weight'      => 10,
						'description' => 'Includes monitoring and alerting',
					),
					array(
						'keyword'     => 'batch|bulk|group|chunk',
						'weight'      => 10,
						'description' => 'Considers batching for high-traffic scenarios',
					),
					array(
						'keyword'     => 'idempotent|duplicate|dedup|unique.*key',
						'weight'      => 5,
						'description' => 'Considers idempotency to prevent duplicate syncs',
					),
					array(
						'keyword'     => 'transient|option|custom.*table|post.*meta|status',
						'weight'      => 5,
						'description' => 'Stores sync status persistently',
					),
					array(
						'keyword'     => 'wp_remote_post|wp_remote_request|HTTP',
						'weight'      => 5,
						'description' => 'Uses WordPress HTTP API for CRM calls',
					),
				),
			),

			array(
				'id'               => 'ac-009',
				'category'         => 'architecture',
				'type'             => 'open_ended',
				'question'         => 'A WordPress multisite network with 200 sites needs a shared "announcements" feature. Network admins create announcements that appear on all sites. Site admins can dismiss announcements for their site. Users can dismiss announcements for themselves. Design the data model: where would you store announcements, dismissals, and display rules? What are the trade-offs between using a custom table vs post meta vs site options vs user meta? Consider query performance at scale.',
				'correct_answer'   => '',
				'explanation'      => 'Tests data modeling skills, understanding of WordPress multisite architecture, and performance reasoning at scale.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'network|multisite|site_option|network_admin',
						'weight'      => 10,
						'description' => 'Demonstrates understanding of multisite architecture',
					),
					array(
						'keyword'     => 'custom.*table|CREATE TABLE|dbDelta',
						'weight'      => 15,
						'description' => 'Considers custom tables for announcements',
					),
					array(
						'keyword'     => 'user_meta|update_user_meta|get_user_meta',
						'weight'      => 10,
						'description' => 'Uses user meta for per-user dismissals',
					),
					array(
						'keyword'     => 'site.*option|blog.*option|update_option',
						'weight'      => 10,
						'description' => 'Uses site options for per-site dismissals',
					),
					array(
						'keyword'     => 'trade.*off|pro.*con|advantage|disadvantage|versus|vs',
						'weight'      => 10,
						'description' => 'Discusses trade-offs between storage approaches',
					),
					array(
						'keyword'     => 'performance|scale|query|index|JOIN',
						'weight'      => 10,
						'description' => 'Considers query performance at scale',
					),
					array(
						'keyword'     => 'cache|transient|object.*cache',
						'weight'      => 10,
						'description' => 'Considers caching strategy',
					),
					array(
						'keyword'     => 'switch_to_blog|restore_current_blog|global.*table',
						'weight'      => 10,
						'description' => 'Addresses cross-site data access patterns',
					),
					array(
						'keyword'     => 'expir|TTL|schedule|date|active',
						'weight'      => 5,
						'description' => 'Considers announcement expiration/scheduling',
					),
				),
			),

			// ── Multi-Step ───────────────────────────────────────────────

			array(
				'id'               => 'ac-010',
				'category'         => 'multi_step',
				'type'             => 'open_ended',
				'question'         => "Given this WordPress plugin structure, perform a security audit and list every vulnerability you find. For each vulnerability, state the risk level (critical/high/medium/low), the attack vector, and the specific fix:\n\n```php\n// Plugin: User Profile Editor\nadd_action( 'wp_ajax_update_profile', 'handle_profile_update' );\nadd_action( 'wp_ajax_nopriv_update_profile', 'handle_profile_update' );\n\nfunction handle_profile_update() {\n    \$user_id = \$_POST['user_id'];\n    \$email = \$_POST['email'];\n    \$role = \$_POST['role'];\n    \$bio = \$_POST['bio'];\n    \n    wp_update_user( array(\n        'ID' => \$user_id,\n        'user_email' => \$email,\n        'role' => \$role,\n        'description' => \$bio,\n    ) );\n    \n    echo '<div class=\"success\">Profile updated for ' . \$email . '</div>';\n    wp_die();\n}\n```",
				'correct_answer'   => '',
				'explanation'      => 'Multiple critical vulnerabilities: no nonce check, no auth check (nopriv allows unauthenticated access), no capability check, privilege escalation via role parameter, no sanitization, XSS in echo output, IDOR via user_id parameter.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'nopriv|unauthenticated|no.*auth|logged.*out',
						'weight'      => 15,
						'description' => 'Identifies nopriv handler allowing unauthenticated access',
					),
					array(
						'keyword'     => 'nonce|CSRF|cross.*site.*request',
						'weight'      => 10,
						'description' => 'Identifies missing nonce/CSRF protection',
					),
					array(
						'keyword'     => 'privilege.*escalation|role.*change|admin.*role|escalat',
						'weight'      => 15,
						'description' => 'Identifies privilege escalation via role parameter',
					),
					array(
						'keyword'     => 'IDOR|insecure.*direct|user_id.*manipulat|other.*user',
						'weight'      => 10,
						'description' => 'Identifies IDOR vulnerability via user_id',
					),
					array(
						'keyword'     => 'XSS|cross.*site.*script|echo.*unsanitized|esc_html',
						'weight'      => 10,
						'description' => 'Identifies XSS in the echo output',
					),
					array(
						'keyword'     => 'sanitize|sanitize_email|sanitize_text_field',
						'weight'      => 10,
						'description' => 'Identifies missing input sanitization',
					),
					array(
						'keyword'     => 'current_user_can|capability|permission.*check',
						'weight'      => 10,
						'description' => 'Identifies missing capability check',
					),
					array(
						'keyword'     => 'critical|high|severe',
						'weight'      => 5,
						'description' => 'Assigns appropriate severity levels',
					),
					array(
						'keyword'     => 'wp_unslash|stripslashes',
						'weight'      => 5,
						'description' => 'Mentions wp_unslash for superglobal data',
					),
					array(
						'keyword'     => 'wp_send_json|wp_send_json_success|JSON',
						'weight'      => 5,
						'description' => 'Suggests JSON response instead of raw HTML echo',
					),
				),
			),

			array(
				'id'               => 'ac-011',
				'category'         => 'multi_step',
				'type'             => 'open_ended',
				'question'         => 'A WordPress site is migrating from a custom fields plugin (storing data in postmeta with keys like "_old_plugin_price", "_old_plugin_sku", "_old_plugin_stock") to WooCommerce (which uses "_price", "_sku", "_stock"). Write a complete WP-CLI command that: 1) counts affected posts, 2) performs the migration in batches of 100 to avoid memory issues, 3) maps old meta keys to new ones, 4) preserves the old data as a backup meta key, 5) provides a --dry-run flag, and 6) outputs progress. Include error handling for edge cases.',
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to write production-quality WP-CLI commands with batching, dry-run support, progress reporting, and data safety.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'WP_CLI|WP_CLI::add_command|extends.*WP_CLI_Command',
						'weight'      => 15,
						'description' => 'Uses WP-CLI command framework correctly',
					),
					array(
						'keyword'     => 'batch|chunk|offset|LIMIT|posts_per_page',
						'weight'      => 15,
						'description' => 'Implements batch processing',
					),
					array(
						'keyword'     => 'dry.run|dry_run|--dry-run',
						'weight'      => 10,
						'description' => 'Implements dry-run flag',
					),
					array(
						'keyword'     => 'progress|WP_CLI::log|WP_CLI::success|WP_CLI::warning',
						'weight'      => 10,
						'description' => 'Provides progress output',
					),
					array(
						'keyword'     => 'backup|_backup_|preserve|old.*key',
						'weight'      => 10,
						'description' => 'Preserves old data as backup',
					),
					array(
						'keyword'     => 'update_post_meta|add_post_meta',
						'weight'      => 10,
						'description' => 'Uses WordPress meta API for migration',
					),
					array(
						'keyword'     => 'count|total|found_posts|COUNT',
						'weight'      => 5,
						'description' => 'Counts affected posts before migration',
					),
					array(
						'keyword'     => 'error|try|catch|WP_CLI::error|exception',
						'weight'      => 5,
						'description' => 'Includes error handling',
					),
					array(
						'keyword'     => 'meta_query|meta_key|EXISTS',
						'weight'      => 5,
						'description' => 'Queries posts by meta key existence',
					),
					array(
						'keyword'     => 'wp_cache_flush|stop_the_insanity|memory',
						'weight'      => 5,
						'description' => 'Manages memory during batch processing',
					),
				),
			),

			array(
				'id'               => 'ac-012',
				'category'         => 'multi_step',
				'type'             => 'open_ended',
				'question'         => 'Design and write a WordPress REST API middleware system that: 1) logs all API requests with timing data, 2) implements per-user rate limiting (100 requests per hour stored in transients), 3) adds CORS headers for a configurable list of allowed origins, and 4) returns standardized error responses in JSON:API format. Show how each middleware hooks into the REST API lifecycle and how they compose together.',
				'correct_answer'   => '',
				'explanation'      => 'Tests understanding of WordPress REST API lifecycle hooks, transient-based rate limiting, CORS handling, and middleware composition patterns.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'rest_api_init|rest_pre_dispatch|rest_post_dispatch|rest_request_after_callbacks',
						'weight'      => 15,
						'description' => 'Uses correct REST API lifecycle hooks',
					),
					array(
						'keyword'     => 'transient|set_transient|get_transient',
						'weight'      => 10,
						'description' => 'Uses transients for rate limit storage',
					),
					array(
						'keyword'     => 'rate.*limit|X-RateLimit|429|Too Many Requests',
						'weight'      => 10,
						'description' => 'Implements rate limiting with proper HTTP status',
					),
					array(
						'keyword'     => 'Access-Control-Allow-Origin|CORS|cors',
						'weight'      => 10,
						'description' => 'Implements CORS headers',
					),
					array(
						'keyword'     => 'microtime|time\(\)|hrtime|performance|timing',
						'weight'      => 10,
						'description' => 'Measures request timing',
					),
					array(
						'keyword'     => 'error|WP_Error|status.*code|json.*api|jsonapi',
						'weight'      => 10,
						'description' => 'Implements standardized error responses',
					),
					array(
						'keyword'     => 'get_current_user_id|user.*id|per.*user',
						'weight'      => 5,
						'description' => 'Implements per-user tracking',
					),
					array(
						'keyword'     => 'header|send_header|@header|rest_send_cors_headers',
						'weight'      => 5,
						'description' => 'Sets HTTP headers correctly',
					),
					array(
						'keyword'     => 'filter|add_filter|apply_filters|configur',
						'weight'      => 5,
						'description' => 'Makes the system configurable',
					),
					array(
						'keyword'     => 'compose|middleware|pipeline|chain|stack',
						'weight'      => 10,
						'description' => 'Explains how middlewares compose together',
					),
				),
			),

			// ── Code Generation (advanced) ───────────────────────────────

			array(
				'id'               => 'ac-013',
				'category'         => 'code_generation',
				'type'             => 'open_ended',
				'question'         => 'Write a WordPress Gutenberg block (using @wordpress/scripts and React) that renders an interactive pricing table. The block should have: 1) An InspectorControls sidebar panel for setting the number of columns (2-4), 2) RichText components for plan names and prices, 3) A repeater for feature list items per column, 4) A save function that outputs semantic HTML with proper escaping. Show both the edit and save functions.',
				'correct_answer'   => '',
				'explanation'      => 'Tests ability to write modern WordPress Gutenberg blocks with React, block attributes, InspectorControls, and proper save/edit separation.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'registerBlockType|register_block_type',
						'weight'      => 10,
						'description' => 'Registers the block correctly',
					),
					array(
						'keyword'     => 'InspectorControls',
						'weight'      => 10,
						'description' => 'Uses InspectorControls for sidebar settings',
					),
					array(
						'keyword'     => 'RichText',
						'weight'      => 10,
						'description' => 'Uses RichText component for editable content',
					),
					array(
						'keyword'     => 'attributes|attribute',
						'weight'      => 10,
						'description' => 'Defines block attributes schema',
					),
					array(
						'keyword'     => 'edit|Edit',
						'weight'      => 10,
						'description' => 'Implements edit function/component',
					),
					array(
						'keyword'     => 'save|Save',
						'weight'      => 10,
						'description' => 'Implements save function/component',
					),
					array(
						'keyword'     => 'useBlockProps|blockProps|block-props',
						'weight'      => 10,
						'description' => 'Uses useBlockProps hook',
					),
					array(
						'keyword'     => 'RangeControl|NumberControl|PanelBody',
						'weight'      => 5,
						'description' => 'Uses appropriate control components',
					),
					array(
						'keyword'     => 'setAttributes|onChange',
						'weight'      => 5,
						'description' => 'Updates attributes via setAttributes',
					),
					array(
						'keyword'     => 'Button|repeater|add.*item|remove.*item',
						'weight'      => 10,
						'description' => 'Implements repeater pattern for features',
					),
					array(
						'keyword'     => '@wordpress/block-editor|@wordpress/blocks|@wordpress/components',
						'weight'      => 5,
						'description' => 'Imports from correct WordPress packages',
					),
				),
			),

			array(
				'id'               => 'ac-014',
				'category'         => 'code_generation',
				'type'             => 'open_ended',
				'question'         => 'Write a PHP class that implements a circuit breaker pattern for external API calls in a WordPress plugin. The circuit breaker should: track failures in a transient, open after 5 consecutive failures, stay open for 30 seconds before allowing a test request (half-open state), reset on success, and provide a fallback response when the circuit is open. Include proper type declarations and PHPDoc.',
				'correct_answer'   => '',
				'explanation'      => 'Tests knowledge of resilience patterns, WordPress transient API, and PHP 8.x class design with type declarations.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'class|CircuitBreaker|circuit.*breaker',
						'weight'      => 5,
						'description' => 'Implements as a proper PHP class',
					),
					array(
						'keyword'     => 'open|closed|half.open|state',
						'weight'      => 15,
						'description' => 'Implements three circuit breaker states',
					),
					array(
						'keyword'     => 'transient|set_transient|get_transient',
						'weight'      => 10,
						'description' => 'Uses transients for state persistence',
					),
					array(
						'keyword'     => 'failure.*count|consecutive|threshold|5',
						'weight'      => 10,
						'description' => 'Tracks failure count with threshold',
					),
					array(
						'keyword'     => 'timeout|30|cooldown|reset.*time',
						'weight'      => 10,
						'description' => 'Implements timeout before half-open',
					),
					array(
						'keyword'     => 'fallback|default.*response|cached',
						'weight'      => 10,
						'description' => 'Provides fallback when circuit is open',
					),
					array(
						'keyword'     => 'callable|Closure|callback|\$callback',
						'weight'      => 10,
						'description' => 'Accepts callable for the protected operation',
					),
					array(
						'keyword'     => 'string|int|bool|float|array|void|return.*type',
						'weight'      => 5,
						'description' => 'Uses PHP type declarations',
					),
					array(
						'keyword'     => '@param|@return|@throws|PHPDoc',
						'weight'      => 5,
						'description' => 'Includes PHPDoc documentation',
					),
					array(
						'keyword'     => 'try|catch|Exception|Throwable',
						'weight'      => 10,
						'description' => 'Uses exception handling for failure detection',
					),
				),
			),

			// ── Debugging (advanced) ─────────────────────────────────────

			array(
				'id'               => 'ac-015',
				'category'         => 'debugging',
				'type'             => 'open_ended',
				'question'         => "A WordPress site intermittently shows a white screen of death (WSOD) on the frontend but the admin works fine. error_log shows 'Allowed memory size of 268435456 bytes exhausted'. The issue started after a theme update. The theme has 50+ template files. Describe your complete debugging strategy: how would you isolate the problematic template, what tools would you use, what are the most likely causes of a memory exhaustion that only affects the frontend, and how would you fix it without rolling back the theme?",
				'correct_answer'   => '',
				'explanation'      => 'Tests real-world debugging methodology for a complex, intermittent issue requiring systematic isolation.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'WP_DEBUG|WP_DEBUG_LOG|debug.*log|error.*log',
						'weight'      => 10,
						'description' => 'Enables WordPress debug logging',
					),
					array(
						'keyword'     => 'template|template_include|get_template_part|template.*hierarchy',
						'weight'      => 10,
						'description' => 'Investigates template-level issues',
					),
					array(
						'keyword'     => 'infinite.*loop|recursion|recursive|loop.*within.*loop',
						'weight'      => 15,
						'description' => 'Considers infinite loop/recursion as likely cause',
					),
					array(
						'keyword'     => 'WP_Query|query.*inside.*loop|nested.*query|new WP_Query.*loop',
						'weight'      => 10,
						'description' => 'Considers nested WP_Query in template loop',
					),
					array(
						'keyword'     => 'memory_limit|WP_MEMORY_LIMIT|ini_set',
						'weight'      => 5,
						'description' => 'Mentions memory limit configuration',
					),
					array(
						'keyword'     => 'Xdebug|xdebug|profil|cachegrind|blackfire',
						'weight'      => 10,
						'description' => 'Suggests profiling tools',
					),
					array(
						'keyword'     => 'binary.*search|bisect|half|isolat|one.*by.*one',
						'weight'      => 10,
						'description' => 'Uses systematic isolation strategy',
					),
					array(
						'keyword'     => 'diff|compare|git.*diff|changed.*files',
						'weight'      => 10,
						'description' => 'Compares theme versions to find changes',
					),
					array(
						'keyword'     => 'frontend.*only|admin.*works|conditional|is_admin',
						'weight'      => 10,
						'description' => 'Explains why issue is frontend-only',
					),
					array(
						'keyword'     => 'wp_reset_postdata|wp_reset_query|global.*\$post',
						'weight'      => 5,
						'description' => 'Considers missing post data reset',
					),
				),
			),

			// ── Agent Task: Restaurant Website ───────────────────────────────

			array(
				'id'               => 'ac-016',
				'category'         => 'agent_task',
				'type'             => 'open_ended',
				'question'         => "You are a WordPress AI agent. Build a complete restaurant website for \"La Bella Cucina\", an Italian restaurant in Chicago. Complete all of the following steps:\n\n1. Set the site title to \"La Bella Cucina\" and tagline to \"Authentic Italian Cuisine in Chicago\"\n2. Create a Home page with a hero section (restaurant name, tagline, \"Reserve a Table\" CTA), an About section (founded 2010, family-owned, 20 years of culinary tradition), and a Featured Dishes section with at least 3 dishes\n3. Create a Menu page listing at least 6 dishes across categories (Antipasti, Primi, Secondi, Dolci) with descriptions and prices\n4. Create an About Us page with the restaurant story, chef bio, and awards\n5. Create a Contact page with address (123 N Michigan Ave, Chicago IL 60601), phone, hours (Mon-Thu 5pm-10pm, Fri-Sat 5pm-11pm, Sun 4pm-9pm), and a note about reservations\n6. Set the Home page as the static front page\n7. Create a navigation menu called \"Main Menu\" with links to all 4 pages and assign it to the primary menu location\n\nFor each step, describe what you did and confirm it was completed. If you cannot complete a step, explain why and what is missing.",
				'correct_answer'   => '',
				'explanation'      => 'End-to-end restaurant website build test. Validates the agent can execute a complete multi-step site build: site identity, multiple pages with structured content, navigation setup, and static front page configuration. Tests orchestration across PostAbilities, SiteBuilderAbilities, NavigationAbilities, and WordPressAbilities.',
				'scoring_criteria' => array(
					array(
						'keyword'     => 'site.*title|blogname|La Bella Cucina',
						'weight'      => 5,
						'description' => 'Sets site title to La Bella Cucina',
					),
					array(
						'keyword'     => 'tagline|blogdescription|Authentic Italian',
						'weight'      => 5,
						'description' => 'Sets site tagline',
					),
					array(
						'keyword'     => 'Home|home.*page|front.*page|hero|Reserve a Table',
						'weight'      => 15,
						'description' => 'Creates Home page with hero, about, and featured dishes sections',
					),
					array(
						'keyword'     => 'Menu|Antipasti|Primi|Secondi|Dolci|menu.*page',
						'weight'      => 15,
						'description' => 'Creates Menu page with dishes across categories',
					),
					array(
						'keyword'     => 'About|chef|story|founded|culinary',
						'weight'      => 10,
						'description' => 'Creates About Us page with restaurant story and chef bio',
					),
					array(
						'keyword'     => 'Contact|Michigan Ave|Chicago|hours|reservation',
						'weight'      => 10,
						'description' => 'Creates Contact page with address, phone, and hours',
					),
					array(
						'keyword'     => 'front.*page|show_on_front|page_on_front|static.*front',
						'weight'      => 10,
						'description' => 'Sets Home page as static front page',
					),
					array(
						'keyword'     => 'nav.*menu|navigation|Main Menu|wp_nav_menu|menu.*location|primary',
						'weight'      => 15,
						'description' => 'Creates navigation menu and assigns to primary location',
					),
					array(
						'keyword'     => 'completed|created|set|done|confirmed|step',
						'weight'      => 10,
						'description' => 'Confirms completion of each step',
					),
					array(
						'keyword'     => 'create.post|create-post|wp_insert_post|page.*type|post_type.*page',
						'weight'      => 5,
						'description' => 'Uses correct tool calls to create pages',
					),
				),
			),
		);
	}
}
