/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ErrorBoundary from '../components/error-boundary';
import AbilitiesExplorerApp from './abilities-explorer-app';
import './style.css';

const container = document.getElementById( 'sd-ai-agent-abilities-root' );
if ( container ) {
	const root = createRoot( container );
	root.render(
		<ErrorBoundary label={ __( 'Abilities Explorer', 'sd-ai-agent' ) }>
			<AbilitiesExplorerApp />
		</ErrorBoundary>
	);
}
