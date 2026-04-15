<?php

declare(strict_types=1);
/**
 * Plugin Name: Valid Simple Plugin
 * Plugin URI:  https://example.com
 * Description: A minimal test fixture plugin — passes all sandbox layers.
 * Version:     1.0.0
 * Author:      Test
 * License:     GPL-2.0-or-later
 */

namespace ValidSimplePlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		// Intentionally empty — fixture for sandbox and installer tests.
	}
);
