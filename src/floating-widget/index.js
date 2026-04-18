/**
 * WordPress dependencies
 */
import { createRoot, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
// Register gratis-ai-agent-js/* client-side abilities into core/abilities
// before the chat mounts (t165 — closes the wiring gap in #815).
import '../abilities';
import ErrorBoundary from '../components/error-boundary';
import FloatingButton from './floating-button';
import FloatingPanel from './floating-panel';
import SiteBuilderOverlay from './site-builder-overlay';
import useKeyboardShortcut from './use-keyboard-shortcut';
import { getActiveJobs } from '../utils/active-jobs-storage';
import '../components/shared.css';
import './style.css';

/**
 * Root floating widget component.
 *
 * Fetches providers and sessions on mount, gathers page context, and
 * renders one of three states:
 * - SiteBuilderOverlay: full-screen overlay for fresh installs (t062)
 * - FloatingButton: FAB when the panel is closed
 * - FloatingPanel: draggable chat panel when open
 * Registers a configurable keyboard shortcut (default: Alt+A) to toggle the panel.
 *
 * @return {JSX.Element} The floating widget element.
 */
function FloatingWidget() {
	const {
		fetchProviders,
		fetchSessions,
		fetchAlerts,
		setPageContext,
		setSiteBuilderMode,
		setFloatingOpen,
		pollJob,
	} = useDispatch( STORE_NAME );

	const { isOpen, isSiteBuilderMode, settings, bootError } = useSelect(
		( select ) => ( {
			isOpen: select( STORE_NAME ).isFloatingOpen(),
			isSiteBuilderMode: select( STORE_NAME ).isSiteBuilderMode(),
			settings: select( STORE_NAME ).getSettings(),
			bootError: select( STORE_NAME ).getBootError(),
		} ),
		[]
	);

	// Keyboard shortcut — default "alt+a", configurable via settings.
	const shortcut = settings?.keyboard_shortcut ?? 'alt+a';
	const togglePanel = useCallback( () => {
		setFloatingOpen( ! isOpen );
	}, [ setFloatingOpen, isOpen ] );
	useKeyboardShortcut( shortcut, togglePanel );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
	}, [ fetchProviders, fetchSessions ] );

	// Cross-page navigation survival (Phase 4 / t206):
	// Restore any active poll loops from sessionStorage. If the user navigated
	// away from an admin page while a background job was running, sessionStorage
	// still holds the jobId → sessionId mapping. Re-starting the poll loop here
	// reconnects to the in-progress job without a full page reload.
	// sessionStorage is cleared when the tab closes, so stale entries from a
	// previous tab session are never restored. pollJob handles already-completed
	// jobs gracefully — the first poll returns 'complete' and exits cleanly.
	useEffect( () => {
		const activeJobs = getActiveJobs();
		const entries = Object.entries( activeJobs );
		if ( entries.length === 0 ) {
			return;
		}
		for ( const [ sessionIdStr, jobId ] of entries ) {
			const sessionId = parseInt( sessionIdStr, 10 );
			if ( ! isNaN( sessionId ) && jobId ) {
				pollJob( jobId, sessionId );
			}
		}
	}, [ pollJob ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Fetch settings on mount so the keyboard shortcut is available.
	const { fetchSettings } = useDispatch( STORE_NAME );
	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	// Fetch alerts on mount and refresh every 5 minutes.
	// Skip if a boot error was raised — no point polling a broken API.
	useEffect( () => {
		if ( bootError ) {
			return;
		}
		fetchAlerts();
		const interval = setInterval( fetchAlerts, 5 * 60 * 1000 );
		return () => clearInterval( interval );
	}, [ fetchAlerts, bootError ] );

	// Gather page context on mount.
	useEffect( () => {
		const context = gatherPageContext();
		if ( context ) {
			setPageContext( context );
		}
	}, [ setPageContext ] );

	// Activate site builder mode when the PHP layer signals it (t060/t062).
	// Only activate on the main AI Agent page — not on Changes, Abilities, or
	// other admin pages where the overlay would block unrelated content (#511).
	useEffect( () => {
		if (
			window.gratisAiAgentSiteBuilder?.siteBuilderMode &&
			isMainAgentPage()
		) {
			setSiteBuilderMode( true );
		}
	}, [ setSiteBuilderMode ] );

	// If API calls failed, hide the widget entirely — the full error
	// screen is shown by the admin-page bundle instead.
	if ( bootError ) {
		return null;
	}

	// Site builder full-screen overlay takes priority over normal FAB/panel.
	// Guard: only render on the main AI Agent page.
	if ( isSiteBuilderMode && isMainAgentPage() ) {
		return <SiteBuilderOverlay />;
	}

	return (
		<>
			{ ! isOpen && <FloatingButton /> }
			{ isOpen && <FloatingPanel /> }
		</>
	);
}

/**
 * Determine whether the current admin page is the main AI Agent page.
 *
 * The site builder overlay must only render on the main AI Agent page
 * (slug: `gratis-ai-agent`). On other admin pages — Changes, Abilities,
 * Settings, or any unrelated WordPress page — the overlay must not appear
 * even when `siteBuilderMode` is true in the store (#511).
 *
 * WordPress sets `window.pagenow` to the page slug for top-level menu pages
 * (e.g. `toplevel_page_gratis-ai-agent`) and `window.adminpage` to the
 * hook suffix. We also check the URL `page` query param as a fallback.
 *
 * @return {boolean} True only when on the main AI Agent admin page.
 */
function isMainAgentPage() {
	const MAIN_PAGE_SLUG = 'gratis-ai-agent';

	// WordPress sets pagenow to `toplevel_page_{slug}` for top-level menu pages.
	if ( window.pagenow ) {
		return window.pagenow === 'toplevel_page_' + MAIN_PAGE_SLUG;
	}

	// Fallback: check the URL `page` query parameter.
	const urlParams = new URLSearchParams( window.location.search );
	return urlParams.get( 'page' ) === MAIN_PAGE_SLUG;
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
	<ErrorBoundary label={ __( 'AI Agent widget', 'gratis-ai-agent' ) }>
		<FloatingWidget />
	</ErrorBoundary>
);
