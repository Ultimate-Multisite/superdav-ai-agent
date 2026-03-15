/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

export default function ImportDialog( { onClose } ) {
	const [ fileData, setFileData ] = useState( null );
	const [ fileName, setFileName ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const { importSession } = useDispatch( STORE_NAME );
	const dialogRef = useRef( null );
	const dropRef = useRef( null );

	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	const handleFile = useCallback( ( file ) => {
		setError( '' );
		setFileName( file.name );

		const reader = new FileReader();
		reader.onload = ( evt ) => {
			try {
				const data = JSON.parse( evt.target.result );
				const validFormats = [ 'gratis-ai-agent-v1', 'ai-agent-v1' ];
				if ( ! validFormats.includes( data.format ) ) {
					setError(
						__(
							'Invalid format. Expected gratis-ai-agent-v1.',
							'gratis-ai-agent'
						)
					);
					return;
				}
				setFileData( data );
			} catch {
				setError( __( 'Invalid JSON file.', 'gratis-ai-agent' ) );
			}
		};
		reader.readAsText( file );
	}, [] );

	const handleDrop = useCallback(
		( e ) => {
			e.preventDefault();
			const file = e.dataTransfer?.files?.[ 0 ];
			if ( file ) {
				handleFile( file );
			}
		},
		[ handleFile ]
	);

	const handleImport = useCallback( () => {
		if ( fileData ) {
			importSession( fileData );
			onClose();
		}
	}, [ fileData, importSession, onClose ] );

	return (
		<div className="gratis-ai-agent-shortcuts-overlay">
			<div className="gratis-ai-agent-export-dialog" ref={ dialogRef }>
				<div className="gratis-ai-agent-export-header">
					<h3>{ __( 'Import Conversation', 'gratis-ai-agent' ) }</h3>
					<button type="button" onClick={ onClose }>
						&times;
					</button>
				</div>
				<div className="gratis-ai-agent-export-body">
					{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */ }
					<div
						ref={ dropRef }
						className="gratis-ai-agent-import-dropzone"
						onDragOver={ ( e ) => e.preventDefault() }
						onDrop={ handleDrop }
						onClick={ () => {
							const input = document.createElement( 'input' );
							input.type = 'file';
							input.accept = '.json';
							input.onchange = ( e ) => {
								const file = e.target.files?.[ 0 ];
								if ( file ) {
									handleFile( file );
								}
							};
							input.click();
						} }
					>
						{ fileName ? (
							<div className="gratis-ai-agent-import-file">
								<strong>{ fileName }</strong>
								{ fileData && (
									<p>
										{ fileData.title ||
											__(
												'Untitled',
												'gratis-ai-agent'
											) }{ ' ' }
										({ fileData.messages?.length || 0 }{ ' ' }
										{ __( 'messages', 'gratis-ai-agent' ) })
									</p>
								) }
							</div>
						) : (
							<p>
								{ __(
									'Drop a .json file here or click to browse',
									'gratis-ai-agent'
								) }
							</p>
						) }
					</div>
					{ error && (
						<p className="gratis-ai-agent-import-error">
							{ error }
						</p>
					) }
				</div>
				<div className="gratis-ai-agent-export-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ handleImport }
						disabled={ ! fileData }
					>
						{ __( 'Import', 'gratis-ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
