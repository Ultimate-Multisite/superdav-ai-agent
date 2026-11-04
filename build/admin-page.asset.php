<?php
declare(strict_types=1);

/**
 * Prevents direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
return array('dependencies' => array('react', 'react-dom', 'react-jsx-runtime', 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wp-primitives'), 'version' => '845ea86c995e8cc3d426');
