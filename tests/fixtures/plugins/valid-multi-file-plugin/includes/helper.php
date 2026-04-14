<?php

declare(strict_types=1);
/**
 * Helper functions for the Valid Multi-File Plugin test fixture.
 *
 * @package ValidMultiFilePlugin
 */

namespace ValidMultiFilePlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin initialisation callback — intentionally a no-op for testing.
 */
function valid_multi_file_plugin_init(): void {
	// No-op: fixture used only for integration tests.
}
