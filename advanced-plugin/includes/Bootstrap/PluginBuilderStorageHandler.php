<?php

declare(strict_types=1);
/**
 * DI handler for private plugin-builder storage migration.
 *
 * @package SdAiAgentAdvanced\Bootstrap
 * @license GPL-2.0-or-later
 */

namespace SdAiAgentAdvanced\Bootstrap;

use SdAiAgent\PluginBuilder\PluginUpdater;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves historical public backups and staging files after plugins load.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class PluginBuilderStorageHandler {

	/**
	 * Migrate legacy storage. Failures remain retryable on the next request.
	 */
	#[Action( tag: 'init', priority: 1 )]
	public function migrate_legacy_storage(): void {
		$result = ( new PluginUpdater() )->migrate_legacy_storage();
		if ( is_wp_error( $result ) ) {
			update_option( 'sd_ai_agent_plugin_storage_migration_error', $result->get_error_code(), false );
			return;
		}

		delete_option( 'sd_ai_agent_plugin_storage_migration_error' );
	}
}
