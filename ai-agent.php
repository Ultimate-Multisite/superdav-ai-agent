<?php
/**
 * Plugin Name: AI Agent
 * Plugin URI:  https://developer.wordpress.org/
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.1.0
 * Author:      Dave
 * License:     GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Text Domain: ai-agent
 *
 * @package AiAgent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_AGENT_DIR', __DIR__ );
define( 'AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

// Load Jetpack Autoloader for PSR-4 autoloading with version conflict resolution.
// Jetpack Autoloader ensures the newest version of shared packages (like php-ai-client) is used.
if ( file_exists( AI_AGENT_DIR . '/vendor/autoload_packages.php' ) ) {
	require_once AI_AGENT_DIR . '/vendor/autoload_packages.php';
} else {
	require_once AI_AGENT_DIR . '/vendor/autoload.php';
}

// Load compatibility layer for WordPress < 7.0 (Abilities API + AI Client SDK).
require_once AI_AGENT_DIR . '/compat/load.php';

use AiAgent\Abilities\AbilityDiscoveryAbilities;
use AiAgent\Abilities\BlockAbilities;
use AiAgent\Abilities\ContentAbilities;
use AiAgent\Abilities\DatabaseAbilities;
use AiAgent\Abilities\FileAbilities;
use AiAgent\Abilities\KnowledgeAbilities;
use AiAgent\Abilities\MarketingAbilities;
use AiAgent\Abilities\MemoryAbilities;
use AiAgent\Abilities\NavigationAbilities;
use AiAgent\Abilities\SeoAbilities;
use AiAgent\Abilities\SkillAbilities;
use AiAgent\Abilities\StockImageAbilities;
use AiAgent\Abilities\WordPressAbilities;
use AiAgent\Admin\AdminPage;
use AiAgent\Admin\FloatingWidget;
use AiAgent\Admin\ScreenMetaPanel;
use AiAgent\Automations\AutomationRunner;
use AiAgent\Automations\EventTriggerHandler;
use AiAgent\CLI\CliCommand;
use AiAgent\Core\Database;
use AiAgent\Core\Settings;
use AiAgent\Knowledge\KnowledgeHooks;
use AiAgent\REST\RestController;
use AiAgent\Tools\CustomToolExecutor;
use AiAgent\Tools\ToolDiscovery;

register_activation_hook( __FILE__, [ Database::class, 'install' ] );
register_activation_hook( __FILE__, [ AutomationRunner::class, 'reschedule_all' ] );
register_deactivation_hook( __FILE__, [ KnowledgeHooks::class, 'deactivate' ] );
register_deactivation_hook( __FILE__, [ AutomationRunner::class, 'unschedule_all' ] );
add_action( 'admin_init', [ Database::class, 'install' ] );

add_action( 'rest_api_init', [ RestController::class, 'register_routes' ] );
add_action( 'admin_menu', [ AdminPage::class, 'register' ] );
add_action( 'admin_menu', [ Settings::class, 'register' ] );

// Register ability category.
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'ai-agent',
				[
					'label'       => __( 'AI Agent', 'ai-agent' ),
					'description' => __( 'AI Agent memory and skill abilities.', 'ai-agent' ),
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

// SEO, content, and marketing abilities.
SeoAbilities::register();
ContentAbilities::register();
MarketingAbilities::register();

// Block content abilities (markdown-to-blocks, block discovery, content creation).
BlockAbilities::register();

// File operation abilities (read, write, edit, delete, list, search).
FileAbilities::register();

// Database query abilities (SELECT only).
DatabaseAbilities::register();

// WordPress management abilities (plugins, themes, install, run PHP).
WordPressAbilities::register();

// Navigation abilities (navigate, get page HTML).
NavigationAbilities::register();

// Custom tool abilities (registered as WordPress Abilities).
CustomToolExecutor::register();

// Automation cron handler.
AutomationRunner::register();

// Event-driven automation trigger handler.
EventTriggerHandler::register();
add_action( 'ai_agent_run_event_automation', [ EventTriggerHandler::class, 'execute_event_run' ] );

// Floating widget on all admin pages.
FloatingWidget::register();

// Screen-meta Help tab chat panel.
ScreenMetaPanel::register();

// WP-CLI command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'ai-agent', CliCommand::class );
}
