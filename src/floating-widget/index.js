/**
 * WordPress dependencies
 */
import { createRoot, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import FloatingButton from './floating-button';
import FloatingPanel from './floating-panel';
import './style.css';

/**
 * Root floating widget component. Renders either the floating button or the
 * floating panel depending on open state. Fetches providers and sessions on mount
 * and gathers page context.
 *
 * @return {JSX.Element} Floating widget element.
 */
function FloatingWidget() {
	const { fetchProviders, fetchSessions, setPageContext } =
		useDispatch( STORE_NAME );
	const isOpen = useSelect(
		( select ) => select( STORE_NAME ).isFloatingOpen(),
		[]
	);

	useEffect( () => {
		fetchProviders();
		fetchSessions();
	}, [ fetchProviders, fetchSessions ] );

	// Gather page context on mount.
	useEffect( () => {
		const context = gatherPageContext();
		if ( context ) {
			setPageContext( context );
		}
	}, [ setPageContext ] );

	return (
		<>
			{ ! isOpen && <FloatingButton /> }
			{ isOpen && <FloatingPanel /> }
		</>
	);
}

/**
 * Gather structured context about the current WordPress admin page.
 *
 * @return {{url: string, admin_page?: string, screen_id?: string, post_id?: number, page_title?: string}}
 *   Page context object with available fields populated from the DOM and window globals.
 */
function gatherPageContext() {
	const context = {
		url: window.location.href,
	};

	// Admin page slug from body classes.
	const bodyClasses = document.body.className;
	const pageMatch = bodyClasses.match(
		/(?:toplevel|[\w-]+)_page_[\w-]+|edit-php|post-php|upload-php|edit-tags-php/
	);
	if ( pageMatch ) {
		context.admin_page = pageMatch[ 0 ];
	}

	// Use window.pagenow if available (set by WordPress).
	if ( window.pagenow ) {
		context.admin_page = window.pagenow;
	}

	// Screen ID from window.adminpage (set by WordPress).
	if ( window.adminpage ) {
		context.screen_id = window.adminpage;
	}

	// Post ID if on an edit screen.
	const urlParams = new URLSearchParams( window.location.search );
	const postParam = urlParams.get( 'post' );
	if ( postParam ) {
		context.post_id = parseInt( postParam, 10 ) || 0;
	}

	// Page title for extra context.
	const heading =
		document.querySelector( '.wrap > h1' ) ||
		document.querySelector( '#wpbody-content h1' );
	if ( heading ) {
		context.page_title = heading.textContent.trim();
	}

	return context;
}

// Mount the floating widget.
const wrapper = document.createElement( 'div' );
wrapper.id = 'gratis-ai-agent-floating-root';
document.body.appendChild( wrapper );

const root = createRoot( wrapper );
root.render( <FloatingWidget /> );
