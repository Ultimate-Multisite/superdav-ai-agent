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
				'slug'        => 'wp-core-v1',
				'name'        => __( 'WordPress Core v1', 'gratis-ai-agent' ),
				'description' => __( 'Tests knowledge of WordPress core APIs, hooks, coding standards, and best practices.', 'gratis-ai-agent' ),
				'question_count' => count( self::get_wp_core_questions() ),
			),
			array(
				'slug'        => 'wp-quick',
				'name'        => __( 'WordPress Quick Test', 'gratis-ai-agent' ),
				'description' => __( 'A quick 5-question test for rapid model evaluation.', 'gratis-ai-agent' ),
				'question_count' => 5,
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

			default:
				return null;
		}
	}

	/**
	 * Get questions for a suite.
	 *
	 * @param string $slug Suite slug.
	 * @return array
	 */
	public static function get_questions( string $slug ): array {
		$suite = self::get_suite( $slug );
		return $suite ? $suite['questions'] : array();
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
				'id'       => 'wp-001',
				'category' => 'general',
				'type'     => 'knowledge',
				'question' => 'What is the correct way to enqueue a JavaScript file in WordPress?',
				'options'  => array(
					'A' => 'wp_script_enqueue( "my-script", "/path/to/script.js" );',
					'B' => 'wp_enqueue_script( "my-script", "/path/to/script.js", array(), "1.0", true );',
					'C' => 'add_script( "my-script", "/path/to/script.js" );',
					'D' => 'enqueue_script( "/path/to/script.js" );',
				),
				'correct_answer' => 'B',
				'explanation'    => 'wp_enqueue_script() is the correct function, with parameters for handle, source, dependencies, version, and footer placement.',
			),
			array(
				'id'       => 'wp-002',
				'category' => 'general',
				'type'     => 'knowledge',
				'question' => 'Which hook should you use to add functionality that runs after WordPress has finished loading but before any headers are sent?',
				'options'  => array(
					'A' => 'wp_loaded',
					'B' => 'init',
					'C' => 'wp_head',
					'D' => 'template_redirect',
				),
				'correct_answer' => 'B',
				'explanation'    => 'The init hook fires after WordPress has finished loading but before any headers are sent. Most of WP is set up at this stage.',
			),
			array(
				'id'       => 'wp-003',
				'category' => 'general',
				'type'     => 'knowledge',
				'question' => 'What is the recommended way to check if the current user has a specific capability?',
				'options'  => array(
					'A' => 'user_can( "edit_posts" )',
					'B' => 'current_user_can( "edit_posts" )',
					'C' => 'has_cap( "edit_posts" )',
					'D' => 'wp_user_can( "edit_posts" )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'current_user_can() is the proper function to check if the current user has a specific capability or role.',
			),
			array(
				'id'       => 'wp-004',
				'category' => 'general',
				'type'     => 'knowledge',
				'question' => 'What is the correct prefix for all WordPress database tables by default?',
				'options'  => array(
					'A' => 'wp_',
					'B' => 'wordpress_',
					'C' => 'db_',
					'D' => 'tbl_',
				),
				'correct_answer' => 'A',
				'explanation'    => 'The default table prefix is wp_, but this can be changed during installation for security purposes.',
			),
			array(
				'id'       => 'wp-005',
				'category' => 'general',
				'type'     => 'knowledge',
				'question' => 'Which function is used to create a nonce in WordPress for security purposes?',
				'options'  => array(
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
				'id'       => 'wp-006',
				'category' => 'database',
				'type'     => 'knowledge',
				'question' => 'Which class should be used for custom database queries in WordPress?',
				'options'  => array(
					'A' => 'WP_Query',
					'B' => 'wpdb',
					'C' => 'WP_Database',
					'D' => 'Query_Builder',
				),
				'correct_answer' => 'B',
				'explanation'    => 'The $wpdb class is the WordPress database access abstraction object used for custom queries.',
			),
			array(
				'id'       => 'wp-007',
				'category' => 'database',
				'type'     => 'knowledge',
				'question' => 'How do you properly escape a string for a SQL query in WordPress?',
				'options'  => array(
					'A' => 'mysql_real_escape_string( $string )',
					'B' => '$wpdb->escape( $string )',
					'C' => 'esc_sql( $string )',
					'D' => '$wpdb->prepare() with placeholders',
				),
				'correct_answer' => 'D',
				'explanation'    => '$wpdb->prepare() is the correct method as it prepares a SQL query with proper escaping of placeholders.',
			),
			array(
				'id'       => 'wp-008',
				'category' => 'database',
				'type'     => 'knowledge',
				'question' => 'Which function retrieves a single post by its ID?',
				'options'  => array(
					'A' => 'get_post_by_id( $id )',
					'B' => 'get_post( $id )',
					'C' => 'wp_get_post( $id )',
					'D' => 'fetch_post( $id )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'get_post() retrieves post data given a post ID or post object.',
			),
			array(
				'id'       => 'wp-009',
				'category' => 'database',
				'type'     => 'knowledge',
				'question' => 'What is the correct way to update a post meta value?',
				'options'  => array(
					'A' => 'update_meta( $post_id, "key", "value" )',
					'B' => 'update_post_meta( $post_id, "key", "value" )',
					'C' => 'set_post_meta( $post_id, "key", "value" )',
					'D' => 'save_post_meta( $post_id, "key", "value" )',
				),
				'correct_answer' => 'B',
				'explanation'    => 'update_post_meta() updates the value of an existing meta key for the specified post.',
			),
			array(
				'id'       => 'wp-010',
				'category' => 'database',
				'type'     => 'knowledge',
				'question' => 'Which hook runs when a post is saved or updated?',
				'options'  => array(
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
				'id'       => 'wp-011',
				'category' => 'hooks',
				'type'     => 'knowledge',
				'question' => 'What is the difference between do_action() and apply_filters()?',
				'options'  => array(
					'A' => 'They are synonyms with no difference',
					'B' => 'do_action() is for actions, apply_filters() modifies and returns data',
					'C' => 'apply_filters() is for actions, do_action() modifies data',
					'D' => 'do_action() runs once, apply_filters() runs multiple times',
				),
				'correct_answer' => 'B',
				'explanation'    => 'do_action() executes hooked functions without returning a value. apply_filters() passes data through functions and returns the modified value.',
			),
			array(
				'id'       => 'wp-012',
				'category' => 'hooks',
				'type'     => 'knowledge',
				'question' => 'Which function is used to add a filter hook?',
				'options'  => array(
					'A' => 'add_hook()',
					'B' => 'add_action()',
					'C' => 'add_filter()',
					'D' => 'register_filter()',
				),
				'correct_answer' => 'C',
				'explanation'    => 'add_filter() hooks a function to a specific filter action, allowing modification of data.',
			),
			array(
				'id'       => 'wp-013',
				'category' => 'hooks',
				'type'     => 'knowledge',
				'question' => 'What is the default priority for hooks in WordPress?',
				'options'  => array(
					'A' => '0',
					'B' => '1',
					'C' => '10',
					'D' => '100',
				),
				'correct_answer' => 'C',
				'explanation'    => 'The default priority is 10. Lower numbers run earlier, higher numbers run later.',
			),
			array(
				'id'       => 'wp-014',
				'category' => 'hooks',
				'type'     => 'knowledge',
				'question' => 'Which hook fires when WordPress is determining which template to load?',
				'options'  => array(
					'A' => 'template_include',
					'B' => 'template_redirect',
					'C' => 'get_template_part',
					'D' => 'load_template',
				),
				'correct_answer' => 'A',
				'explanation'    => 'template_include is the filter hook used to return the template file path before it\'s loaded.',
			),
			array(
				'id'       => 'wp-015',
				'category' => 'hooks',
				'type'     => 'knowledge',
				'question' => 'How do you remove an action that was added by a plugin?',
				'options'  => array(
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
				'id'       => 'wp-016',
				'category' => 'security',
				'type'     => 'knowledge',
				'question' => 'Which function should be used to sanitize text input from users?',
				'options'  => array(
					'A' => 'clean_text()',
					'B' => 'sanitize_text_field()',
					'C' => 'esc_text()',
					'D' => 'strip_tags()',
				),
				'correct_answer' => 'B',
				'explanation'    => 'sanitize_text_field() sanitizes a string from user input or from the database.',
			),
			array(
				'id'       => 'wp-017',
				'category' => 'security',
				'type'     => 'knowledge',
				'question' => 'What does wp_kses_post() do?',
				'options'  => array(
					'A' => 'Validates a post object',
					'B' => 'Sanitizes content for allowed HTML tags in posts',
					'C' => 'Checks if post data is secure',
					'D' => 'Encrypts post content',
				),
				'correct_answer' => 'B',
				'explanation'    => 'wp_kses_post() returns content with only allowed HTML tags and attributes for post content.',
			),
			array(
				'id'       => 'wp-018',
				'category' => 'security',
				'type'     => 'knowledge',
				'question' => 'Which constant should be defined to disable file editing in the WordPress admin?',
				'options'  => array(
					'A' => 'DISABLE_FILE_EDIT',
					'B' => 'DISALLOW_FILE_EDIT',
					'C' => 'WP_NO_FILE_EDIT',
					'D' => 'SECURE_FILE_EDIT',
				),
				'correct_answer' => 'B',
				'explanation'    => 'DISALLOW_FILE_EDIT disables the file editor in the WordPress admin for themes and plugins.',
			),
			array(
				'id'       => 'wp-019',
				'category' => 'security',
				'type'     => 'knowledge',
				'question' => 'What is the purpose of check_admin_referer()?',
				'options'  => array(
					'A' => 'Checks if user is an admin',
					'B' => 'Verifies the admin referrer URL',
					'C' => 'Validates nonce for admin requests',
					'D' => 'Redirects to admin page',
				),
				'correct_answer' => 'C',
				'explanation'    => 'check_admin_referer() validates the nonce on admin forms to protect against CSRF attacks.',
			),
			array(
				'id'       => 'wp-020',
				'category' => 'security',
				'type'     => 'knowledge',
				'question' => 'Which function escapes HTML attributes?',
				'options'  => array(
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
				'id'       => 'wp-021',
				'category' => 'coding',
				'type'     => 'knowledge',
				'question' => 'What is the WordPress coding standard for naming hooks?',
				'options'  => array(
					'A' => 'camelCase',
					'B' => 'PascalCase',
					'C' => 'snake_case',
					'D' => 'kebab-case',
				),
				'correct_answer' => 'C',
				'explanation'    => 'WordPress uses snake_case (lowercase with underscores) for hook names.',
			),
			array(
				'id'       => 'wp-022',
				'category' => 'coding',
				'type'     => 'knowledge',
				'question' => 'Which PHP version is the minimum required for WordPress 6.9?',
				'options'  => array(
					'A' => '7.0',
					'B' => '7.4',
					'C' => '8.0',
					'D' => '8.2',
				),
				'correct_answer' => 'B',
				'explanation'    => 'WordPress 6.9 requires PHP 7.4 or higher, though 8.0+ is recommended.',
			),
			array(
				'id'       => 'wp-023',
				'category' => 'coding',
				'type'     => 'knowledge',
				'question' => 'What should you use instead of direct SQL queries when possible?',
				'options'  => array(
					'A' => 'Raw PHP arrays',
					'B' => 'WordPress APIs (WP_Query, get_posts, etc.)',
					'C' => 'External database libraries',
					'D' => 'JSON files',
				),
				'correct_answer' => 'B',
				'explanation'    => 'WordPress APIs like WP_Query, get_posts(), and built-in functions should be preferred over direct SQL.',
			),
			array(
				'id'       => 'wp-024',
				'category' => 'coding',
				'type'     => 'knowledge',
				'question' => 'What is the correct way to check if a plugin is active?',
				'options'  => array(
					'A' => 'is_plugin_active( "plugin/plugin.php" )',
					'B' => 'plugin_is_active( "plugin/plugin.php" )',
					'C' => 'active_plugins[ "plugin/plugin.php" ]',
					'D' => 'is_active_plugin( "plugin/plugin.php" )',
				),
				'correct_answer' => 'A',
				'explanation'    => 'is_plugin_active() checks if a plugin is active. Must be called from admin context or include plugin.php.',
			),
			array(
				'id'       => 'wp-025',
				'category' => 'coding',
				'type'     => 'knowledge',
				'question' => 'Which function is used to get the current theme directory path?',
				'options'  => array(
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
}
