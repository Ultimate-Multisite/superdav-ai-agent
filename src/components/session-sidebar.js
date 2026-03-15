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

function SessionItem( { session, isActive } ) {
	const [ showMenu, setShowMenu ] = useState( false );
	const { openSession } = useDispatch( STORE_NAME );

	const isPinned = parseInt( session.pinned, 10 ) === 1;

	return (
		<div
			className={ `ai-agent-session-item ${
				isActive ? 'is-active' : ''
			} ${ isPinned ? 'is-pinned' : '' }` }
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
				/>
			) }
		</div>
	);
}

function getEmptyMessage( filter ) {
	if ( filter === 'trash' ) {
		return __( 'Trash is empty', 'ai-agent' );
	}
	if ( filter === 'archived' ) {
		return __( 'No archived conversations', 'ai-agent' );
	}
	return __( 'No conversations yet', 'ai-agent' );
}

export default function SessionSidebar() {
	const {
		sessions,
		currentSessionId,
		sessionFilter,
		sessionFolder,
		sessionSearch,
		folders,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			sessions: store.getSessions(),
			currentSessionId: store.getCurrentSessionId(),
			sessionFilter: store.getSessionFilter(),
			sessionFolder: store.getSessionFolder(),
			sessionSearch: store.getSessionSearch(),
			folders: store.getFolders(),
		};
	}, [] );

	const {
		clearCurrentSession,
		fetchSessions,
		fetchFolders,
		setSessionFilter,
		setSessionFolder,
		setSessionSearch,
		importSession,
	} = useDispatch( STORE_NAME );

	const searchTimerRef = useRef( null );
	const fileInputRef = useRef( null );

	// Fetch folders on mount.
	useEffect( () => {
		fetchFolders();
	}, [ fetchFolders ] );

	// Refetch sessions when filter/folder changes.
	useEffect( () => {
		fetchSessions();
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
		{ key: 'archived', label: __( 'Archived', 'ai-agent' ) },
		{ key: 'trash', label: __( 'Trash', 'ai-agent' ) },
	];

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
				<input
					type="text"
					className="ai-agent-sidebar-search"
					placeholder={ __( 'Search conversations…', 'ai-agent' ) }
					onChange={ handleSearchChange }
				/>
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
				{ sessions.length === 0 && (
					<div className="ai-agent-session-empty">
						{ getEmptyMessage( sessionFilter ) }
					</div>
				) }
				{ sessions.map( ( session ) => (
					<SessionItem
						key={ session.id }
						session={ session }
						isActive={
							currentSessionId === parseInt( session.id, 10 )
						}
					/>
				) ) }
			</div>
		</div>
	);
}
