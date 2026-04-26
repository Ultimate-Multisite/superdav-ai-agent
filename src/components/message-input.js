/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, arrowUp, closeSmall } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SlashCommandMenu from './slash-command-menu';
import FeedbackConsentModal from './feedback-consent-modal';
import useSpeechRecognition from './use-speech-recognition';

/** Maximum file size in bytes (10 MB). */
const MAX_FILE_SIZE = 10 * 1024 * 1024;

/** Accepted image MIME types for vision models. */
const ACCEPTED_IMAGE_TYPES = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
];

/** Accepted document MIME types (text extraction fallback). */
const ACCEPTED_DOC_TYPES = [ 'text/plain', 'text/csv', 'application/pdf' ];

const ACCEPTED_TYPES = [ ...ACCEPTED_IMAGE_TYPES, ...ACCEPTED_DOC_TYPES ];

/**
 * Read a File as a base64 data URL.
 *
 * @param {File} file - The file to read.
 * @return {Promise<string>} Resolves with the data URL string.
 */
function readFileAsDataUrl( file ) {
	return new Promise( ( resolve, reject ) => {
		const reader = new FileReader();
		reader.onload = ( e ) => resolve( e.target.result );
		reader.onerror = () => reject( new Error( 'Failed to read file' ) );
		reader.readAsDataURL( file );
	} );
}

/**
 * Validate a file against size and type constraints.
 *
 * @param {File} file - The file to validate.
 * @return {string|null} Error message, or null if valid.
 */
function validateFile( file ) {
	if ( file.size > MAX_FILE_SIZE ) {
		return __( 'File exceeds 10 MB limit.', 'gratis-ai-agent' );
	}
	if ( ! ACCEPTED_TYPES.includes( file.type ) ) {
		return __( 'Unsupported file type.', 'gratis-ai-agent' );
	}
	return null;
}

/**
 * Paperclip icon SVG for the upload button.
 *
 * @return {JSX.Element} SVG element.
 */
function PaperclipIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="18"
			height="18"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
		</svg>
	);
}

/**
 * Attachment thumbnail strip shown above the input when files are pending.
 *
 * @param {Object}   props             - Component props.
 * @param {Array}    props.attachments - Array of attachment objects.
 * @param {Function} props.onRemove    - Called with index to remove an attachment.
 * @return {JSX.Element|null} The thumbnail strip, or null when empty.
 */
function AttachmentPreviews( { attachments, onRemove } ) {
	if ( ! attachments.length ) {
		return null;
	}

	return (
		<div className="gratis-ai-agent-attachment-previews">
			{ attachments.map( ( att, i ) => (
				<div key={ i } className="gratis-ai-agent-attachment-thumb">
					{ att.isImage ? (
						<img
							src={ att.dataUrl }
							alt={ att.name }
							className="gratis-ai-agent-attachment-thumb__img"
						/>
					) : (
						<div className="gratis-ai-agent-attachment-thumb__file">
							<span className="gratis-ai-agent-attachment-thumb__ext">
								{ att.name.split( '.' ).pop().toUpperCase() }
							</span>
						</div>
					) }
					<span className="gratis-ai-agent-attachment-thumb__name">
						{ att.name }
					</span>
					<button
						type="button"
						className="gratis-ai-agent-attachment-thumb__remove"
						onClick={ () => onRemove( i ) }
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
	);
}

/**
 * Message input area with auto-resize, slash command menu, file upload, and send/stop controls.
 *
 * Handles the /remember and /forget slash commands locally; all other messages
 * are dispatched via streamMessage (when Fetch + ReadableStream are available)
 * or sendMessage (polling fallback).
 *
 * Supports image/file attachments via:
 * - Drag-and-drop onto the input area
 * - Clipboard paste (Ctrl+V / Cmd+V with image data)
 * - Upload button (paperclip icon) opening a file picker
 *
 * @param {Object}   props                  - Component props.
 * @param {boolean}  [props.compact=false]  - Whether to render in compact mode.
 * @param {Function} [props.onSlashCommand] - Callback for slash command results.
 *                                          Signature: (type: string, message?: string) => void
 * @return {JSX.Element} The message input element.
 */
export default function MessageInput( { compact = false, onSlashCommand } ) {
	const [ text, setText ] = useState( '' );
	const [ showSlash, setShowSlash ] = useState( false );
	const [ attachments, setAttachments ] = useState( [] );
	const [ isDragOver, setIsDragOver ] = useState( false );
	const [ feedbackModal, setFeedbackModal ] = useState( {
		isOpen: false,
		reportType: 'user_reported',
		userDescription: '',
	} );
	const textareaRef = useRef( null );
	const fileInputRef = useRef( null );
	const sending = useSelect(
		( select ) => select( STORE_NAME ).isSending(),
		[]
	);
	const currentSessionId = useSelect(
		( select ) => select( STORE_NAME ).getCurrentSessionId(),
		[]
	);
	const queueCount = useSelect(
		( select ) => select( STORE_NAME ).getMessageQueue().length,
		[]
	);
	const {
		sendMessage,
		stopGeneration,
		clearCurrentSession,
		compactConversation,
		exportSession,
	} = useDispatch( STORE_NAME );

	// Push-to-talk: append recognised speech to the textarea.
	const handleSpeechResult = useCallback( ( transcript ) => {
		setText( ( prev ) => ( prev ? prev + ' ' + transcript : transcript ) );
	}, [] );

	const {
		isListening,
		isSupported: isSpeechSupported,
		toggleListening,
	} = useSpeechRecognition( {
		interimResults: true,
		onResult: handleSpeechResult,
	} );

	// Auto-resize textarea to fit content.
	useEffect( () => {
		const el = textareaRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height =
			Math.min( el.scrollHeight, compact ? 120 : 200 ) + 'px';
	}, [ text, compact ] );

	// Check for slash command prefix (hide menu once user starts typing arguments).
	useEffect( () => {
		if ( text.startsWith( '/' ) && ! text.includes( ' ' ) ) {
			setShowSlash( true );
		} else {
			setShowSlash( false );
		}
	}, [ text ] );

	/**
	 * Process a list of File objects into attachment objects.
	 *
	 * @param {FileList|File[]} files - Files to process.
	 */
	const processFiles = useCallback(
		async ( files ) => {
			const fileArray = Array.from( files );
			const newAttachments = [];

			for ( const file of fileArray ) {
				const error = validateFile( file );
				if ( error ) {
					if ( onSlashCommand ) {
						onSlashCommand( 'notice', error );
					}
					continue;
				}

				try {
					const dataUrl = await readFileAsDataUrl( file );
					newAttachments.push( {
						name: file.name,
						type: file.type,
						dataUrl,
						isImage: ACCEPTED_IMAGE_TYPES.includes( file.type ),
					} );
				} catch {
					if ( onSlashCommand ) {
						onSlashCommand(
							'notice',
							__( 'Failed to read file.', 'gratis-ai-agent' )
						);
					}
				}
			}

			if ( newAttachments.length ) {
				setAttachments( ( prev ) => [ ...prev, ...newAttachments ] );
			}
		},
		[ onSlashCommand ]
	);

	/** Remove an attachment by index. */
	const handleRemoveAttachment = useCallback( ( index ) => {
		setAttachments( ( prev ) => prev.filter( ( _, i ) => i !== index ) );
	}, [] );

	/** Handle file input change (upload button). */
	const handleFileInputChange = useCallback(
		( e ) => {
			if ( e.target.files?.length ) {
				processFiles( e.target.files );
				// Reset so the same file can be re-selected.
				e.target.value = '';
			}
		},
		[ processFiles ]
	);

	/** Handle drag-over — show drop indicator. */
	const handleDragOver = useCallback( ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragOver( true );
	}, [] );

	/** Handle drag-leave — hide drop indicator. */
	const handleDragLeave = useCallback( ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragOver( false );
	}, [] );

	/** Handle drop event. */
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

	/** Handle paste event — capture image data from clipboard. */
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
					.map( ( item ) => item.getAsFile() )
					.filter( Boolean );
				if ( files.length ) {
					processFiles( files );
				}
			}
		},
		[ processFiles ]
	);

	const handleSend = useCallback( () => {
		const trimmed = text.trim();
		if ( ! trimmed && ! attachments.length ) {
			return;
		}

		// Handle /remember <fact> command.
		if ( trimmed.startsWith( '/remember ' ) ) {
			const fact = trimmed.slice( 10 ).trim();
			if ( fact ) {
				apiFetch( {
					path: '/gratis-ai-agent/v1/memory',
					method: 'POST',
					data: { category: 'general', content: fact },
				} )
					.then( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__( 'Memory saved.', 'gratis-ai-agent' )
							);
						}
					} )
					.catch( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__(
									'Failed to save memory.',
									'gratis-ai-agent'
								)
							);
						}
					} );
			}
			setText( '' );
			return;
		}

		// Handle /forget <topic> command.
		if ( trimmed.startsWith( '/forget ' ) ) {
			const topic = trimmed.slice( 8 ).trim();
			if ( topic ) {
				apiFetch( {
					path: '/gratis-ai-agent/v1/memory/forget',
					method: 'POST',
					data: { topic },
				} )
					.then( ( result ) => {
						if ( onSlashCommand ) {
							const count = result?.deleted || 0;
							onSlashCommand(
								'notice',
								count > 0
									? `${ count } ${
											count === 1
												? __(
														'memory',
														'gratis-ai-agent'
												  )
												: __(
														'memories',
														'gratis-ai-agent'
												  )
									  } ${ __(
											'deleted.',
											'gratis-ai-agent'
									  ) }`
									: __(
											'No matching memories found.',
											'gratis-ai-agent'
									  )
							);
						}
					} )
					.catch( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__(
									'Failed to forget memories.',
									'gratis-ai-agent'
								)
							);
						}
					} );
			}
			setText( '' );
			return;
		}

		// Handle /report-issue [optional description] command.
		if (
			trimmed === '/report-issue' ||
			trimmed.startsWith( '/report-issue ' )
		) {
			const description = trimmed.startsWith( '/report-issue ' )
				? trimmed.slice( 14 ).trim()
				: '';
			setFeedbackModal( {
				isOpen: true,
				reportType: 'user_reported',
				userDescription: description,
			} );
			setText( '' );
			return;
		}

		sendMessage( trimmed, attachments );
		setText( '' );
		setAttachments( [] );
		setTimeout(
			() => textareaRef.current?.focus( { preventScroll: true } ),
			0
		);
	}, [ text, attachments, sendMessage, onSlashCommand ] );

	const handleSlashSelect = useCallback(
		( cmd ) => {
			setShowSlash( false );
			setText( '' );

			switch ( cmd.action ) {
				case 'new':
					clearCurrentSession();
					break;
				case 'clear':
					clearCurrentSession();
					break;
				case 'compact':
					compactConversation();
					break;
				case 'export':
					if ( currentSessionId ) {
						exportSession( currentSessionId, 'json' );
					}
					break;
				case 'help':
					if ( onSlashCommand ) {
						onSlashCommand( 'help' );
					}
					break;
				case 'model':
					// Focus back on input for model name typing.
					setText( '/model ' );
					setTimeout(
						() =>
							textareaRef.current?.focus( {
								preventScroll: true,
							} ),
						0
					);
					return;
				case 'remember':
					// Focus back on input for fact typing.
					setText( '/remember ' );
					setTimeout(
						() =>
							textareaRef.current?.focus( {
								preventScroll: true,
							} ),
						0
					);
					return;
				case 'forget':
					// Focus back on input for topic typing.
					setText( '/forget ' );
					setTimeout(
						() =>
							textareaRef.current?.focus( {
								preventScroll: true,
							} ),
						0
					);
					return;
				case 'report-issue':
					// Focus back on input for optional description typing.
					// Pressing Enter will open the FeedbackConsentModal.
					setText( '/report-issue ' );
					setTimeout(
						() =>
							textareaRef.current?.focus( {
								preventScroll: true,
							} ),
						0
					);
					return;
			}

			setTimeout(
				() => textareaRef.current?.focus( { preventScroll: true } ),
				0
			);
		},
		[
			clearCurrentSession,
			compactConversation,
			exportSession,
			currentSessionId,
			onSlashCommand,
		]
	);

	const handleKeyDown = useCallback(
		( e ) => {
			// Don't intercept if slash menu is handling arrow keys.
			if ( showSlash ) {
				return;
			}
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				handleSend();
			}
		},
		[ handleSend, showSlash ]
	);

	const canSend = text.trim().length > 0 || attachments.length > 0;

	return (
		<div
			className={ `gratis-ai-agent-input-area ${
				compact ? 'is-compact' : ''
			} ${ isDragOver ? 'is-drag-over' : '' }` }
			onDragOver={ handleDragOver }
			onDragLeave={ handleDragLeave }
			onDrop={ handleDrop }
		>
			{ showSlash && (
				<SlashCommandMenu
					filter={ text }
					onSelect={ handleSlashSelect }
					onClose={ () => setShowSlash( false ) }
				/>
			) }
			{ feedbackModal.isOpen && (
				<FeedbackConsentModal
					reportType={ feedbackModal.reportType }
					userDescription={ feedbackModal.userDescription }
					sessionId={ currentSessionId }
					onClose={ () =>
						setFeedbackModal( ( prev ) => ( {
							...prev,
							isOpen: false,
						} ) )
					}
				/>
			) }
			<AttachmentPreviews
				attachments={ attachments }
				onRemove={ handleRemoveAttachment }
			/>
			{ isDragOver && (
				<div className="gratis-ai-agent-drop-overlay">
					{ __( 'Drop files here', 'gratis-ai-agent' ) }
				</div>
			) }
			{ queueCount > 0 && (
				<div className="gratis-ai-agent-queue-indicator">
					{ queueCount === 1
						? __( '1 message queued', 'gratis-ai-agent' )
						: `${ queueCount } ${ __(
								'messages queued',
								'gratis-ai-agent'
						  ) }` }
				</div>
			) }
			<div className="gratis-ai-agent-input-row">
				{ /* Hidden file input */ }
				<input
					ref={ fileInputRef }
					type="file"
					accept={ ACCEPTED_TYPES.join( ',' ) }
					multiple
					className="gratis-ai-agent-file-input"
					onChange={ handleFileInputChange }
					aria-hidden="true"
					tabIndex={ -1 }
					style={ { display: 'none' } }
				/>
				{ /* Upload button (paperclip) */ }
				<Button
					onClick={ () => fileInputRef.current?.click() }
					className="gratis-ai-agent-upload-btn"
					label={ __( 'Attach file', 'gratis-ai-agent' ) }
					icon={ <PaperclipIcon /> }
				/>
				<textarea
					ref={ textareaRef }
					className="gratis-ai-agent-input"
					rows={ 1 }
					placeholder={
						sending
							? __(
									'Type to queue a message…',
									'gratis-ai-agent'
							  )
							: __(
									'Type a message or / for commands…',
									'gratis-ai-agent'
							  )
					}
					value={ text }
					onChange={ ( e ) => setText( e.target.value ) }
					onKeyDown={ handleKeyDown }
					onPaste={ handlePaste }
				/>
				{ isSpeechSupported && (
					<Button
						onClick={ toggleListening }
						className={ `gratis-ai-agent-mic-btn${
							isListening ? ' is-listening' : ''
						}` }
						label={
							isListening
								? __( 'Stop recording', 'gratis-ai-agent' )
								: __( 'Start voice input', 'gratis-ai-agent' )
						}
						aria-label={
							isListening
								? __( 'Stop recording', 'gratis-ai-agent' )
								: __( 'Voice input', 'gratis-ai-agent' )
						}
						aria-pressed={ isListening }
						icon={
							<svg
								xmlns="http://www.w3.org/2000/svg"
								viewBox="0 0 24 24"
								width="18"
								height="18"
								fill="currentColor"
								aria-hidden="true"
								focusable="false"
							>
								<path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.93V20H9v2h6v-2h-2v-2.07A7 7 0 0 0 19 11h-2z" />
							</svg>
						}
					/>
				) }
				{ sending && (
					<Button
						onClick={ stopGeneration }
						className="gratis-ai-agent-stop-btn"
						label={ __( 'Stop generation', 'gratis-ai-agent' ) }
						showTooltip
						icon={ <Icon icon={ closeSmall } size={ 18 } /> }
					/>
				) }
				<Button
					variant="primary"
					onClick={ handleSend }
					disabled={ ! canSend }
					className="gratis-ai-agent-send-btn"
					label={
						sending
							? __( 'Queue message', 'gratis-ai-agent' )
							: __( 'Send message', 'gratis-ai-agent' )
					}
					aria-label={
						sending
							? __( 'Queue message', 'gratis-ai-agent' )
							: __( 'Send message', 'gratis-ai-agent' )
					}
					icon={ <Icon icon={ arrowUp } /> }
				/>
			</div>
		</div>
	);
}
