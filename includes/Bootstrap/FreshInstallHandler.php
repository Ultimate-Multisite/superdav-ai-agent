<?php
/**
 * DI handler for fresh-install cache invalidation hooks.
 *
 * Replaces the `FreshInstallDetector::register()` call in CoreServicesHandler
 * by wiring the three cache-clearing actions directly via `#[Action]`
 * attributes.
 *
 * The detection and caching logic lives in
 * {@see \GratisAiAgent\Core\FreshInstallDetector}. This handler is a thin
 * DI bridge — its only job is hook registration and forwarding to the
 * static `clearCache()` method.
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Core\FreshInstallDetector;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invalidates the fresh-install detection cache when site content changes.
 *
 * CTX_GLOBAL is needed because `transition_post_status` and `delete_post`
 * can fire in REST, CLI, and cron contexts as well as admin.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class FreshInstallHandler {

	/**
	 * Clear the detection cache when a post transitions to/from published.
	 *
	 * The hook fires with (new_status, old_status, post) but clearCache()
	 * ignores all arguments, so no parameters are declared here.
	 */
	#[Action( tag: 'transition_post_status', priority: 10 )]
	public function clear_cache_on_status_transition(): void {
		FreshInstallDetector::clearCache();
	}

	/**
	 * Clear the detection cache when a post is permanently deleted.
	 */
	#[Action( tag: 'delete_post', priority: 10 )]
	public function clear_cache_on_delete(): void {
		FreshInstallDetector::clearCache();
	}

	/**
	 * Clear the detection cache when the active theme changes.
	 */
	#[Action( tag: 'switch_theme', priority: 10 )]
	public function clear_cache_on_theme_switch(): void {
		FreshInstallDetector::clearCache();
	}
}
