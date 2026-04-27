/**
 * Conversation header — session title, running dot, changes pill,
 * read-aloud / more icon buttons.
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, moreHorizontal, sidebar as sidebarIcon } from '@wordpress/icons';

import STORE_NAME from '../../store';
import { isTTSSupported } from '../use-text-to-speech';
import SessionContextMenu from '../session-context-menu';
import { AiIcon, Speaker, SpeakerMuted } from './icons';

/**
 *
 * @param {Object} root0
 * @param {*}      root0.sidebarCollapsed
 * @param {*}      root0.onExpandSidebar
 * @param {*}      root0.changesCount
 * @param {*}      root0.onShowChanges
 */
export default function ConvoHeader( {
	sidebarCollapsed,
	onExpandSidebar,
	changesCount,
	onShowChanges,
} ) {
	const { renameSession, setTtsEnabled } = useDispatch( STORE_NAME );
	const { session, isRunning, ttsEnabled } = useSelect( ( sel ) => {
		const store = sel( STORE_NAME );
		const currentSessionId = store.getCurrentSessionId();
		const sessions = store.getSessions();
		const sessionJobs = store.getSessionJobs();
		return {
			session:
				sessions.find( ( s ) => s.id === currentSessionId ) || null,
			isRunning: !! sessionJobs[ currentSessionId ],
			ttsEnabled: store.isTtsEnabled(),
		};
	}, [] );

	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( '' );
	const [ showMenu, setShowMenu ] = useState( false );
	const inputRef = useRef( null );

	useEffect( () => {
		if ( editing && inputRef.current ) {
			inputRef.current.focus();
			inputRef.current.select();
		}
	}, [ editing ] );

	const title = session?.title || __( 'New conversation', 'gratis-ai-agent' );

	const startRename = useCallback( () => {
		if ( ! session ) {
			return;
		}
		setDraft( session.title || '' );
		setEditing( true );
	}, [ session ] );

	const commitRename = useCallback( () => {
		if ( session && draft.trim() && draft.trim() !== session.title ) {
			renameSession( session.id, draft.trim() );
		}
		setEditing( false );
	}, [ session, draft, renameSession ] );

	return (
		<div className="gaa-cr-convo-head">
			{ sidebarCollapsed && (
				<button
					type="button"
					className="gaa-cr-icon-btn"
					onClick={ onExpandSidebar }
					aria-label={ __( 'Expand sidebar', 'gratis-ai-agent' ) }
				>
					<Icon icon={ sidebarIcon } size={ 16 } />
				</button>
			) }

			{ editing ? (
				<input
					ref={ inputRef }
					className="gaa-cr-convo-head-title-input"
					value={ draft }
					onChange={ ( e ) => setDraft( e.target.value ) }
					onBlur={ commitRename }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							commitRename();
						} else if ( e.key === 'Escape' ) {
							setEditing( false );
						}
					} }
				/>
			) : (
				<button
					type="button"
					className="gaa-cr-convo-head-title"
					onClick={ session ? startRename : undefined }
					disabled={ ! session }
					title={ title }
				>
					<span className="gaa-cr-convo-head-title-text">
						{ title }
					</span>
					{ isRunning && (
						<span
							className="gaa-cr-dot"
							title={ __( 'Agent running', 'gratis-ai-agent' ) }
						/>
					) }
					{ session && (
						<span className="gaa-cr-convo-head-rename-hint">
							{ __( 'Click to rename', 'gratis-ai-agent' ) }
						</span>
					) }
				</button>
			) }

			<div className="gaa-cr-convo-head-actions">
				<span
					className="gaa-cr-convo-head-ai-avatar"
					aria-hidden="true"
				>
					<AiIcon thinking={ isRunning } size={ 16 } />
				</span>
				{ changesCount > 0 && (
					<span
						className="gaa-cr-changes-pill"
						title={ __(
							'Changes made in this session',
							'gratis-ai-agent'
						) }
					>
						<span className="gaa-cr-changes-pill-count">
							{ changesCount }
						</span>
						<span>{ __( 'changes', 'gratis-ai-agent' ) }</span>
						<button
							type="button"
							className="gaa-cr-changes-pill-btn"
							onClick={ onShowChanges }
						>
							{ __( 'View', 'gratis-ai-agent' ) }
						</button>
					</span>
				) }
				{ isTTSSupported && (
					<button
						type="button"
						className={ `gaa-cr-icon-btn gratis-ai-agent-tts-btn${
							ttsEnabled ? ' is-active' : ''
						}` }
						onClick={ () => setTtsEnabled( ! ttsEnabled ) }
						aria-label={
							ttsEnabled
								? __( 'Disable read aloud', 'gratis-ai-agent' )
								: __(
										'Read responses aloud',
										'gratis-ai-agent'
								  )
						}
						aria-pressed={ ttsEnabled }
					>
						{ ttsEnabled ? <Speaker /> : <SpeakerMuted /> }
					</button>
				) }
				<div className="gaa-cr-convo-head-menu-wrap">
					<button
						type="button"
						className="gaa-cr-icon-btn"
						aria-label={ __( 'More options', 'gratis-ai-agent' ) }
						aria-haspopup="menu"
						aria-expanded={ showMenu }
						disabled={ ! session }
						onClick={ () => setShowMenu( ( v ) => ! v ) }
					>
						<Icon icon={ moreHorizontal } size={ 16 } />
					</button>
					{ showMenu && session && (
						<div className="gaa-cr-context-menu gaa-cr-context-menu--header">
							<SessionContextMenu
								session={ session }
								onClose={ () => setShowMenu( false ) }
								isOwner={ true }
							/>
						</div>
					) }
				</div>
			</div>
		</div>
	);
}
