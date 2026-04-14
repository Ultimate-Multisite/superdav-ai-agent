<?php

declare(strict_types=1);
/**
 * Plugin Builder abilities — AI-powered plugin generation, sandboxing, and activation.
 *
 * Registers six abilities via the WordPress 7.0+ Abilities API:
 *   - gratis-ai-agent/generate-plugin
 *   - gratis-ai-agent/sandbox-test-plugin
 *   - gratis-ai-agent/sandbox-activate-plugin
 *   - gratis-ai-agent/update-plugin-sandboxed
 *   - gratis-ai-agent/scan-plugin-hooks
 *   - gratis-ai-agent/scan-theme-hooks
 *
 * @package GratisAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\PluginBuilder\HookScanner;
use GratisAiAgent\PluginBuilder\PluginGenerator;
use GratisAiAgent\PluginBuilder\PluginInstaller;
use GratisAiAgent\PluginBuilder\PluginSandbox;
use GratisAiAgent\PluginBuilder\PluginUpdater;
// ToolCapabilities is in the same GratisAiAgent\Abilities namespace — no import needed.
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginBuilderAbilities — static registry and proxy class.
 *
 * @since 1.5.0
 */
class PluginBuilderAbilities {

	/**
	 * Register abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		// Auto-deactivate any plugins that triggered a fatal on a previous activation.
		add_action( 'init', [ PluginSandbox::class, 'auto_deactivate_fatal_plugins' ] );
	}

	/**
	 * Register all plugin builder abilities with the WordPress Abilities API.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/generate-plugin',
			[
				'label'         => __( 'Generate Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Generate a WordPress plugin from a natural-language description. Returns the implementation plan and complete PHP source code.', 'gratis-ai-agent' ),
				'ability_class' => GeneratePluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/sandbox-test-plugin',
			[
				'label'         => __( 'Sandbox Test Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Run layers 1 and 2 of the sandbox safety check against a plugin: PHP syntax validation and isolated subprocess include test.', 'gratis-ai-agent' ),
				'ability_class' => SandboxTestPluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/sandbox-activate-plugin',
			[
				'label'         => __( 'Sandbox Activate Plugin', 'gratis-ai-agent' ),
				'description'   => __( 'Activate a plugin using layer 3 transactional safety: error handler + shutdown guard. Auto-deactivates on fatal error.', 'gratis-ai-agent' ),
				'ability_class' => SandboxActivatePluginAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/update-plugin-sandboxed',
			[
				'label'         => __( 'Update Plugin (Sandboxed)', 'gratis-ai-agent' ),
				'description'   => __( 'Update a running plugin with new code: backup → stage → sandbox test → swap. Rolls back automatically on failure.', 'gratis-ai-agent' ),
				'ability_class' => UpdatePluginSandboxedAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/scan-plugin-hooks',
			[
				'label'         => __( 'Scan Plugin Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed plugin for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanPluginHooksAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/scan-theme-hooks',
			[
				'label'         => __( 'Scan Theme Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed theme for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanThemeHooksAbility::class,
			]
		);
	}
}

// ─── Ability classes ─────────────────────────────────────────────────────────

/**
 * Generate Plugin ability.
 *
 * Generates an implementation plan and full PHP source for a WordPress plugin
 * from a natural-language description, then installs it to disk.
 *
 * @since 1.5.0
 */
class GeneratePluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Generate Plugin', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Generate a WordPress plugin from a natural-language description. Returns the implementation plan and complete PHP source code.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'description' => [
					'type'        => 'string',
					'description' => 'Natural-language description of what the plugin should do.',
				],
				'slug'        => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name). Defaults to a sanitized version of the description.',
				],
				'install'     => [
					'type'        => 'boolean',
					'description' => 'Whether to install the generated plugin to wp-content/plugins/. Defaults to true.',
				],
			],
			'required'   => [ 'description' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plan'        => [ 'type' => 'string' ],
				'files'       => [ 'type' => 'object' ],
				'plugin_file' => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'installed'   => [ 'type' => 'boolean' ],
				'record_id'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$description = (string) ( $input['description'] ?? '' );
		$slug_input  = (string) ( $input['slug'] ?? '' );
		$install     = isset( $input['install'] ) ? (bool) $input['install'] : true;

		if ( empty( $description ) ) {
			return new WP_Error(
				'gratis_ai_agent_empty_description',
				__( 'description is required.', 'gratis-ai-agent' )
			);
		}

		// Step 1: Generate structured plan (returns an array, not text).
		$plan = PluginGenerator::generate_plan( $description );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		// Override slug if the caller provided an explicit one.
		if ( ! empty( $slug_input ) ) {
			$plan['slug'] = sanitize_title( $slug_input );
		}

		// Step 2: Generate code file-by-file respecting dependency order.
		$code_result = PluginGenerator::generate_code( $plan );
		if ( is_wp_error( $code_result ) ) {
			return $code_result;
		}

		$files = $code_result['files'];
		$plan  = $code_result['plan'];
		$slug  = $plan['slug'];

		$plugin_file = PluginGenerator::detect_main_file( $files, $slug );

		$result = [
			'plan'        => $plan,
			'files'       => $files,
			'plugin_file' => $plugin_file,
			'slug'        => $slug,
			'installed'   => false,
			'record_id'   => 0,
		];

		// Step 3: Install to disk (optional).
		if ( $install ) {
			$install_result = PluginInstaller::install(
				$slug,
				$files,
				$description,
				(string) wp_json_encode( $plan ),
				$plugin_file
			);
			if ( is_wp_error( $install_result ) ) {
				return $install_result;
			}
			$result['installed'] = true;
			$result['record_id'] = $install_result['id'];
		}

		return $result;
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
 * Sandbox Test Plugin ability.
 *
 * Runs layers 1 and 2 of the plugin sandbox against an installed plugin.
 *
 * @since 1.5.0
 */
class SandboxTestPluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Sandbox Test Plugin', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Run layers 1 and 2 of the sandbox safety check against a plugin: PHP syntax validation and isolated subprocess include test.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name under wp-content/plugins/).',
				],
				'plugin_file' => [
					'type'        => 'string',
					'description' => 'Main plugin file path relative to the plugin directory (e.g. "my-plugin.php").',
				],
			],
			'required'   => [ 'slug', 'plugin_file' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'layer1_passed' => [ 'type' => 'boolean' ],
				'layer2_passed' => [ 'type' => 'boolean' ],
				'layer3_passed' => [ 'type' => 'boolean' ],
				'errors'        => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'passed'        => [ 'type' => 'boolean' ],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$plugin_file = (string) ( $input['plugin_file'] ?? '' );

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}
		if ( empty( $plugin_file ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_plugin_file', __( 'plugin_file is required.', 'gratis-ai-agent' ) );
		}

		$plugin_dir = WP_CONTENT_DIR . '/plugins/' . $slug . '/';

		return PluginSandbox::run_all( $plugin_dir, $plugin_file );
	}

	protected function permission_callback( $input ): bool {
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
 * Sandbox Activate Plugin ability.
 *
 * Activates a plugin using layer 3 transactional safety.
 *
 * @since 1.5.0
 */
class SandboxActivatePluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Sandbox Activate Plugin', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Activate a plugin using layer 3 transactional safety: error handler + shutdown guard. Auto-deactivates on fatal error.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugin_file' => [
					'type'        => 'string',
					'description' => 'Plugin file path relative to the plugins directory (e.g. "my-plugin/my-plugin.php").',
				],
			],
			'required'   => [ 'plugin_file' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'activated'   => [ 'type' => 'boolean' ],
				'plugin_file' => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$plugin_file = (string) ( $input['plugin_file'] ?? '' );

		if ( empty( $plugin_file ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_plugin_file',
				__( 'plugin_file is required.', 'gratis-ai-agent' )
			);
		}

		return PluginSandbox::layer3_activate( $plugin_file );
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
 * Update Plugin (Sandboxed) ability.
 *
 * @since 1.5.0
 */
class UpdatePluginSandboxedAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update Plugin (Sandboxed)', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Update a running plugin with new code: backup → stage → sandbox test → swap. Rolls back automatically on failure.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name under wp-content/plugins/).',
				],
				'files'       => [
					'type'        => 'object',
					'description' => 'Map of relative file paths to new PHP source code.',
				],
				'plugin_file' => [
					'type'        => 'string',
					'description' => 'Main plugin file path relative to the plugins directory.',
				],
			],
			'required'   => [ 'slug', 'files', 'plugin_file' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'updated'     => [ 'type' => 'boolean' ],
				'plugin_file' => [ 'type' => 'string' ],
				'backup_dir'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$slug        = (string) ( $input['slug'] ?? '' );
		$plugin_file = (string) ( $input['plugin_file'] ?? '' );

		// Coerce to array<string,string>: PluginUpdater::update() requires that shape.
		$raw_files = is_array( $input['files'] ?? null ) ? $input['files'] : [];
		/** @var array<string,string> $files */
		$files = array_filter(
			$raw_files,
			static fn( $v ) => is_string( $v )
		);

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}
		if ( empty( $files ) ) {
			return new WP_Error( 'gratis_ai_agent_no_files', __( 'files must not be empty.', 'gratis-ai-agent' ) );
		}
		if ( empty( $plugin_file ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_plugin_file', __( 'plugin_file is required.', 'gratis-ai-agent' ) );
		}

		return PluginUpdater::update( $slug, $files, $plugin_file );
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
 * Scan Plugin Hooks ability.
 *
 * @since 1.5.0
 */
class ScanPluginHooksAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Scan Plugin Hooks', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Scan an installed plugin for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug' => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name under wp-content/plugins/).',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'hooks' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'type' => [ 'type' => 'string' ],
							'name' => [ 'type' => 'string' ],
							'file' => [ 'type' => 'string' ],
							'line' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$slug = (string) ( $input['slug'] ?? '' );

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}

		return HookScanner::scan_plugin( $slug );
	}

	protected function permission_callback( $input ): bool {
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
 * Scan Theme Hooks ability.
 *
 * @since 1.5.0
 */
class ScanThemeHooksAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Scan Theme Hooks', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Scan an installed theme for WordPress hooks (actions and filters) to enable extension-plugin generation.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug' => [
					'type'        => 'string',
					'description' => 'Theme slug (directory name under wp-content/themes/).',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'hooks' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'type' => [ 'type' => 'string' ],
							'name' => [ 'type' => 'string' ],
							'file' => [ 'type' => 'string' ],
							'line' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	protected function execute_callback( $input ): array|\WP_Error {
		$slug = (string) ( $input['slug'] ?? '' );

		if ( empty( $slug ) ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}

		return HookScanner::scan_theme( $slug );
	}

	protected function permission_callback( $input ): bool {
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
