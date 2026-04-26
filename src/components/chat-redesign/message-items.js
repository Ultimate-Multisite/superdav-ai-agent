/**
 * Shared message item components used by both the main chat (MessageList)
 * and the floating widget (WidgetMessageList).
 *
 * All rendering is scoped to `.gaa-cr-*` classes so the widget and full
 * chat look identical (the widget's bundle also loads chat-redesign.css
 * via components/chat-widget/index.js).
 *
 * Actions are deliberately minimal:
 *   - assistant message:  copy + thumbs-down (opens FeedbackConsentModal)
 *   - user message:       edit/resend + copy
 * A meta row below each message shows model · duration · tokens · cost
 * (assistant) or model · time (user), sourced from store.messageTokens.
 */

import { useState, useRef, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	copy as copyIcon,
	check,
	pencil,
	thumbsDown,
} from '@wordpress/icons';

import STORE_NAME from '../../store';
import MarkdownMessage from '../markdown-message';
import { AiIcon } from './icons';
import ToolCard from './ToolCard';
import {
	extractText,
	pairToolCalls,
	parseSuggestions,
} from './message-helpers';
import { linkifyText } from '../../utils/linkify';

/**
 *
 * @param {number} n
 */
function formatNumber( n ) {
	if ( ! Number.isFinite( n ) ) {
		return '';
	}
	return new Intl.NumberFormat( undefined ).format( Math.round( n ) );
}

/**
 *
 * @param {number} seconds
 */
function formatDuration( seconds ) {
	if ( ! Number.isFinite( seconds ) || seconds <= 0 ) {
		return '';
	}
	if ( seconds < 1 ) {
		return `${ Math.round( seconds * 1000 ) }ms`;
	}
	return `${ seconds.toFixed( 1 ) }s`;
}

/**
 *
 * @param {number} cost
 */
function formatCost( cost ) {
	if ( ! Number.isFinite( cost ) || cost <= 0 ) {
		return '';
	}
	// Round to 4 decimals if small, else 2.
	return cost < 0.01 ? `$${ cost.toFixed( 4 ) }` : `$${ cost.toFixed( 2 ) }`;
}

/**
 *
 * @param {string} ts
 */
function formatTime( ts ) {
	if ( ! ts ) {
		return '';
	}
	try {
		const date = new Date( ts );
		return date.toLocaleTimeString( undefined, {
			hour: 'numeric',
			minute: '2-digit',
		} );
	} catch {
		return '';
	}
}

/**
 * Meta row shown under an assistant message: model · duration · tokens · cost.
 *
 * @param {Object} root0
 * @param {*}      root0.tokens Per-message token record from store.
 */
function AssistantMeta( { tokens } ) {
	const parts = [];
	if ( tokens?.modelName ) {
		parts.push(
			<span key="model" className="gaa-cr-msg-meta-model">
				{ tokens.modelName }
			</span>
		);
	}
	const dur = formatDuration( tokens?.duration );
	if ( dur ) {
		parts.push( <span key="dur">{ dur }</span> );
	}
	const total = ( tokens?.prompt || 0 ) + ( tokens?.completion || 0 );
	if ( total > 0 ) {
		parts.push(
			<span key="tok">{ `${ formatNumber( total ) } tok` }</span>
		);
	}
	const cost = formatCost( tokens?.cost );
	if ( cost ) {
		parts.push( <span key="cost">{ cost }</span> );
	}
	if ( parts.length === 0 ) {
		return null;
	}
	const withSeps = [];
	parts.forEach( ( p, i ) => {
		if ( i > 0 ) {
			withSeps.push(
				<span key={ `sep${ i }` } className="gaa-cr-msg-meta-sep">
					·
				</span>
			);
		}
		withSeps.push( p );
	} );
	return <span className="gaa-cr-msg-meta-text">{ withSeps }</span>;
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.msg
 * @param {number} root0.index
 */
export function UserMessage( { msg, index } ) {
	const [ copied, setCopied ] = useState( false );
	const [ draft, setDraft ] = useState( '' );
	const textareaRef = useRef( null );
	const { editAndResend, setEditingMessageIndex } = useDispatch( STORE_NAME );
	const { sending, messageToken, selectedModelName, editing } = useSelect(
		( sel ) => {
			const store = sel( STORE_NAME );
			const tokens = store.getMessageTokens() || [];
			const providers = store.getProviders() || [];
			const provider = providers.find(
				( p ) => p.id === store.getSelectedProviderId()
			);
			const model = provider?.models?.find(
				( m ) => m.id === store.getSelectedModelId()
			);
			return {
				sending: store.isSending(),
				messageToken: tokens[ index ],
				selectedModelName: model?.name || model?.id || '',
				// Derive editing from the store's editingMessageIndex so only the
				// exact message whose index matches enters edit mode. Using a
				// store-level flag (rather than local useState) means a single
				// dispatch controls which message is active, preventing the bug
				// where all user messages simultaneously showed the editing UI.
				editing: store.getEditingMessageIndex() === index,
			};
		},
		[ index ]
	);

	const attachments = msg.attachments || [];
	const text = extractText( msg );

	useEffect( () => {
		if ( editing && textareaRef.current ) {
			textareaRef.current.focus();
			textareaRef.current.select();
		}
	}, [ editing ] );

	const handleCopy = useCallback( () => {
		if ( ! text ) {
			return;
		}
		navigator.clipboard.writeText( text ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 1500 );
		} );
	}, [ text ] );

	const handleSubmit = useCallback( () => {
		if ( draft.trim() && draft.trim() !== text ) {
			editAndResend( index, draft.trim() );
		} else {
			setEditingMessageIndex( null );
		}
	}, [ draft, text, index, editAndResend, setEditingMessageIndex ] );

	// Prefer the model actually used for the nearest assistant reply if
	// available, else show the currently-selected model.
	const modelLabel = messageToken?.modelName || selectedModelName || '';
	const timeLabel = formatTime( msg.ts || msg.created_at );

	if ( editing ) {
		return (
			<div className="gaa-cr-msg-row gaa-cr-msg-user">
				<div className="gaa-cr-bubble-user gaa-cr-bubble-user--editing">
					<textarea
						ref={ textareaRef }
						className="gaa-cr-bubble-user-edit"
						value={ draft }
						onChange={ ( e ) => setDraft( e.target.value ) }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' && ! e.shiftKey ) {
								e.preventDefault();
								handleSubmit();
							}
							if ( e.key === 'Escape' ) {
								setEditingMessageIndex( null );
							}
						} }
						rows={ 3 }
					/>
					<div className="gaa-cr-bubble-user-edit-actions">
						<button
							type="button"
							className="gaa-cr-btn-sm"
							onClick={ () => setEditingMessageIndex( null ) }
						>
							{ __( 'Cancel', 'gratis-ai-agent' ) }
						</button>
						<button
							type="button"
							className="gaa-cr-btn-sm is-primary"
							onClick={ handleSubmit }
							disabled={ sending || ! draft.trim() }
						>
							{ __( 'Send', 'gratis-ai-agent' ) }
						</button>
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className="gaa-cr-msg-row gaa-cr-msg-user">
			<div className="gaa-cr-bubble-user">
				{ attachments.length > 0 && (
					<div className="gaa-cr-bubble-attachments">
						{ attachments.map( ( a, i ) => (
							<img
								key={ i }
								src={ a.dataUrl || a.image_url }
								alt={ a.name || a.image_name || '' }
							/>
						) ) }
					</div>
				) }
				{ text }
			</div>
			<div className="gaa-cr-msg-meta gaa-cr-msg-meta-user">
				<span className="gaa-cr-msg-meta-text">
					{ modelLabel && (
						<span className="gaa-cr-msg-meta-model">
							{ modelLabel }
						</span>
					) }
					{ modelLabel && timeLabel && (
						<span className="gaa-cr-msg-meta-sep">·</span>
					) }
					{ timeLabel && <span>{ timeLabel }</span> }
				</span>
				<span className="gaa-cr-msg-meta-actions">
					<button
						type="button"
						className="gaa-cr-icon-btn"
						onClick={ () => {
							setDraft( text || '' );
							setEditingMessageIndex( index );
						} }
						disabled={ sending }
						title={ __( 'Edit & resend', 'gratis-ai-agent' ) }
						aria-label={ __( 'Edit & resend', 'gratis-ai-agent' ) }
					>
						<Icon icon={ pencil } size={ 16 } />
					</button>
					<button
						type="button"
						className="gaa-cr-icon-btn"
						onClick={ handleCopy }
						title={
							copied
								? __( 'Copied!', 'gratis-ai-agent' )
								: __( 'Copy', 'gratis-ai-agent' )
						}
						aria-label={
							copied
								? __( 'Copied!', 'gratis-ai-agent' )
								: __( 'Copy message', 'gratis-ai-agent' )
						}
					>
						<Icon icon={ copied ? check : copyIcon } size={ 16 } />
					</button>
				</span>
			</div>
		</div>
	);
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.msg
 * @param {*}      root0.index
 * @param {*}      root0.onSuggestionSelect
 * @param {*}      root0.onThumbsDown
 * @param {*}      root0.isLastModel
 */
export function AssistantMessage( {
	msg,
	index,
	onSuggestionSelect,
	onThumbsDown,
	isLastModel,
} ) {
	const [ copied, setCopied ] = useState( false );

	const { messageToken } = useSelect(
		( sel ) => {
			const tokens = sel( STORE_NAME ).getMessageTokens() || [];
			return { messageToken: tokens[ index ] };
		},
		[ index ]
	);

	const rawText = extractText( msg );
	const { cleanText, suggestions } = parseSuggestions( rawText );
	const pairs = pairToolCalls( msg.toolCalls );

	const handleCopy = () => {
		if ( ! cleanText ) {
			return;
		}
		navigator.clipboard.writeText( cleanText ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 1500 );
		} );
	};

	return (
		<div className="gaa-cr-msg-row gaa-cr-msg-assistant">
			<div className="gaa-cr-avatar" aria-hidden="true">
				<AiIcon />
			</div>
			<div className="gaa-cr-msg-body">
				{ pairs.map( ( pair, i ) => (
					<ToolCard
						key={ pair.call.id || i }
						call={ pair.call }
						response={ pair.response }
					/>
				) ) }
				{ cleanText && <MarkdownMessage content={ cleanText } /> }
				{ isLastModel && suggestions.length > 0 && (
					<div className="gaa-cr-suggestions">
						{ suggestions.map( ( s, i ) => (
							<button
								type="button"
								key={ i }
								className="gaa-cr-suggestion-chip"
								onClick={ () => onSuggestionSelect( s ) }
							>
								{ s }
							</button>
						) ) }
					</div>
				) }
				<div className="gaa-cr-msg-meta gaa-cr-msg-meta-assistant">
					<span className="gaa-cr-msg-meta-actions">
						<button
							type="button"
							className="gaa-cr-icon-btn"
							onClick={ handleCopy }
							title={
								copied
									? __( 'Copied!', 'gratis-ai-agent' )
									: __( 'Copy', 'gratis-ai-agent' )
							}
							aria-label={
								copied
									? __( 'Copied!', 'gratis-ai-agent' )
									: __( 'Copy message', 'gratis-ai-agent' )
							}
						>
							<Icon
								icon={ copied ? check : copyIcon }
								size={ 16 }
							/>
						</button>
						<button
							type="button"
							className="gaa-cr-icon-btn"
							onClick={ () => onThumbsDown?.( index ) }
							title={ __(
								'Report an issue with this response',
								'gratis-ai-agent'
							) }
							aria-label={ __(
								'Report an issue with this response',
								'gratis-ai-agent'
							) }
						>
							<Icon icon={ thumbsDown } size={ 16 } />
						</button>
					</span>
					<AssistantMeta tokens={ messageToken } />
				</div>
			</div>
		</div>
	);
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.step
 * @param {*}      root0.liveToolCalls
 */
export function RunningMessage( { step, liveToolCalls } ) {
	const pairs = pairToolCalls( liveToolCalls );
	return (
		<div className="gaa-cr-msg-row gaa-cr-msg-assistant">
			<div className="gaa-cr-avatar" aria-hidden="true">
				<AiIcon thinking={ true } />
			</div>
			<div className="gaa-cr-msg-body">
				{ pairs.map( ( pair, i ) => (
					<ToolCard
						key={ pair.call.id || i }
						call={ pair.call }
						response={ pair.response }
						defaultOpen={ ! pair.response }
					/>
				) ) }
				<div className="gaa-cr-running-line">
					<span className="gaa-cr-running-dot" aria-hidden="true" />
					<span>{ step }</span>
				</div>
			</div>
		</div>
	);
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.text
 */
export function SystemMessage( { text } ) {
	return (
		<div className="gaa-cr-msg-row">
			<div className="gaa-cr-msg-system">{ linkifyText( text ) }</div>
		</div>
	);
}
