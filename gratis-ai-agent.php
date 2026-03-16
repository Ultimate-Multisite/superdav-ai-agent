<?php
/**
 * Plugin Name: Gratis AI Agent
 * Plugin URI:  https://developer.wordpress.org/
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.1.0
 * Author:      Dave
 * License:     GPL-2.0-or-later
 * Requires at least: 6.9
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

// Load compatibility layer for WordPress < 7.0 (Abilities API + AI Client SDK).
require_once GRATIS_AI_AGENT_DIR . '/compat/load.php';

use GratisAiAgent\Abilities\AbilityDiscoveryAbilities;
use GratisAiAgent\Abilities\AiImageAbilities;
use GratisAiAgent\Abilities\BlockAbilities;
use GratisAiAgent\Abilities\ContentAbilities;
use GratisAiAgent\Abilities\DatabaseAbilities;
use GratisAiAgent\Abilities\EditorialAbilities;
use GratisAiAgent\Abilities\FileAbilities;
use GratisAiAgent\Abilities\ImageAbilities;
use GratisAiAgent\Abilities\GitAbilities;
use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use GratisAiAgent\Abilities\KnowledgeAbilities;
use GratisAiAgent\Abilities\PluginDownloadAbilities;
use GratisAiAgent\Abilities\MarketingAbilities;
use GratisAiAgent\Abilities\MediaAbilities;
use GratisAiAgent\Abilities\MemoryAbilities;
use GratisAiAgent\Abilities\NavigationAbilities;
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
use GratisAiAgent\Admin\AdminPage;
use GratisAiAgent\Admin\ChangesAdminPage;
use GratisAiAgent\Admin\FloatingWidget;
use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Models\GitTrackerManager;
use GratisAiAgent\Automations\EventTriggerHandler;
use GratisAiAgent\CLI\CliCommand;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\OnboardingManager;
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

// Register per-tool capabilities on admin_init so role-management plugins can discover them.
add_action(
	'admin_init',
	function () {
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
	}
);

add_action( 'rest_api_init', [ RestController::class, 'register_routes' ] );
add_action( 'admin_menu', [ AdminPage::class, 'register' ] );
add_action( 'admin_menu', [ ChangesAdminPage::class, 'register' ] );
add_action( 'admin_menu', [ Settings::class, 'register' ] );

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

// Memory abilities.
MemoryAbilities::register();

// Skill abilities.
SkillAbilities::register();

// Knowledge abilities and hooks.
KnowledgeAbilities::register();
KnowledgeHooks::register();

// Tool discovery meta-tools.
ToolDiscovery::register();

// Ability discovery meta-tools (list_abilities, get_ability, execute_ability).
AbilityDiscoveryAbilities::register();

// Stock image import ability.
StockImageAbilities::register();

// AI image generation ability (DALL-E 3).
AiImageAbilities::register();

// SEO, content, and marketing abilities.
SeoAbilities::register();
ContentAbilities::register();
MarketingAbilities::register();

// Google Analytics traffic analysis abilities.
GoogleAnalyticsAbilities::register();

// Block content abilities (markdown-to-blocks, block discovery, content creation).
BlockAbilities::register();

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

// WooCommerce abilities (product CRUD, order queries, store stats) — only registers when WooCommerce is active.
WooCommerceAbilities::register();

// Site health abilities (plugin updates, error log, disk space, security, performance).
SiteHealthAbilities::register();

// Navigation abilities (navigate, get page HTML).
NavigationAbilities::register();

// Post management abilities (get, create, update, delete posts).
PostAbilities::register();

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

// Fresh install detection — registers cache-invalidation hooks.
FreshInstallDetector::register();

// Floating widget on all admin pages.
FloatingWidget::register();

// WP-CLI command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'gratis-ai-agent', CliCommand::class );
	// Backwards-compatible alias for the old command name.
	\WP_CLI::add_command( 'ai-agent', CliCommand::class );
}
