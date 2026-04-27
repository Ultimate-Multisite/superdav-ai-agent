/**
 * Redesigned sidebar — status tabs (Active / Archived / Trash) with a flat
 * session list under them. Each session row shows the leading emoji from
 * its generated title (falls back to a chat glyph) and a per-row "more"
 * menu. Pulls sessions/search from the existing gratis-ai-agent store.
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	plus,
	search,
	moreVertical,
	pin,
	commentContent,
	sidebar as sidebarIcon,
} from '@wordpress/icons';

import STORE_NAME from '../../store';
import SessionContextMenu from '../session-context-menu';

// Match the leading emoji in a session title (extended grapheme) so we
// can surface it next to the row. Preceded by start-of-string and
// optionally followed by a space.
const LEADING_EMOJI_RE =
	/^(\p{Extended_Pictographic}(\p{Extended_Pictographic}|‍|️|[\u{1F3FB}-\u{1F3FF}])*)\s*/u;

/**
 * Split the stored title into `{ emoji, title }`. If the first character
 * isn't an emoji, `emoji` is an empty string and the whole title is kept.
 *
 * @param {string} raw
 * @return {{emoji: string, title: string}} Parsed emoji and title parts.
 */
function splitTitleEmoji( raw ) {
	const src = ( raw || '' ).trim();
	if ( ! src ) {
		return { emoji: '', title: '' };
	}
	const m = src.match( LEADING_EMOJI_RE );
	if ( m ) {
		return {
			emoji: m[ 1 ],
			title: src.slice( m[ 0 ].length ),
		};
	}
	return { emoji: '', title: src };
}

/**
 *
 * @param {*} dateStr
 */
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

/**
 *
 * @param {Object} root0
 * @param {*}      root0.session
 * @param {*}      root0.isActive
 * @param {*}      root0.job
 * @param {*}      root0.onPick
 */
function SessionRow( { session, isActive, job, onPick } ) {
	const [ showMenu, setShowMenu ] = useState( false );
	const isPinned = parseInt( session.pinned, 10 ) === 1;
	const isRunning = !! job && job.status === 'processing';
	const isAwaiting = !! job && job.status === 'awaiting_confirmation';
	const changesCount = job?.changesCount;
	const { emoji, title } = splitTitleEmoji( session.title );

	let leadIcon;
	if ( isRunning ) {
		leadIcon = (
			<span
				className="gaa-cr-dot"
				title={ __( 'Agent running', 'gratis-ai-agent' ) }
			/>
		);
	} else if ( emoji ) {
		leadIcon = (
			<span className="gaa-cr-session-row-emoji" aria-hidden="true">
				{ emoji }
			</span>
		);
	} else if ( isPinned ) {
		leadIcon = <Icon icon={ pin } size={ 16 } />;
	} else {
		leadIcon = <Icon icon={ commentContent } size={ 16 } />;
	}

	let metaLabel;
	let metaIsRunning = false;
	if ( isRunning ) {
		metaLabel = changesCount
			? `${ __(
					'Running',
					'gratis-ai-agent'
			  ) } · ${ changesCount } ${ __( 'changes', 'gratis-ai-agent' ) }`
			: __( 'Running…', 'gratis-ai-agent' );
		metaIsRunning = true;
	} else if ( isAwaiting ) {
		metaLabel = __( 'Approval needed', 'gratis-ai-agent' );
		metaIsRunning = true;
	} else {
		metaLabel = relativeTime( session.updated_at );
	}

	return (
		<div
			className={ `gaa-cr-session-row${ isActive ? ' is-active' : '' }` }
			onClick={ () => onPick( session.id ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					onPick( session.id );
				}
			} }
			role="button"
			tabIndex={ 0 }
			aria-current={ isActive ? 'true' : undefined }
		>
			<span className="gaa-cr-session-row-icon">{ leadIcon }</span>
			<div className="gaa-cr-session-row-body">
				<div className="gaa-cr-session-row-title">
					{ title || __( 'Untitled', 'gratis-ai-agent' ) }
				</div>
				<div
					className={ `gaa-cr-session-row-meta${
						metaIsRunning ? ' is-running' : ''
					}` }
				>
					{ metaLabel }
				</div>
			</div>
			<div className="gaa-cr-session-row-actions">
				<button
					type="button"
					className="gaa-cr-icon-btn is-small"
					onClick={ ( e ) => {
						e.stopPropagation();
						setShowMenu( ( v ) => ! v );
					} }
					aria-label={ __( 'Session options', 'gratis-ai-agent' ) }
					aria-haspopup="menu"
					aria-expanded={ showMenu }
				>
					<Icon icon={ moreVertical } size={ 16 } />
				</button>
				{ showMenu && (
					<div className="gaa-cr-context-menu">
						<SessionContextMenu
							session={ session }
							onClose={ () => setShowMenu( false ) }
							isOwner={ true }
						/>
					</div>
				) }
			</div>
		</div>
	);
}

const FILTERS = [
	{ key: 'active', label: __( 'Active', 'gratis-ai-agent' ) },
	{ key: 'archived', label: __( 'Archived', 'gratis-ai-agent' ) },
	{ key: 'trash', label: __( 'Trash', 'gratis-ai-agent' ) },
];

/**
 *
 * @param {Object} root0
 * @param {*}      root0.collapsed
 * @param {*}      root0.onToggleCollapse
 */
export default function Sidebar( { collapsed, onToggleCollapse } ) {
	const {
		clearCurrentSession,
		fetchSessions,
		fetchSharedSessions,
		setSessionSearch,
		setSessionFilter,
		openSession,
	} = useDispatch( STORE_NAME );
	const {
		sessions,
		currentSessionId,
		sessionSearch,
		sessionFilter,
		sessionJobs,
	} = useSelect(
		( sel ) => ( {
			sessions: sel( STORE_NAME ).getSessions(),
			currentSessionId: sel( STORE_NAME ).getCurrentSessionId(),
			sessionSearch: sel( STORE_NAME ).getSessionSearch(),
			sessionFilter: sel( STORE_NAME ).getSessionFilter(),
			sessionJobs: sel( STORE_NAME ).getSessionJobs(),
		} ),
		[]
	);

	const searchTimer = useRef( null );
	const [ localQuery, setLocalQuery ] = useState( sessionSearch || '' );

	useEffect( () => {
		fetchSessions();
	}, [ fetchSessions, sessionSearch, sessionFilter ] );

	// Fetch shared sessions on mount so the context menu can show
	// Share/Unshare based on whether each session is currently shared.
	useEffect( () => {
		fetchSharedSessions();
	}, [ fetchSharedSessions ] );

	const handleSearchChange = useCallback(
		( e ) => {
			const value = e.target.value;
			setLocalQuery( value );
			clearTimeout( searchTimer.current );
			searchTimer.current = setTimeout(
				() => setSessionSearch( value ),
				300
			);
		},
		[ setSessionSearch ]
	);

	// On medium+ screens (≥782px) opening a conversation keeps the sidebar
	// open so users can jump between conversations quickly. Only small
	// screens auto-collapse so the conversation panel isn't crowded out.
	const handlePickSession = useCallback(
		( id ) => {
			openSession( id );
			const isSmall =
				typeof window !== 'undefined' &&
				window.matchMedia &&
				window.matchMedia( '(max-width: 781px)' ).matches;
			if ( isSmall ) {
				onToggleCollapse();
			}
		},
		[ openSession, onToggleCollapse ]
	);

	if ( collapsed ) {
		return null;
	}

	const total = sessions.length;

	return (
		<aside
			className="gaa-cr-sidebar"
			aria-label={ __( 'Conversations', 'gratis-ai-agent' ) }
		>
			<div className="gaa-cr-sidebar-brand">
				<div className="gaa-cr-sidebar-brand-text">
					<div className="gaa-cr-sidebar-brand-title">
						{ __( 'AI Agent', 'gratis-ai-agent' ) }
					</div>
					<div className="gaa-cr-sidebar-brand-subtitle">
						{ __(
							'Universal agent for every plugin on your site',
							'gratis-ai-agent'
						) }
					</div>
				</div>
				<button
					type="button"
					className="gaa-cr-icon-btn gaa-cr-sidebar-brand-collapse"
					onClick={ onToggleCollapse }
					aria-label={ __( 'Hide sidebar', 'gratis-ai-agent' ) }
				>
					<Icon icon={ sidebarIcon } size={ 16 } />
				</button>
			</div>
			<div className="gaa-cr-sidebar-head">
				<button
					type="button"
					className="components-button is-primary is-compact gaa-cr-new-chat"
					onClick={ () => {
						clearCurrentSession();
					} }
				>
					<Icon icon={ plus } size={ 16 } />
					<span>{ __( 'New chat', 'gratis-ai-agent' ) }</span>
				</button>
			</div>

			<div
				className="gaa-cr-sidebar-tabs"
				role="tablist"
				aria-label={ __( 'Session filter', 'gratis-ai-agent' ) }
			>
				{ FILTERS.map( ( f ) => {
					const active = sessionFilter === f.key;
					return (
						<button
							key={ f.key }
							type="button"
							role="tab"
							aria-selected={ active }
							className={ `gaa-cr-sidebar-tab${
								active ? ' is-active' : ''
							}` }
							onClick={ () => setSessionFilter( f.key ) }
						>
							{ f.label }
						</button>
					);
				} ) }
			</div>

			<div className="gaa-cr-sidebar-search">
				<div className="gaa-cr-search-field">
					<span className="gaa-cr-search-icon" aria-hidden="true">
						<Icon icon={ search } size={ 14 } />
					</span>
					<input
						type="text"
						className="gaa-cr-search-input"
						placeholder={ __(
							'Search conversations',
							'gratis-ai-agent'
						) }
						aria-label={ __(
							'Search conversations',
							'gratis-ai-agent'
						) }
						value={ localQuery }
						onChange={ handleSearchChange }
					/>
				</div>
			</div>

			<div className="gaa-cr-sidebar-list">
				{ total === 0 && (
					<div className="gaa-cr-session-empty">
						{ sessionFilter === 'trash' &&
							__( 'Trash is empty', 'gratis-ai-agent' ) }
						{ sessionFilter === 'archived' &&
							__(
								'No archived conversations',
								'gratis-ai-agent'
							) }
						{ sessionFilter === 'active' &&
							__( 'No conversations yet', 'gratis-ai-agent' ) }
					</div>
				) }
				{ sessions.map( ( s ) => (
					<SessionRow
						key={ s.id }
						session={ s }
						isActive={ currentSessionId === s.id }
						job={ sessionJobs[ s.id ] || null }
						onPick={ handlePickSession }
					/>
				) ) }
			</div>

			<div className="gaa-cr-sidebar-foot">
				<span>
					{ total === 1
						? __( '1 conversation', 'gratis-ai-agent' )
						: `${ total } ${ __(
								'conversations',
								'gratis-ai-agent'
						  ) }` }
				</span>
			</div>
		</aside>
	);
}
