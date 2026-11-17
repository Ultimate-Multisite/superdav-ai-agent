<?php
declare(strict_types=1);

/**
 * Prevents direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
return array('dependencies' => array('react', 'react-dom', 'react-jsx-runtime', 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wp-primitives'), 'version' => '64ddaf615fa08f35f5bf');
