<?php
/**
 * Plugin entry-point module for the Gratis AI Agent DI container.
 *
 * This class is decorated with `#[Module]` so the `x-wp/di` library discovers
 * it when bootstrapping the container via `xwp_load_app()`.
 *
 * PR 1 scope: the module intentionally imports no submodules and registers no
 * handlers — its sole purpose in this first phase is to stand up a functioning
 * PHP-DI container (see `configure()` for available definitions) so subsequent
 * refactor PRs can incrementally migrate the legacy `XxxAbilities::register()`
 * style wiring in `gratis-ai-agent.php` into real DI-managed handlers.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent;

use GratisAiAgent\Bootstrap\AbilitiesHandler;
use GratisAiAgent\Bootstrap\AdminHandler;
use GratisAiAgent\Bootstrap\CliHandler;
use GratisAiAgent\Bootstrap\CoreServicesHandler;
use GratisAiAgent\Bootstrap\FrontendAssetsHandler;
use GratisAiAgent\Infrastructure\AiClient\RequestTimeoutFilter;
use GratisAiAgent\Infrastructure\WordPress\Abilities\AbilityCategoryRegistrar;
use GratisAiAgent\Infrastructure\WordPress\Abilities\AbilitySchemaFilter;
use GratisAiAgent\Infrastructure\WordPress\Abilities\UsageInstructionsFilter;
use GratisAiAgent\REST\AgentController;
use GratisAiAgent\REST\AutomationController;
use GratisAiAgent\REST\BenchmarkController;
use GratisAiAgent\REST\ChangesController;
use GratisAiAgent\REST\FeedbackController;
use GratisAiAgent\REST\KnowledgeController;
use GratisAiAgent\REST\McpController;
use GratisAiAgent\REST\MemoryController;
use GratisAiAgent\REST\ResaleApiController;
use GratisAiAgent\REST\RestController;
use GratisAiAgent\REST\SessionController;
use GratisAiAgent\REST\SettingsController;
use GratisAiAgent\REST\SkillController;
use GratisAiAgent\REST\ToolController;
use GratisAiAgent\REST\TraceController;
use GratisAiAgent\REST\WebhookController;
use XWP\DI\Decorators\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Root application module for the DI container.
 *
 * The `#[Module]` attribute is consumed by `x-wp/di` when the plugin calls
 * {@see xwp_load_app()} in `gratis-ai-agent.php`. The container ID
 * `gratis-ai-agent` matches the plugin slug and is the key used by the rest of
 * the codebase to resolve the container through {@see xwp_app()}.
 *
 * `extendable: true` publishes the `xwp_extend_import_gratis-ai-agent` filter so
 * companion plugins (e.g. Ultimate Multisite add-ons) can register additional
 * modules against our container without having to patch this class.
 */
#[Module(
	container: 'gratis-ai-agent',
	hook: 'plugins_loaded',
	priority: 1,
	imports: array(),
	handlers: array(
		AbilitySchemaFilter::class,
		AbilityCategoryRegistrar::class,
		UsageInstructionsFilter::class,
		RequestTimeoutFilter::class,
		CliHandler::class,
		AbilitiesHandler::class,
		AdminHandler::class,
		CoreServicesHandler::class,
		FrontendAssetsHandler::class,
		MemoryController::class,
		SkillController::class,
		FeedbackController::class,
		TraceController::class,
		McpController::class,
		BenchmarkController::class,
		RestController::class,
		ToolController::class,
		AgentController::class,
		ChangesController::class,
		AutomationController::class,
		KnowledgeController::class,
		SessionController::class,
		SettingsController::class,
		WebhookController::class,
		ResaleApiController::class,
	),
	extendable: true,
)]
final class Plugin {

	/**
	 * PHP-DI container definitions exposed by the root module.
	 *
	 * Keys are intentionally namespaced under `plugin.*` so later modules can
	 * inject the plugin version / paths via `#[Infuse('plugin.version')]`
	 * without relying on the legacy `GRATIS_AI_AGENT_*` constants.
	 *
	 * @see https://php-di.org/doc/php-definitions.html
	 *
	 * @return array<string, mixed>
	 */
	public static function configure(): array {
		return array(
			'plugin.version' => \DI\value( GRATIS_AI_AGENT_VERSION ),
			'plugin.dir'     => \DI\value( GRATIS_AI_AGENT_DIR ),
			'plugin.url'     => \DI\value( GRATIS_AI_AGENT_URL ),
		);
	}
}
