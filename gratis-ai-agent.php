<?php
/**
 * Plugin Name: Gratis AI Agent
 * Plugin URI:  https://github.com/Ultimate-Multisite/gratis-ai-agent
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.3.2
 * Author:      superdav42
 * Author URI:  https://github.com/superdav42
 * License:     GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: gratis-ai-agent
 *
 * @package GratisAiAgent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRATIS_AI_AGENT_DIR', __DIR__ );
define( 'GRATIS_AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Built-in fallback model ID used when no model is configured in settings
 * and no connector default is available.
 *
 * Developers can override the effective default at runtime via the
 * `gratis_ai_agent_default_model` filter rather than changing this constant.
 */
define( 'GRATIS_AI_AGENT_DEFAULT_MODEL', 'claude-sonnet-4' );

// Load Jetpack Autoloader for PSR-4 autoloading with version conflict resolution.
// Jetpack Autoloader ensures the newest version of shared packages (like php-ai-client) is used.
if ( file_exists( GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php' ) ) {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php';
} else {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload.php';
}

use GratisAiAgent\Abilities\AiImageAbilities;
use GratisAiAgent\Abilities\BlockAbilities;
use GratisAiAgent\Abilities\ContentAbilities;
use GratisAiAgent\Abilities\CustomPostTypeAbilities;
use GratisAiAgent\Abilities\CustomTaxonomyAbilities;
use GratisAiAgent\Abilities\DatabaseAbilities;
use GratisAiAgent\Abilities\DesignSystemAbilities;
use GratisAiAgent\Abilities\EditorialAbilities;
use GratisAiAgent\Abilities\FileAbilities;
use GratisAiAgent\Abilities\ImageAbilities;
use GratisAiAgent\Abilities\GitAbilities;
use GratisAiAgent\Abilities\GlobalStylesAbilities;
use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use GratisAiAgent\Abilities\GscAbilities;
use GratisAiAgent\Abilities\KnowledgeAbilities;
use GratisAiAgent\Abilities\PluginDownloadAbilities;
use GratisAiAgent\Abilities\MarketingAbilities;
use GratisAiAgent\Abilities\MediaAbilities;
use GratisAiAgent\Abilities\MemoryAbilities;
use GratisAiAgent\Abilities\MenuAbilities;
use GratisAiAgent\Abilities\NavigationAbilities;
use GratisAiAgent\Abilities\OptionsAbilities;
use GratisAiAgent\Abilities\PostAbilities;
use GratisAiAgent\Abilities\UserAbilities;
use GratisAiAgent\Abilities\SeoAbilities;
use GratisAiAgent\Abilities\SiteBuilderAbilities;
use GratisAiAgent\Abilities\SiteHealthAbilities;
use GratisAiAgent\Abilities\SkillAbilities;
use GratisAiAgent\Abilities\StockImageAbilities;
use GratisAiAgent\Abilities\ToolCapabilities;
use GratisAiAgent\Abilities\WooCommerceAbilities;
use GratisAiAgent\Abilities\WordPressAbilities;
use GratisAiAgent\Admin\FloatingWidget;
use GratisAiAgent\Admin\ModelBenchmarkPage;
use GratisAiAgent\Admin\ScreenMetaPanel;
use GratisAiAgent\Admin\UnifiedAdminMenu;
use GratisAiAgent\REST\BenchmarkController;
use GratisAiAgent\REST\TraceController;
use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Models\GitTrackerManager;
use GratisAiAgent\Automations\EventTriggerHandler;
use GratisAiAgent\CLI\BenchmarkCommand;
use GratisAiAgent\CLI\CliCommand;
use GratisAiAgent\CLI\TraceCommand;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\OnboardingManager;
use GratisAiAgent\Core\ProviderTraceLogger;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Core\SiteScanner;
use GratisAiAgent\Knowledge\KnowledgeHooks;
use GratisAiAgent\REST\RestController;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\ToolDiscovery;

register_activation_hook( __FILE__, [ Database::class, 'install' ] );
register_activation_hook( __FILE__, [ AutomationRunner::class, 'reschedule_all' ] );
register_activation_hook( __FILE__, [ OnboardingManager::class, 'on_activation' ] );
register_activation_hook(
	__FILE__,
	function () {
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
	}
);
register_deactivation_hook( __FILE__, [ KnowledgeHooks::class, 'deactivate' ] );
register_deactivation_hook( __FILE__, [ AutomationRunner::class, 'unschedule_all' ] );
register_deactivation_hook( __FILE__, [ SiteScanner::class, 'unschedule' ] );
add_action( 'admin_init', [ Database::class, 'install' ] );

// Translations are automatically loaded by WordPress since 4.6 for plugins hosted on WordPress.org.

// Register per-tool capabilities on admin_init so role-management plugins can discover them.
add_action(
	'admin_init',
	function () {
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
	}
);

add_action( 'rest_api_init', [ RestController::class, 'register_routes' ] );
add_action( 'rest_api_init', [ BenchmarkController::class, 'register_routes' ] );
add_action( 'rest_api_init', [ TraceController::class, 'register_routes' ] );

// Unified admin menu — single top-level menu with hash-based React routing.
add_action( 'admin_menu', [ UnifiedAdminMenu::class, 'register' ] );

// Benchmark page — registered under Tools for standalone access.
add_action( 'admin_menu', [ ModelBenchmarkPage::class, 'register' ] );

// Redirect old menu URLs to the unified structure.
add_action( 'admin_init', [ UnifiedAdminMenu::class, 'handleLegacyRedirects' ] );

// Normalise ability input schemas so every ability exposes a JSON Schema
// draft-2020-12 compatible object schema. Anthropic's tool-use API validates
// `input_schema` strictly and rejects bare arrays, missing `type`, or arrays
// used where objects are expected — we saw 400 errors from third-party
// abilities like `core/get-user-info` and `mcp-adapter/discover-abilities`
// that registered `input_schema => []`.
if ( ! function_exists( 'gratis_ai_agent_normalize_ability_schema' ) ) {
	/**
	 * Recursively normalise a JSON schema so it satisfies Anthropic's
	 * draft-2020-12 tool-use validator. Coerces empty `properties` / `items`
	 * arrays to stdClass (so they serialise as `{}` instead of `[]`), ensures
	 * object schemas have a `type` and a `properties` field, and drops stray
	 * empty-array `default` entries that mis-type object schemas.
	 *
	 * @param mixed $schema Schema node (array or scalar).
	 * @return mixed Normalised schema.
	 */
	function gratis_ai_agent_normalize_ability_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		// Top-level: empty schema → empty object schema.
		// `properties` must be `(object) []` so JSON-encoding emits `{}` not
		// `[]`; the latter violates JSON Schema and crashes Ollama's tool
		// validator with "Value looks like object, but can't find closing
		// '}' symbol" before the model is even invoked.
		if ( empty( $schema ) ) {
			return [
				'type'       => 'object',
				'properties' => (object) [],
			];
		}

		// Ensure `type` is set on object-shaped schemas (heuristic: presence
		// of `properties` / `required` or no other type hints).
		if ( ! isset( $schema['type'] ) && ( isset( $schema['properties'] ) || isset( $schema['required'] ) ) ) {
			$schema['type'] = 'object';
		}

		// `properties`: keep as PHP array so the REST validator can do array
		// access (`isset( $schema['properties'][ $name ] )`). Wire encoding to
		// JSON is handled separately.
		if ( array_key_exists( 'properties', $schema ) ) {
			$props = $schema['properties'];
			if ( is_array( $props ) && empty( $props ) ) {
				// Empty properties must encode as JSON `{}`, not `[]` — see
				// note above.
				$schema['properties'] = (object) [];
			} elseif ( is_array( $props ) ) {
				$promoted_required = [];
				foreach ( $props as $k => $v ) {
					// Strip draft-04 style boolean `required` from property
					// schemas and promote `required: true` to the parent
					// object's `required` array (draft-2020-12 form).
					if ( is_array( $v ) && array_key_exists( 'required', $v ) && is_bool( $v['required'] ) ) {
						if ( true === $v['required'] ) {
							$promoted_required[] = $k;
						}
						unset( $v['required'] );
					}
					$props[ $k ] = gratis_ai_agent_normalize_ability_schema( $v );
				}
				$schema['properties'] = $props;

				if ( ! empty( $promoted_required ) ) {
					$existing           = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : [];
					$schema['required'] = array_values( array_unique( array_merge( $existing, $promoted_required ) ) );
				}
			}
		}

		// Object schemas must have a `properties` field. Use stdClass so
		// JSON encoding emits `{}` instead of `[]` (see note above).
		if ( isset( $schema['type'] ) && 'object' === $schema['type'] && ! isset( $schema['properties'] ) ) {
			$schema['properties'] = (object) [];
		}

		// `items`: draft-2020-12 requires a schema object, never an array.
		// If empty/list-form, replace with a permissive object so the
		// validator doesn't reject it (OpenAI requires `items` on arrays).
		if ( array_key_exists( 'items', $schema ) && is_array( $schema['items'] ) ) {
			if ( empty( $schema['items'] ) || array_is_list( $schema['items'] ) ) {
				$schema['items'] = (object) [];
			} else {
				$schema['items'] = gratis_ai_agent_normalize_ability_schema( $schema['items'] );
			}
		}

		// Array schemas must have `items` — OpenAI's function-calling
		// validator rejects array types without it.
		if ( isset( $schema['type'] ) && 'array' === $schema['type'] && ! array_key_exists( 'items', $schema ) ) {
			$schema['items'] = (object) [];
		}

		// Drop stray empty-array `default` entries that mis-type object schemas.
		if ( isset( $schema['default'] ) && is_array( $schema['default'] ) && empty( $schema['default'] ) ) {
			unset( $schema['default'] );
		}

		// Recurse into any remaining nested schema keywords.
		foreach ( [ 'anyOf', 'oneOf', 'allOf' ] as $combiner ) {
			if ( isset( $schema[ $combiner ] ) && is_array( $schema[ $combiner ] ) ) {
				foreach ( $schema[ $combiner ] as $k => $sub ) {
					$schema[ $combiner ][ $k ] = gratis_ai_agent_normalize_ability_schema( $sub );
				}
			}
		}

		return $schema;
	}
}

add_filter(
	'wp_register_ability_args',
	function ( $args ) {
		if ( ! isset( $args['input_schema'] ) ) {
			$args['input_schema'] = [
				'type'       => 'object',
				'properties' => (object) [],
			];
			return $args;
		}

		$args['input_schema'] = gratis_ai_agent_normalize_ability_schema( $args['input_schema'] );
		return $args;
	}
);

// Register ability category.
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'gratis-ai-agent',
				[
					'label'       => __( 'Gratis AI Agent', 'gratis-ai-agent' ),
					'description' => __( 'Gratis AI Agent memory and skill abilities.', 'gratis-ai-agent' ),
				]
			);
		}
	}
);

// Default usage instructions for the auto-discovery manifest. Plugins can
// add their own blocks by hooking into the same filter at a later priority.
add_filter(
	'gratis_ai_agent_ability_usage_instructions',
	function ( $blocks ) {
		if ( ! is_array( $blocks ) ) {
			$blocks = [];
		}

		$defaults = [
			'gratis-ai-agent'    => 'Built-in agent abilities — memory, knowledge, file ops, image/SEO/analytics helpers, WP/site management, and the discovery meta-tools (`ability-search`, `ability-call`).',
			'multisite-ultimate' => 'CRUD for the Multisite Ultimate WaaS platform: subsites, customers, memberships, products, payments, domains, broadcasts, and webhooks. **Prefer these abilities over `db-query`/`run-php` when creating or managing subsites and related entities.**',
			'site'               => 'Built-in WordPress core abilities for posts, pages, media, options, taxonomies, and site information.',
			'user'               => 'Built-in WordPress core abilities for user lookup and management.',
			'ai-experiments'     => 'WordPress core AI experiments — prompt helpers, image analysis, etc.',
			'mcp-adapter'        => 'MCP-adapter introspection abilities for browsing other registered abilities.',
			'wpcli'              => 'WP-CLI bridge abilities — every WP-CLI command exposed as an ability. Use these for site/post/option/theme/plugin operations when no more specific ability exists.',
		];

		foreach ( $defaults as $cat => $text ) {
			if ( ! isset( $blocks[ $cat ] ) ) {
				$blocks[ $cat ] = $text;
			}
		}

		return $blocks;
	}
);

// Memory abilities.
MemoryAbilities::register();

// Skill abilities.
SkillAbilities::register();

// Knowledge abilities and hooks.
KnowledgeAbilities::register();
KnowledgeHooks::register();

// Tool discovery meta-tools (ability-search, ability-call) + auto-discovery layer.
ToolDiscovery::register();

// Stock image import ability.
StockImageAbilities::register();

// AI image generation ability (DALL-E 3).
AiImageAbilities::register();

// SEO, content, and marketing abilities.
SeoAbilities::register();
GscAbilities::register();
ContentAbilities::register();
MarketingAbilities::register();

// Google Analytics traffic analysis abilities.
GoogleAnalyticsAbilities::register();

// Block content abilities (markdown-to-blocks, block discovery, content creation).
BlockAbilities::register();

// Global styles (theme.json) management abilities (get, update, reset).
GlobalStylesAbilities::register();

// File operation abilities (read, write, edit, delete, list, search).
FileAbilities::register();

// Git file tracking abilities (snapshot, diff, restore, list, revert).
GitAbilities::register();

// Plugin download abilities (list modified plugins, get download URL).
PluginDownloadAbilities::register();

// Database query abilities (SELECT only).
DatabaseAbilities::register();

// WordPress management abilities (plugins, themes, install, run PHP).
WordPressAbilities::register();

// Options management abilities (get, update, delete, list options with safety blocklist).
OptionsAbilities::register();

// WooCommerce abilities (product CRUD, order queries, store stats) — only registers when WooCommerce is active.
WooCommerceAbilities::register();

// Site health abilities (plugin updates, error log, disk space, security, performance).
SiteHealthAbilities::register();

// Navigation abilities (navigate, get page HTML).
NavigationAbilities::register();

// Navigation menu management abilities (list, create, delete menus; add/remove items; assign locations).
MenuAbilities::register();

// Post management abilities (get, create, update, delete posts).
PostAbilities::register();

// Custom post type abilities (register, list, delete CPTs with persistence).
CustomPostTypeAbilities::register();

// Custom taxonomy abilities (register, list, delete taxonomies with persistence).
CustomTaxonomyAbilities::register();

// User management abilities (list, create, update role).
UserAbilities::register();

// Media library abilities (list, upload from URL, delete).
MediaAbilities::register();

// Editorial AI abilities (title generation, excerpt generation, summarization, block review).
EditorialAbilities::register();

// Image AI abilities (alt text generation, image prompt generation, import base64 image).
ImageAbilities::register();

// Site builder abilities (detect fresh install, manage site builder mode).
SiteBuilderAbilities::register();

// Design system abilities (custom CSS injection, block patterns, site logo, theme.json presets).
DesignSystemAbilities::register();

// Custom tool abilities (registered as WordPress Abilities).
CustomToolExecutor::register();

// Smart onboarding — scan site on first activation.
OnboardingManager::register();

// Automation cron handler.
AutomationRunner::register();

// Event-driven automation trigger handler.
EventTriggerHandler::register();
add_action( 'gratis_ai_agent_run_event_automation', [ EventTriggerHandler::class, 'execute_event_run' ] );

// Git change tracking — snapshot files before AI modifications, enable revert.
GitTrackerManager::register();

// Change logger — hooks into WordPress core to record AI-made changes.
ChangeLogger::register();

// Provider trace logger — captures LLM provider HTTP traffic when enabled.
ProviderTraceLogger::register();

// Fresh install detection — registers cache-invalidation hooks.
FreshInstallDetector::register();

// Floating widget on all admin pages.
FloatingWidget::register();

// Screen-meta Help tab chat panel.
ScreenMetaPanel::register();

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Prompt subcommand: wp ai-agent prompt "hello".
	\WP_CLI::add_command( 'ai-agent prompt', CliCommand::class );
	\WP_CLI::add_command( 'gratis-ai-agent prompt', CliCommand::class );
	// Provider trace CLI subcommands.
	\WP_CLI::add_command( 'ai-agent trace', TraceCommand::class );
	\WP_CLI::add_command( 'gratis-ai-agent trace', TraceCommand::class );
	// Model benchmark CLI subcommands.
	\WP_CLI::add_command( 'ai-agent benchmark', BenchmarkCommand::class );
	\WP_CLI::add_command( 'gratis-ai-agent benchmark', BenchmarkCommand::class );
}
