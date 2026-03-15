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

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SessionSidebar from '../components/session-sidebar';
import ChatPanel from '../components/chat-panel';
import OnboardingWizard from '../components/onboarding-wizard';
import ShortcutsHelp from '../components/shortcuts-help';
import { useKeyboardShortcuts } from '../utils/keyboard-shortcuts';
import './style.css';

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
	const [ showShortcuts, setShowShortcuts ] = useState( false );

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
		return (
			<OnboardingWizard onComplete={ () => setShowOnboarding( false ) } />
		);
	}

	return (
		<>
			<div className="gratis-ai-agent-layout">
				<SessionSidebar />
				<div className="gratis-ai-agent-main">
					<ChatPanel onSlashCommand={ handleSlashCommand } />
				</div>
			</div>
			{ showShortcuts && (
				<ShortcutsHelp onClose={ () => setShowShortcuts( false ) } />
			) }
		</>
	);
}

const container = document.getElementById( 'gratis-ai-agent-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <AdminPageApp /> );
}
