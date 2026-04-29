<?php
/**
 * DI handler for frontend asset enqueuing.
 *
 * Replaces the inline `FloatingWidget::register()` call in
 * `sd-ai-agent.php` for the frontend (non-admin) widget.
 *
 * @package SdAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Admin\FloatingWidget;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the floating widget on the public frontend.
 *
 * Context CTX_FRONTEND ensures this handler only loads on public-facing
 * pages — not admin, REST, CLI, or cron requests.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_FRONTEND,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class FrontendAssetsHandler {

	/**
	 * Enqueue frontend floating widget assets.
	 */
	#[Action( tag: 'wp_enqueue_scripts', priority: 10 )]
	public function enqueue_frontend_assets(): void {
		FloatingWidget::enqueue_assets_frontend();
	}
}
