<?php

declare(strict_types=1);
/**
 * Plugin Name: Valid Multi-File Plugin
 * Plugin URI:  https://example.com
 * Description: A multi-file test fixture plugin — verifies sandbox handles includes.
 * Version:     1.0.0
 * Author:      Test
 * License:     GPL-2.0-or-later
 */

namespace ValidMultiFilePlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/helper.php';

add_action(
	'init',
	static function () {
		valid_multi_file_plugin_init();
	}
);
