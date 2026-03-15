/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import AbilitiesExplorerApp from './abilities-explorer-app';
import './style.css';

const container = document.getElementById( 'ai-agent-abilities-explorer-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <AbilitiesExplorerApp /> );
}
