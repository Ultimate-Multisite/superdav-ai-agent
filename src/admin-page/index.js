/**
 * WordPress dependencies
 */
import {
	createRoot,
	useEffect,
	useState,
	useCallback,
	useMemo,
} from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
// Register gratis-ai-agent-js/* client-side abilities into core/abilities
// before the chat mounts (t165 — closes the wiring gap in #815).
import '../abilities';
import SessionSidebar from '../components/session-sidebar';
import ChatPanel from '../components/ChatPanel';
import BootError from '../components/boot-error';
import OnboardingGate from '../components/onboarding-gate';
import ShortcutsHelp from '../components/shortcuts-help';
import { useKeyboardShortcuts } from '../utils/keyboard-shortcuts';
import '../components/shared.css';
import './style.css';

/**
 * Root admin page application component. Renders the session sidebar and chat panel,
 * handles onboarding gate display, keyboard shortcuts, and slash command routing.
 *
 * The gate blocks access until at least one AI provider is connected; it then
 * transitions to the main chat UI automatically.
 *
 * @return {JSX.Element|null} Admin page app element, or null while settings are loading.
 */
function AdminPageApp() {
	const {
		fetchProviders,
		fetchSessions,
		fetchSettings,
		clearCurrentSession,
		restoreActiveJobs,
	} = useDispatch( STORE_NAME );
	const { settings, settingsLoaded, bootError } = useSelect(
		( select ) => ( {
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
			bootError: select( STORE_NAME ).getBootError(),
		} ),
		[]
	);

	const [ showOnboarding, setShowOnboarding ] = useState( false );
	const [ showShortcuts, setShowShortcuts ] = useState( false );
	const [ sidebarOpen, setSidebarOpen ] = useState( false );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
		fetchSettings();
		restoreActiveJobs();
	}, [ fetchProviders, fetchSessions, fetchSettings, restoreActiveJobs ] );

	useEffect( () => {
		if ( settingsLoaded && settings ) {
			setShowOnboarding( settings.onboarding_complete === false );
		}
	}, [ settingsLoaded, settings ] );

	/**
	 * Called when the gate clears (a provider is detected). Show the main chat UI.
	 */
	const handleGateComplete = useCallback( () => {
		setShowOnboarding( false );
	}, [] );

	const handleSlashCommand = useCallback( ( command ) => {
		if ( command === 'help' ) {
			setShowShortcuts( true );
		}
	}, [] );

	// Keyboard shortcuts.
	const shortcuts = useMemo(
		() => ( {
			'mod+n': () => clearCurrentSession(),
			'mod+k': () => {
				const searchInput = document.querySelector(
					'.gratis-ai-agent-sidebar-search'
				);
				if ( searchInput ) {
					searchInput.focus();
				}
			},
			'mod+/': () => setShowShortcuts( ( prev ) => ! prev ),
		} ),
		[ clearCurrentSession ]
	);

	useKeyboardShortcuts( shortcuts );

	// Show a friendly error instead of spinning forever when API calls fail.
	if ( bootError ) {
		return <BootError />;
	}

	if ( ! settingsLoaded ) {
		return null;
	}

	if ( showOnboarding ) {
		return <OnboardingGate onComplete={ handleGateComplete } />;
	}

	return (
		<>
			<div
				className={ `gratis-ai-agent-layout${
					sidebarOpen ? ' sidebar-is-open' : ''
				}` }
			>
				{ /* Backdrop — tapping closes the drawer on mobile */ }
				{ sidebarOpen && (
					<div
						className="gratis-ai-agent-sidebar-backdrop"
						onClick={ () => setSidebarOpen( false ) }
						aria-hidden="true"
					/>
				) }
				<SessionSidebar onClose={ () => setSidebarOpen( false ) } />
				<div className="gratis-ai-agent-main">
					{ /* Hamburger button — visible only on mobile */ }
					<button
						type="button"
						className="gratis-ai-agent-sidebar-toggle"
						onClick={ () => setSidebarOpen( ( prev ) => ! prev ) }
						aria-label={ __( 'Toggle sidebar', 'gratis-ai-agent' ) }
						aria-expanded={ sidebarOpen }
					>
						<span aria-hidden="true">&#9776;</span>
					</button>
					<ChatPanel onSlashCommand={ handleSlashCommand } />
				</div>
			</div>
			{ showShortcuts && (
				<ShortcutsHelp onClose={ () => setShowShortcuts( false ) } />
			) }
		</>
	);
}

/**
 * Mount the AdminPageApp into a given container element.
 *
 * Called by the unified admin's ChatRoute via window.gratisAiAgentChat.mount().
 * Returns a root instance so the caller can unmount cleanly.
 *
 * @param {HTMLElement} container - DOM element to mount into.
 * @return {import('@wordpress/element').Root} React root.
 */
function mountAdminPageApp( container ) {
	const root = createRoot( container );
	root.render( <AdminPageApp /> );
	return root;
}

/**
 * Expose the mount/unmount API for the unified admin's ChatRoute.
 *
 * The unified admin (src/unified-admin/routes/chat.js) calls
 * window.gratisAiAgentChat.mount(container) to embed the full chat UI
 * (sidebar + chat panel) inside the #gratis-ai-agent-chat-container div that
 * ChatRoute renders. This avoids the old pattern of both the unified admin
 * and the admin-page bundle competing to mount into #gratis-ai-agent-root.
 */
window.gratisAiAgentChat = {
	/**
	 * Mount the admin page app into the given container.
	 *
	 * @param {HTMLElement} container - Target DOM element.
	 */
	mount( container ) {
		if ( ! container ) {
			return;
		}
		// Store the root so unmount() can tear it down cleanly.
		container.__gratisAiRoot = mountAdminPageApp( container );
	},

	/**
	 * Unmount the admin page app from the given container.
	 *
	 * @param {HTMLElement} container - Target DOM element.
	 */
	unmount( container ) {
		if ( container && container.__gratisAiRoot ) {
			container.__gratisAiRoot.unmount();
			delete container.__gratisAiRoot;
		}
	},
};
