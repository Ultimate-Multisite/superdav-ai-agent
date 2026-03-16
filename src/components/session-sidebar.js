/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { plus, upload } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SessionContextMenu from './session-context-menu';

/**
 * @typedef {import('../types').Session} Session
 */

/**
 * Format a date string as a relative time label (e.g. 'just now', '3h ago').
 *
 * @param {string} dateStr - ISO 8601 date string without trailing Z (UTC assumed).
 * @return {string} Human-readable relative time, or '' when dateStr is falsy.
 */
function relativeTime( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}
	const date = new Date( dateStr + 'Z' );
	const now = new Date();
	const diff = Math.floor( ( now - date ) / 1000 );

	if ( diff < 60 ) {
		return __( 'just now', 'ai-agent' );
	}
	if ( diff < 3600 ) {
		return Math.floor( diff / 60 ) + 'm ago';
	}
	if ( diff < 86400 ) {
		return Math.floor( diff / 3600 ) + 'h ago';
	}
	if ( diff < 604800 ) {
		return Math.floor( diff / 86400 ) + 'd ago';
	}
	return date.toLocaleDateString();
}

/**
 * A single session row in the sidebar list.
 *
 * Clicking the row opens the session. The ⋯ button opens a context menu
 * with rename, pin, folder, export, archive, trash, and share actions.
 *
 * @param {Object}  props          - Component props.
 * @param {Session} props.session  - Session data.
 * @param {boolean} props.isActive - Whether this session is currently open.
 * @param {boolean} props.isOwner  - Whether the current user owns this session.
 * @return {JSX.Element} The session item element.
 */
function SessionItem( { session, isActive, isOwner = true } ) {
	const [ showMenu, setShowMenu ] = useState( false );
	const { openSession } = useDispatch( STORE_NAME );

	const isPinned = parseInt( session.pinned, 10 ) === 1;
	const isShared =
		parseInt( session.is_shared, 10 ) === 1 || session.is_shared === true;

	return (
		<div
			className={ `ai-agent-session-item ${
				isActive ? 'is-active' : ''
			} ${ isPinned ? 'is-pinned' : '' } ${
				isShared ? 'is-shared' : ''
			}` }
			onClick={ () => openSession( parseInt( session.id, 10 ) ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' ) {
					openSession( parseInt( session.id, 10 ) );
				}
			} }
			role="button"
			tabIndex={ 0 }
		>
			<div className="ai-agent-session-title">
				{ isPinned && (
					<span
						className="ai-agent-pin-icon"
						title={ __( 'Pinned', 'ai-agent' ) }
					>
						&#128204;
					</span>
				) }
				{ isShared && (
					<span
						className="ai-agent-shared-icon"
						title={ __( 'Shared with admins', 'ai-agent' ) }
					>
						&#128101;
					</span>
				) }
				{ session.title || __( 'Untitled', 'ai-agent' ) }
			</div>
			<div className="ai-agent-session-meta">
				{ session.folder && (
					<span className="ai-agent-session-folder-badge">
						{ session.folder }
					</span>
				) }
				{ relativeTime( session.updated_at ) }
			</div>
			<button
				className="ai-agent-session-more"
				onClick={ ( e ) => {
					e.stopPropagation();
					setShowMenu( ! showMenu );
				} }
				title={ __( 'More', 'ai-agent' ) }
				type="button"
			>
				&#8943;
			</button>
			{ showMenu && (
				<SessionContextMenu
					session={ session }
					onClose={ () => setShowMenu( false ) }
					isOwner={ isOwner }
				/>
			) }
		</div>
	);
}

/**
 * @param {string} filter
 */
function getEmptyMessage( filter ) {
	if ( filter === 'trash' ) {
		return __( 'Trash is empty', 'ai-agent' );
	}
	if ( filter === 'archived' ) {
		return __( 'No archived conversations', 'ai-agent' );
	}
	if ( filter === 'shared' ) {
		return __( 'No shared conversations', 'ai-agent' );
	}
	return __( 'No conversations yet', 'ai-agent' );
}

/**
 * Session management sidebar.
 *
 * Provides search, filter tabs (Active/Archived/Trash), folder tabs,
 * a session list, and import/new-chat controls.
 *
 * @return {JSX.Element} The session sidebar element.
 */
export default function SessionSidebar() {
	const {
		sessions,
		sharedSessions,
		currentSessionId,
		sessionFilter,
		sessionFolder,
		sessionSearch,
		folders,
		currentUserId,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			sessions: store.getSessions(),
			sharedSessions: store.getSharedSessions(),
			currentSessionId: store.getCurrentSessionId(),
			sessionFilter: store.getSessionFilter(),
			sessionFolder: store.getSessionFolder(),
			sessionSearch: store.getSessionSearch(),
			folders: store.getFolders(),
			// WordPress exposes the current user ID via wpApiSettings or similar.
			currentUserId: window.gratisAiAgentData?.currentUserId || 0,
		};
	}, [] );

	const {
		clearCurrentSession,
		fetchSessions,
		fetchFolders,
		fetchSharedSessions,
		setSessionFilter,
		setSessionFolder,
		setSessionSearch,
		importSession,
	} = useDispatch( STORE_NAME );

	const searchTimerRef = useRef( null );
	const fileInputRef = useRef( null );

	// Fetch folders and shared sessions on mount.
	useEffect( () => {
		fetchFolders();
		fetchSharedSessions();
	}, [ fetchFolders, fetchSharedSessions ] );

	// Refetch sessions when filter/folder changes (skip for 'shared' tab — uses separate list).
	useEffect( () => {
		if ( sessionFilter !== 'shared' ) {
			fetchSessions();
		}
	}, [ sessionFilter, sessionFolder, sessionSearch, fetchSessions ] );

	const handleSearchChange = useCallback(
		( e ) => {
			const value = e.target.value;
			// Debounce search.
			clearTimeout( searchTimerRef.current );
			searchTimerRef.current = setTimeout( () => {
				setSessionSearch( value );
			}, 300 );
		},
		[ setSessionSearch ]
	);

	const handleImport = useCallback(
		( e ) => {
			const file = e.target.files?.[ 0 ];
			if ( ! file ) {
				return;
			}
			const reader = new FileReader();
			reader.onload = ( evt ) => {
				try {
					const data = JSON.parse( evt.target.result );
					importSession( data );
				} catch {
					// eslint-disable-next-line no-alert
					window.alert( __( 'Invalid JSON file.', 'ai-agent' ) );
				}
			};
			reader.readAsText( file );
			e.target.value = '';
		},
		[ importSession ]
	);

	const filterTabs = [
		{ key: 'active', label: __( 'Active', 'ai-agent' ) },
		{ key: 'shared', label: __( 'Shared', 'ai-agent' ) },
		{ key: 'archived', label: __( 'Archived', 'ai-agent' ) },
		{ key: 'trash', label: __( 'Trash', 'ai-agent' ) },
	];

	// Determine which session list to render.
	const isSharedTab = sessionFilter === 'shared';
	const displaySessions = isSharedTab ? sharedSessions : sessions;

	return (
		<div className="ai-agent-sidebar">
			<div className="ai-agent-sidebar-header">
				<div className="ai-agent-sidebar-actions">
					<Button
						variant="primary"
						icon={ plus }
						onClick={ clearCurrentSession }
						className="ai-agent-new-chat-btn"
					>
						{ __( 'New Chat', 'ai-agent' ) }
					</Button>
					<Button
						variant="tertiary"
						icon={ upload }
						onClick={ () => fileInputRef.current?.click() }
						className="ai-agent-import-btn"
						label={ __( 'Import', 'ai-agent' ) }
					/>
					<input
						ref={ fileInputRef }
						type="file"
						accept=".json"
						style={ { display: 'none' } }
						onChange={ handleImport }
					/>
				</div>
			{ ! isSharedTab && (
				<input
					type="text"
					className="gratis-ai-agent-sidebar-search"
					placeholder={ __(
						'Search conversations…',
						'ai-agent'
					) }
					onChange={ handleSearchChange }
				/>
			) }
			</div>
			<div className="ai-agent-sidebar-filters">
				{ filterTabs.map( ( tab ) => (
					<button
						key={ tab.key }
						type="button"
						className={ `ai-agent-filter-tab ${
							sessionFilter === tab.key ? 'is-active' : ''
						}` }
						onClick={ () => {
							setSessionFilter( tab.key );
							setSessionFolder( '' );
							if ( tab.key === 'shared' ) {
								fetchSharedSessions();
							}
						} }
					>
						{ tab.label }
					</button>
				) ) }
			</div>
			{ folders.length > 0 && sessionFilter === 'active' && (
				<div className="ai-agent-sidebar-folders">
					<button
						type="button"
						className={ `ai-agent-folder-tab ${
							! sessionFolder ? 'is-active' : ''
						}` }
						onClick={ () => setSessionFolder( '' ) }
					>
						{ __( 'All', 'ai-agent' ) }
					</button>
					{ folders.map( ( folder ) => (
						<button
							key={ folder }
							type="button"
							className={ `ai-agent-folder-tab ${
								sessionFolder === folder ? 'is-active' : ''
							}` }
							onClick={ () => setSessionFolder( folder ) }
						>
							{ folder }
						</button>
					) ) }
				</div>
			) }
			<div className="ai-agent-session-list">
				{ displaySessions.length === 0 && (
					<div className="ai-agent-session-empty">
						{ getEmptyMessage( sessionFilter ) }
					</div>
				) }
				{ displaySessions.map( ( session ) => (
					<SessionItem
						key={ session.id }
						session={ session }
						isActive={
							currentSessionId === parseInt( session.id, 10 )
						}
						isOwner={
							! isSharedTab ||
							parseInt( session.user_id, 10 ) === currentUserId
						}
					/>
				) ) }
			</div>
		</div>
	);
}
