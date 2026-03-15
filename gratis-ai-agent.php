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
use GratisAiAgent\Abilities\BlockAbilities;
use GratisAiAgent\Abilities\ContentAbilities;
use GratisAiAgent\Abilities\DatabaseAbilities;
use GratisAiAgent\Abilities\FileAbilities;
use GratisAiAgent\Abilities\KnowledgeAbilities;
use GratisAiAgent\Abilities\MarketingAbilities;
use GratisAiAgent\Abilities\MemoryAbilities;
use GratisAiAgent\Abilities\NavigationAbilities;
use GratisAiAgent\Abilities\SeoAbilities;
use GratisAiAgent\Abilities\SkillAbilities;
use GratisAiAgent\Abilities\StockImageAbilities;
use GratisAiAgent\Abilities\WordPressAbilities;
use GratisAiAgent\Admin\AdminPage;
use GratisAiAgent\Admin\FloatingWidget;
use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Automations\EventTriggerHandler;
use GratisAiAgent\CLI\CliCommand;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Knowledge\KnowledgeHooks;
use GratisAiAgent\REST\RestController;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\ToolDiscovery;

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
add_action( 'gratis_ai_agent_run_event_automation', [ EventTriggerHandler::class, 'execute_event_run' ] );

// Floating widget on all admin pages.
FloatingWidget::register();

// WP-CLI command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'gratis-ai-agent', CliCommand::class );
	// Backwards-compatible alias for the old command name.
	\WP_CLI::add_command( 'ai-agent', CliCommand::class );
}
