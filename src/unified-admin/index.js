/**
 * WordPress dependencies
 */
import { createRoot, useState, useEffect } from '@wordpress/element';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.css';
import Router from './router';
import Navigation from './navigation';
import { AppProvider } from './context';

/**
 * Derive the initial route from the URL hash (JS-side), falling back to the
 * PHP-localized value (which cannot read fragments) or 'chat'.
 *
 * @return {string} Initial route string.
 */
function getInitialRoute() {
	const hash = window.location.hash;
	if ( hash && hash.startsWith( '#/' ) ) {
		return hash.substring( 2 );
	}
	return window.gratisAiAgentData?.initialRoute || 'chat';
}

/**
 * Unified Admin App Component
 *
 * Main entry point for the unified admin interface with hash-based routing.
 *
 * @return {JSX.Element} App element.
 */
function UnifiedAdminApp() {
	const [ currentRoute, setCurrentRoute ] = useState( getInitialRoute );
	const [ notice, setNotice ] = useState( null );

	// Listen for hash changes.
	useEffect( () => {
		const handleHashChange = () => {
			const hash = window.location.hash;
			if ( hash && hash.startsWith( '#/' ) ) {
				setCurrentRoute( hash.substring( 2 ) );
			} else if ( ! hash ) {
				setCurrentRoute( 'chat' );
			}
		};

		window.addEventListener( 'hashchange', handleHashChange );

		return () =>
			window.removeEventListener( 'hashchange', handleHashChange );
	}, [] );

	// Update document title based on route.
	useEffect( () => {
		const menuItems = window.gratisAiAgentData?.menuItems || [];
		const baseRoute = currentRoute.split( '/' )[ 0 ];
		const currentItem = menuItems.find(
			( item ) => item.slug === baseRoute
		);
		if ( currentItem ) {
			document.title = `${ currentItem.label } - AI Agent`;
		}
	}, [ currentRoute ] );

	const appContext = {
		currentRoute,
		setCurrentRoute,
		showNotice: ( status, message ) => setNotice( { status, message } ),
		clearNotice: () => setNotice( null ),
	};

	return (
		<AppProvider value={ appContext }>
			<div className="gratis-ai-unified-admin">
				{ notice && (
					<Notice
						status={ notice.status }
						isDismissible
						onRemove={ () => setNotice( null ) }
						className="gratis-ai-admin-notice"
					>
						{ notice.message }
					</Notice>
				) }

				<div className="gratis-ai-admin-layout">
					<Navigation
						currentRoute={ currentRoute }
						onNavigate={ ( route ) => {
							window.location.hash = '#/' + route;
						} }
					/>

					<main className="gratis-ai-admin-main">
						<Router route={ currentRoute } />
					</main>
				</div>
			</div>
		</AppProvider>
	);
}

const container = document.getElementById( 'gratis-ai-agent-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <UnifiedAdminApp /> );
}
