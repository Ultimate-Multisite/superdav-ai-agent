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
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SessionSidebar from '../components/session-sidebar';
import ChatPanel from '../components/chat-panel';
import OnboardingWizard from '../components/onboarding-wizard';
import OnboardingInterview from '../components/onboarding-interview';
import ShortcutsHelp from '../components/shortcuts-help';
import { useKeyboardShortcuts } from '../utils/keyboard-shortcuts';
import './style.css';

/**
 * Root admin page application component. Renders the session sidebar and chat panel,
 * handles onboarding wizard display, keyboard shortcuts, and slash command routing.
 *
 * After the wizard completes, the interview is shown if the site scan has
 * finished and the interview has not yet been done (t064).
 *
 * @return {JSX.Element|null} Admin page app element, or null while settings are loading.
 */
function AdminPageApp() {
	const {
		fetchProviders,
		fetchSessions,
		fetchSettings,
		clearCurrentSession,
	} = useDispatch( STORE_NAME );
	const { settings, settingsLoaded } = useSelect(
		( select ) => ( {
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
		} ),
		[]
	);

	const [ showOnboarding, setShowOnboarding ] = useState( false );
	const [ showInterview, setShowInterview ] = useState( false );
	const [ showShortcuts, setShowShortcuts ] = useState( false );
	const [ sidebarOpen, setSidebarOpen ] = useState( false );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
		fetchSettings();
	}, [ fetchProviders, fetchSessions, fetchSettings ] );

	useEffect( () => {
		if ( settingsLoaded && settings ) {
			setShowOnboarding( settings.onboarding_complete === false );
		}
	}, [ settingsLoaded, settings ] );

	/**
	 * Poll the interview endpoint until the scan is done, then show the interview.
	 * Gives up after 2 minutes (40 × 3 s) to avoid blocking the user indefinitely.
	 */
	const checkInterviewReady = useCallback( () => {
		let attempts = 0;
		const maxAttempts = 40;

		const poll = () => {
			apiFetch( { path: '/gratis-ai-agent/v1/onboarding/interview' } )
				.then( ( data ) => {
					if ( data.done ) {
						// Already completed — go straight to chat.
						return;
					}
					if ( data.ready ) {
						setShowInterview( true );
						return;
					}
					// Scan still running — keep polling.
					attempts++;
					if ( attempts < maxAttempts ) {
						setTimeout( poll, 3000 );
					}
				} )
				.catch( () => {
					// Non-fatal — skip the interview on error.
				} );
		};

		poll();
	}, [] );

	/**
	 * Called when the wizard finishes. Check whether the interview should be shown.
	 */
	const handleWizardComplete = useCallback( () => {
		setShowOnboarding( false );
		checkInterviewReady();
	}, [ checkInterviewReady ] );

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

	if ( ! settingsLoaded ) {
		return null;
	}

	if ( showOnboarding ) {
		return <OnboardingWizard onComplete={ handleWizardComplete } />;
	}

	if ( showInterview ) {
		return (
			<OnboardingInterview
				onComplete={ () => setShowInterview( false ) }
			/>
		);
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
