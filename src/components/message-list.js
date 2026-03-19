/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';

/**
 * Suggestion cards shown in the empty state welcome screen.
 * Each card has a title, description, and the prompt text to send on click.
 *
 * @type {Array<{title: string, description: string, prompt: string}>}
 */
const SUGGESTION_CARDS = [
	{
		title: __( 'Check site health', 'gratis-ai-agent' ),
		description: __(
			'Run a full health check and surface any issues.',
			'gratis-ai-agent'
		),
		prompt: __( 'Check site health', 'gratis-ai-agent' ),
	},
	{
		title: __( 'List plugins', 'gratis-ai-agent' ),
		description: __(
			'Show all installed plugins and their status.',
			'gratis-ai-agent'
		),
		prompt: __( 'List installed plugins', 'gratis-ai-agent' ),
	},
	{
		title: __( 'Create a blog post', 'gratis-ai-agent' ),
		description: __(
			'Draft a new post with a title, content, and tags.',
			'gratis-ai-agent'
		),
		prompt: __( 'Create a blog post', 'gratis-ai-agent' ),
	},
	{
		title: __( 'Run security check', 'gratis-ai-agent' ),
		description: __(
			'Scan for vulnerabilities and security misconfigurations.',
			'gratis-ai-agent'
		),
		prompt: __( 'Run a security check', 'gratis-ai-agent' ),
	},
	{
		title: __( 'Analyze SEO', 'gratis-ai-agent' ),
		description: __(
			'Review SEO settings and suggest improvements.',
			'gratis-ai-agent'
		),
		prompt: __( 'Analyze SEO', 'gratis-ai-agent' ),
	},
	{
		title: __( 'Check for updates', 'gratis-ai-agent' ),
		description: __(
			'List available plugin, theme, and core updates.',
			'gratis-ai-agent'
		),
		prompt: __( 'Check for updates', 'gratis-ai-agent' ),
	},
];

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
 * Renders a single message bubble with role-appropriate styling.
 *
 * @param {Object} props      - Component props.
 * @param {string} props.role - Message role: 'user', 'model', or 'system'.
 * @param {string} props.text - Rendered text content (markdown for model messages).
 * @return {JSX.Element} The message bubble element.
 */
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

/**
 * Rich welcome screen shown when the chat has no messages yet.
 * Displays a greeting and 6 clickable suggestion cards.
 *
 * @param {Object}   props          - Component props.
 * @param {string}   props.greeting - Greeting text shown above the cards.
 * @param {Function} props.onSelect - Called with the prompt text when a card is clicked.
 * @return {JSX.Element} The empty state welcome element.
 */
function EmptyStateWelcome( { greeting, onSelect } ) {
	return (
		<div className="ai-agent-empty-state">
			<div className="ai-agent-welcome">
				<p className="ai-agent-welcome__greeting">{ greeting }</p>
				<div className="ai-agent-welcome__grid">
					{ SUGGESTION_CARDS.map( ( card, i ) => (
						<button
							key={ i }
							type="button"
							className="ai-agent-welcome__card"
							onClick={ () => onSelect( card.prompt ) }
						>
							<span className="ai-agent-welcome__card-title">
								{ card.title }
							</span>
							<span className="ai-agent-welcome__card-desc">
								{ card.description }
							</span>
						</button>
					) ) }
				</div>
				<p className="ai-agent-welcome__hint">
					{ __(
						'Or type a message below to ask anything.',
						'gratis-ai-agent'
					) }
				</p>
			</div>
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
		<div className="ai-agent-messages" ref={ messagesRef }>
			{ visibleMessages.length === 0 && ! sending && (
				<EmptyStateWelcome
					greeting={ greeting }
					onSelect={ sendMessage }
				/>
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
					<div key={ i } className="ai-agent-message-row">
						{ msg.toolCalls?.length > 0 && (
							<ToolCallDetails toolCalls={ msg.toolCalls } />
						) }
						<MessageBubble role={ msg.role } text={ cleanText } />
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
							<div className="ai-agent-retry-row">
								<Button
									variant="secondary"
									className="ai-agent-retry-btn"
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
			{ pendingActionCard && (
				<div className="ai-agent-message-row ai-agent-message-row-action-card">
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
				<div className="ai-agent-bubble ai-agent-assistant ai-agent-thinking">
					<Spinner />
					{ __( 'Thinking…', 'gratis-ai-agent' ) }
				</div>
			) }
		</div>
	);
}
