/**
 * Input area — frame with auto-grow textarea, bottom toolbar (paperclip /
 * model chip / mic / send).
 *
 * Wraps the existing store's sendMessage + stopGeneration + speech hook.
 */

import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, arrowUp } from '@wordpress/icons';

import STORE_NAME from '../../store';
import useSpeechRecognition from '../use-speech-recognition';
import { Paperclip, Microphone, Stop } from './icons';
import ModelPicker from './ModelPicker';

const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
];
const ACCEPTED_DOC_TYPES = [ 'text/plain', 'text/csv', 'application/pdf' ];
const ACCEPTED_TYPES = [ ...ACCEPTED_IMAGE_TYPES, ...ACCEPTED_DOC_TYPES ];

/**
 *
 * @param {*} file
 */
function readAsDataUrl( file ) {
	return new Promise( ( resolve, reject ) => {
		const reader = new FileReader();
		reader.onload = ( e ) => resolve( e.target.result );
		reader.onerror = () => reject( new Error( 'read failed' ) );
		reader.readAsDataURL( file );
	} );
}

/**
 *
 */
export default function InputArea() {
	const { sendMessage, stopGeneration } = useDispatch( STORE_NAME );
	const { sending, queueCount } = useSelect(
		( sel ) => ( {
			sending: sel( STORE_NAME ).isSending(),
			queueCount: sel( STORE_NAME ).getMessageQueue().length,
		} ),
		[]
	);

	const [ text, setText ] = useState( '' );
	const [ attachments, setAttachments ] = useState( [] );
	const [ isDragOver, setIsDragOver ] = useState( false );
	const taRef = useRef( null );
	const fileRef = useRef( null );

	// Push-to-talk.
	const handleSpeechResult = useCallback( ( transcript ) => {
		setText( ( prev ) => ( prev ? prev + ' ' + transcript : transcript ) );
	}, [] );
	const {
		isListening,
		isSupported: micSupported,
		toggleListening,
	} = useSpeechRecognition( {
		interimResults: true,
		onResult: handleSpeechResult,
	} );

	// Auto-grow textarea.
	useEffect( () => {
		const el = taRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 200 ) + 'px';
	}, [ text ] );

	const processFiles = useCallback( async ( files ) => {
		const next = [];
		for ( const file of Array.from( files ) ) {
			if ( file.size > MAX_FILE_SIZE ) {
				continue;
			}
			if ( ! ACCEPTED_TYPES.includes( file.type ) ) {
				continue;
			}
			try {
				const dataUrl = await readAsDataUrl( file );
				next.push( {
					name: file.name,
					type: file.type,
					dataUrl,
					isImage: ACCEPTED_IMAGE_TYPES.includes( file.type ),
				} );
			} catch {
				// ignore
			}
		}
		if ( next.length ) {
			setAttachments( ( prev ) => [ ...prev, ...next ] );
		}
	}, [] );

	const canSend = !! text.trim() || attachments.length > 0;

	const handleSend = useCallback( () => {
		if ( ! canSend ) {
			return;
		}
		sendMessage( text.trim(), attachments );
		setText( '' );
		setAttachments( [] );
		setTimeout( () => taRef.current?.focus( { preventScroll: true } ), 0 );
	}, [ canSend, text, attachments, sendMessage ] );

	const handleKey = useCallback(
		( e ) => {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				handleSend();
			}
		},
		[ handleSend ]
	);

	const handlePaste = useCallback(
		( e ) => {
			const items = e.clipboardData?.items;
			if ( ! items ) {
				return;
			}
			const imageItems = Array.from( items ).filter(
				( item ) =>
					item.kind === 'file' && item.type.startsWith( 'image/' )
			);
			if ( imageItems.length ) {
				e.preventDefault();
				const files = imageItems
					.map( ( i ) => i.getAsFile() )
					.filter( Boolean );
				if ( files.length ) {
					processFiles( files );
				}
			}
		},
		[ processFiles ]
	);

	const handleDrop = useCallback(
		( e ) => {
			e.preventDefault();
			e.stopPropagation();
			setIsDragOver( false );
			if ( e.dataTransfer?.files?.length ) {
				processFiles( e.dataTransfer.files );
			}
		},
		[ processFiles ]
	);

	// Clicking empty space inside the frame (e.g. between the model chip
	// and the microphone icon) focuses the textarea — standard chat-UX.
	const handleFrameMouseDown = useCallback( ( e ) => {
		if (
			e.target.closest(
				'button, input, textarea, a, [role="button"], [role="menu"], [role="menuitem"]'
			)
		) {
			return;
		}
		e.preventDefault();
		taRef.current?.focus( { preventScroll: true } );
	}, [] );

	return (
		<div className="gaa-cr-input-area">
			{ queueCount > 0 && (
				<div className="gaa-cr-queue-indicator">
					{ queueCount === 1
						? __( '1 message queued', 'gratis-ai-agent' )
						: sprintf(
								/* translators: %d: queued message count */
								__( '%d messages queued', 'gratis-ai-agent' ),
								queueCount
						  ) }
				</div>
			) }
			<div className="gaa-cr-input-inner">
				<div
					className={ `gaa-cr-input-frame${
						isDragOver ? ' is-drag-over' : ''
					}` }
					role="presentation"
					onMouseDown={ handleFrameMouseDown }
					onDragOver={ ( e ) => {
						e.preventDefault();
						setIsDragOver( true );
					} }
					onDragLeave={ ( e ) => {
						e.preventDefault();
						setIsDragOver( false );
					} }
					onDrop={ handleDrop }
				>
					{ attachments.length > 0 && (
						<div className="gaa-cr-attachments">
							{ attachments.map( ( a, i ) => (
								<div
									key={ i }
									className="gaa-cr-attachment-thumb"
								>
									{ a.isImage ? (
										<img src={ a.dataUrl } alt={ a.name } />
									) : (
										<span>
											{ a.name
												.split( '.' )
												.pop()
												.toUpperCase() }
										</span>
									) }
									<span className="gaa-cr-attachment-thumb-name">
										{ a.name }
									</span>
									<button
										type="button"
										className="gaa-cr-attachment-thumb-remove"
										onClick={ () =>
											setAttachments( ( prev ) =>
												prev.filter(
													( _, j ) => j !== i
												)
											)
										}
										aria-label={ __(
											'Remove attachment',
											'gratis-ai-agent'
										) }
									>
										&times;
									</button>
								</div>
							) ) }
						</div>
					) }
					<textarea
						ref={ taRef }
						className="gaa-cr-input-textarea"
						placeholder={
							sending
								? __(
										'Type to queue a message…',
										'gratis-ai-agent'
								  )
								: __(
										'Ask the agent to do something, or type / for commands…',
										'gratis-ai-agent'
								  )
						}
						value={ text }
						onChange={ ( e ) => setText( e.target.value ) }
						onKeyDown={ handleKey }
						onPaste={ handlePaste }
						rows={ 2 }
					/>
					<div className="gaa-cr-input-toolbar">
						<div className="gaa-cr-input-toolbar-left">
							<input
								ref={ fileRef }
								type="file"
								accept={ ACCEPTED_TYPES.join( ',' ) }
								multiple
								style={ { display: 'none' } }
								onChange={ ( e ) => {
									if ( e.target.files?.length ) {
										processFiles( e.target.files );
										e.target.value = '';
									}
								} }
							/>
							<button
								type="button"
								className="gaa-cr-icon-btn"
								onClick={ () => fileRef.current?.click() }
								aria-label={ __(
									'Attach file',
									'gratis-ai-agent'
								) }
							>
								<Paperclip />
							</button>
							<ModelPicker />
						</div>
						<div className="gaa-cr-input-toolbar-right">
							{ micSupported && (
								<button
									type="button"
									className={ `gaa-cr-icon-btn${
										isListening ? ' is-active' : ''
									}` }
									onClick={ toggleListening }
									aria-label={
										isListening
											? __(
													'Stop recording',
													'gratis-ai-agent'
											  )
											: __(
													'Voice input',
													'gratis-ai-agent'
											  )
									}
									aria-pressed={ isListening }
								>
									<Microphone />
								</button>
							) }
							{ sending ? (
								<button
									type="button"
									className="gaa-cr-send-btn is-stop"
									onClick={ stopGeneration }
									aria-label={ __(
										'Stop generation',
										'gratis-ai-agent'
									) }
								>
									<Stop />
								</button>
							) : (
								<button
									type="button"
									className="gaa-cr-send-btn"
									onClick={ handleSend }
									disabled={ ! canSend }
									aria-label={ __(
										'Send message',
										'gratis-ai-agent'
									) }
								>
									<Icon icon={ arrowUp } size={ 16 } />
								</button>
							) }
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
