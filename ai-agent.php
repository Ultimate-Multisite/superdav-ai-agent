<?php
/**
 * Plugin Name: AI Agent
 * Plugin URI:  https://developer.wordpress.org/
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.1.0
 * Author:      Dave
 * License:     GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: ai-agent
 *
 * @package AiAgent
 */

namespace AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_AGENT_DIR', __DIR__ );
define( 'AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

// Load compatibility layer for WordPress < 7.0 (Abilities API + AI Client SDK).
require_once AI_AGENT_DIR . '/compat/load.php';

require_once AI_AGENT_DIR . '/includes/class-database.php';
require_once AI_AGENT_DIR . '/includes/class-settings.php';
require_once AI_AGENT_DIR . '/includes/class-memory.php';
require_once AI_AGENT_DIR . '/includes/class-memory-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-skill.php';
require_once AI_AGENT_DIR . '/includes/class-skill-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-knowledge-database.php';
require_once AI_AGENT_DIR . '/includes/class-chunker.php';
require_once AI_AGENT_DIR . '/includes/class-document-parser.php';
require_once AI_AGENT_DIR . '/includes/class-knowledge.php';
require_once AI_AGENT_DIR . '/includes/class-knowledge-hooks.php';
require_once AI_AGENT_DIR . '/includes/class-knowledge-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-context-providers.php';
require_once AI_AGENT_DIR . '/includes/class-tool-discovery.php';
require_once AI_AGENT_DIR . '/includes/class-stock-image-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-seo-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-content-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-marketing-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-markdown-to-blocks.php';
require_once AI_AGENT_DIR . '/includes/class-block-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-custom-tools.php';
require_once AI_AGENT_DIR . '/includes/class-custom-tool-executor.php';
require_once AI_AGENT_DIR . '/includes/class-tool-profiles.php';
require_once AI_AGENT_DIR . '/includes/class-conversation-trimmer.php';
require_once AI_AGENT_DIR . '/includes/class-automations.php';
require_once AI_AGENT_DIR . '/includes/class-automation-logs.php';
require_once AI_AGENT_DIR . '/includes/class-automation-runner.php';
require_once AI_AGENT_DIR . '/includes/class-event-automations.php';
require_once AI_AGENT_DIR . '/includes/class-event-trigger-registry.php';
require_once AI_AGENT_DIR . '/includes/class-placeholder-resolver.php';
require_once AI_AGENT_DIR . '/includes/class-event-trigger-handler.php';
require_once AI_AGENT_DIR . '/includes/class-agent-loop.php';
require_once AI_AGENT_DIR . '/includes/class-cost-calculator.php';
require_once AI_AGENT_DIR . '/includes/class-export.php';
require_once AI_AGENT_DIR . '/includes/class-rest-controller.php';
require_once AI_AGENT_DIR . '/includes/class-admin-page.php';
require_once AI_AGENT_DIR . '/includes/class-floating-widget.php';

register_activation_hook( __FILE__, [ Database::class, 'install' ] );
register_activation_hook( __FILE__, [ Automation_Runner::class, 'reschedule_all' ] );
register_deactivation_hook( __FILE__, [ Knowledge_Hooks::class, 'deactivate' ] );
register_deactivation_hook( __FILE__, [ Automation_Runner::class, 'unschedule_all' ] );
add_action( 'admin_init', [ Database::class, 'install' ] );

add_action( 'rest_api_init', [ Rest_Controller::class, 'register_routes' ] );
add_action( 'admin_menu', [ Admin_Page::class, 'register' ] );
add_action( 'admin_menu', [ Settings::class, 'register' ] );

// Register ability category.
add_action( 'wp_abilities_api_categories_init', function () {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category( 'ai-agent', [
			'label'       => __( 'AI Agent', 'ai-agent' ),
			'description' => __( 'AI Agent memory and skill abilities.', 'ai-agent' ),
		] );
	}
} );

// Memory abilities.
Memory_Abilities::register();

// Skill abilities.
Skill_Abilities::register();

// Knowledge abilities and hooks.
Knowledge_Abilities::register();
Knowledge_Hooks::register();

// Tool discovery meta-tools.
Tool_Discovery::register();

// Stock image import ability.
Stock_Image_Abilities::register();

// SEO, content, and marketing abilities.
SEO_Abilities::register();
Content_Abilities::register();
Marketing_Abilities::register();

// Block content abilities (markdown-to-blocks, block discovery, content creation).
Block_Abilities::register();

// Custom tool abilities (registered as WordPress Abilities).
Custom_Tool_Executor::register();

// Automation cron handler.
Automation_Runner::register();

// Event-driven automation trigger handler.
Event_Trigger_Handler::register();
add_action( 'ai_agent_run_event_automation', [ Event_Trigger_Handler::class, 'execute_event_run' ] );

// Floating widget on all admin pages.
Floating_Widget::register();

// WP-CLI command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once AI_AGENT_DIR . '/includes/class-cli-command.php';
	\WP_CLI::add_command( 'ai-agent', CLI_Command::class );
}
