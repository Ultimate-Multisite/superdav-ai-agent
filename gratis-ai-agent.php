<?php
/**
 * Plugin Name: Gratis AI Agent
 * Plugin URI:  https://github.com/Ultimate-Multisite/gratis-ai-agent
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.5.0
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

define( 'GRATIS_AI_AGENT_VERSION', '1.5.0' );
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

use GratisAiAgent\Bootstrap\LifecycleHandler;
use GratisAiAgent\Plugin;
use GratisAiAgent\Abilities\AiImageAbilities;
use GratisAiAgent\Abilities\BlockAbilities;
use GratisAiAgent\Abilities\FeedbackAbilities;
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
use GratisAiAgent\Abilities\InternetSearchAbilities;
use GratisAiAgent\Abilities\KnowledgeAbilities;
use GratisAiAgent\Abilities\PluginBuilderAbilities;
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
use GratisAiAgent\Abilities\WpCliAbilities;
use GratisAiAgent\Admin\FloatingWidget;
use GratisAiAgent\Admin\ModelBenchmarkPage;
use GratisAiAgent\Admin\ScreenMetaPanel;
use GratisAiAgent\Admin\UnifiedAdminMenu;
use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Models\GitTrackerManager;
use GratisAiAgent\Automations\EventTriggerHandler;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\OnboardingManager;
use GratisAiAgent\Core\ProviderTraceLogger;
use GratisAiAgent\Knowledge\KnowledgeHooks;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\ToolDiscovery;

// Activation / deactivation hooks fire *before* `plugins_loaded`, so they
// cannot be wired through the DI container. `LifecycleHandler` consolidates
// the handful of static calls that used to live inline here.
register_activation_hook( __FILE__, [ LifecycleHandler::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ LifecycleHandler::class, 'deactivate' ] );

// Bootstrap the DI container. We let `xwp_load_app()` schedule the container
// build at its default `plugins_loaded:PHP_INT_MIN` so it runs *before* the
// `Plugin` module's own `#[Module(hook: 'plugins_loaded', priority: 1)]`
// registration fires. If both the app bootstrap and the module registration
// share the same hook + priority, PHP's `foreach` over that priority level is
// iterating a snapshot taken *before* `xwp_create_app()` queued the module —
// the deferred callback would never fire and `Module::on_initialize()` would
// silently never call `xwp_register_hook_handler()` for any of the child
// handlers listed in `Plugin::$handlers`.
//
// The legacy `XxxAbilities::register()` / `add_action()` calls that still
// live in this file are migrated into `#[Handler]` classes one PR at a time.
// Each extracted handler is imported via `GratisAiAgent\Plugin::$handlers`.
xwp_load_app(
	[
		'id'            => 'gratis-ai-agent',
		'module'        => Plugin::class,
		'autowiring'    => true,
		'compile'       => 'production' === wp_get_environment_type(),
		// The default `compile_class` is `CompiledContainer` + uppercased ID,
		// which produces invalid PHP class names when the ID contains hyphens.
		'compile_class' => 'CompiledContainerGratisAiAgent',
		'compile_dir'   => GRATIS_AI_AGENT_DIR . '/build/di-cache/' . GRATIS_AI_AGENT_VERSION,
	],
);

// Idempotent safety-net for older installs where the activation hook never
// fired (e.g. reactivation via `wp plugin activate` before this file was
// updated). Runs on every admin page load — cheap because `dbDelta` is a
// no-op when the schema is already up to date.
add_action( 'admin_init', [ Database::class, 'install' ] );

// Translations are automatically loaded by WordPress since 4.6 for plugins hosted on WordPress.org.

// Register per-tool capabilities on admin_init so role-management plugins can discover them.
add_action(
	'admin_init',
	function () {
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
	}
);

// All REST controllers are now DI-managed #[Handler] / #[REST_Handler] classes
// registered in Plugin.php — no manual rest_api_init wiring needed.

// Unified admin menu — single top-level menu with hash-based React routing.
add_action( 'admin_menu', [ UnifiedAdminMenu::class, 'register' ] );

// Benchmark page — registered under Tools for standalone access.
add_action( 'admin_menu', [ ModelBenchmarkPage::class, 'register' ] );

// Redirect old menu URLs to the unified structure.
add_action( 'admin_init', [ UnifiedAdminMenu::class, 'handleLegacyRedirects' ] );

// Memory abilities.
MemoryAbilities::register();

// Feedback abilities (report-inability).
FeedbackAbilities::register();

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

// Internet search ability (DuckDuckGo zero-config + optional Brave Search API).
InternetSearchAbilities::register();

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

// Plugin builder abilities (AI-powered generation, sandboxing, activation, hook scanning).
PluginBuilderAbilities::register();

// Database query abilities (SELECT only).
DatabaseAbilities::register();

// WordPress management abilities (plugins, themes, install, run PHP).
WordPressAbilities::register();

// WP-CLI command execution ability (wp-cli/execute).
WpCliAbilities::register();

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
