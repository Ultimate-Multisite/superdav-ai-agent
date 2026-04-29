/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ErrorBoundary from '../components/error-boundary';
import ChangesApp from './changes-app';
import './style.css';

const container = document.getElementById( 'sd-ai-agent-changes-root' );
if ( container ) {
	const root = createRoot( container );
	root.render(
		<ErrorBoundary label={ __( 'AI Changes', 'sd-ai-agent' ) }>
			<ChangesApp />
		</ErrorBoundary>
	);
}
