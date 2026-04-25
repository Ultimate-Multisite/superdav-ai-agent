/**
 * WordPress dependencies
 */
import { createRoot, useEffect, useState, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
// Register gratis-ai-agent-js/* client-side abilities into core/abilities
// before the chat mounts (t165 — closes the wiring gap in #815).
import '../abilities';
import ChatRedesign from '../components/chat-redesign';
import BootError from '../components/boot-error';
import ConnectorGate from '../components/connector-gate';
import OnboardingBootstrap from '../components/onboarding-bootstrap';
import ShortcutsHelp from '../components/shortcuts-help';
import { useKeyboardShortcuts } from '../utils/keyboard-shortcuts';
import '../components/shared.css';
import './style.css';

/**
 * Root admin page application component.
 *
 * Implements a two-state onboarding flow:
 *
 * 1. **Connector gate** — shown when no AI provider is configured. The user
 *    is directed to the WordPress Connectors page. The gate polls every 5 s
 *    so it disappears automatically once a provider becomes available.
 *
 * 2. **Onboarding bootstrap** — shown when a provider exists but onboarding
 *    has not yet completed. Renders the normal ChatPanel and auto-sends a
 *    kickoff message so the AI explores the site before asking any questions.
 *
 * After onboarding completes the full layout (sidebar + ChatPanel) is shown.
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
	const { settings, settingsLoaded, bootError, providers, providersLoaded } =
		useSelect(
			( select ) => ( {
				settings: select( STORE_NAME ).getSettings(),
				settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
				bootError: select( STORE_NAME ).getBootError(),
				providers: select( STORE_NAME ).getProviders(),
				providersLoaded: select( STORE_NAME ).getProvidersLoaded(),
			} ),
			[]
		);

	const [ showShortcuts, setShowShortcuts ] = useState( false );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
		fetchSettings();
		restoreActiveJobs();
	}, [ fetchProviders, fetchSessions, fetchSettings, restoreActiveJobs ] );

	// Poll for providers every 5 s while the connector gate is shown.
	// Stops once at least one provider appears.
	useEffect( () => {
		const hasProvider = providers.length > 0;
		if ( ! providersLoaded || hasProvider ) {
			return;
		}

		const timer = setInterval( () => {
			fetchProviders();
		}, 5000 );

		return () => clearInterval( timer );
	}, [ providers, providersLoaded, fetchProviders ] );

	// Refresh providers when user returns to the tab (e.g., after making
	// changes on the Connectors admin page).
	useEffect( () => {
		const handleVisibilityChange = () => {
			if ( ! document.hidden && providersLoaded ) {
				fetchProviders();
			}
		};

		document.addEventListener( 'visibilitychange', handleVisibilityChange );
		return () =>
			document.removeEventListener(
				'visibilitychange',
				handleVisibilityChange
			);
	}, [ providersLoaded, fetchProviders ] );

	// Keyboard shortcuts.
	const shortcuts = useMemo(
		() => ( {
			'mod+n': () => clearCurrentSession(),
			'mod+k': () => {
				const searchInput = document.querySelector(
					'.gaa-cr-search-input, .gratis-ai-agent-sidebar-search'
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

	if ( ! settingsLoaded || ! providersLoaded ) {
		return null;
	}

	// Phase 1 gate: no connector → show connector gate.
	const hasProvider = providers.length > 0;
	if ( ! hasProvider ) {
		return <ConnectorGate />;
	}

	// Phase 2 gate: connector exists but onboarding not yet started → bootstrap.
	const onboardingComplete = settings?.onboarding_complete !== false;
	if ( ! onboardingComplete ) {
		return <OnboardingBootstrap />;
	}

	// Normal chat layout — redesigned shell.
	return (
		<>
			<ChatRedesign />
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
