/**
 * Message list for the redesigned chat.
 *
 * The actual message row components live in ./message-items.js so the
 * floating widget can reuse them verbatim and the two UIs render
 * identically. This file is only responsible for the scroll container,
 * filter of visible messages, the running placeholder, and wiring the
 * feedback consent modal invoked by a thumbs-down click.
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useRef, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import STORE_NAME from '../../store';
import FeedbackConsentModal from '../feedback-consent-modal';
import { extractText, getRunningToolName } from './message-helpers';
import {
	AssistantMessage,
	RunningMessage,
	SystemMessage,
	UserMessage,
} from './message-items';

/**
 *
 */
export default function MessageList() {
	const {
		messages,
		sending,
		currentSessionId,
		liveToolCalls,
		sessionJobs,
		greeting,
	} = useSelect( ( sel ) => {
		const store = sel( STORE_NAME );
		return {
			messages: store.getCurrentSessionMessages(),
			sending: store.isSending(),
			currentSessionId: store.getCurrentSessionId(),
			liveToolCalls: store.getLiveToolCalls(),
			sessionJobs: store.getSessionJobs(),
			greeting:
				store.getSettings()?.greeting_message ||
				__(
					'Ask the agent to make a change, write a post, or audit your site.',
					'gratis-ai-agent'
				),
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
		const savedY = window.scrollY;
		el.scrollTop = el.scrollHeight;
		if ( window.scrollY !== savedY ) {
			window.scrollTo( 0, savedY );
		}
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
			<div className="gaa-cr-messages" ref={ ref }>
				<div className="gaa-cr-messages-inner">
					{ visible.length === 0 && ! sending && (
						<div className="gaa-cr-empty">{ greeting }</div>
					) }

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
