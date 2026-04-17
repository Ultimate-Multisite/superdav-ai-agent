<?php
/**
 * DI handler for core background services.
 *
 * Replaces the inline `XxxService::register()` calls in
 * `gratis-ai-agent.php` for services that hook into various WordPress
 * actions/filters (change logging, provider tracing, knowledge indexing,
 * automations, git tracking, onboarding, tool discovery, etc.).
 *
 * Each service's existing `register()` method internally calls
 * `add_action()` / `add_filter()` for the hooks it needs. This handler
 * delegates to those methods during `on_initialize()`, which fires
 * during `plugins_loaded` — before any of the target hooks run.
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Automations\EventTriggerHandler;
use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\OnboardingManager;
use GratisAiAgent\Core\ProviderTraceLogger;
use GratisAiAgent\Knowledge\KnowledgeHooks;
use GratisAiAgent\Models\GitTrackerManager;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\ToolDiscovery;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers core background services.
 *
 * Uses `INIT_IMMEDIATELY` so all internal `add_action()` / `add_filter()`
 * calls execute during `plugins_loaded`, before any of the target hooks
 * (like `post_updated`, `save_post`, `init`, cron hooks) fire.
 */
#[Handler(
	container: 'gratis-ai-agent',
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class CoreServicesHandler {

	/**
	 * Register all core services.
	 *
	 * Called automatically by x-wp/di during handler initialization.
	 * Each service's `register()` method adds its own hooks — this
	 * handler is a thin orchestrator, not a reimplementation.
	 */
	public function on_initialize(): void {
		// Change logging — hooks post_updated, updated_option, added_option,
		// edited_term, profile_update to record AI-made changes.
		ChangeLogger::register();

		// Provider trace logger — captures LLM provider HTTP traffic.
		ProviderTraceLogger::register();

		// Knowledge indexing — hooks save_post, delete_post, schedules
		// hourly reindex cron.
		KnowledgeHooks::register();

		// Tool discovery meta-tools + auto-discovery layer.
		ToolDiscovery::register();

		// Custom tool execution (registered as WordPress Abilities).
		CustomToolExecutor::register();

		// Automation cron handler + custom cron schedules.
		AutomationRunner::register();

		// Event-driven automation trigger handler — attaches hooks on init.
		EventTriggerHandler::register();

		// Event automation execution action.
		add_action(
			'gratis_ai_agent_run_event_automation',
			array( EventTriggerHandler::class, 'execute_event_run' )
		);

		// Git change tracking — snapshot files before AI modifications.
		GitTrackerManager::register();

		// Smart onboarding — scan site on first activation.
		OnboardingManager::register();

		// Fresh install detection — cache-invalidation hooks.
		FreshInstallDetector::register();
	}
}
