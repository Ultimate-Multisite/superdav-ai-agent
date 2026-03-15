/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, arrowUp } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SlashCommandMenu from './slash-command-menu';

export default function MessageInput( { compact = false, onSlashCommand } ) {
	const [ text, setText ] = useState( '' );
	const [ showSlash, setShowSlash ] = useState( false );
	const textareaRef = useRef( null );
	const sending = useSelect(
		( select ) => select( STORE_NAME ).isSending(),
		[]
	);
	const currentSessionId = useSelect(
		( select ) => select( STORE_NAME ).getCurrentSessionId(),
		[]
	);
	const debugMode = useSelect(
		( select ) => select( STORE_NAME ).isDebugMode(),
		[]
	);
	const {
		sendMessage,
		streamMessage,
		stopGeneration,
		clearCurrentSession,
		compactConversation,
		exportSession,
		setDebugMode,
	} = useDispatch( STORE_NAME );

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

	const handleSend = useCallback( () => {
		const trimmed = text.trim();
		if ( ! trimmed || sending ) {
			return;
		}

		// Handle /remember <fact> command.
		if ( trimmed.startsWith( '/remember ' ) ) {
			const fact = trimmed.slice( 10 ).trim();
			if ( fact ) {
				apiFetch( {
					path: '/ai-agent/v1/memory',
					method: 'POST',
					data: { category: 'general', content: fact },
				} )
					.then( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__( 'Memory saved.', 'ai-agent' )
							);
						}
					} )
					.catch( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__( 'Failed to save memory.', 'ai-agent' )
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
					path: '/ai-agent/v1/memory/forget',
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
												? __( 'memory', 'ai-agent' )
												: __( 'memories', 'ai-agent' )
									  } ${ __( 'deleted.', 'ai-agent' ) }`
									: __(
											'No matching memories found.',
											'ai-agent'
									  )
							);
						}
					} )
					.catch( () => {
						if ( onSlashCommand ) {
							onSlashCommand(
								'notice',
								__( 'Failed to forget memories.', 'ai-agent' )
							);
						}
					} );
			}
			setText( '' );
			return;
		}

		// Use streaming when the Fetch API and ReadableStream are available.
		if ( window.fetch && window.ReadableStream ) {
			streamMessage( trimmed );
		} else {
			sendMessage( trimmed );
		}
		setText( '' );
		setTimeout(
			() => textareaRef.current?.focus( { preventScroll: true } ),
			0
		);
	}, [ text, sending, sendMessage, streamMessage, onSlashCommand ] );

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
				case 'debug':
					setDebugMode( ! debugMode );
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
			debugMode,
			setDebugMode,
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

	return (
		<div
			className={ `ai-agent-input-area ${ compact ? 'is-compact' : '' }` }
		>
			{ showSlash && (
				<SlashCommandMenu
					filter={ text }
					onSelect={ handleSlashSelect }
					onClose={ () => setShowSlash( false ) }
				/>
			) }
			<textarea
				ref={ textareaRef }
				className="ai-agent-input"
				rows={ 1 }
				placeholder={ __(
					'Type a message or / for commands…',
					'ai-agent'
				) }
				value={ text }
				onChange={ ( e ) => setText( e.target.value ) }
				onKeyDown={ handleKeyDown }
				disabled={ sending }
			/>
			{ sending ? (
				<Button
					variant="secondary"
					onClick={ stopGeneration }
					className="ai-agent-stop-btn"
					label={ __( 'Stop', 'ai-agent' ) }
				>
					{ __( 'Stop', 'ai-agent' ) }
				</Button>
			) : (
				<Button
					variant="primary"
					onClick={ handleSend }
					disabled={ ! text.trim() }
					className="ai-agent-send-btn"
					label={ __( 'Send', 'ai-agent' ) }
					icon={ <Icon icon={ arrowUp } /> }
				/>
			) }
		</div>
	);
}
