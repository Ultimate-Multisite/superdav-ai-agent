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
		return __( 'just now', 'gratis-ai-agent' );
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
			className={ `gratis-ai-agent-session-item ${
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
			<div className="gratis-ai-agent-session-title">
				{ isPinned && (
					<span
						className="gratis-ai-agent-pin-icon"
						title={ __( 'Pinned', 'gratis-ai-agent' ) }
					>
						&#128204;
					</span>
				) }
				{ session.title || __( 'Untitled', 'gratis-ai-agent' ) }
			</div>
			<div className="gratis-ai-agent-session-meta">
				{ session.folder && (
					<span className="gratis-ai-agent-session-folder-badge">
						{ session.folder }
					</span>
				) }
				{ relativeTime( session.updated_at ) }
			</div>
			<button
				className="gratis-ai-agent-session-more"
				onClick={ ( e ) => {
					e.stopPropagation();
					setShowMenu( ! showMenu );
				} }
				title={ __( 'More', 'gratis-ai-agent' ) }
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
					window.alert(
						__( 'Invalid JSON file.', 'gratis-ai-agent' )
					);
				}
			};
			reader.readAsText( file );
			e.target.value = '';
		},
		[ importSession ]
	);

	const filterTabs = [
		{ key: 'active', label: __( 'Active', 'gratis-ai-agent' ) },
		{ key: 'archived', label: __( 'Archived', 'gratis-ai-agent' ) },
		{ key: 'trash', label: __( 'Trash', 'gratis-ai-agent' ) },
	];

	return (
		<div className="gratis-ai-agent-sidebar">
			<div className="gratis-ai-agent-sidebar-header">
				<div className="gratis-ai-agent-sidebar-actions">
					<Button
						variant="primary"
						icon={ plus }
						onClick={ clearCurrentSession }
						className="gratis-ai-agent-new-chat-btn"
					>
						{ __( 'New Chat', 'gratis-ai-agent' ) }
					</Button>
					<Button
						variant="tertiary"
						icon={ upload }
						onClick={ () => fileInputRef.current?.click() }
						className="gratis-ai-agent-import-btn"
						label={ __( 'Import', 'gratis-ai-agent' ) }
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
					className="gratis-ai-agent-sidebar-search"
					placeholder={ __(
						'Search conversations…',
						'gratis-ai-agent'
					) }
					onChange={ handleSearchChange }
				/>
			</div>
			<div className="gratis-ai-agent-sidebar-filters">
				{ filterTabs.map( ( tab ) => (
					<button
						key={ tab.key }
						type="button"
						className={ `gratis-ai-agent-filter-tab ${
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
				<div className="gratis-ai-agent-sidebar-folders">
					<button
						type="button"
						className={ `gratis-ai-agent-folder-tab ${
							! sessionFolder ? 'is-active' : ''
						}` }
						onClick={ () => setSessionFolder( '' ) }
					>
						{ __( 'All', 'gratis-ai-agent' ) }
					</button>
					{ folders.map( ( folder ) => (
						<button
							key={ folder }
							type="button"
							className={ `gratis-ai-agent-folder-tab ${
								sessionFolder === folder ? 'is-active' : ''
							}` }
							onClick={ () => setSessionFolder( folder ) }
						>
							{ folder }
						</button>
					) ) }
				</div>
			) }
			<div className="gratis-ai-agent-session-list">
				{ sessions.length === 0 && (
					<div className="gratis-ai-agent-session-empty">
						{ ( () => {
							if ( sessionFilter === 'trash' ) {
								return __(
									'Trash is empty',
									'gratis-ai-agent'
								);
							}
							if ( sessionFilter === 'archived' ) {
								return __(
									'No archived conversations',
									'gratis-ai-agent'
								);
							}
							return __(
								'No conversations yet',
								'gratis-ai-agent'
							);
						} )() }
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
