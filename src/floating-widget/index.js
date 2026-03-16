/**
 * WordPress dependencies
 */
import { createRoot, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ErrorBoundary from '../components/error-boundary';
import FloatingButton from './floating-button';
import FloatingPanel from './floating-panel';
import './style.css';

/**
 * Site builder config injected by PHP via wp_localize_script.
 *
 * @type {{ siteBuilderMode: boolean, onboardingComplete: boolean, startEndpoint: string, statusEndpoint: string, nonce: string }|undefined}
 */
const siteBuilderConfig = window.gratisAiAgentSiteBuilder;

/**
 * Root floating widget component.
 *
 * Fetches providers and sessions on mount, gathers page context, and
 * renders either the FloatingButton (when closed) or FloatingPanel (when open).
 *
 * When site builder mode is active (set by PHP), the panel opens automatically
 * and injects the opening interview greeting so the user sees the first question
 * immediately without having to type anything.
 *
 * @return {JSX.Element} The floating widget element.
 */
function FloatingWidget() {
	const {
		fetchProviders,
		fetchSessions,
		fetchAlerts,
		setPageContext,
		setFloatingOpen,
		appendMessage,
		sendMessage,
	} = useDispatch( STORE_NAME );

	const isOpen = useSelect(
		( select ) => select( STORE_NAME ).isFloatingOpen(),
		[]
	);

	const siteBuilderInitialized = useRef( false );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
	}, [ fetchProviders, fetchSessions ] );

	// Fetch alerts on mount and refresh every 5 minutes.
	useEffect( () => {
		fetchAlerts();
		const interval = setInterval( fetchAlerts, 5 * 60 * 1000 );
		return () => clearInterval( interval );
	}, [ fetchAlerts ] );

	// Gather page context on mount.
	useEffect( () => {
		const context = gatherPageContext();
		if ( context ) {
			setPageContext( context );
		}
	}, [ setPageContext ] );

	// Site builder mode: auto-open and inject the opening greeting.
	useEffect( () => {
		if (
			! siteBuilderConfig?.siteBuilderMode ||
			siteBuilderConfig?.onboardingComplete ||
			siteBuilderInitialized.current
		) {
			return;
		}

		siteBuilderInitialized.current = true;

		// Open the floating panel.
		setFloatingOpen( true );

		// Inject the site builder opening message as an assistant message so
		// the user sees the first interview question immediately.
		const greeting =
			__(
				"Hi! I'm your site builder assistant. I'll help you create a complete website in just a few minutes.",
				'gratis-ai-agent'
			) +
			'\n\n' +
			__(
				"Let's start with the basics — **what is the name of your business or website?**",
				'gratis-ai-agent'
			);

		appendMessage( {
			role: 'model',
			parts: [ { text: greeting } ],
		} );
	}, [ setFloatingOpen, appendMessage, sendMessage ] );

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
 * Reads from body classes, `window.pagenow`, `window.adminpage`, URL params,
 * and the page heading to build a context object for the AI.
 *
 * @return {{url: string, admin_page?: string, screen_id?: string, post_id?: number, page_title?: string}}
 *   Context object with available page metadata.
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
root.render(
	<ErrorBoundary label={ __( 'AI Agent widget', 'ai-agent' ) }>
		<FloatingWidget />
	</ErrorBoundary>
);
