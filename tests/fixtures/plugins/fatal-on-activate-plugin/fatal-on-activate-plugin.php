<?php

declare(strict_types=1);
/**
 * Plugin Name: Fatal On Activate Plugin
 * Plugin URI:  https://example.com
 * Description: Fixture that triggers a fatal error during plugin activation.
 *              Passes sandbox layers 1 and 2 (valid syntax, safe to include).
 *              Used to test layer-3 transient-based rollback mechanism.
 * Version:     1.0.0
 * Author:      Test
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook(
	__FILE__,
	static function () {
		// Simulates a fatal-level error on activation.
		// PluginSandbox layer 3 detects this via the shutdown + transient guard.
		trigger_error( 'Simulated fatal error on plugin activation.', E_USER_ERROR );
	}
);
