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

/**
 * Context menu for a session item (rename, pin, folder, export, archive, trash).
 *
 * Closes when the user clicks outside the menu. Renders an inline rename
 * input or a FolderPicker when those sub-flows are active.
 *
 * @param {Object}                     props         - Component props.
 * @param {import('../types').Session} props.session - Session data.
 * @param {Function}                   props.onClose - Called when the menu should close.
 * @return {JSX.Element} The context menu element.
 */
export default function SessionContextMenu( { session, onClose } ) {
	const [ showFolderPicker, setShowFolderPicker ] = useState( false );
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
						{ __( 'Save', 'ai-agent' ) }
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
			{ ! isTrashed && (
				<>
					<button
						type="button"
						onClick={ () => setIsRenaming( true ) }
					>
						{ __( 'Rename', 'ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => {
							pinSession( sessionId, ! isPinned );
							onClose();
						} }
					>
						{ isPinned
							? __( 'Unpin', 'ai-agent' )
							: __( 'Pin', 'ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => setShowFolderPicker( true ) }
					>
						{ __( 'Move to Folder', 'ai-agent' ) }
					</button>
					<button
						type="button"
						onClick={ () => {
							exportSession( sessionId, 'json' );
							onClose();
						} }
					>
						{ __( 'Export', 'ai-agent' ) }
					</button>
					<hr />
				</>
			) }
			{ ! isArchived && ! isTrashed && (
				<button
					type="button"
					onClick={ () => {
						archiveSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Archive', 'ai-agent' ) }
				</button>
			) }
			{ ( isArchived || isTrashed ) && (
				<button
					type="button"
					onClick={ () => {
						restoreSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Restore', 'ai-agent' ) }
				</button>
			) }
			{ ! isTrashed && (
				<button
					type="button"
					className="ai-agent-context-menu-danger"
					onClick={ () => {
						trashSession( sessionId );
						onClose();
					} }
				>
					{ __( 'Move to Trash', 'ai-agent' ) }
				</button>
			) }
		</div>
	);
}
