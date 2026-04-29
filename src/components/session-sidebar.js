/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	plus,
	upload,
	pin,
	people,
	moreVertical,
	closeSmall,
} from '@wordpress/icons';

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
		return __( 'just now', 'sd-ai-agent' );
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
 * @param {Object}  props                        - Component props.
 * @param {Session} props.session                - Session data.
 * @param {boolean} props.isActive               - Whether this session is currently open.
 * @param {boolean} props.isOwner                - Whether the current user owns this session.
 * @param {boolean} props.hasActiveJob           - Whether the session has an active job running.
 * @param {boolean} props.hasPendingConfirmation - Whether the session job is awaiting tool confirmation.
 * @return {JSX.Element} The session item element.
 */
function SessionItem( {
	session,
	isActive,
	isOwner = true,
	hasActiveJob = false,
	hasPendingConfirmation = false,
} ) {
	const [ showMenu, setShowMenu ] = useState( false );
	const { openSession } = useDispatch( STORE_NAME );

	const isPinned = parseInt( session.pinned, 10 ) === 1;
	const isShared =
		parseInt( session.is_shared, 10 ) === 1 || session.is_shared === true;

	return (
		<div
			className={ `sd-ai-agent-session-item ${
				isActive ? 'is-active' : ''
			} ${ isPinned ? 'is-pinned' : '' } ${
				isShared ? 'is-shared' : ''
			}` }
			onClick={ () => openSession( session.id ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' ) {
					openSession( session.id );
				}
			} }
			role="button"
			tabIndex={ 0 }
			aria-current={ isActive ? 'true' : undefined }
		>
			<div className="sd-ai-agent-session-title">
				{ isPinned && (
					<span
						className="sd-ai-agent-pin-icon"
						title={ __( 'Pinned', 'sd-ai-agent' ) }
						aria-label={ __( 'Pinned', 'sd-ai-agent' ) }
					>
						<Icon icon={ pin } size={ 12 } />
					</span>
				) }
				{ isShared && (
					<span
						className="sd-ai-agent-shared-icon"
						title={ __( 'Shared with admins', 'sd-ai-agent' ) }
						aria-label={ __( 'Shared', 'sd-ai-agent' ) }
					>
						<Icon icon={ people } size={ 12 } />
					</span>
				) }
				{ session.title || __( 'Untitled', 'sd-ai-agent' ) }
				{ hasPendingConfirmation && ! isActive && (
					<span
						className="sd-ai-agent-session-confirm-badge"
						title={ __( 'Approval needed', 'sd-ai-agent' ) }
						aria-label={ __( 'Approval needed', 'sd-ai-agent' ) }
					>
						{ '\u26A0' }
					</span>
				) }
				{ hasActiveJob && ! hasPendingConfirmation && ! isActive && (
					<span
						className="sd-ai-agent-session-job-badge"
						title={ __( 'Agent is working', 'sd-ai-agent' ) }
						aria-label={ __( 'Agent is working', 'sd-ai-agent' ) }
					>
						{ '\u2022' }
					</span>
				) }
			</div>
			<div className="sd-ai-agent-session-meta">
				{ session.folder && (
					<span className="sd-ai-agent-session-folder-badge">
						{ session.folder }
					</span>
				) }
				{ relativeTime( session.updated_at ) }
			</div>
			<button
				className="sd-ai-agent-session-more"
				onClick={ ( e ) => {
					e.stopPropagation();
					setShowMenu( ! showMenu );
				} }
				title={ __( 'More', 'sd-ai-agent' ) }
				aria-label={ __( 'Session options', 'sd-ai-agent' ) }
				aria-haspopup="menu"
				aria-expanded={ showMenu }
				type="button"
			>
				<Icon icon={ moreVertical } size={ 16 } />
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
		return __( 'Trash is empty', 'sd-ai-agent' );
	}
	if ( filter === 'archived' ) {
		return __( 'No archived conversations', 'sd-ai-agent' );
	}
	if ( filter === 'shared' ) {
		return __( 'No shared conversations', 'sd-ai-agent' );
	}
	return __( 'No conversations yet', 'sd-ai-agent' );
}

/**
 * Session management sidebar.
 *
 * Provides search, filter tabs (Active/Archived/Trash), folder tabs,
 * a session list, and import/new-chat controls.
 *
 * @param {Object}   props           - Component props.
 * @param {Function} [props.onClose] - Called when the close button is clicked
 *                                   (used on mobile to collapse the drawer).
 * @return {JSX.Element} The session sidebar element.
 */
export default function SessionSidebar( { onClose } ) {
	const {
		sessions,
		sharedSessions,
		currentSessionId,
		sessionFilter,
		sessionFolder,
		sessionSearch,
		folders,
		currentUserId,
		sessionJobs,
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
			currentUserId: window.sdAiAgentData?.currentUserId || 0,
			sessionJobs: store.getSessionJobs(),
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
					window.alert( __( 'Invalid JSON file.', 'sd-ai-agent' ) );
				}
			};
			reader.readAsText( file );
			e.target.value = '';
		},
		[ importSession ]
	);

	const filterTabs = [
		{ key: 'active', label: __( 'Active', 'sd-ai-agent' ) },
		{ key: 'shared', label: __( 'Shared', 'sd-ai-agent' ) },
		{ key: 'archived', label: __( 'Archived', 'sd-ai-agent' ) },
		{ key: 'trash', label: __( 'Trash', 'sd-ai-agent' ) },
	];

	// Determine which session list to render.
	const isSharedTab = sessionFilter === 'shared';
	const displaySessions = isSharedTab ? sharedSessions : sessions;

	return (
		<div className="sd-ai-agent-sidebar">
			<div className="sd-ai-agent-sidebar-header">
				<div className="sd-ai-agent-sidebar-actions">
					<Button
						variant="primary"
						icon={ plus }
						onClick={ clearCurrentSession }
						className="sd-ai-agent-new-chat-btn"
					>
						{ __( 'New Chat', 'sd-ai-agent' ) }
					</Button>
					<Button
						variant="tertiary"
						icon={ upload }
						onClick={ () => fileInputRef.current?.click() }
						className="sd-ai-agent-import-btn"
						label={ __( 'Import', 'sd-ai-agent' ) }
					/>
					<input
						ref={ fileInputRef }
						type="file"
						accept=".json"
						style={ { display: 'none' } }
						onChange={ handleImport }
					/>
					{ onClose && (
						<Button
							className="sd-ai-agent-sidebar-close-btn"
							onClick={ onClose }
							label={ __( 'Close sidebar', 'sd-ai-agent' ) }
							showTooltip
							icon={ <Icon icon={ closeSmall } size={ 20 } /> }
						/>
					) }
				</div>
				{ ! isSharedTab && (
					<input
						type="text"
						className="sd-ai-agent-sidebar-search"
						placeholder={ __(
							'Search conversations…',
							'sd-ai-agent'
						) }
						aria-label={ __(
							'Search conversations',
							'sd-ai-agent'
						) }
						onChange={ handleSearchChange }
					/>
				) }
			</div>
			<div
				className="sd-ai-agent-sidebar-filters"
				role="tablist"
				aria-label={ __( 'Conversation filters', 'sd-ai-agent' ) }
			>
				{ filterTabs.map( ( tab ) => (
					<button
						key={ tab.key }
						type="button"
						role="tab"
						aria-selected={ sessionFilter === tab.key }
						className={ `sd-ai-agent-filter-tab ${
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
				<div
					className="sd-ai-agent-sidebar-folders"
					role="tablist"
					aria-label={ __( 'Conversation folders', 'sd-ai-agent' ) }
				>
					<button
						type="button"
						role="tab"
						aria-selected={ ! sessionFolder }
						className={ `sd-ai-agent-folder-tab ${
							! sessionFolder ? 'is-active' : ''
						}` }
						onClick={ () => setSessionFolder( '' ) }
					>
						{ __( 'All', 'sd-ai-agent' ) }
					</button>
					{ folders.map( ( folder ) => (
						<button
							key={ folder }
							type="button"
							role="tab"
							aria-selected={ sessionFolder === folder }
							className={ `sd-ai-agent-folder-tab ${
								sessionFolder === folder ? 'is-active' : ''
							}` }
							onClick={ () => setSessionFolder( folder ) }
						>
							{ folder }
						</button>
					) ) }
				</div>
			) }
			<div className="sd-ai-agent-session-list">
				{ displaySessions.length === 0 && (
					<div className="sd-ai-agent-session-empty">
						{ getEmptyMessage( sessionFilter ) }
					</div>
				) }
				{ displaySessions.map( ( session ) => (
					<SessionItem
						key={ session.id }
						session={ session }
						isActive={ currentSessionId === session.id }
						isOwner={
							! isSharedTab ||
							parseInt( session.user_id, 10 ) === currentUserId
						}
						hasActiveJob={ !! sessionJobs[ session.id ] }
						hasPendingConfirmation={
							sessionJobs[ session.id ]?.status ===
							'awaiting_confirmation'
						}
					/>
				) ) }
			</div>
		</div>
	);
}
