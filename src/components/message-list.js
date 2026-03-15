/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ToolCallDetails from './tool-call-details';
import MarkdownMessage from './markdown-message';
import MessageActions from './message-actions';
import DebugPanel from './debug-panel';

/**
 * Parse suggestion chips from the end of a model response.
 * Suggestions are lines starting with `[suggestion]`.
 *
 * @param {string} text The full response text.
 * @return {{ cleanText: string, suggestions: string[] }} Parsed text and suggestion chips.
 */
function parseSuggestions( text ) {
	const lines = text.split( '\n' );
	const suggestions = [];
	let lastContentIdx = lines.length - 1;

	// Walk backward to find suggestion lines.
	for ( let i = lines.length - 1; i >= 0; i-- ) {
		const trimmed = lines[ i ].trim();
		if ( trimmed.startsWith( '[suggestion]' ) ) {
			suggestions.unshift( trimmed.replace( /^\[suggestion\]\s*/, '' ) );
			lastContentIdx = i - 1;
		} else if ( trimmed === '' && suggestions.length > 0 ) {
			// Skip blank lines between content and suggestions.
			lastContentIdx = i - 1;
		} else {
			break;
		}
	}

	const cleanText = lines
		.slice( 0, lastContentIdx + 1 )
		.join( '\n' )
		.trimEnd();
	return { cleanText, suggestions };
}

function MessageBubble( { role, text } ) {
	const classMap = {
		user: 'ai-agent-bubble ai-agent-user',
		model: 'ai-agent-bubble ai-agent-assistant',
		system: 'ai-agent-bubble ai-agent-system',
	};

	if ( role === 'model' ) {
		return (
			<div className={ classMap.model }>
				<MarkdownMessage content={ text } />
			</div>
		);
	}

	return (
		<div className={ classMap[ role ] || classMap.system }>{ text }</div>
	);
}

function SuggestionChips( { suggestions, onSelect } ) {
	if ( ! suggestions?.length ) {
		return null;
	}

	return (
		<div className="ai-agent-suggestion-chips">
			{ suggestions.map( ( suggestion, i ) => (
				<Button
					key={ i }
					variant="tertiary"
					className="ai-agent-suggestion-chip"
					onClick={ () => onSelect( suggestion ) }
				>
					{ suggestion }
				</Button>
			) ) }
		</div>
	);
}

function extractText( message ) {
	if ( ! message.parts?.length ) {
		return '';
	}
	return message.parts
		.filter( ( p ) => p.text )
		.map( ( p ) => p.text )
		.join( '' );
}

export default function MessageList() {
	const { messages, sending, debugMode, streamingText, isStreaming } =
		useSelect( ( select ) => {
			const store = select( STORE_NAME );
			return {
				messages: store.getCurrentSessionMessages(),
				sending: store.isSending(),
				debugMode: store.isDebugMode(),
				streamingText: store.getStreamingText(),
				isStreaming: store.isStreamingActive(),
			};
		}, [] );

	const { sendMessage } = useDispatch( STORE_NAME );
	const messagesRef = useRef( null );

	useEffect( () => {
		const el = messagesRef.current;
		if ( el ) {
			// Save page scroll position — setting scrollTop on a flex child
			// can inadvertently scroll the outer page in some layouts.
			const savedY = window.scrollY;
			el.scrollTop = el.scrollHeight;
			if ( window.scrollY !== savedY ) {
				window.scrollTo( 0, savedY );
			}
		}
	}, [ messages, sending, streamingText ] );

	const visibleMessages = messages.filter( ( msg ) => {
		// Skip function-role messages (tool responses).
		if ( msg.role === 'function' ) {
			return false;
		}
		// Skip model messages that only have function calls and no text.
		if ( msg.role === 'model' ) {
			const text = extractText( msg );
			if ( ! text ) {
				return false;
			}
		}
		return true;
	} );

	return (
		<div className="ai-agent-messages" ref={ messagesRef }>
			{ visibleMessages.length === 0 && ! sending && (
				<div className="ai-agent-empty-state">
					{ __(
						'Send a message to start a conversation.',
						'ai-agent'
					) }
				</div>
			) }
			{ visibleMessages.map( ( msg, i ) => {
				const rawText = extractText( msg );
				if ( ! rawText ) {
					return null;
				}

				const isModel = msg.role === 'model';
				const { cleanText, suggestions } = isModel
					? parseSuggestions( rawText )
					: { cleanText: rawText, suggestions: [] };

				const isLastModel =
					isModel && ! sending && i === visibleMessages.length - 1;

				return (
					<div key={ i } className="ai-agent-message-row">
						{ msg.toolCalls?.length > 0 && (
							<ToolCallDetails toolCalls={ msg.toolCalls } />
						) }
						<MessageBubble role={ msg.role } text={ cleanText } />
						<MessageActions message={ msg } index={ i } />
						{ debugMode && isModel && msg.debug && (
							<DebugPanel debug={ msg.debug } />
						) }
						{ isLastModel && (
							<SuggestionChips
								suggestions={ suggestions }
								onSelect={ sendMessage }
							/>
						) }
					</div>
				);
			} ) }
			{ isStreaming && streamingText && (
				<div className="ai-agent-message-row ai-agent-message-row--streaming">
					<div className="ai-agent-bubble ai-agent-assistant ai-agent-streaming">
						<MarkdownMessage content={ streamingText } />
						<span
							className="ai-agent-streaming-cursor"
							aria-hidden="true"
						/>
					</div>
				</div>
			) }
			{ sending && ! isStreaming && (
				<div className="ai-agent-bubble ai-agent-assistant ai-agent-thinking">
					<Spinner />
					{ __( 'Thinking…', 'ai-agent' ) }
				</div>
			) }
		</div>
	);
}
