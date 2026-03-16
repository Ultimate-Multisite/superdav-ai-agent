/**
 * WordPress dependencies
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import FolderPicker from './folder-picker';
import ShareSessionDialog from './share-session-dialog';

/**
 * Context menu for a session item (rename, pin, folder, share, export, archive, trash).
 *
 * Closes when the user clicks outside the menu. Renders an inline rename
 * input, a FolderPicker, or a ShareSessionDialog when those sub-flows are active.
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
	const [ showFolderPicker, setShowFolderPicker ] = useState( false );
	const [ showShareDialog, setShowShareDialog ] = useState( false );
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
		renameSession,
		moveSessionToFolder,
		exportSession,
	} = useDispatch( STORE_NAME );

	const sessionId = parseInt( session.id, 10 );
	const isPinned = parseInt( session.pinned, 10 ) === 1;
	const isArchived = session.status === 'archived';
	const isTrashed = session.status === 'trash';

	// Close on click outside (but not when share dialog is open — it handles its own backdrop).
	useEffect( () => {
		if ( showShareDialog ) {
			return;
		}
		const handler = ( e ) => {
			if ( menuRef.current && ! menuRef.current.contains( e.target ) ) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ onClose, showShareDialog ] );

	const handleRename = () => {
		if ( renameTitle.trim() ) {
			renameSession( sessionId, renameTitle.trim() );
		}
		setIsRenaming( false );
		onClose();
	};

	// Share dialog renders outside the context menu (as a Modal).
	if ( showShareDialog ) {
		return (
			<ShareSessionDialog
				sessionId={ sessionId }
				onClose={ () => {
					setShowShareDialog( false );
					onClose();
				} }
			/>
		);
	}

	if ( isRenaming ) {
		return (
			<div className="ai-agent-context-menu" ref={ menuRef }>
				<div className="ai-agent-context-menu-rename">
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

	if ( showFolderPicker ) {
		return (
			<div className="ai-agent-context-menu" ref={ menuRef }>
				<FolderPicker
					currentFolder={ session.folder || '' }
					onSelect={ ( folder ) => {
						moveSessionToFolder( sessionId, folder );
						onClose();
					} }
					onClose={ onClose }
				/>
			</div>
		);
	}

	return (
		<div className="ai-agent-context-menu" ref={ menuRef }>
			{ ! isTrashed && isOwner && (
				<>
					<button
						type="button"
						onClick={ () => setIsRenaming( true ) }
					>
						{ __( 'Rename', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => {
							pinSession( sessionId, ! isPinned );
							onClose();
						} }
					>
						{ isPinned
							? __( 'Unpin', 'gratis-ai-agent' )
							: __( 'Pin', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => setShowFolderPicker( true ) }
					>
						{ __( 'Move to Folder', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => setShowShareDialog( true ) }
					>
						{ __( 'Share', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => {
							exportSession( sessionId, 'json' );
							onClose();
						} }
					>
						{ __( 'Export', 'gratis-ai-agent' ) }
					</button>
					<hr />
				</>
			) }
			{ ! isTrashed && ! isOwner && (
				<>
					<button
						type="button"
						onClick={ () => {
							exportSession( sessionId, 'json' );
							onClose();
						} }
					>
						{ __( 'Export', 'gratis-ai-agent' ) }
					</button>
					<hr />
				</>
			) }
			{ ! isArchived && ! isTrashed && isOwner && (
				<button
					type="button"
					onClick={ () => {
						archiveSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Archive', 'gratis-ai-agent' ) }
				</button>
			) }
			{ ( isArchived || isTrashed ) && isOwner && (
				<button
					type="button"
					onClick={ () => {
						restoreSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Restore', 'gratis-ai-agent' ) }
				</button>
			) }
			{ ! isTrashed && isOwner && (
				<button
					type="button"
					className="ai-agent-context-menu-danger"
					onClick={ () => {
						trashSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Move to Trash', 'gratis-ai-agent' ) }
				</button>
			) }
		</div>
	);
}
