/**
 * Compact input row for the widget — same wiring as the chat-redesign
 * InputArea (sendMessage / stopGeneration / speech / attachments) but
 * rendered as a tight 1-line textarea with paperclip, model chip, mic
 * and send/stop in a single toolbar.
 */

import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, arrowUp } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import STORE_NAME from '../../store';
import useSpeechRecognition from '../use-speech-recognition';
import SlashCommandMenu from '../slash-command-menu';
import FeedbackConsentModal from '../feedback-consent-modal';
import { Paperclip, Microphone, Stop } from '../chat-redesign/icons';
import ModelPicker from '../chat-redesign/ModelPicker';
import AgentPicker from '../chat-redesign/AgentPicker';

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
export default function WidgetInput() {
	const {
		sendMessage,
		stopGeneration,
		clearCurrentSession,
		compactConversation,
		exportSession,
		setDebugMode,
	} = useDispatch( STORE_NAME );
	const { sending, queueCount, currentSessionId, debugMode } = useSelect(
		( sel ) => ( {
			sending: sel( STORE_NAME ).isSending(),
			queueCount: sel( STORE_NAME ).getMessageQueue().length,
			currentSessionId: sel( STORE_NAME ).getCurrentSessionId(),
			debugMode: sel( STORE_NAME ).isDebugMode(),
		} ),
		[]
	);

	const [ text, setText ] = useState( '' );
	const [ showSlash, setShowSlash ] = useState( false );
	const [ attachments, setAttachments ] = useState( [] );
	const [ feedbackModal, setFeedbackModal ] = useState( {
		isOpen: false,
		reportType: 'user_reported',
		userDescription: '',
	} );
	const taRef = useRef( null );
	const fileRef = useRef( null );

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

	useEffect( () => {
		const el = taRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 140 ) + 'px';
	}, [ text ] );

	useEffect( () => {
		setShowSlash( text.startsWith( '/' ) && ! text.includes( ' ' ) );
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
		const trimmed = text.trim();
		if ( ! trimmed && ! attachments.length ) {
			return;
		}

		if ( trimmed.startsWith( '/remember ' ) ) {
			const fact = trimmed.slice( 10 ).trim();
			if ( fact ) {
				apiFetch( {
					path: '/gratis-ai-agent/v1/memory',
					method: 'POST',
					data: { category: 'general', content: fact },
				} ).catch( () => {} );
			}
			setText( '' );
			return;
		}

		if ( trimmed.startsWith( '/forget ' ) ) {
			const topic = trimmed.slice( 8 ).trim();
			if ( topic ) {
				apiFetch( {
					path: '/gratis-ai-agent/v1/memory/forget',
					method: 'POST',
					data: { topic },
				} ).catch( () => {} );
			}
			setText( '' );
			return;
		}

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
		setTimeout( () => taRef.current?.focus( { preventScroll: true } ), 0 );
	}, [ text, attachments, sendMessage ] );

	const handleSlashSelect = useCallback(
		( cmd ) => {
			setShowSlash( false );
			setText( '' );

			switch ( cmd.action ) {
				case 'new':
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
				case 'debug':
					setDebugMode( ! debugMode );
					break;
				case 'model':
					setText( '/model ' );
					setTimeout(
						() => taRef.current?.focus( { preventScroll: true } ),
						0
					);
					return;
				case 'remember':
					setText( '/remember ' );
					setTimeout(
						() => taRef.current?.focus( { preventScroll: true } ),
						0
					);
					return;
				case 'forget':
					setText( '/forget ' );
					setTimeout(
						() => taRef.current?.focus( { preventScroll: true } ),
						0
					);
					return;
				case 'report-issue':
					setText( '/report-issue ' );
					setTimeout(
						() => taRef.current?.focus( { preventScroll: true } ),
						0
					);
					return;
				case 'help':
					break;
			}

			setTimeout(
				() => taRef.current?.focus( { preventScroll: true } ),
				0
			);
		},
		[
			clearCurrentSession,
			compactConversation,
			exportSession,
			currentSessionId,
			debugMode,
			setDebugMode,
		]
	);

	const handleKey = useCallback(
		( e ) => {
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
		<div className="gaa-w-input">
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
			{ queueCount > 0 && (
				<div className="gaa-w-queue-indicator">
					{ queueCount === 1
						? __( '1 message queued', 'gratis-ai-agent' )
						: sprintf(
								/* translators: %d: queued message count */
								__( '%d messages queued', 'gratis-ai-agent' ),
								queueCount
						  ) }
				</div>
			) }
			<div
				className="gaa-w-input-frame"
				role="presentation"
				onMouseDown={ handleFrameMouseDown }
			>
				{ attachments.length > 0 && (
					<div className="gaa-w-attachments">
						{ attachments.map( ( a, i ) => (
							<div key={ i } className="gaa-w-attachment-thumb">
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
								<button
									type="button"
									className="gaa-w-attachment-thumb-remove"
									onClick={ () =>
										setAttachments( ( prev ) =>
											prev.filter( ( _, j ) => j !== i )
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
					className="gaa-w-input-textarea"
					placeholder={
						sending
							? __(
									'Type to queue a message…',
									'gratis-ai-agent'
							  )
							: __(
									'Ask the agent or type / for commands…',
									'gratis-ai-agent'
							  )
					}
					value={ text }
					onChange={ ( e ) => setText( e.target.value ) }
					onKeyDown={ handleKey }
					onPaste={ handlePaste }
					rows={ 1 }
				/>
				<div className="gaa-w-input-toolbar">
					<div className="gaa-w-input-toolbar-left">
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
							title={ __( 'Attach file', 'gratis-ai-agent' ) }
						>
							<Paperclip />
						</button>
						<ModelPicker />
						<AgentPicker />
					</div>
					<div className="gaa-w-input-toolbar-right">
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
										: __( 'Voice input', 'gratis-ai-agent' )
								}
								aria-pressed={ isListening }
								title={
									isListening
										? __(
												'Stop recording',
												'gratis-ai-agent'
										  )
										: __( 'Voice input', 'gratis-ai-agent' )
								}
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
								title={ __(
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
								title={ __(
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
	);
}
