/**
 * Sessions slice — session list, current session, messages, tool calls,
 * sending state, job polling, streaming, and session management thunks.
 */

/**
 * @typedef {import('../../types').Session}  Session
 * @typedef {import('../../types').Message}  Message
 * @typedef {import('../../types').ToolCall} ToolCall
 * @typedef {import('../../types').TokenUsage} TokenUsage
 * @typedef {import('../../types').PendingConfirmation} PendingConfirmation
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { snapshotDescriptors } from '../../abilities/registry';
import { ensureRegistered as ensureClientAbilitiesRegistered } from '../../abilities';

/**
 * Associate tool call log entries with the correct model text messages.
 *
 * The DB stores tool calls as a flat chronological array and messages separately.
 * This function walks through both, matching tool call IDs found in model message
 * function-call parts to entries in the tool_calls log, and attaches them to the
 * next model message that contains text content.
 *
 * @param {Message[]}  messages  - Messages from the session DB row.
 * @param {ToolCall[]} toolCalls - Flat tool call log from the session DB row.
 * @return {Message[]} Messages with toolCalls property attached to model text messages.
 */
function associateToolCallsWithMessages( messages, toolCalls ) {
	if ( ! toolCalls?.length || ! messages?.length ) {
		return messages;
	}

	// Build a map of callId → { call entry, response entry } from the flat log.
	const callMap = {};
	for ( const tc of toolCalls ) {
		if ( tc.type === 'call' && tc.id ) {
			callMap[ tc.id ] = { call: tc };
		} else if ( tc.type === 'response' && tc.id && callMap[ tc.id ] ) {
			callMap[ tc.id ].response = tc;
		}
	}

	// Walk through messages, collecting function call IDs from model messages
	// and attaching the matched tool call entries to visible model messages.
	const result = [];
	let pendingCallIds = [];

	for ( const msg of messages ) {
		if ( msg.role === 'model' && msg.parts?.length ) {
			// Collect function call IDs from this message's parts.
			for ( const part of msg.parts ) {
				if ( part.functionCall?.id ) {
					pendingCallIds.push( part.functionCall.id );
				}
			}

			// Check if this model message has text content (visible message).
			const hasText = msg.parts.some( ( p ) => p.text );
			if ( hasText && pendingCallIds.length > 0 ) {
				// Build the toolCalls array for this message from matched pairs.
				const msgToolCalls = [];
				for ( const id of pendingCallIds ) {
					const pair = callMap[ id ];
					if ( pair?.call ) {
						msgToolCalls.push( pair.call );
						if ( pair.response ) {
							msgToolCalls.push( pair.response );
						}
					}
				}
				if ( msgToolCalls.length > 0 ) {
					result.push( { ...msg, toolCalls: msgToolCalls } );
				} else {
					result.push( msg );
				}
				pendingCallIds = [];
				continue;
			}
		}

		result.push( msg );
	}

	// If there are unmatched tool calls (e.g. model never produced text after
	// the last round of tool calls), attach them to the last model message
	// that has text, if any.
	if ( pendingCallIds.length > 0 ) {
		const unmatched = [];
		for ( const id of pendingCallIds ) {
			const pair = callMap[ id ];
			if ( pair?.call ) {
				unmatched.push( pair.call );
				if ( pair.response ) {
					unmatched.push( pair.response );
				}
			}
		}
		if ( unmatched.length > 0 ) {
			// Find last model message with text.
			for ( let i = result.length - 1; i >= 0; i-- ) {
				if (
					result[ i ].role === 'model' &&
					result[ i ].parts?.some( ( p ) => p.text )
				) {
					const existing = result[ i ].toolCalls || [];
					result[ i ] = {
						...result[ i ],
						toolCalls: [ ...existing, ...unmatched ],
					};
					break;
				}
			}
		}
	}

	return result;
}

export const initialState = {
	sessions: [],
	sessionsLoaded: false,
	currentSessionId: null,
	currentSessionMessages: [],
	currentSessionToolCalls: [],
	sending: false,
	currentJobId: null,

	// Live tool call progress from the background job (shown while processing).
	liveToolCalls: [],

	// Per-session background job tracking.
	// Map of sessionId → { jobId, toolCalls, status }
	// Allows multiple sessions to have active jobs simultaneously.
	sessionJobs: {},

	// Token usage (current session)
	tokenUsage: { prompt: 0, completion: 0 },

	// Live token counter (t111) — accumulated from done events.
	sessionTokens: 0,
	sessionCost: 0,
	// Per-message token data: array of { prompt, completion, cost } indexed by
	// message position. Populated when a done event arrives.
	messageTokens: [],

	// Pending confirmation (Batch 8)
	pendingConfirmation: null,

	// Action card — inline confirmation rendered in the message list (t074).
	pendingActionCard: null,

	// Streaming state — token buffer for the in-progress assistant message.
	streamingText: '',
	isStreaming: false,

	// Stream error state — true when the last stream attempt failed.
	// Used to show a "Try again" button in the message list.
	streamError: false,

	// Last user message text — stored so retryLastMessage can resend it.
	lastUserMessage: '',

	// Shared sessions — sessions shared with all admins (t077).
	sharedSessions: [],
	sharedSessionsLoaded: false,

	// Pending optimistic titles — { [sessionId]: title } set by updateSessionTitle()
	// and merged into state.sessions by SET_SESSIONS so that a fetchSessions()
	// round-trip returning "Untitled" from the server does not overwrite a title
	// that was already delivered via the SSE done event.
	pendingTitles: {},

	// Inability-reported flag (t185) — set when the AI calls report-inability.
	// { reason: string, attempted_steps: string[] } or null.
	inabilityReported: null,

	// Feedback banner (t183) — set when the AI exits due to spin/timeout/max_iterations.
	// { exitReason: string } or null.
	feedbackBanner: null,

	// Message queue — messages typed while the agent is processing.
	// Each entry: { text: string, attachments: [], timestamp: number }
	// Drained automatically when the current job completes.
	messageQueue: [],
};

export const actions = {
	/**
	 * Replace the sessions list.
	 *
	 * @param {Session[]} sessions - Session summaries.
	 * @return {Object} Redux action.
	 */
	setSessions( sessions ) {
		return { type: 'SET_SESSIONS', sessions };
	},

	/**
	 * Set the active session and its messages/tool-calls.
	 *
	 * @param {number}     sessionId - Session identifier.
	 * @param {Message[]}  messages  - Messages for the session.
	 * @param {ToolCall[]} toolCalls - Tool calls for the session.
	 * @return {Object} Redux action.
	 */
	setCurrentSession( sessionId, messages, toolCalls ) {
		return {
			type: 'SET_CURRENT_SESSION',
			sessionId,
			messages,
			toolCalls,
		};
	},

	/**
	 * Clear the active session (start a new chat).
	 *
	 * Also cancels any in-flight request so the UI returns to idle state
	 * immediately, allowing the empty state to render without waiting for
	 * the current job to complete or error.
	 *
	 * @return {Function} Redux thunk.
	 */
	clearCurrentSession() {
		return async ( { dispatch, select } ) => {
			// Cancel any active SSE stream.
			const controller = select.getStreamAbortController();
			if ( controller ) {
				controller.abort();
				dispatch.setStreamAbortController( null );
			}
			// Stop polling / sending state so the empty state renders immediately.
			dispatch.setCurrentJobId( null );
			dispatch.setSending( false );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
			// Clear the session.
			dispatch( { type: 'CLEAR_CURRENT_SESSION' } );
		};
	},

	/**
	 * Set the sending/loading state.
	 *
	 * @param {boolean} sending - Whether a message is in-flight.
	 * @return {Object} Redux action.
	 */
	setSending( sending ) {
		return { type: 'SET_SENDING', sending };
	},

	/**
	 * Set the active polling job ID.
	 *
	 * @param {string|null} jobId - Job identifier, or null to clear.
	 * @return {Object} Redux action.
	 */
	setCurrentJobId( jobId ) {
		return { type: 'SET_CURRENT_JOB_ID', jobId };
	},

	/**
	 * Set live tool call progress from the background job.
	 * Shown in the UI while the job is still processing.
	 *
	 * @param {Array} toolCalls - Tool call log entries from the job.
	 * @return {Object} Redux action.
	 */
	setLiveToolCalls( toolCalls ) {
		return { type: 'SET_LIVE_TOOL_CALLS', toolCalls };
	},

	/**
	 * Set a background job for a specific session.
	 *
	 * @param {number}      sessionId - Session identifier.
	 * @param {Object|null} job       - Job data { jobId, toolCalls, status } or null to clear.
	 * @return {Object} Redux action.
	 */
	setSessionJob( sessionId, job ) {
		return { type: 'SET_SESSION_JOB', sessionId, job };
	},

	/**
	 * Append a message to the current session.
	 *
	 * @param {Message} message - Message to append.
	 * @return {Object} Redux action.
	 */
	appendMessage( message ) {
		return { type: 'APPEND_MESSAGE', message };
	},

	/**
	 * Remove the last message from the current session.
	 *
	 * @return {Object} Redux action.
	 */
	removeLastMessage() {
		return { type: 'REMOVE_LAST_MESSAGE' };
	},

	/**
	 * Update cumulative token usage for the current session.
	 *
	 * @param {TokenUsage} tokenUsage - Token usage counters.
	 * @return {Object} Redux action.
	 */
	setTokenUsage( tokenUsage ) {
		return { type: 'SET_TOKEN_USAGE', tokenUsage };
	},

	// ─── Live token counter (t111) ───────────────────────────────

	/**
	 * Accumulate session-level token counts and cost from a done event.
	 *
	 * @param {number} tokens - Total tokens for this exchange (prompt + completion).
	 * @param {number} cost   - Estimated cost in USD for this exchange.
	 * @return {Object} Redux action.
	 */
	accumulateSessionTokens( tokens, cost ) {
		return { type: 'ACCUMULATE_SESSION_TOKENS', tokens, cost };
	},

	/**
	 * Record per-message token data at the given message index.
	 *
	 * @param {number} index     - Message index in currentSessionMessages.
	 * @param {Object} tokenData - { prompt, completion, cost } for this message.
	 * @return {Object} Redux action.
	 */
	setMessageTokens( index, tokenData ) {
		return { type: 'SET_MESSAGE_TOKENS', index, tokenData };
	},

	/**
	 * Reset session token counters (called when a new session starts).
	 *
	 * @return {Object} Redux action.
	 */
	resetSessionTokens() {
		return { type: 'RESET_SESSION_TOKENS' };
	},

	/**
	 * Set or clear the pending tool confirmation.
	 *
	 * @param {PendingConfirmation|null} confirmation - Confirmation payload, or null to clear.
	 * @return {Object} Redux action.
	 */
	setPendingConfirmation( confirmation ) {
		return { type: 'SET_PENDING_CONFIRMATION', confirmation };
	},

	setPendingActionCard( card ) {
		return { type: 'SET_PENDING_ACTION_CARD', card };
	},

	/**
	 * Truncate the message list to the given index (exclusive).
	 *
	 * @param {number} index - Keep messages[0..index-1]; discard the rest.
	 * @return {Object} Redux action.
	 */
	truncateMessagesTo( index ) {
		return { type: 'TRUNCATE_MESSAGES_TO', index };
	},

	/**
	 * Record the timestamp of the most recent send (for latency calculation).
	 *
	 * @param {number} ts - Timestamp in milliseconds since epoch.
	 * @return {Object} Redux action.
	 */
	setSendTimestamp( ts ) {
		return { type: 'SET_SEND_TIMESTAMP', ts };
	},

	/**
	 * Replace the streaming text buffer.
	 *
	 * @param {string} text - Full accumulated streaming text.
	 * @return {Object} Redux action.
	 */
	setStreamingText( text ) {
		return { type: 'SET_STREAMING_TEXT', text };
	},

	/**
	 * Append a token to the streaming text buffer.
	 *
	 * @param {string} token - Token string to append.
	 * @return {Object} Redux action.
	 */
	appendStreamingText( token ) {
		return { type: 'APPEND_STREAMING_TEXT', token };
	},

	/**
	 * Set whether an SSE stream is currently active.
	 *
	 * @param {boolean} streaming - Whether streaming is in progress.
	 * @return {Object} Redux action.
	 */
	setIsStreaming( streaming ) {
		return { type: 'SET_IS_STREAMING', streaming };
	},

	/**
	 * Store the AbortController for the active SSE stream.
	 *
	 * @param {AbortController|null} controller - Controller, or null to clear.
	 * @return {Object} Redux action.
	 */
	setStreamAbortController( controller ) {
		return { type: 'SET_STREAM_ABORT_CONTROLLER', controller };
	},

	/**
	 * Set or clear the stream error flag.
	 *
	 * @param {boolean} error - Whether the last stream attempt failed.
	 * @return {Object} Redux action.
	 */
	setStreamError( error ) {
		return { type: 'SET_STREAM_ERROR', error };
	},

	/**
	 * Store the last user message text for retry purposes.
	 *
	 * @param {string} message - The user message text.
	 * @return {Object} Redux action.
	 */
	setLastUserMessage( message ) {
		return { type: 'SET_LAST_USER_MESSAGE', message };
	},

	/**
	 * Set or clear the inability-reported data (t185).
	 * Set to an object { reason, attempted_steps } when the AI calls
	 * report-inability; set to null to dismiss the banner.
	 *
	 * @param {Object|null} data - Inability data or null.
	 * @return {Object} Redux action.
	 */
	setInabilityReported( data ) {
		return { type: 'SET_INABILITY_REPORTED', data };
	},

	/**
	 * Set or clear the feedback banner (t183).
	 * Set to an object { exitReason } when the AI exits due to
	 * spin_detected, timeout, or max_iterations; set to null to dismiss.
	 *
	 * @param {Object|null} data - Banner data or null.
	 * @return {Object} Redux action.
	 */
	setFeedbackBanner( data ) {
		return { type: 'SET_FEEDBACK_BANNER', data };
	},

	// ─── Message queue (always-on input) ────────────────────────

	/**
	 * Enqueue a message to be processed after the current job finishes.
	 *
	 * @param {string} text        - Message text.
	 * @param {Array}  attachments - Optional attachment objects.
	 * @return {Object} Redux action.
	 */
	enqueueMessage( text, attachments ) {
		return {
			type: 'ENQUEUE_MESSAGE',
			text,
			attachments: attachments || [],
			timestamp: Date.now(),
		};
	},

	/**
	 * Remove the first message from the queue (after it's been sent).
	 *
	 * @return {Object} Redux action.
	 */
	dequeueMessage() {
		return { type: 'DEQUEUE_MESSAGE' };
	},

	/**
	 * Clear the entire message queue.
	 *
	 * @return {Object} Redux action.
	 */
	clearMessageQueue() {
		return { type: 'CLEAR_MESSAGE_QUEUE' };
	},

	/**
	 * Replace the shared sessions list.
	 *
	 * @param {Session[]} sessions - Shared session summaries.
	 * @return {Object} Redux action.
	 */
	setSharedSessions( sessions ) {
		return { type: 'SET_SHARED_SESSIONS', sessions };
	},

	/**
	 * Optimistically update the title of a session in the sessions list.
	 *
	 * Called immediately after the AI generates a title so the sidebar
	 * reflects the new title without waiting for a full fetchSessions round-trip.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @param {string} title     - New session title.
	 * @return {Object} Redux action.
	 */
	updateSessionTitle( sessionId, title ) {
		return { type: 'UPDATE_SESSION_TITLE', sessionId, title };
	},

	// ─── Thunks ──────────────────────────────────────────────────

	/**
	 * Fetch sessions from the REST API, applying the current filter/folder/search.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSessions() {
		return async ( { dispatch, select } ) => {
			try {
				const params = new URLSearchParams();
				const filter = select.getSessionFilter();
				const folder = select.getSessionFolder();
				const search = select.getSessionSearch();

				if ( filter ) {
					params.set( 'status', filter );
				}
				if ( folder ) {
					params.set( 'folder', folder );
				}
				if ( search ) {
					params.set( 'search', search );
				}

				const qs = params.toString();
				const path =
					'/gratis-ai-agent/v1/sessions' + ( qs ? '?' + qs : '' );

				const sessions = await apiFetch( { path } );
				dispatch.setSessions( sessions );
			} catch {
				dispatch.setSessions( [] );
			}
		};
	},

	/**
	 * Load a session by ID and make it the active session.
	 * Restores the provider/model selection if the provider is still available.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	openSession( sessionId ) {
		return async ( { dispatch, select } ) => {
			try {
				const session = await apiFetch( {
					path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				} );
				dispatch.setCurrentSession(
					session.id,
					session.messages || [],
					session.tool_calls || []
				);
				// Only restore provider/model if the provider is still available.
				if ( session.provider_id ) {
					const providers = select.getProviders();
					const providerExists = providers.some(
						( p ) => p.id === session.provider_id
					);
					if ( providerExists ) {
						dispatch.setSelectedProvider( session.provider_id );
						if ( session.model_id ) {
							dispatch.setSelectedModel( session.model_id );
						}
					}
				}
				if ( session.token_usage ) {
					dispatch.setTokenUsage( session.token_usage );
				}
				// Reset live counter when switching sessions.
				dispatch.resetSessionTokens();
			} catch {
				// ignore
			}
		};
	},

	/**
	 * Permanently delete a session.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteSession( sessionId ) {
		return async ( { dispatch, select } ) => {
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
					method: 'DELETE',
				} );
				if ( select.getCurrentSessionId() === sessionId ) {
					dispatch.clearCurrentSession();
				}
				dispatch.fetchSessions();
			} catch {
				// ignore
			}
		};
	},

	/**
	 * Pin or unpin a session.
	 *
	 * @param {number}  sessionId - Session identifier.
	 * @param {boolean} pinned    - Whether to pin (true) or unpin (false).
	 * @return {Function} Redux thunk.
	 */
	pinSession( sessionId, pinned ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { pinned },
			} );
			dispatch.fetchSessions();
		};
	},

	/**
	 * Archive a session (move to archived status).
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	archiveSession( sessionId ) {
		return async ( { dispatch, select } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { status: 'archived' },
			} );
			if ( select.getCurrentSessionId() === sessionId ) {
				dispatch.clearCurrentSession();
			}
			dispatch.fetchSessions();
		};
	},

	/**
	 * Move a session to trash.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	trashSession( sessionId ) {
		return async ( { dispatch, select } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { status: 'trash' },
			} );
			if ( select.getCurrentSessionId() === sessionId ) {
				dispatch.clearCurrentSession();
			}
			dispatch.fetchSessions();
		};
	},

	/**
	 * Restore a session from archived or trash back to active.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	restoreSession( sessionId ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { status: 'active' },
			} );
			dispatch.fetchSessions();
		};
	},

	/**
	 * Move a session to a folder (or remove from folder when folder is empty string).
	 *
	 * @param {number} sessionId - Session identifier.
	 * @param {string} folder    - Target folder name, or '' to remove from folder.
	 * @return {Function} Redux thunk.
	 */
	moveSessionToFolder( sessionId, folder ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { folder },
			} );
			dispatch.fetchSessions();
			dispatch.fetchFolders();
		};
	},

	/**
	 * Rename a session.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @param {string} title     - New session title.
	 * @return {Function} Redux thunk.
	 */
	renameSession( sessionId, title ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }`,
				method: 'PATCH',
				data: { title },
			} );
			dispatch.fetchSessions();
		};
	},

	/**
	 * Export a session and trigger a browser download.
	 *
	 * @param {number}            sessionId       - Session identifier.
	 * @param {'json'|'markdown'} [format='json'] - Export format.
	 * @return {Function} Redux thunk.
	 */
	exportSession( sessionId, format = 'json' ) {
		return async () => {
			const result = await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }/export?format=${ format }`,
			} );
			const content =
				format === 'json'
					? JSON.stringify( result.content, null, 2 )
					: result.content;
			const blob = new Blob( [ content ], {
				type: format === 'json' ? 'application/json' : 'text/markdown',
			} );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = result.filename;
			a.click();
			URL.revokeObjectURL( url );
		};
	},

	/**
	 * Import a session from exported JSON data.
	 *
	 * @param {Object} data - Parsed export JSON (gratis-ai-agent-v1 format).
	 * @return {Function} Redux thunk.
	 */
	importSession( data ) {
		return async ( { dispatch } ) => {
			const session = await apiFetch( {
				path: '/gratis-ai-agent/v1/sessions/import',
				method: 'POST',
				data,
			} );
			dispatch.fetchSessions();
			dispatch.openSession( session.id );
		};
	},

	/**
	 * Regenerate the model response for the message at the given index.
	 * Finds the preceding user message, truncates to that point, and resends.
	 *
	 * @param {number} index - Index of the message to regenerate from.
	 * @return {Function} Redux thunk.
	 */
	regenerateMessage( index ) {
		return async ( { dispatch, select } ) => {
			const messages = select.getCurrentSessionMessages();
			// Find the user message at or before this index.
			let userIdx = index;
			while ( userIdx >= 0 && messages[ userIdx ]?.role !== 'user' ) {
				userIdx--;
			}
			if ( userIdx < 0 ) {
				return;
			}
			const userText = messages[ userIdx ]?.parts
				?.filter( ( p ) => p.text )
				.map( ( p ) => p.text )
				.join( '' );
			if ( ! userText ) {
				return;
			}
			// Truncate to just before this user message.
			dispatch.truncateMessagesTo( userIdx );
			dispatch.sendMessage( userText );
		};
	},

	/**
	 * Edit a user message and resend from that point.
	 *
	 * @param {number} index   - Index of the message to replace.
	 * @param {string} newText - Replacement message text.
	 * @return {Function} Redux thunk.
	 */
	editAndResend( index, newText ) {
		return async ( { dispatch } ) => {
			dispatch.truncateMessagesTo( index );
			dispatch.sendMessage( newText );
		};
	},

	/**
	 * Abort any active SSE stream or polling job and reset sending state.
	 *
	 * @return {Function} Redux thunk.
	 */
	stopGeneration() {
		return async ( { dispatch, select } ) => {
			// Abort any active SSE stream.
			const controller = select.getStreamAbortController();
			if ( controller ) {
				controller.abort();
				dispatch.setStreamAbortController( null );
			}
			dispatch.setCurrentJobId( null );
			dispatch.setSending( false );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
		};
	},

	/**
	 * Retry the last failed stream by removing the error message and
	 * resending the last user message via streamMessage.
	 *
	 * @return {Function} Redux thunk.
	 */
	retryLastMessage() {
		return async ( { dispatch, select } ) => {
			const lastMessage = select.getLastUserMessage();
			if ( ! lastMessage ) {
				return;
			}
			// Remove the error system message appended on failure.
			dispatch.removeLastMessage();
			// Remove the user message that was appended before the failure.
			dispatch.removeLastMessage();
			// Clear the error flag.
			dispatch.setStreamError( false );
			// Resend.
			dispatch.streamMessage( lastMessage );
		};
	},

	/**
	 * Send a message and stream the response token-by-token via SSE.
	 *
	 * Uses the Fetch API with a ReadableStream reader to consume the
	 * text/event-stream response from POST /gratis-ai-agent/v1/stream.
	 *
	 * @param {string}  message           The user message to send.
	 * @param {Array}   attachments       Optional array of attachment objects with
	 *                                    { name, type, dataUrl, isImage } shape.
	 * @param {Object}  options           Optional flags.
	 * @param {boolean} options.fromQueue When true, the user message is already
	 *                                    visible in the message list (queued earlier)
	 *                                    so we skip appending it again.
	 */
	streamMessage( message, attachments = [], options = {} ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
			dispatch.setStreamError( false );
			dispatch.setInabilityReported( null );
			dispatch.setFeedbackBanner( null );
			dispatch.setLastUserMessage( message );

			// Build message parts — text first, then image attachments.
			const parts = [];
			if ( message ) {
				parts.push( { text: message } );
			}
			const imageAttachments = attachments.filter( ( a ) => a.isImage );
			imageAttachments.forEach( ( att ) => {
				parts.push( { image_url: att.dataUrl, image_name: att.name } );
			} );

			// Append user message immediately (with attachment previews),
			// unless this message came from the queue (already visible).
			if ( ! options.fromQueue ) {
				dispatch.appendMessage( {
					role: 'user',
					parts: parts.length ? parts : [ { text: '' } ],
					attachments: imageAttachments,
				} );
			}

			let sessionId = select.getCurrentSessionId();

			// Lazy-create session on first message.
			if ( ! sessionId ) {
				try {
					const sessionData = {
						provider_id: select.getSelectedProviderId(),
						model_id: select.getSelectedModelId(),
					};
					const agentIdForSession = select.getSelectedAgentId();
					if ( agentIdForSession ) {
						sessionData.agent_id = agentIdForSession;
					}
					const session = await apiFetch( {
						path: '/gratis-ai-agent/v1/sessions',
						method: 'POST',
						data: sessionData,
					} );
					sessionId = session.id;
					dispatch.setCurrentSession(
						session.id,
						select.getCurrentSessionMessages(),
						[]
					);
				} catch {
					dispatch.appendMessage( {
						role: 'system',
						parts: [ { text: 'Error: Failed to create session.' } ],
					} );
					dispatch.setSending( false );
					return;
				}
			}

			const body = {
				message,
				session_id: sessionId,
				provider_id: select.getSelectedProviderId(),
				model_id: select.getSelectedModelId(),
			};

			// Include image attachments as base64 data URLs for vision models.
			if ( attachments?.length ) {
				body.attachments = attachments.map( ( att ) => ( {
					name: att.name,
					type: att.type,
					data_url: att.dataUrl,
					is_image: att.isImage,
				} ) );
			}

			const pageContext = select.getPageContext();
			if ( pageContext ) {
				// Normalise to object — screen-meta may set a string.
				body.page_context =
					typeof pageContext === 'string'
						? { summary: pageContext }
						: pageContext;
			}

			const selectedAgentId = select.getSelectedAgentId();
			if ( selectedAgentId ) {
				body.agent_id = selectedAgentId;
			}

			// Include client-side ability descriptors so the server can route
			// JS tool calls back to the browser instead of executing them
			// server-side. The WP 7.0 abilities API is fully async — both
			// registerAbilityCategory and registerAbility return Promises —
			// so we must await ensureRegistered() before snapshotting,
			// otherwise the @wordpress/data store will be empty even though
			// the registration calls have been kicked off (the t166 fix
			// for the bug t165 only partially closed). snapshotDescriptors()
			// itself is synchronous: it reads directly from the data store
			// once registration has completed.
			try {
				await ensureClientAbilitiesRegistered();
			} catch ( _err ) {
				// Registration failure must never block the user's chat
				// message — fall through with an empty descriptor list.
			}
			let clientAbilities = [];
			try {
				clientAbilities = await snapshotDescriptors();
			} catch ( _err ) {
				// snapshotDescriptors() calls wp.abilities.getAbilities()
				// which may throw or reject if the WP 7.0 abilities API is
				// not available or returns an error. Fall through with an
				// empty descriptor list so the message is still sent.
			}
			if (
				Array.isArray( clientAbilities ) &&
				clientAbilities.length > 0
			) {
				body.client_abilities = clientAbilities;
			}

			dispatch.setSendTimestamp( Date.now() );

			// POST to /run — returns a job_id immediately, processes
			// the agent loop in a background PHP worker. The browser
			// polls /job/{id} for progress and the final result.
			let runResult;
			try {
				runResult = await apiFetch( {
					path: '/gratis-ai-agent/v1/run',
					method: 'POST',
					data: body,
				} );
			} catch ( err ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `${ __( 'Error:', 'gratis-ai-agent' ) } ${
								err.message ||
								__( 'Failed to start agent', 'gratis-ai-agent' )
							}`,
						},
					],
				} );
				dispatch.setStreamError( true );
				dispatch.setSending( false );
				return;
			}

			if ( runResult?.job_id ) {
				dispatch.setCurrentJobId( runResult.job_id );
				// Track job per-session so other sessions aren't affected.
				if ( sessionId ) {
					dispatch.setSessionJob( sessionId, {
						jobId: runResult.job_id,
						toolCalls: [],
						status: 'processing',
					} );
				}
				dispatch.pollJob( runResult.job_id );
			} else {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: __(
								'Error: No job ID returned.',
								'gratis-ai-agent'
							),
						},
					],
				} );
				dispatch.setSending( false );
			}
		};
	},

	/**
	 * Confirm a pending tool call and resume the job.
	 *
	 * @param {string}  jobId               - Job identifier awaiting confirmation.
	 * @param {boolean} [alwaysAllow=false] - Whether to grant permanent auto-allow.
	 * @return {Function} Redux thunk.
	 */
	confirmToolCall( jobId, alwaysAllow = false ) {
		return async ( { dispatch } ) => {
			dispatch.setPendingConfirmation( null );
			dispatch.setPendingActionCard( null );
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/job/${ jobId }/confirm`,
					method: 'POST',
					data: { always_allow: alwaysAllow },
				} );
				dispatch.pollJob( jobId );
			} catch ( err ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `Error: ${
								err.message || 'Failed to confirm tool call'
							}`,
						},
					],
				} );
				dispatch.setSending( false );
				dispatch.setCurrentJobId( null );
			}
		};
	},

	/**
	 * Reject a pending tool call and resume the job without executing the tool.
	 *
	 * @param {string} jobId - Job identifier awaiting confirmation.
	 * @return {Function} Redux thunk.
	 */
	rejectToolCall( jobId ) {
		return async ( { dispatch } ) => {
			dispatch.setPendingConfirmation( null );
			dispatch.setPendingActionCard( null );
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/job/${ jobId }/reject`,
					method: 'POST',
				} );
				dispatch.pollJob( jobId );
			} catch ( err ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `Error: ${
								err.message || 'Failed to reject tool call'
							}`,
						},
					],
				} );
				dispatch.setSending( false );
				dispatch.setCurrentJobId( null );
			}
		};
	},

	/**
	 * Send a message or enqueue it if the agent is currently processing.
	 *
	 * When the agent is idle, dispatches streamMessage immediately.
	 * When the agent is busy (sending === true), the message is added to
	 * the queue and will be sent automatically when the current job
	 * completes. The user message is still appended to the message list
	 * immediately with a "queued" flag so it appears in the chat.
	 *
	 * @param {string} message     - User message text.
	 * @param {Array}  attachments - Optional array of attachment objects with
	 *                             { name, type, dataUrl, isImage } shape.
	 * @return {Function} Redux thunk.
	 */
	sendMessage( message, attachments = [] ) {
		return ( { dispatch, select } ) => {
			const isBusy = select.isSending();

			if ( ! isBusy ) {
				dispatch.streamMessage( message, attachments );
			} else {
				// Enqueue the message for later processing.
				dispatch.enqueueMessage( message, attachments );

				// Show the user message in the chat immediately with a
				// "queued" marker so the user sees their message was accepted.
				const parts = [];
				if ( message ) {
					parts.push( { text: message } );
				}
				const imageAttachments = ( attachments || [] ).filter(
					( a ) => a.isImage
				);
				imageAttachments.forEach( ( att ) => {
					parts.push( {
						image_url: att.dataUrl,
						image_name: att.name,
					} );
				} );
				dispatch.appendMessage( {
					role: 'user',
					parts: parts.length ? parts : [ { text: '' } ],
					attachments: imageAttachments,
					queued: true,
				} );
			}
		};
	},

	/**
	 * Process the next message in the queue.
	 *
	 * Called automatically when a job completes and the queue is non-empty.
	 * Dequeues the first message and sends it via streamMessage.
	 *
	 * @return {Function} Redux thunk.
	 */
	drainMessageQueue() {
		return ( { dispatch, select } ) => {
			const queue = select.getMessageQueue();
			if ( ! queue.length ) {
				return;
			}

			const next = queue[ 0 ];
			dispatch.dequeueMessage();
			// Pass fromQueue: true so streamMessage doesn't re-append
			// the user message that was already shown when enqueued.
			dispatch.streamMessage( next.text, next.attachments, {
				fromQueue: true,
			} );
		};
	},

	/**
	 * Send an interrupt message to the currently running agent job.
	 *
	 * The message is injected into the running agent loop's context
	 * so the AI becomes aware of the new information immediately.
	 * The user message is also shown in the chat.
	 *
	 * @param {string} message - The interrupt message text.
	 * @return {Function} Redux thunk.
	 */
	interruptAgent( message ) {
		return async ( { dispatch, select } ) => {
			const jobId = select.getCurrentJobId();
			if ( ! jobId ) {
				return;
			}

			// Show the interrupt message in the chat.
			dispatch.appendMessage( {
				role: 'user',
				parts: [ { text: message } ],
				interrupt: true,
			} );

			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/job/${ jobId }/interrupt`,
					method: 'POST',
					data: { message },
				} );
			} catch {
				// Best-effort — the message is already visible in the chat.
			}
		};
	},

	/**
	 * Poll a job until it completes, errors, or requires confirmation.
	 * Retries every 3 seconds up to 200 attempts (~10 minutes).
	 *
	 * @param {string} jobId - Job identifier to poll.
	 * @return {Function} Redux thunk.
	 */
	pollJob( jobId ) {
		return async ( { dispatch, select } ) => {
			let attempts = 0;
			const maxAttempts = 200;
			// Capture the session this job belongs to so we can update
			// per-session state even if the user switches tabs.
			const jobSessionId = select.getCurrentSessionId();

			const poll = async () => {
				attempts++;
				if ( attempts > maxAttempts ) {
					dispatch.appendMessage( {
						role: 'system',
						parts: [ { text: 'Error: Request timed out.' } ],
					} );
					dispatch.setSending( false );
					dispatch.setCurrentJobId( null );
					if ( jobSessionId ) {
						dispatch.setSessionJob( jobSessionId, null );
					}
					return;
				}

				// If job was cancelled (different jobId now), stop.
				if ( select.getCurrentJobId() !== jobId ) {
					return;
				}

				try {
					const result = await apiFetch( {
						path: `/gratis-ai-agent/v1/job/${ jobId }`,
					} );

					if ( result.status === 'processing' ) {
						// Update live tool call progress.
						if ( result.tool_calls?.length ) {
							dispatch.setLiveToolCalls( result.tool_calls );
							if ( jobSessionId ) {
								dispatch.setSessionJob( jobSessionId, {
									jobId,
									toolCalls: result.tool_calls,
									status: 'processing',
								} );
							}
						}
						setTimeout( poll, 3000 );
						return;
					}

					if ( result.status === 'awaiting_confirmation' ) {
						if ( jobSessionId ) {
							dispatch.setSessionJob( jobSessionId, {
								jobId,
								toolCalls: result.tool_calls || [],
								status: 'awaiting_confirmation',
							} );
						}
						const cardData = {
							jobId,
							tools: result.pending_tools || [],
						};
						dispatch.setPendingConfirmation( cardData );
						dispatch.setPendingActionCard( cardData );
						// Don't clear sending — we're still waiting.
						return;
					}

					if ( result.status === 'error' ) {
						// Build an error message that includes backtrace
						// context when available (file, line, stack trace).
						let errorText = `Error: ${
							result.message || 'Unknown error'
						}`;
						if ( result.error_context ) {
							const ctx = result.error_context;
							errorText += `\n\n**Location:** \`${ ctx.file }:${ ctx.line }\``;
							if (
								Array.isArray( ctx.trace ) &&
								ctx.trace.length > 0
							) {
								errorText +=
									'\n\n**Stack trace:**\n```\n' +
									ctx.trace.join( '\n' ) +
									'\n```';
							}
						}
						dispatch.appendMessage( {
							role: 'system',
							parts: [ { text: errorText } ],
						} );
						// WP_Error max_iterations — show feedback banner (t183).
						const errMsg = result.message || '';
						if ( /max.?iteration/i.test( errMsg ) ) {
							dispatch.setFeedbackBanner( {
								exitReason: 'max_iterations',
							} );
						}
					}

					if ( result.status === 'complete' ) {
						// Reload the session from the DB — the server already
						// persisted the reply. This is the single source of
						// truth and avoids duplicate messages from local append
						// + DB reload races.
						if ( result.session_id ) {
							try {
								const session = await apiFetch( {
									path: `/gratis-ai-agent/v1/sessions/${ result.session_id }`,
								} );
								// Only update if this is still the active session.
								if (
									select.getCurrentSessionId() ===
									result.session_id
								) {
									dispatch.setCurrentSession(
										session.id,
										session.messages || [],
										session.tool_calls || []
									);
								}
							} catch {
								// Fallback: append locally if DB reload fails.
								if ( result.reply ) {
									dispatch.appendMessage( {
										role: 'model',
										parts: [ { text: result.reply } ],
										toolCalls: result.tool_calls,
									} );
								}
							}
						} else if ( result.reply ) {
							// No session_id — append locally.
							dispatch.appendMessage( {
								role: 'model',
								parts: [ { text: result.reply } ],
								toolCalls: result.tool_calls,
							} );
						}

						// Update token usage.
						if ( result.token_usage ) {
							const current = select.getTokenUsage();
							dispatch.setTokenUsage( {
								prompt:
									current.prompt +
									( result.token_usage.prompt || 0 ),
								completion:
									current.completion +
									( result.token_usage.completion || 0 ),
							} );

							const tu = result.token_usage;
							const totalTokens =
								( tu.prompt || 0 ) + ( tu.completion || 0 );
							const cost = result.cost_estimate || 0;
							dispatch.accumulateSessionTokens(
								totalTokens,
								cost
							);
						}

						// Update session title in sidebar.
						if ( result.generated_title && result.session_id ) {
							dispatch.updateSessionTitle(
								result.session_id,
								result.generated_title
							);
						}

						// Handle inability-reported flag (t185).
						// Set when the AI called report-inability ability.
						if ( result.inability_reported ) {
							dispatch.setInabilityReported(
								result.inability_reported
							);
						}

						// Show feedback banner on problematic exit reasons (t183).
						// spin_detected and timeout arrive as exit_reason on the
						// complete result; max_iterations may also arrive here
						// (distinct from the WP_Error path above).
						const FEEDBACK_EXIT_REASONS = [
							'spin_detected',
							'timeout',
							'max_iterations',
						];
						if (
							FEEDBACK_EXIT_REASONS.includes( result.exit_reason )
						) {
							dispatch.setFeedbackBanner( {
								exitReason: result.exit_reason,
							} );
						}

						dispatch.fetchSessions();
					}
				} catch {
					// Network blip — keep polling.
					setTimeout( poll, 3000 );
					return;
				}

				dispatch.setSending( false );
				dispatch.setCurrentJobId( null );
				dispatch.setLiveToolCalls( [] );
				if ( jobSessionId ) {
					dispatch.setSessionJob( jobSessionId, null );
				}

				// Auto-drain the message queue: if the user sent messages
				// while the agent was processing, send the next one now.
				dispatch.drainMessageQueue();
			};

			setTimeout( poll, 2000 );
		};
	},

	/**
	 * Compact the current conversation into a new session with a summary.
	 * Builds a text summary of all messages, creates a new session, and
	 * sends the summary as the first message to preserve context.
	 *
	 * @return {Function} Redux thunk.
	 */
	compactConversation() {
		return async ( { dispatch, select } ) => {
			const messages = select.getCurrentSessionMessages();
			if ( ! messages.length ) {
				return;
			}

			// Build a summary request.
			const summaryText = messages
				.map( ( m ) => {
					const role = m.role === 'model' ? 'Assistant' : 'User';
					const text = m.parts
						?.filter( ( p ) => p.text )
						.map( ( p ) => p.text )
						.join( '' );
					return text ? `${ role }: ${ text }` : null;
				} )
				.filter( Boolean )
				.join( '\n' );

			// Create a new session.
			try {
				const session = await apiFetch( {
					path: '/gratis-ai-agent/v1/sessions',
					method: 'POST',
					data: {
						title: 'Compacted conversation',
						provider_id: select.getSelectedProviderId(),
						model_id: select.getSelectedModelId(),
					},
				} );

				// Send the summary as the first message in the new session.
				dispatch.setCurrentSession( session.id, [], [] );
				dispatch.setTokenUsage( { prompt: 0, completion: 0 } );
				dispatch.resetSessionTokens();
				dispatch.sendMessage(
					'Please provide a concise summary of this conversation so we can continue in a fresh context:\n\n' +
						summaryText
				);
				dispatch.fetchSessions();
			} catch {
				// ignore
			}
		};
	},

	/**
	 * Fetch all sessions shared with admins.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSharedSessions() {
		return async ( { dispatch } ) => {
			try {
				const sessions = await apiFetch( {
					path: '/gratis-ai-agent/v1/sessions/shared',
				} );
				dispatch.setSharedSessions( sessions );
			} catch {
				dispatch.setSharedSessions( [] );
			}
		};
	},

	/**
	 * Share a session with all admins.
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	shareSession( sessionId ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }/share`,
				method: 'POST',
			} );
			dispatch.fetchSessions();
			dispatch.fetchSharedSessions();
		};
	},

	/**
	 * Unshare a session (remove from shared sessions).
	 *
	 * @param {number} sessionId - Session identifier.
	 * @return {Function} Redux thunk.
	 */
	unshareSession( sessionId ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/sessions/${ sessionId }/share`,
				method: 'DELETE',
			} );
			dispatch.fetchSessions();
			dispatch.fetchSharedSessions();
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Session[]} Session list.
	 */
	getSessions( state ) {
		return state.sessions;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether sessions have been fetched.
	 */
	getSessionsLoaded( state ) {
		return state.sessionsLoaded;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {number|null} Active session ID, or null.
	 */
	getCurrentSessionId( state ) {
		return state.currentSessionId;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Message[]} Messages in the active session.
	 */
	getCurrentSessionMessages( state ) {
		return state.currentSessionMessages;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {ToolCall[]} Tool calls in the active session.
	 */
	getCurrentSessionToolCalls( state ) {
		return state.currentSessionToolCalls;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether a message is in-flight.
	 */
	isSending( state ) {
		return state.sending;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string|null} Active polling job ID, or null.
	 */
	getCurrentJobId( state ) {
		return state.currentJobId;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Array} Live tool call progress from the background job.
	 */
	getLiveToolCalls( state ) {
		return state.liveToolCalls;
	},

	/**
	 * Get the full sessionJobs map.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Object} Map of sessionId → job data.
	 */
	getSessionJobs( state ) {
		return state.sessionJobs;
	},

	/**
	 * Get the job for a specific session.
	 *
	 * @param {import('../../types').StoreState} state
	 * @param {number}                           sessionId - Session identifier.
	 * @return {Object|null} Job data or null.
	 */
	getSessionJob( state, sessionId ) {
		return state.sessionJobs[ sessionId ] || null;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {import('../../types').TokenUsage} Cumulative token usage for the current session.
	 */
	getTokenUsage( state ) {
		return state.tokenUsage;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {number} Accumulated session token count (prompt + completion).
	 */
	getSessionTokens( state ) {
		return state.sessionTokens;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {number} Accumulated session cost estimate in USD.
	 */
	getSessionCost( state ) {
		return state.sessionCost;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Array} Per-message token data array.
	 */
	getMessageTokens( state ) {
		return state.messageTokens;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {import('../../types').PendingConfirmation|null} Pending tool confirmation, or null.
	 */
	getPendingConfirmation( state ) {
		return state.pendingConfirmation;
	},

	// Pending action card (inline confirmation in message list, t074)
	getPendingActionCard( state ) {
		return state.pendingActionCard;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Accumulated streaming text buffer.
	 */
	getStreamingText( state ) {
		return state.streamingText;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether an SSE stream is currently active.
	 */
	isStreamingActive( state ) {
		return state.isStreaming;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {AbortController|null} Controller for the active stream, or null.
	 */
	getStreamAbortController( state ) {
		return state.streamAbortController || null;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether the last stream attempt failed with an error.
	 */
	hasStreamError( state ) {
		return state.streamError;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} The last user message text (for retry).
	 */
	getLastUserMessage( state ) {
		return state.lastUserMessage;
	},

	/**
	 * Get inability-reported data (t185).
	 * Returns the data object { reason, attempted_steps } or null.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Object|null} Inability data or null.
	 */
	getInabilityReported( state ) {
		return state.inabilityReported || null;
	},

	/**
	 * Get feedback banner data (t183).
	 * Returns { exitReason } when the AI exited due to spin/timeout/max_iterations, or null.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Object|null} Feedback banner data or null.
	 */
	getFeedbackBanner( state ) {
		return state.feedbackBanner || null;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Session[]} Sessions shared with all admins.
	 */
	getSharedSessions( state ) {
		return state.sharedSessions;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether shared sessions have been fetched.
	 */
	getSharedSessionsLoaded( state ) {
		return state.sharedSessionsLoaded;
	},

	// ─── Message queue ──────────────────────────────────────────

	/**
	 * Get the current message queue.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Array} Queued messages: { text, attachments, timestamp }.
	 */
	getMessageQueue( state ) {
		return state.messageQueue;
	},

	/**
	 * Whether there are messages waiting in the queue.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} True when the queue is non-empty.
	 */
	hasQueuedMessages( state ) {
		return state.messageQueue.length > 0;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_SESSIONS': {
			// Merge any pending optimistic titles into the incoming sessions list.
			// When updateSessionTitle() fires before fetchSessions() returns, the
			// server response may still carry "Untitled" (the AI title is generated
			// client-side from the SSE done event and never written back to the DB
			// in the same request). Preserving the optimistic title here ensures the
			// sidebar reflects the generated title even after the fetchSessions()
			// round-trip completes.
			const pending = state.pendingTitles || {};
			const sessions = action.sessions.map( ( s ) => {
				const optimistic = pending[ s.id ];
				return optimistic ? { ...s, title: optimistic } : s;
			} );
			return {
				...state,
				sessions,
				sessionsLoaded: true,
				pendingTitles: {},
			};
		}
		case 'SET_CURRENT_SESSION':
			return {
				...state,
				currentSessionId: action.sessionId,
				currentSessionMessages: associateToolCallsWithMessages(
					action.messages,
					action.toolCalls
				),
				currentSessionToolCalls: action.toolCalls,
			};
		case 'CLEAR_CURRENT_SESSION':
			return {
				...state,
				currentSessionId: null,
				currentSessionMessages: [],
				currentSessionToolCalls: [],
				tokenUsage: { prompt: 0, completion: 0 },
				sessionTokens: 0,
				sessionCost: 0,
				messageTokens: [],
				feedbackBanner: null,
				messageQueue: [],
			};
		case 'SET_SENDING':
			return { ...state, sending: action.sending };
		case 'SET_CURRENT_JOB_ID':
			return {
				...state,
				currentJobId: action.jobId,
				// Clear live tool calls when job changes.
				liveToolCalls: action.jobId ? state.liveToolCalls : [],
			};
		case 'SET_LIVE_TOOL_CALLS':
			return { ...state, liveToolCalls: action.toolCalls };
		case 'SET_SESSION_JOB': {
			const newJobs = { ...state.sessionJobs };
			if ( action.job ) {
				newJobs[ action.sessionId ] = action.job;
			} else {
				delete newJobs[ action.sessionId ];
			}
			return { ...state, sessionJobs: newJobs };
		}
		case 'APPEND_MESSAGE':
			return {
				...state,
				currentSessionMessages: [
					...state.currentSessionMessages,
					action.message,
				],
			};
		case 'REMOVE_LAST_MESSAGE':
			return {
				...state,
				currentSessionMessages: state.currentSessionMessages.slice(
					0,
					-1
				),
			};
		case 'SET_TOKEN_USAGE':
			return { ...state, tokenUsage: action.tokenUsage };
		case 'ACCUMULATE_SESSION_TOKENS':
			return {
				...state,
				sessionTokens: state.sessionTokens + action.tokens,
				sessionCost: state.sessionCost + action.cost,
			};
		case 'SET_MESSAGE_TOKENS': {
			const newMessageTokens = [ ...state.messageTokens ];
			newMessageTokens[ action.index ] = action.tokenData;
			return { ...state, messageTokens: newMessageTokens };
		}
		case 'RESET_SESSION_TOKENS':
			return {
				...state,
				sessionTokens: 0,
				sessionCost: 0,
				messageTokens: [],
			};
		case 'SET_PENDING_CONFIRMATION':
			return { ...state, pendingConfirmation: action.confirmation };
		case 'SET_PENDING_ACTION_CARD':
			return { ...state, pendingActionCard: action.card };
		case 'TRUNCATE_MESSAGES_TO':
			return {
				...state,
				currentSessionMessages: state.currentSessionMessages.slice(
					0,
					action.index
				),
			};
		case 'SET_SEND_TIMESTAMP':
			return { ...state, sendTimestamp: action.ts };
		case 'SET_STREAMING_TEXT':
			return { ...state, streamingText: action.text };
		case 'APPEND_STREAMING_TEXT':
			return {
				...state,
				streamingText: state.streamingText + action.token,
			};
		case 'SET_IS_STREAMING':
			return { ...state, isStreaming: action.streaming };
		case 'SET_STREAM_ABORT_CONTROLLER':
			return { ...state, streamAbortController: action.controller };
		case 'SET_STREAM_ERROR':
			return { ...state, streamError: action.error };
		case 'SET_LAST_USER_MESSAGE':
			return { ...state, lastUserMessage: action.message };
		case 'SET_INABILITY_REPORTED':
			return { ...state, inabilityReported: action.data };
		case 'SET_FEEDBACK_BANNER':
			return { ...state, feedbackBanner: action.data };
		case 'SET_SHARED_SESSIONS':
			return {
				...state,
				sharedSessions: action.sessions,
				sharedSessionsLoaded: true,
			};
		case 'UPDATE_SESSION_TITLE': {
			const exists = state.sessions.some(
				( s ) => parseInt( s.id, 10 ) === action.sessionId
			);
			// If the session is already in the list, update its title in place.
			// If it is not yet in the list (e.g. a brand-new session whose
			// setCurrentSession ran before fetchSessions populated state.sessions),
			// prepend a minimal stub so the sidebar shows the generated title
			// immediately without waiting for the fetchSessions round-trip.
			const updatedSessions = exists
				? state.sessions.map( ( s ) =>
						parseInt( s.id, 10 ) === action.sessionId
							? { ...s, title: action.title }
							: s
				  )
				: [
						{
							id: action.sessionId,
							title: action.title,
							created_at: new Date().toISOString(),
							updated_at: new Date().toISOString(),
							status: 'active',
							message_count: 0,
						},
						...state.sessions,
				  ];
			// Record the title in pendingTitles so SET_SESSIONS can preserve it
			// when the subsequent fetchSessions() round-trip returns "Untitled"
			// from the server (the server never writes the AI-generated title back
			// to the DB in the same request cycle).
			return {
				...state,
				sessions: updatedSessions,
				pendingTitles: {
					...( state.pendingTitles || {} ),
					[ action.sessionId ]: action.title,
				},
			};
		}
		// ─── Message queue ──────────────────────────────────────
		case 'ENQUEUE_MESSAGE':
			return {
				...state,
				messageQueue: [
					...state.messageQueue,
					{
						text: action.text,
						attachments: action.attachments,
						timestamp: action.timestamp,
					},
				],
			};
		case 'DEQUEUE_MESSAGE':
			return {
				...state,
				messageQueue: state.messageQueue.slice( 1 ),
			};
		case 'CLEAR_MESSAGE_QUEUE':
			return {
				...state,
				messageQueue: [],
			};
		default:
			return state;
	}
}
