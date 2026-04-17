<?php
/**
 * Handler: raise the WordPress AI Client SDK default request timeout.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Infrastructure\AiClient;

use GratisAiAgent\Core\AgentLoop;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aligns the WordPress AI Client SDK's HTTP request timeout with the agent
 * loop's wall-clock budget.
 *
 * The SDK default is 30s, which is too short for agentic workloads that
 * combine research plus long-form generation (e.g. "research X and write a
 * blog post" — the final generation call alone can exceed 30s). We raise
 * it to match {@see AgentLoop::LOOP_TIMEOUT_SECONDS} so a single provider
 * call can consume the entire loop budget if needed.
 *
 * The filter is read when the AI Client SDK builds a prompt, so registering
 * it via `#[Filter]` on `plugins_loaded` is safe — any REST request arrives
 * later on the request lifecycle.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class RequestTimeoutFilter {

	/**
	 * Return the agent-loop timeout (in seconds) for the AI Client SDK.
	 *
	 * @return int Seconds to wait before aborting an upstream provider request.
	 */
	#[Filter( tag: 'wp_ai_client_default_request_timeout', priority: 10 )]
	public function raise_timeout(): int {
		return AgentLoop::LOOP_TIMEOUT_SECONDS;
	}
}
