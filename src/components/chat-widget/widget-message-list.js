/**
 * Floating-widget scroll container around the shared message-items.
 *
 * Rendering of user / assistant / running / system messages is handled
 * by `chat-redesign/message-items.js` so the widget and full chat look
 * identical. This file only owns the widget-scoped scroll container,
 * the visible-messages filter, the running placeholder, and the
 * feedback consent modal invoked by a thumbs-down click.
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useRef, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import STORE_NAME from '../../store';
import FeedbackConsentModal from '../feedback-consent-modal';
import {
	extractText,
	getRunningToolName,
} from '../chat-redesign/message-helpers';
import {
	AssistantMessage,
	RunningMessage,
	SystemMessage,
	UserMessage,
} from '../chat-redesign/message-items';

/**
 *
 */
export default function WidgetMessageList() {
	const { messages, sending, currentSessionId, liveToolCalls, sessionJobs } =
		useSelect( ( sel ) => {
			const store = sel( STORE_NAME );
			return {
				messages: store.getCurrentSessionMessages(),
				sending: store.isSending(),
				currentSessionId: store.getCurrentSessionId(),
				liveToolCalls: store.getLiveToolCalls(),
				sessionJobs: store.getSessionJobs(),
			};
		}, [] );

	const { sendMessage } = useDispatch( STORE_NAME );
	const ref = useRef( null );
	const [ thumbsDownMessageIndex, setThumbsDownMessageIndex ] =
		useState( null );

	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return;
		}
		el.scrollTop = el.scrollHeight;
	}, [ messages, sending, liveToolCalls ] );

	const visible = [];
	for ( let i = 0; i < messages.length; i++ ) {
		const m = messages[ i ];
		if ( m.role === 'function' ) {
			continue;
		}
		if ( m.role === 'model' ) {
			const text = extractText( m );
			if ( ! text && ! m.toolCalls?.length ) {
				continue;
			}
		}
		if ( m.role === 'user' ) {
			const text = extractText( m );
			if ( ! text ) {
				continue;
			}
		}
		visible.push( { msg: m, index: i } );
	}

	const lastRunningJob = currentSessionId
		? sessionJobs[ currentSessionId ]
		: null;
	const runningToolCalls =
		lastRunningJob?.toolCalls?.length > 0
			? lastRunningJob.toolCalls
			: liveToolCalls;

	const runningToolName = getRunningToolName( runningToolCalls );
	const runningStep = runningToolName
		? `${ __( 'Running', 'gratis-ai-agent' ) } ${ runningToolName }…`
		: __( 'Composing reply…', 'gratis-ai-agent' );

	return (
		<>
			<div className="gaa-w-body" ref={ ref }>
				<div className="gaa-w-body-inner">
					{ visible.map( ( { msg, index }, i ) => {
						const isLast = i === visible.length - 1;
						if ( msg.role === 'user' ) {
							return (
								<UserMessage
									key={ i }
									msg={ msg }
									index={ index }
								/>
							);
						}
						if ( msg.role === 'model' ) {
							return (
								<AssistantMessage
									key={ i }
									msg={ msg }
									index={ index }
									onSuggestionSelect={ sendMessage }
									onThumbsDown={ setThumbsDownMessageIndex }
									isLastModel={ isLast && ! sending }
								/>
							);
						}
						if ( msg.role === 'system' ) {
							return (
								<SystemMessage
									key={ i }
									text={ extractText( msg ) }
								/>
							);
						}
						return null;
					} ) }

					{ sending && (
						<RunningMessage
							step={ runningStep }
							liveToolCalls={ runningToolCalls }
						/>
					) }
				</div>
			</div>

			{ thumbsDownMessageIndex !== null && (
				<FeedbackConsentModal
					reportType="thumbs_down"
					sessionId={ currentSessionId }
					messageIndex={ thumbsDownMessageIndex }
					onClose={ () => setThumbsDownMessageIndex( null ) }
				/>
			) }
		</>
	);
}
