<?php

declare(strict_types=1);
/**
 * WordPress management abilities for the AI agent.
 *
 * Provides plugin/theme listing, plugin installation, and PHP execution.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WordPressAbilities {

	/**
	 * Register WordPress abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all WordPress management abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/get-plugins',
			[
				'label'               => __( 'List Plugins', 'gratis-ai-agent' ),
				'description'         => __( 'List all installed WordPress plugins with their status (active/inactive).', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'plugins'      => [ 'type' => 'array' ],
						'total'        => [ 'type' => 'integer' ],
						'active_count' => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_plugins' ],
				'permission_callback' => function () {
					return current_user_can( 'activate_plugins' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-themes',
			[
				'label'               => __( 'List Themes', 'gratis-ai-agent' ),
				'description'         => __( 'List all installed WordPress themes with their status.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'themes' => [ 'type' => 'array' ],
						'total'  => [ 'type' => 'integer' ],
						'active' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_themes' ],
				'permission_callback' => function () {
					return current_user_can( 'switch_themes' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/install-plugin',
			[
				'label'               => __( 'Install Plugin', 'gratis-ai-agent' ),
				'description'         => __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug'     => [
							'type'        => 'string',
							'description' => 'The plugin slug from wordpress.org (e.g., "akismet", "contact-form-7")',
						],
						'activate' => [
							'type'        => 'boolean',
							'description' => 'Whether to activate the plugin after installation (default: false)',
						],
					],
					'required'   => [ 'slug' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'status'      => [ 'type' => 'string' ],
						'message'     => [ 'type' => 'string' ],
						'plugin_file' => [ 'type' => 'string' ],
						'active'      => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_install_plugin' ],
				'permission_callback' => function () {
					return current_user_can( 'install_plugins' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/run-php',
			[
				'label'               => __( 'Run PHP', 'gratis-ai-agent' ),
				'description'         => __( 'Execute PHP code in the WordPress environment. Use this to call WordPress functions like wp_insert_post(), get_option(), WP_Query, etc. The code runs with full WordPress context.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'code' => [
							'type'        => 'string',
							'description' => 'PHP code to execute. Do not include <?php tags. The code should return a value.',
						],
					],
					'required'   => [ 'code' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'result' => [],
						'output' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_run_php' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Handle the get-plugins ability.
	 *
	 * @return array
	 */
	public static function handle_get_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$plugins = [];
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$plugins[] = [
				'file'        => $plugin_file,
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'description' => $plugin_data['Description'],
				'author'      => $plugin_data['Author'],
				'active'      => in_array( $plugin_file, $active_plugins, true ),
			];
		}

		return [
			'plugins'      => $plugins,
			'total'        => count( $plugins ),
			'active_count' => count( $active_plugins ),
		];
	}

	/**
	 * Handle the get-themes ability.
	 *
	 * @return array
	 */
	public static function handle_get_themes(): array {
		$all_themes   = wp_get_themes();
		$active_theme = get_stylesheet();

		$themes = [];
		foreach ( $all_themes as $theme_slug => $theme ) {
			$themes[] = [
				'slug'        => $theme_slug,
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'description' => $theme->get( 'Description' ),
				'author'      => $theme->get( 'Author' ),
				'active'      => $theme_slug === $active_theme,
			];
		}

		return [
			'themes' => $themes,
			'total'  => count( $themes ),
			'active' => $active_theme,
		];
	}

	/**
	 * Handle the install-plugin ability.
	 *
	 * @param array $input Input with slug and optional activate.
	 * @return array|WP_Error
	 */
	public static function handle_install_plugin( array $input ) {
		$slug     = $input['slug'] ?? '';
		$activate = (bool) ( $input['activate'] ?? false );

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_slug', __( 'Plugin slug is required.', 'gratis-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Check if already installed.
		$installed_plugins = get_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				$is_active = is_plugin_active( $plugin_file );

				if ( $activate && ! $is_active ) {
					$result = activate_plugin( $plugin_file );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return [
						'status'      => 'activated',
						'message'     => sprintf( 'Plugin "%s" was already installed and has been activated.', $slug ),
						'plugin_file' => $plugin_file,
						'active'      => true,
					];
				}

				return [
					'status'      => 'already_installed',
					'message'     => sprintf( 'Plugin "%s" is already installed%s.', $slug, $is_active ? ' and active' : '' ),
					'plugin_file' => $plugin_file,
					'active'      => $is_active,
				];
			}
		}

		// Get plugin info from wordpress.org.
		$api = plugins_api(
			'plugin_information',
			[
				'slug'   => $slug,
				'fields' => [
					'sections'          => false,
					'short_description' => true,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		// Install the plugin.
		$skin          = new \WP_Ajax_Upgrader_Skin();
		$upgrader      = new \Plugin_Upgrader( $skin );
		$download_link = is_object( $api ) ? $api->download_link : '';
		$result        = $upgrader->install( $download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			$errors = $skin->get_errors();
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new WP_Error( 'gratis_ai_agent_install_failed', __( 'Installation failed for unknown reason.', 'gratis-ai-agent' ) );
		}

		$plugin_file = $upgrader->plugin_info();

		if ( $activate && $plugin_file ) {
			$activate_result = activate_plugin( $plugin_file );
			if ( is_wp_error( $activate_result ) ) {
				return [
					'status'      => 'installed',
					'message'     => sprintf( 'Plugin "%s" installed but activation failed: %s', $slug, $activate_result->get_error_message() ),
					'plugin_file' => $plugin_file,
					'active'      => false,
				];
			}
			return [
				'status'      => 'installed_and_activated',
				'message'     => sprintf( 'Plugin "%s" installed and activated successfully.', $slug ),
				'plugin_file' => $plugin_file,
				'active'      => true,
			];
		}

		return [
			'status'      => 'installed',
			'message'     => sprintf( 'Plugin "%s" installed successfully.', $slug ),
			'plugin_file' => $plugin_file,
			'active'      => false,
		];
	}

	/**
	 * Handle the run-php ability.
	 *
	 * @param array $input Input with code.
	 * @return array|WP_Error
	 */
	public static function handle_run_php( array $input ) {
		$code = $input['code'] ?? '';

		if ( empty( $code ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_code', __( 'PHP code is required.', 'gratis-ai-agent' ) );
		}

		ob_start();
		$error  = null;
		$result = null;

		try {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Intentional: AI agent PHP execution ability.
			$result = eval( $code );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		$output = ob_get_clean();

		if ( null !== $error ) {
			return new WP_Error( 'gratis_ai_agent_php_error', sprintf( 'PHP error: %s', $error ) );
		}

		return [
			'result' => $result,
			'output' => $output,
		];
	}
}
