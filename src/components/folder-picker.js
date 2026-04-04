/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Folder picker for moving a session to an existing or new folder.
 *
 * Lists existing folders, allows creating a new one by typing and pressing
 * Enter, and provides a "Remove from folder" option when a folder is active.
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.currentFolder - Currently assigned folder name, or ''.
 * @param {Function} props.onSelect      - Called with the chosen folder name (or '' to remove).
 * @param {Function} props.onClose       - Called when the picker should close without selecting.
 * @return {JSX.Element} The folder picker element.
 */
export default function FolderPicker( { currentFolder, onSelect, onClose } ) {
	const [ newFolder, setNewFolder ] = useState( '' );
	const { fetchFolders } = useDispatch( STORE_NAME );

	const { folders, foldersLoaded } = useSelect(
		( select ) => ( {
			folders: select( STORE_NAME ).getFolders(),
			foldersLoaded: select( STORE_NAME ).getFoldersLoaded(),
		} ),
		[]
	);

	useEffect( () => {
		if ( ! foldersLoaded ) {
			fetchFolders();
		}
	}, [ foldersLoaded, fetchFolders ] );

	return (
		<div className="gratis-ai-agent-folder-picker">
			<div className="gratis-ai-agent-folder-picker-header">
				{ __( 'Move to Folder', 'gratis-ai-agent' ) }
			</div>
			{ currentFolder && (
				<button
					type="button"
					className="gratis-ai-agent-folder-picker-item"
					onClick={ () => onSelect( '' ) }
				>
					{ __( 'Remove from folder', 'gratis-ai-agent' ) }
				</button>
			) }
			{ folders.map( ( folder ) => (
				<button
					key={ folder }
					type="button"
					className={ `gratis-ai-agent-folder-picker-item ${
						folder === currentFolder ? 'is-current' : ''
					}` }
					onClick={ () => onSelect( folder ) }
				>
					{ folder }
				</button>
			) ) }
			<div className="gratis-ai-agent-folder-picker-new">
				<input
					type="text"
					placeholder={ __( 'New folder…', 'gratis-ai-agent' ) }
					value={ newFolder }
					onChange={ ( e ) => setNewFolder( e.target.value ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' && newFolder.trim() ) {
							onSelect( newFolder.trim() );
						}
						if ( e.key === 'Escape' ) {
							onClose();
						}
					} }
				/>
				{ newFolder.trim() && (
					<button
						type="button"
						onClick={ () => onSelect( newFolder.trim() ) }
					>
						{ __( 'Create', 'gratis-ai-agent' ) }
					</button>
				) }
			</div>
		</div>
	);
}
