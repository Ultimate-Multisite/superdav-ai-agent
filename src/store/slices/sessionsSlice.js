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

export const initialState = {
	sessions: [],
	sessionsLoaded: false,
	currentSessionId: null,
	currentSessionMessages: [],
	currentSessionToolCalls: [],
	sending: false,
	currentJobId: null,

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
	 * @param {string} message     The user message to send.
	 * @param {Array}  attachments Optional array of attachment objects with
	 *                             { name, type, dataUrl, isImage } shape.
	 */
	streamMessage( message, attachments = [] ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
			dispatch.setStreamError( false );
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

			// Append user message immediately (with attachment previews).
			dispatch.appendMessage( {
				role: 'user',
				parts: parts.length ? parts : [ { text: '' } ],
				attachments: imageAttachments,
			} );

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
			const clientAbilities = await snapshotDescriptors();
			if (
				Array.isArray( clientAbilities ) &&
				clientAbilities.length > 0
			) {
				body.client_abilities = clientAbilities;
			}

			dispatch.setSendTimestamp( Date.now() );

			// Streaming was removed when all chat routing was delegated to the
			// WP AI Client SDK, which does not expose a streaming interface.
			// Fire a single synchronous POST to /chat and append the full reply
			// once the agent loop completes.
			let result;
			try {
				result = await apiFetch( {
					path: '/gratis-ai-agent/v1/chat',
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
								__(
									'Failed to reach chat endpoint',
									'gratis-ai-agent'
								)
							}`,
						},
					],
				} );
				dispatch.setStreamError( true );
				dispatch.setSending( false );
				return;
			}

			// Handle tool confirmation pause.
			if ( result?.awaiting_confirmation ) {
				dispatch.setCurrentJobId( result.job_id );
				dispatch.setPendingConfirmation( {
					jobId: result.job_id,
					tools: result.pending_tools || [],
				} );
				// Keep sending=true — we're still waiting for user input.
				return;
			}

			// Handle client-side tool call pause — the server needs the browser
			// to execute these abilities and POST the results back.
			if ( result?.pending_client_tool_calls?.length ) {
				const pendingCalls = result.pending_client_tool_calls;
				const currentSessionId = result.session_id || sessionId;

				// Execute each pending client tool call via the core/abilities store.
				const toolResults = [];
				for ( const call of pendingCalls ) {
					try {
						let callResult;
						if (
							typeof wp !== 'undefined' &&
							wp.data &&
							wp.data.dispatch( 'core/abilities' ) &&
							typeof wp.data.dispatch( 'core/abilities' )
								.executeAbility === 'function'
						) {
							callResult = await wp.data
								.dispatch( 'core/abilities' )
								.executeAbility( call.name, call.args || {} );
						} else {
							// Fallback: look up and call the ability directly.
							const abilityStore =
								wp?.data?.select( 'core/abilities' );
							const abilities =
								abilityStore?.getAbilities?.() || [];
							const ability = abilities.find(
								( a ) => a.name === call.name
							);
							if ( ability?.callback ) {
								callResult = ability.callback(
									call.args || {}
								);
							} else {
								throw new Error(
									`Ability ${ call.name } not found`
								);
							}
						}
						toolResults.push( {
							id: call.id,
							name: call.name,
							result: callResult,
							ran_in_browser: true,
						} );
					} catch ( err ) {
						toolResults.push( {
							id: call.id,
							name: call.name,
							error:
								err?.message ||
								'Client ability execution failed',
							ran_in_browser: true,
						} );
					}
				}

				// POST results back to resume the agent loop.
				let resumeResult;
				try {
					resumeResult = await apiFetch( {
						path: '/gratis-ai-agent/v1/chat/tool-result',
						method: 'POST',
						data: {
							session_id: currentSessionId,
							tool_results: toolResults,
						},
					} );
				} catch ( err ) {
					dispatch.appendMessage( {
						role: 'system',
						parts: [
							{
								text: `${ __( 'Error:', 'gratis-ai-agent' ) } ${
									err.message ||
									__(
										'Failed to resume after client tool execution',
										'gratis-ai-agent'
									)
								}`,
							},
						],
					} );
					dispatch.setStreamError( true );
					dispatch.setSending( false );
					return;
				}

				// Replace result with the resumed response for downstream processing.
				result = resumeResult;

				// Log client tool calls in the tool-call-details UI.
				if ( toolResults.length > 0 ) {
					const clientToolCallLog = toolResults.flatMap( ( tr ) => [
						{
							type: 'call',
							id: tr.id,
							name: tr.name,
							args:
								pendingCalls.find( ( c ) => c.id === tr.id )
									?.args || {},
							ran_in_browser: true,
						},
						{
							type: 'response',
							id: tr.id,
							name: tr.name,
							response: tr.result ?? tr.error,
							ran_in_browser: true,
						},
					] );
					// Merge into the result's tool_calls for display.
					if ( result ) {
						result = {
							...result,
							tool_calls: [
								...( result.tool_calls || [] ),
								...clientToolCallLog,
							],
						};
					}
				}
			}

			// Append the assistant reply.
			if ( result?.reply ) {
				const msg = {
					role: 'model',
					parts: [ { text: result.reply } ],
					toolCalls: result.tool_calls || [],
				};

				if ( select.isDebugMode() ) {
					const sendTs = select.getSendTimestamp();
					const elapsed = sendTs ? Date.now() - sendTs : 0;
					const tu = result.token_usage || {};
					const completionTokens = tu.completion || 0;
					const promptTokens = tu.prompt || 0;
					const tokPerSec =
						elapsed > 0 ? completionTokens / ( elapsed / 1000 ) : 0;
					const tc = result.tool_calls || [];
					const toolCalls = tc.filter( ( t ) => t.type === 'call' );
					const toolNames = [
						...new Set( toolCalls.map( ( t ) => t.name ) ),
					];

					msg.debug = {
						responseTimeMs: elapsed,
						tokenUsage: {
							prompt: promptTokens,
							completion: completionTokens,
						},
						tokensPerSecond: Math.round( tokPerSec * 10 ) / 10,
						modelId: result.model_id || '',
						costEstimate: result.cost_estimate || 0,
						iterationsUsed: result.iterations_used || 0,
						toolCallCount: toolCalls.length,
						toolNames,
					};
				}

				dispatch.appendMessage( msg );
			}

			if ( result?.session_id ) {
				dispatch.setCurrentSession(
					result.session_id,
					select.getCurrentSessionMessages(),
					select.getCurrentSessionToolCalls()
				);
			}

			if ( result?.token_usage ) {
				const current = select.getTokenUsage();
				dispatch.setTokenUsage( {
					prompt: current.prompt + ( result.token_usage.prompt || 0 ),
					completion:
						current.completion +
						( result.token_usage.completion || 0 ),
				} );

				const tu = result.token_usage;
				const totalTokens = ( tu.prompt || 0 ) + ( tu.completion || 0 );
				const cost = result.cost_estimate || 0;
				dispatch.accumulateSessionTokens( totalTokens, cost );

				const msgs = select.getCurrentSessionMessages();
				const msgIndex = msgs.length - 1;
				if ( msgIndex >= 0 ) {
					dispatch.setMessageTokens( msgIndex, {
						prompt: tu.prompt || 0,
						completion: tu.completion || 0,
						cost,
					} );
				}
			}

			if ( result?.generated_title && result?.session_id ) {
				dispatch.updateSessionTitle(
					result.session_id,
					result.generated_title
				);
			}

			dispatch.fetchSessions();
			dispatch.setSending( false );
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
	 * Send a message to the synchronous /chat endpoint.
	 * Delegates to streamMessage, which now performs a single POST to /chat
	 * (streaming was removed when chat routing was delegated to the WP AI
	 * Client SDK, which does not expose a streaming interface).
	 *
	 * @param {string} message     - User message text.
	 * @param {Array}  attachments - Optional array of attachment objects with
	 *                             { name, type, dataUrl, isImage } shape.
	 * @return {Function} Redux thunk.
	 */
	sendMessage( message, attachments = [] ) {
		return ( { dispatch } ) => {
			dispatch.streamMessage( message, attachments );
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

			const poll = async () => {
				attempts++;
				if ( attempts > maxAttempts ) {
					dispatch.appendMessage( {
						role: 'system',
						parts: [ { text: 'Error: Request timed out.' } ],
					} );
					dispatch.setSending( false );
					dispatch.setCurrentJobId( null );
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
						setTimeout( poll, 3000 );
						return;
					}

					if ( result.status === 'awaiting_confirmation' ) {
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
						dispatch.appendMessage( {
							role: 'system',
							parts: [
								{
									text: `Error: ${
										result.message || 'Unknown error'
									}`,
								},
							],
						} );
					}

					if ( result.status === 'complete' ) {
						// Add assistant reply.
						if ( result.reply ) {
							const msg = {
								role: 'model',
								parts: [ { text: result.reply } ],
								toolCalls: result.tool_calls,
							};

							// Attach debug metadata when debug mode is active.
							if ( select.isDebugMode() ) {
								const sendTs = select.getSendTimestamp();
								const elapsed = sendTs
									? Date.now() - sendTs
									: 0;
								const tu = result.token_usage || {};
								const completionTokens = tu.completion || 0;
								const promptTokens = tu.prompt || 0;
								const tokPerSec =
									elapsed > 0
										? completionTokens / ( elapsed / 1000 )
										: 0;

								// Derive tool call count and names.
								const tc = result.tool_calls || [];
								const toolCalls = tc.filter(
									( t ) => t.type === 'call'
								);
								const toolNames = [
									...new Set(
										toolCalls.map( ( t ) => t.name )
									),
								];

								msg.debug = {
									responseTimeMs: elapsed,
									tokenUsage: {
										prompt: promptTokens,
										completion: completionTokens,
									},
									tokensPerSecond:
										Math.round( tokPerSec * 10 ) / 10,
									modelId: result.model_id || '',
									costEstimate: result.cost_estimate || 0,
									iterationsUsed: result.iterations_used || 0,
									toolCallCount: toolCalls.length,
									toolNames,
								};
							}

							dispatch.appendMessage( msg );
						}

						if ( result.session_id ) {
							dispatch.setCurrentSession(
								result.session_id,
								select.getCurrentSessionMessages(),
								select.getCurrentSessionToolCalls()
							);
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

							// Live token counter (t111).
							const tu = result.token_usage;
							const totalTokens =
								( tu.prompt || 0 ) + ( tu.completion || 0 );
							const cost = result.cost_estimate || 0;
							dispatch.accumulateSessionTokens(
								totalTokens,
								cost
							);

							const msgs = select.getCurrentSessionMessages();
							const msgIndex = msgs.length - 1;
							if ( msgIndex >= 0 ) {
								dispatch.setMessageTokens( msgIndex, {
									prompt: tu.prompt || 0,
									completion: tu.completion || 0,
									cost,
								} );
							}
						}

						// Optimistically update the session title in the sidebar
						// when the server generated one (first message only).
						if ( result.generated_title && result.session_id ) {
							dispatch.updateSessionTitle(
								result.session_id,
								result.generated_title
							);
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
				currentSessionMessages: action.messages,
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
			};
		case 'SET_SENDING':
			return { ...state, sending: action.sending };
		case 'SET_CURRENT_JOB_ID':
			return { ...state, currentJobId: action.jobId };
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
		default:
			return state;
	}
}
