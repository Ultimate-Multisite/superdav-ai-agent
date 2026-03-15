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
				if ( data.format !== 'ai-agent-v1' ) {
					setError(
						__(
							'Invalid format. Expected ai-agent-v1.',
							'ai-agent'
						)
					);
					return;
				}
				setFileData( data );
			} catch {
				setError( __( 'Invalid JSON file.', 'ai-agent' ) );
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
		<div className="ai-agent-shortcuts-overlay">
			<div className="ai-agent-export-dialog" ref={ dialogRef }>
				<div className="ai-agent-export-header">
					<h3>{ __( 'Import Conversation', 'ai-agent' ) }</h3>
					<button type="button" onClick={ onClose }>
						&times;
					</button>
				</div>
				<div className="ai-agent-export-body">
					<div
						ref={ dropRef }
						className="ai-agent-import-dropzone"
						role="button"
						tabIndex={ 0 }
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
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' || e.key === ' ' ) {
								e.preventDefault();
								const input = document.createElement( 'input' );
								input.type = 'file';
								input.accept = '.json';
								input.onchange = ( ev ) => {
									const file = ev.target.files?.[ 0 ];
									if ( file ) {
										handleFile( file );
									}
								};
								input.click();
							}
						} }
					>
						{ fileName ? (
							<div className="ai-agent-import-file">
								<strong>{ fileName }</strong>
								{ fileData && (
									<p>
										{ fileData.title ||
											__(
												'Untitled',
												'ai-agent'
											) }{ ' ' }
										({ fileData.messages?.length || 0 }{ ' ' }
										{ __( 'messages', 'ai-agent' ) })
									</p>
								) }
							</div>
						) : (
							<p>
								{ __(
									'Drop a .json file here or click to browse',
									'ai-agent'
								) }
							</p>
						) }
					</div>
					{ error && (
						<p className="ai-agent-import-error">{ error }</p>
					) }
				</div>
				<div className="ai-agent-export-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ handleImport }
						disabled={ ! fileData }
					>
						{ __( 'Import', 'ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
