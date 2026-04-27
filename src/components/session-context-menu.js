/**
 * WordPress dependencies
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Context menu for a session item (rename, pin, folder, export, archive, trash, share).
 *
 * Closes when the user clicks outside the menu. Renders an inline rename
 * input or a FolderPicker when those sub-flows are active.
 *
 * @param {Object}                     props         - Component props.
 * @param {import('../types').Session} props.session - Session data.
 * @param {Function}                   props.onClose - Called when the menu should close.
 * @param {boolean}                    props.isOwner - Whether the current user owns this session.
 * @return {JSX.Element} The context menu element.
 */
export default function SessionContextMenu( {
	session,
	onClose,
	isOwner = true,
} ) {
	const [ isRenaming, setIsRenaming ] = useState( false );
	const [ renameTitle, setRenameTitle ] = useState( session.title || '' );
	const menuRef = useRef( null );
	const renameInputRef = useRef( null );

	useEffect( () => {
		if ( isRenaming && renameInputRef.current ) {
			renameInputRef.current.focus();
		}
	}, [ isRenaming ] );

	const {
		pinSession,
		archiveSession,
		trashSession,
		restoreSession,
		deleteSession,
		renameSession,
		exportSession,
		shareSession,
		unshareSession,
	} = useDispatch( STORE_NAME );

	const sharedSessions = useSelect(
		( select ) => select( STORE_NAME ).getSharedSessions(),
		[]
	);

	const sessionId = session.id;
	const isPinned = parseInt( session.pinned, 10 ) === 1;
	const isArchived = session.status === 'archived';
	const isTrashed = session.status === 'trash';
	const isShared = sharedSessions.some( ( s ) => s.id === sessionId );

	// Close on click outside.
	useEffect( () => {
		const handler = ( e ) => {
			if ( menuRef.current && ! menuRef.current.contains( e.target ) ) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ onClose ] );

	const handleRename = () => {
		if ( renameTitle.trim() ) {
			renameSession( sessionId, renameTitle.trim() );
		}
		setIsRenaming( false );
		onClose();
	};

	if ( isRenaming ) {
		return (
			<div className="gratis-ai-agent-context-menu" ref={ menuRef }>
				<div className="gratis-ai-agent-context-menu-rename">
					<input
						ref={ renameInputRef }
						type="text"
						value={ renameTitle }
						onChange={ ( e ) => setRenameTitle( e.target.value ) }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' ) {
								handleRename();
							}
							if ( e.key === 'Escape' ) {
								onClose();
							}
						} }
					/>
					<button type="button" onClick={ handleRename }>
						{ __( 'Save', 'gratis-ai-agent' ) }
					</button>
				</div>
			</div>
		);
	}

	return (
		<div
			className="gratis-ai-agent-context-menu"
			ref={ menuRef }
			role="menu"
		>
			{ ! isTrashed && isOwner && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => setIsRenaming( true ) }
				>
					{ __( 'Rename', 'gratis-ai-agent' ) }
				</button>
			) }
			{ ! isTrashed && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						pinSession( sessionId, ! isPinned );
						onClose();
					} }
				>
					{ isPinned
						? __( 'Unpin', 'gratis-ai-agent' )
						: __( 'Pin', 'gratis-ai-agent' ) }
				</button>
			) }
			{ ! isTrashed && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						exportSession( sessionId, 'json' );
						onClose();
					} }
				>
					{ __( 'Export', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && ! isTrashed && ! isShared && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						shareSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Share with Admins', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && ! isTrashed && isShared && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						unshareSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Unshare', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && ! isArchived && ! isTrashed && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						archiveSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Archive', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && ( isArchived || isTrashed ) && (
				<button
					type="button"
					role="menuitem"
					onClick={ () => {
						restoreSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Restore', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && ! isTrashed && (
				<button
					type="button"
					role="menuitem"
					className="gratis-ai-agent-context-menu-danger"
					onClick={ () => {
						trashSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Move to Trash', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isOwner && isTrashed && (
				<button
					type="button"
					role="menuitem"
					className="gratis-ai-agent-context-menu-danger"
					onClick={ () => {
						// eslint-disable-next-line no-alert
						const confirmed = window.confirm(
							__(
								'Permanently delete this conversation? This cannot be undone.',
								'gratis-ai-agent'
							)
						);
						if ( confirmed ) {
							deleteSession( sessionId );
							onClose();
						}
					} }
				>
					{ __( 'Delete Permanently', 'gratis-ai-agent' ) }
				</button>
			) }
		</div>
	);
}
