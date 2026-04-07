<?php

declare(strict_types=1);
/**
 * WordPress management abilities for the AI agent.
 *
 * Provides plugin/theme listing, plugin installation, and whitelisted WP function calls.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WordPressAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * List all installed plugins.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_plugins( array $input = [] ) {
		$ability = new GetPluginsAbility(
			'gratis-ai-agent/get-plugins',
			[
				'label'       => __( 'List Plugins', 'gratis-ai-agent' ),
				'description' => __( 'List all installed WordPress plugins with their status (active/inactive).', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * List all installed themes.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_themes( array $input = [] ) {
		$ability = new GetThemesAbility(
			'gratis-ai-agent/get-themes',
			[
				'label'       => __( 'List Themes', 'gratis-ai-agent' ),
				'description' => __( 'List all installed WordPress themes with their status.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Install a plugin from WordPress.org.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_install_plugin( array $input = [] ) {
		$ability = new InstallPluginAbility(
			'gratis-ai-agent/install-plugin',
			[
				'label'       => __( 'Install Plugin', 'gratis-ai-agent' ),
				'description' => __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Update an installed plugin to the latest version.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_update_plugin( array $input = [] ) {
		$ability = new UpdatePluginAbility(
			'gratis-ai-agent/update-plugin',
			[
				'label'       => __( 'Update Plugin', 'gratis-ai-agent' ),
				'description' => __( 'Update an installed plugin to the latest version available from its source.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Call a whitelisted WordPress function by name with arguments.
	 *
	 * @param array<string,mixed> $input Input args (function, args).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_run_php( array $input = [] ) {
		$ability = new RunPhpAbility(
			'gratis-ai-agent/run-php',
			[
				'label'       => __( 'Call WordPress Function', 'gratis-ai-agent' ),
				'description' => __( 'Call a whitelisted WordPress function by name with arguments.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

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
				'label'         => __( 'List Plugins', 'gratis-ai-agent' ),
				'description'   => __( 'List all installed WordPress plugins with their status (active/inactive).', 'gratis-ai-agent' ),
				'ability_class' => GetPluginsAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-themes',
			[
				'label'         => __( 'List Themes', 'gratis-ai-agent' ),
				'description'   => __( 'List all installed WordPress themes with their status.', 'gratis-ai-agent' ),
				'ability_class' => GetThemesAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/install-plugin',
			[
				'label'         => __( 'Install Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'gratis-ai-agent' ),
				'ability_class' => InstallPluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/update-plugin',
			[
				'label'         => __( 'Update Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Update an installed plugin to the latest version available from its source.', 'gratis-ai-agent' ),
				'ability_class' => UpdatePluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/run-php',
			[
				'label'         => __( 'Call WordPress Function', 'gratis-ai-agent' ),
				'description'   => __( 'Call a whitelisted WordPress function by name with arguments. Supports get_option, wp_insert_post, get_posts, do_shortcode, and many more.', 'gratis-ai-agent' ),
				'ability_class' => RunPhpAbility::class,
			]
		);
	}
}

/**
 * Get Plugins ability.
 *
 * @since 1.0.0
 */
class GetPluginsAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Plugins', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all installed WordPress plugins with their status (active/inactive).', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => (object) [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugins'      => [ 'type' => 'array' ],
				'total'        => [ 'type' => 'integer' ],
				'active_count' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
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
				// @phpstan-ignore-next-line
				'active'      => in_array( $plugin_file, $active_plugins, true ),
			];
		}

		return [
			'plugins'      => $plugins,
			'total'        => count( $plugins ),
			// @phpstan-ignore-next-line
			'active_count' => count( $active_plugins ),
		];
	}

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Get Themes ability.
 *
 * @since 1.0.0
 */
class GetThemesAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Themes', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all installed WordPress themes with their status.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => (object) [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'themes' => [ 'type' => 'array' ],
				'total'  => [ 'type' => 'integer' ],
				'active' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
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

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Install Plugin ability.
 *
 * @since 1.0.0
 */
class InstallPluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Install Plugin', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
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
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'      => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
				'plugin_file' => [ 'type' => 'string' ],
				'active'      => [ 'type' => 'boolean' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
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
			// @phpstan-ignore-next-line
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				$is_active = is_plugin_active( $plugin_file );

				if ( $activate && ! $is_active ) {
					$result = activate_plugin( $plugin_file );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return [
						'status'      => 'activated',
						// @phpstan-ignore-next-line
						'message'     => sprintf( 'Plugin "%s" was already installed and has been activated.', $slug ),
						'plugin_file' => $plugin_file,
						'active'      => true,
					];
				}

				return [
					'status'      => 'already_installed',
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" is already installed%s.', $slug, $is_active ? ' and active' : '' ),
					'plugin_file' => $plugin_file,
					'active'      => $is_active,
				];
			}
		}

		// Get plugin info from wordpress.org.
		$api = plugins_api(
			'plugin_information',
			// @phpstan-ignore-next-line
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
					// @phpstan-ignore-next-line
					'message'     => sprintf( 'Plugin "%s" installed but activation failed: %s', $slug, $activate_result->get_error_message() ),
					'plugin_file' => $plugin_file,
					'active'      => false,
				];
			}
			return [
				'status'      => 'installed_and_activated',
				// @phpstan-ignore-next-line
				'message'     => sprintf( 'Plugin "%s" installed and activated successfully.', $slug ),
				'plugin_file' => $plugin_file,
				'active'      => true,
			];
		}

		return [
			'status'      => 'installed',
			// @phpstan-ignore-next-line
			'message'     => sprintf( 'Plugin "%s" installed successfully.', $slug ),
			'plugin_file' => $plugin_file,
			'active'      => false,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Update Plugin ability.
 *
 * Updates an installed plugin to the latest available version using the
 * core Plugin_Upgrader. The plugin can be identified by either its slug
 * (directory name) or its plugin file (e.g. "akismet/akismet.php").
 *
 * @since 1.1.0
 */
class UpdatePluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update Plugin', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Update an installed plugin to the latest version available from its source.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'The plugin directory slug (e.g. "akismet"). Either slug or plugin_file is required.',
				],
				'plugin_file' => [
					'type'        => 'string',
					'description' => 'The plugin file relative to the plugins directory (e.g. "akismet/akismet.php"). Either slug or plugin_file is required.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'      => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
				'plugin_file' => [ 'type' => 'string' ],
				'from'        => [ 'type' => 'string' ],
				'to'          => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$slug        = isset( $input['slug'] ) ? (string) $input['slug'] : '';
		$plugin_file = isset( $input['plugin_file'] ) ? (string) $input['plugin_file'] : '';

		if ( '' === $slug && '' === $plugin_file ) {
			return new WP_Error( 'gratis_ai_agent_missing_plugin', __( 'Either "slug" or "plugin_file" is required.', 'gratis-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$installed = get_plugins();

		// Resolve plugin_file from slug if needed.
		if ( '' === $plugin_file ) {
			foreach ( $installed as $file => $_data ) {
				if ( strpos( $file, $slug . '/' ) === 0 || $file === $slug . '.php' ) {
					$plugin_file = $file;
					break;
				}
			}
		}

		if ( '' === $plugin_file || ! isset( $installed[ $plugin_file ] ) ) {
			return new WP_Error(
				'gratis_ai_agent_plugin_not_installed',
				sprintf(
					/* translators: %s: plugin identifier */
					__( 'Plugin not installed: %s', 'gratis-ai-agent' ),
					'' !== $slug ? $slug : $plugin_file
				)
			);
		}

		$from_version = isset( $installed[ $plugin_file ]['Version'] ) ? (string) $installed[ $plugin_file ]['Version'] : '';

		// Force a fresh update check so wp_update_plugins has current data.
		wp_clean_plugins_cache( false );
		wp_update_plugins();

		$updates    = get_site_transient( 'update_plugins' );
		$has_update = is_object( $updates ) && isset( $updates->response[ $plugin_file ] );

		if ( ! $has_update ) {
			return [
				'status'      => 'up_to_date',
				'message'     => sprintf(
					/* translators: 1: plugin file, 2: version */
					__( 'Plugin "%1$s" is already at the latest version (%2$s).', 'gratis-ai-agent' ),
					$plugin_file,
					$from_version
				),
				'plugin_file' => $plugin_file,
				'from'        => $from_version,
				'to'          => $from_version,
			];
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			$errors = $skin->get_errors();
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new WP_Error( 'gratis_ai_agent_update_failed', __( 'Plugin update failed for unknown reason.', 'gratis-ai-agent' ) );
		}

		// Re-read version post-upgrade.
		wp_clean_plugins_cache( false );
		$installed_after = get_plugins();
		$to_version      = isset( $installed_after[ $plugin_file ]['Version'] ) ? (string) $installed_after[ $plugin_file ]['Version'] : '';

		return [
			'status'      => 'updated',
			'message'     => sprintf(
				/* translators: 1: plugin file, 2: old version, 3: new version */
				__( 'Plugin "%1$s" updated from %2$s to %3$s.', 'gratis-ai-agent' ),
				$plugin_file,
				$from_version,
				$to_version
			),
			'plugin_file' => $plugin_file,
			'from'        => $from_version,
			'to'          => $to_version,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Call a whitelisted WordPress function by name with arguments.
 *
 * Replaces the former RunPhpAbility (eval-based) with a safe, whitelisted
 * approach that only allows calling pre-approved WordPress functions via
 * call_user_func_array().
 *
 * @since 1.1.0
 */
class RunPhpAbility extends AbstractAbility {

	/**
	 * Allowed WordPress functions that the AI agent may call.
	 *
	 * Only side-effect-free read functions and common write functions with
	 * well-understood behaviour are included.  Extend via the
	 * `gratis_ai_agent_allowed_wp_functions` filter.
	 *
	 * @var string[]
	 */
	private const ALLOWED_FUNCTIONS = [
		// Options.
		'get_option',
		'update_option',
		'delete_option',
		// Posts / Pages.
		'get_post',
		'get_posts',
		'wp_insert_post',
		'wp_update_post',
		'wp_delete_post',
		'get_post_meta',
		'update_post_meta',
		'delete_post_meta',
		// Terms / Taxonomies.
		'get_terms',
		'get_term',
		'wp_insert_term',
		'wp_update_term',
		'wp_delete_term',
		'wp_set_post_terms',
		'wp_get_post_terms',
		// Users.
		'get_user_by',
		'get_users',
		'get_current_user_id',
		'get_user_meta',
		'update_user_meta',
		// Comments.
		'get_comments',
		'get_comment',
		'wp_insert_comment',
		'wp_update_comment',
		'wp_delete_comment',
		// Queries.
		'wp_count_posts',
		'wp_count_terms',
		'count_users',
		// Transients.
		'get_transient',
		'set_transient',
		'delete_transient',
		// Site info.
		'get_bloginfo',
		'home_url',
		'site_url',
		'admin_url',
		'wp_upload_dir',
		'is_multisite',
		// Plugins / Themes.
		'get_plugins',
		'is_plugin_active',
		'wp_get_theme',
		'wp_get_themes',
		// Shortcodes.
		'do_shortcode',
		'shortcode_exists',
		// Menus.
		'wp_get_nav_menus',
		'wp_get_nav_menu_items',
		// Misc.
		'wp_remote_get',
		'wp_remote_post',
		'current_time',
		'wp_date',
		'wp_create_nonce',
	];

	protected function label(): string {
		return __( 'Call WordPress Function', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Call a whitelisted WordPress function by name with arguments. Supported functions include get_option(), wp_insert_post(), get_posts(), get_user_by(), do_shortcode(), and many more. Use the "function" parameter for the function name and "args" for an ordered array of arguments.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'function' => [
					'type'        => 'string',
					'description' => 'The WordPress function name to call, e.g. "get_option", "wp_insert_post".',
				],
				'args'     => [
					'type'        => 'array',
					'description' => 'Ordered array of arguments to pass to the function. Defaults to an empty array.',
					'items'       => [],
				],
			],
			'required'   => [ 'function' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'result' => [],
				'output' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$function = $input['function'] ?? '';
		$args     = $input['args'] ?? [];

		if ( empty( $function ) || ! is_string( $function ) ) {
			return new WP_Error(
				'gratis_ai_agent_empty_function',
				__( 'A function name is required.', 'gratis-ai-agent' )
			);
		}

		if ( ! is_array( $args ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_args',
				__( 'The "args" parameter must be an array.', 'gratis-ai-agent' )
			);
		}

		// Build the runtime allowlist (static defaults + user extensions).
		$allowed = self::get_allowed_functions();

		if ( ! in_array( $function, $allowed, true ) ) {
			return new WP_Error(
				'gratis_ai_agent_disallowed_function',
				sprintf(
					/* translators: %s: function name */
					__( 'The function "%s" is not in the allowed list. Use the gratis_ai_agent_allowed_wp_functions filter to extend it.', 'gratis-ai-agent' ),
					$function
				)
			);
		}

		if ( ! function_exists( $function ) ) {
			return new WP_Error(
				'gratis_ai_agent_undefined_function',
				sprintf(
					/* translators: %s: function name */
					__( 'The function "%s" does not exist in this WordPress environment.', 'gratis-ai-agent' ),
					$function
				)
			);
		}

		ob_start();
		$error  = null;
		$result = null;

		try {
			$result = call_user_func_array( $function, array_values( $args ) );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		$output = ob_get_clean();

		if ( null !== $error ) {
			return new WP_Error(
				'gratis_ai_agent_php_error',
				sprintf( 'PHP error: %s', $error )
			);
		}

		return [
			'result' => $result,
			'output' => $output,
		];
	}

	/**
	 * Get the full list of allowed functions (built-in + filtered).
	 *
	 * @return string[]
	 */
	private static function get_allowed_functions(): array {
		/**
		 * Filters the list of WordPress functions the AI agent is allowed to call.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $functions List of allowed function names.
		 */
		$functions = apply_filters( 'gratis_ai_agent_allowed_wp_functions', self::ALLOWED_FUNCTIONS );

		// Ensure the list is a flat array of strings (defensive).
		return array_values( array_filter( (array) $functions, 'is_string' ) );
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
