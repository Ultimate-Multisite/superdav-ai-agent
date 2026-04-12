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
import ActionCard from './action-card';
import { getBranding } from '../utils/branding';
import useTextToSpeech from './use-text-to-speech';
import { MessageTokenAnnotation } from './token-counter';

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

/**
 * Renders inline image attachments for a user message.
 *
 * @param {Object} props             - Component props.
 * @param {Array}  props.attachments - Array of attachment objects with dataUrl/name.
 * @return {JSX.Element|null} The attachment images, or null when empty.
 */
function MessageAttachments( { attachments } ) {
	if ( ! attachments?.length ) {
		return null;
	}

	return (
		<div className="gratis-ai-agent-message-attachments">
			{ attachments.map( ( att, i ) => (
				<a
					key={ i }
					href={ att.dataUrl || att.image_url }
					target="_blank"
					rel="noopener noreferrer"
					className="gratis-ai-agent-message-attachment-link"
					aria-label={ att.name || att.image_name }
				>
					<img
						src={ att.dataUrl || att.image_url }
						alt={ att.name || att.image_name || '' }
						className="gratis-ai-agent-message-attachment-img"
					/>
				</a>
			) ) }
		</div>
	);
}

/**
 * Renders a single message bubble with role-appropriate styling.
 *
 * @param {Object} props             - Component props.
 * @param {string} props.role        - Message role: 'user', 'model', or 'system'.
 * @param {string} props.text        - Rendered text content (markdown for model messages).
 * @param {Array}  props.attachments - Optional image attachments for user messages.
 * @return {JSX.Element} The message bubble element.
 */
function MessageBubble( { role, text, attachments } ) {
	const classMap = {
		user: 'gratis-ai-agent-bubble gratis-ai-agent-user',
		model: 'gratis-ai-agent-bubble gratis-ai-agent-assistant',
		system: 'gratis-ai-agent-bubble gratis-ai-agent-system',
	};

	if ( role === 'model' ) {
		return (
			<div className={ classMap.model }>
				<MarkdownMessage content={ text } />
			</div>
		);
	}

	if ( role === 'user' ) {
		return (
			<div className={ classMap.user }>
				<MessageAttachments attachments={ attachments } />
				{ text }
			</div>
		);
	}

	return (
		<div className={ classMap[ role ] || classMap.system }>{ text }</div>
	);
}

/**
 * Renders suggestion chip buttons below the last model message.
 *
 * @param {Object}   props             - Component props.
 * @param {string[]} props.suggestions - Suggestion strings to display.
 * @param {Function} props.onSelect    - Called with the selected suggestion text.
 * @return {JSX.Element|null} The suggestion chips, or null when empty.
 */
function SuggestionChips( { suggestions, onSelect } ) {
	if ( ! suggestions?.length ) {
		return null;
	}

	return (
		<div className="gratis-ai-agent-suggestion-chips">
			{ suggestions.map( ( suggestion, i ) => (
				<Button
					key={ i }
					variant="tertiary"
					className="gratis-ai-agent-suggestion-chip"
					onClick={ () => onSelect( suggestion ) }
				>
					{ suggestion }
				</Button>
			) ) }
		</div>
	);
}

/**
 * Simple empty state shown when the chat has no messages yet.
 *
 * @param {Object} props          - Component props.
 * @param {string} props.greeting - Greeting text.
 * @return {JSX.Element} The empty state element.
 */
function EmptyStateWelcome( { greeting } ) {
	return (
		<div className="gratis-ai-agent-empty-state">
			<p className="gratis-ai-agent-welcome__greeting">{ greeting }</p>
		</div>
	);
}

/**
 * Extract the concatenated text content from a message's parts array.
 *
 * @param {import('../types').Message} message - Message object.
 * @return {string} Concatenated text from all text parts.
 */
function extractText( message ) {
	if ( ! message.parts?.length ) {
		return '';
	}
	return message.parts
		.filter( ( p ) => p.text )
		.map( ( p ) => p.text )
		.join( '' );
}

/**
 * Live tool call progress shown inside the "Thinking" bubble while the
 * background job is processing. Displays each tool call as it happens
 * so the user can see the agent is making progress.
 *
 * @param {Object}     props           - Component props.
 * @param {Array<{type: string, id?: string, name?: string, args?: Object, response?: unknown}>} props.toolCalls - Live tool call log from the job.
 * @return {JSX.Element} Progress indicator.
 */
function LiveToolProgress( { toolCalls } ) {
	// Extract only the "call" entries and deduplicate by name for a clean summary.
	const calls = toolCalls.filter( ( t ) => t.type === 'call' );
	const responses = toolCalls.filter( ( t ) => t.type === 'response' );

	// Show the most recent call prominently, with a count of completed ones.
	const lastCall = calls[ calls.length - 1 ];

	// Format ability name for display: strip wpab__ prefix and replace
	// double-underscore namespace separator with /.
	const formatName = ( name ) => {
		let display = name || '';
		if ( display.startsWith( 'wpab__' ) ) {
			display = display.substring( 6 );
		}
		display = display.replace( /__/g, '/' );
		return display;
	};

	return (
		<div className="gratis-ai-agent-live-progress">
			{ responses.length > 0 && (
				<div className="gratis-ai-agent-live-progress-completed">
					{ responses.length }{ ' ' }
					{ responses.length === 1
						? __( 'tool completed', 'gratis-ai-agent' )
						: __( 'tools completed', 'gratis-ai-agent' ) }
				</div>
			) }
			{ lastCall && (
				<div className="gratis-ai-agent-live-progress-current">
					{ responses.length < calls.length
						? __( 'Running', 'gratis-ai-agent' )
						: __( 'Composing reply…', 'gratis-ai-agent' ) }
					{ responses.length < calls.length && (
						<>
							{ ' ' }
							<code>{ formatName( lastCall.name ) }</code>
							{ calls.length > 1 && (
								<span className="gratis-ai-agent-live-progress-count">
									{ ' ' }
									({ calls.length })
								</span>
							) }
						</>
					) }
				</div>
			) }
		</div>
	);
}

/**
 * Scrollable list of chat messages for the current session.
 *
 * Renders user/model/system bubbles, tool call details, message actions,
 * debug panels, suggestion chips, and the streaming text indicator.
 * Auto-scrolls to the bottom on new messages.
 *
 * When text-to-speech is enabled, each new model response is spoken aloud
 * automatically using the Web Speech API SpeechSynthesis interface.
 *
 * @return {JSX.Element} The message list element.
 */
export default function MessageList() {
	const {
		messages,
		sending,
		debugMode,
		streamingText,
		isStreaming,
		pendingActionCard,
		settingsGreeting,
		ttsEnabled,
		ttsVoiceURI,
		ttsRate,
		ttsPitch,
		streamError,
		messageTokens,
		liveToolCalls,
		currentSessionId,
		sessionJobs,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			messages: store.getCurrentSessionMessages(),
			sending: store.isSending(),
			debugMode: store.isDebugMode(),
			streamingText: store.getStreamingText(),
			isStreaming: store.isStreamingActive(),
			pendingActionCard: store.getPendingActionCard(),
			settingsGreeting: store.getSettings()?.greeting_message || '',
			ttsEnabled: store.isTtsEnabled(),
			ttsVoiceURI: store.getTtsVoiceURI(),
			ttsRate: store.getTtsRate(),
			ttsPitch: store.getTtsPitch(),
			streamError: store.hasStreamError(),
			messageTokens: store.getMessageTokens(),
			liveToolCalls: store.getLiveToolCalls(),
			currentSessionId: store.getCurrentSessionId(),
			sessionJobs: store.getSessionJobs(),
		};
	}, [] );

	// Resolve greeting: Redux store (admin page) → injected branding (floating
	// widget) → built-in default.
	const greeting =
		settingsGreeting ||
		getBranding().greetingMessage ||
		__( 'Send a message to start a conversation.', 'gratis-ai-agent' );

	const { sendMessage, confirmToolCall, rejectToolCall, retryLastMessage } =
		useDispatch( STORE_NAME );
	const messagesRef = useRef( null );

	// TTS hook — configured from store state.
	const { speak, cancel } = useTextToSpeech( {
		voiceURI: ttsVoiceURI,
		rate: ttsRate,
		pitch: ttsPitch,
	} );

	// Track the index of the last message we spoke to avoid re-speaking.
	const lastSpokenIndexRef = useRef( -1 );

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

	// Speak new model messages when TTS is enabled and a response completes.
	// We only speak when: TTS is on, not currently streaming, not sending,
	// and the last message is a model message we haven't spoken yet.
	useEffect( () => {
		if ( ! ttsEnabled || isStreaming || sending ) {
			return;
		}

		const lastIdx = messages.length - 1;
		if ( lastIdx < 0 ) {
			return;
		}

		const lastMsg = messages[ lastIdx ];
		if ( lastMsg.role !== 'model' ) {
			return;
		}

		// Don't re-speak a message we already spoke.
		if ( lastIdx === lastSpokenIndexRef.current ) {
			return;
		}

		const text = extractText( lastMsg );
		if ( ! text ) {
			return;
		}

		lastSpokenIndexRef.current = lastIdx;
		speak( text );
	}, [ messages, ttsEnabled, isStreaming, sending, speak ] );

	// Cancel speech when TTS is disabled mid-conversation.
	useEffect( () => {
		if ( ! ttsEnabled ) {
			cancel();
		}
	}, [ ttsEnabled, cancel ] );

	// Build visible messages with their original indices for token lookup.
	const visibleMessages = messages.reduce( ( acc, msg, originalIndex ) => {
		// Skip function-role messages (tool responses).
		if ( msg.role === 'function' ) {
			return acc;
		}
		// Skip model messages that only have function calls and no text.
		if ( msg.role === 'model' ) {
			const text = extractText( msg );
			if ( ! text ) {
				return acc;
			}
		}
		acc.push( { msg, originalIndex } );
		return acc;
	}, [] );

	return (
		<div className="gratis-ai-agent-messages" ref={ messagesRef }>
			{ visibleMessages.length === 0 && ! sending && (
				<EmptyStateWelcome greeting={ greeting } />
			) }
			{ visibleMessages.map( ( { msg, originalIndex }, i ) => {
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

				const isLastSystemError =
					msg.role === 'system' &&
					streamError &&
					! sending &&
					i === visibleMessages.length - 1;

				return (
					<div key={ i } className="gratis-ai-agent-message-row">
						{ msg.toolCalls?.length > 0 && (
							<ToolCallDetails toolCalls={ msg.toolCalls } />
						) }
						<MessageBubble
							role={ msg.role }
							text={ cleanText }
							attachments={ msg.attachments }
						/>
						<MessageActions
							message={ msg }
							index={ originalIndex }
						/>
						{ isModel && (
							<MessageTokenAnnotation
								tokenData={
									messageTokens[ originalIndex ] || null
								}
							/>
						) }
						{ debugMode && isModel && msg.debug && (
							<DebugPanel debug={ msg.debug } />
						) }
						{ isLastModel && (
							<SuggestionChips
								suggestions={ suggestions }
								onSelect={ sendMessage }
							/>
						) }
						{ isLastSystemError && (
							<div className="gratis-ai-agent-retry-row">
								<Button
									variant="secondary"
									className="gratis-ai-agent-retry-btn"
									onClick={ retryLastMessage }
								>
									{ __( 'Try again', 'gratis-ai-agent' ) }
								</Button>
							</div>
						) }
					</div>
				);
			} ) }
			{ isStreaming && streamingText && (
				<div className="gratis-ai-agent-message-row gratis-ai-agent-message-row--streaming">
					<div className="gratis-ai-agent-bubble gratis-ai-agent-assistant gratis-ai-agent-streaming">
						<MarkdownMessage content={ streamingText } />
						<span
							className="gratis-ai-agent-streaming-cursor"
							aria-hidden="true"
						/>
					</div>
				</div>
			) }
			{ pendingActionCard && (
				<div className="gratis-ai-agent-message-row gratis-ai-agent-message-row-action-card">
					<ActionCard
						card={ pendingActionCard }
						onConfirm={ ( alwaysAllow ) =>
							confirmToolCall(
								pendingActionCard.jobId,
								alwaysAllow
							)
						}
						onCancel={ () =>
							rejectToolCall( pendingActionCard.jobId )
						}
					/>
				</div>
			) }
			{ sending && ! isStreaming && ! pendingActionCard && (
				<div className="gratis-ai-agent-bubble gratis-ai-agent-assistant gratis-ai-agent-thinking">
					<Spinner />
					{ ( () => {
						// Use per-session job tool calls if available,
						// fall back to global liveToolCalls.
						const sessionJob = currentSessionId
							? sessionJobs[ currentSessionId ]
							: null;
						const tc =
							sessionJob?.toolCalls?.length > 0
								? sessionJob.toolCalls
								: liveToolCalls;
						return tc?.length > 0 ? (
							<LiveToolProgress toolCalls={ tc } />
						) : (
							__( 'Thinking…', 'gratis-ai-agent' )
						);
					} )() }
				</div>
			) }
		</div>
	);
}
