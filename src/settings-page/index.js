/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SettingsApp from './settings-app';
import './style.css';

const container = document.getElementById( 'gratis-ai-agent-settings-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <SettingsApp /> );
}
