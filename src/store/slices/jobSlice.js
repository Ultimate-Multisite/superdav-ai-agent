/**
 * Job slice — background job polling, live tool calls, and pending confirmations.
 *
 * Extracted from sessionsSlice (t203) and extended with session-scoped polling,
 * exponential backoff, and visibility throttling (t204).
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { onVisibilityChange } from '../../utils/visibility-manager';
import { setActiveJob, clearActiveJob } from '../../utils/active-jobs-storage';
import { notifyConfirmationNeeded } from '../../utils/notification-manager';

export const initialState = {
	// Active polling job ID (most-recently-started job for the current session).
	currentJobId: null,

	// Live tool call progress from the background job (shown while processing).
	liveToolCalls: [],

	// Per-session background job tracking.
	// Map of sessionId → { jobId, toolCalls, status }
	// Allows multiple sessions to have active jobs simultaneously.
	sessionJobs: {},

	// Pending confirmation (Batch 8)
	pendingConfirmation: null,

	// Action card — inline confirmation rendered in the message list (t074).
	pendingActionCard: null,
};

export const actions = {
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
	 * Set or clear the pending tool confirmation.
	 *
	 * @param {Object|null} confirmation - Confirmation payload, or null to clear.
	 * @return {Object} Redux action.
	 */
	setPendingConfirmation( confirmation ) {
		return { type: 'SET_PENDING_CONFIRMATION', confirmation };
	},

	/**
	 * Set or clear the pending action card (inline confirmation in message list).
	 *
	 * @param {Object|null} card - Action card data or null.
	 * @return {Object} Redux action.
	 */
	setPendingActionCard( card ) {
		return { type: 'SET_PENDING_ACTION_CARD', card };
	},

	/**
	 * Poll a background job with session-scoped tracking and exponential backoff.
	 *
	 * Session-scoped: multiple sessions can poll independently — no check against
	 * currentJobId. Backoff schedule: 1s start → 5s after 10 polls → 10s after 30
	 * polls (hard cap). Resets to 1s on progress (tool_calls length change).
	 * When document is hidden, slows to 15s. On visibility restore, immediately
	 * polls once per active session then resumes normal intervals.
	 *
	 * @param {string} jobId     - Job identifier to poll.
	 * @param {number} sessionId - Session the job belongs to.
	 * @return {Function} Redux thunk.
	 */
	pollJob( jobId, sessionId ) {
		return async ( { dispatch, select } ) => {
			let attempts = 0;
			const maxAttempts = 200;
			let lastToolCallsLength = 0;
			let visibilityPaused = false;
			let resumeCallback = null;

			// Register for visibility-resume notification.
			// When the page becomes visible again, we want to poll immediately.
			const unsubscribeVisibility = onVisibilityChange( ( hidden ) => {
				if ( ! hidden && visibilityPaused ) {
					// Page just became visible — trigger an immediate poll.
					visibilityPaused = false;
					if ( resumeCallback ) {
						resumeCallback();
					}
				}
			} );

			/**
			 * Calculate the poll interval based on attempt count and visibility.
			 *
			 * @param {number} attemptCount - Number of polls completed so far.
			 * @return {number} Interval in milliseconds.
			 */
			const getInterval = ( attemptCount ) => {
				if ( typeof document !== 'undefined' && document.hidden ) {
					return 15000; // 15s when tab is hidden.
				}
				if ( attemptCount >= 30 ) {
					return 10000; // 10s cap after 30 polls.
				}
				if ( attemptCount >= 10 ) {
					return 5000; // 5s after 10 polls.
				}
				return 1000; // 1s initially.
			};

			const poll = async () => {
				attempts++;
				if ( attempts > maxAttempts ) {
					unsubscribeVisibility();
					clearActiveJob( sessionId );
					// Only append error and update UI for the current session.
					if ( select.getCurrentSessionId() === sessionId ) {
						dispatch.appendMessage( {
							role: 'system',
							parts: [ { text: 'Error: Request timed out.' } ],
						} );
						dispatch.setSending( false );
					}
					dispatch.setCurrentJobId( null );
					dispatch.setSessionJob( sessionId, null );
					return;
				}

				try {
					const result = await apiFetch( {
						path: `/gratis-ai-agent/v1/job/${ jobId }`,
					} );

					if ( result.status === 'processing' ) {
						// Update per-session job state for ALL sessions.
						if ( result.tool_calls?.length ) {
							dispatch.setSessionJob( sessionId, {
								jobId,
								toolCalls: result.tool_calls,
								status: 'processing',
							} );
						}

						// Only update live tool calls when this is the active session.
						if (
							result.tool_calls?.length &&
							select.getCurrentSessionId() === sessionId
						) {
							dispatch.setLiveToolCalls( result.tool_calls );
						}

						// Detect progress: reset backoff when tool_calls length increases.
						const newLen = result.tool_calls?.length || 0;
						if ( newLen > lastToolCallsLength ) {
							lastToolCallsLength = newLen;
							attempts = 0; // Reset backoff on progress.
						}

						// Slow poll if tab is hidden.
						if (
							typeof document !== 'undefined' &&
							document.hidden
						) {
							visibilityPaused = true;
							// Set up a promise that resolves on visibility restore
							// OR after the slow interval, whichever comes first.
							await new Promise( ( resolve ) => {
								resumeCallback = resolve;
								setTimeout( () => {
									visibilityPaused = false;
									resolve();
								}, getInterval( attempts ) );
							} );
						} else {
							await new Promise( ( resolve ) =>
								setTimeout( resolve, getInterval( attempts ) )
							);
						}

						// Re-check job is still active before continuing.
						const currentJobId = select.getCurrentJobId();
						if ( currentJobId !== jobId && currentJobId !== null ) {
							// Different job is now active; stop this poller.
							unsubscribeVisibility();
							clearActiveJob( sessionId );
							return;
						}

						poll();
						return;
					}

					if ( result.status === 'awaiting_confirmation' ) {
						dispatch.setSessionJob( sessionId, {
							jobId,
							toolCalls: result.tool_calls || [],
							status: 'awaiting_confirmation',
						} );

						// Only show confirmation UI for the active session.
						if ( select.getCurrentSessionId() === sessionId ) {
							const cardData = {
								jobId,
								tools: result.pending_tools || [],
							};
							dispatch.setPendingConfirmation( cardData );
							dispatch.setPendingActionCard( cardData );
						}

						// Fire a browser notification when the user is not
						// looking at the page so they know approval is needed.
						if (
							typeof document !== 'undefined' &&
							document.hidden
						) {
							const firstTool = result.pending_tools?.[ 0 ];
							const toolName =
								firstTool?.function?.name ||
								firstTool?.name ||
								'';
							notifyConfirmationNeeded( jobId, toolName );
						}

						// Don't clear sending — still waiting.
						unsubscribeVisibility();
						clearActiveJob( sessionId );
						return;
					}

					if ( result.status === 'error' ) {
						let errorText = `${ __(
							'Error:',
							'gratis-ai-agent'
						) } ${
							result.message ||
							__( 'Unknown error', 'gratis-ai-agent' )
						}`;
						if ( result.error_context ) {
							const ctx = result.error_context;
							errorText += `\n\n**${ __(
								'Location:',
								'gratis-ai-agent'
							) }** \`${ ctx.file }:${ ctx.line }\``;
							if (
								Array.isArray( ctx.trace ) &&
								ctx.trace.length > 0
							) {
								errorText +=
									'\n\n**' +
									__( 'Stack trace:', 'gratis-ai-agent' ) +
									'**\n```\n' +
									ctx.trace.join( '\n' ) +
									'\n```';
							}
						}

						if ( select.getCurrentSessionId() === sessionId ) {
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
					}

					if ( result.status === 'complete' ) {
						// Reload the session from the DB when it's the active session.
						if (
							result.session_id &&
							select.getCurrentSessionId() === sessionId
						) {
							try {
								const session = await apiFetch( {
									path: `/gratis-ai-agent/v1/sessions/${ result.session_id }`,
								} );
								// Guard: still the active session after the async fetch.
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
						} else if (
							result.reply &&
							select.getCurrentSessionId() === sessionId
						) {
							dispatch.appendMessage( {
								role: 'model',
								parts: [ { text: result.reply } ],
								toolCalls: result.tool_calls,
							} );
						}

						if (
							result.token_usage &&
							select.getCurrentSessionId() === sessionId
						) {
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

						if ( result.generated_title && result.session_id ) {
							dispatch.updateSessionTitle(
								result.session_id,
								result.generated_title
							);
						}

						if (
							result.inability_reported &&
							select.getCurrentSessionId() === sessionId
						) {
							dispatch.setInabilityReported(
								result.inability_reported
							);
						}

						const FEEDBACK_EXIT_REASONS = [
							'spin_detected',
							'timeout',
							'max_iterations',
						];
						if (
							FEEDBACK_EXIT_REASONS.includes(
								result.exit_reason
							) &&
							select.getCurrentSessionId() === sessionId
						) {
							dispatch.setFeedbackBanner( {
								exitReason: result.exit_reason,
							} );
						}

						if ( select.getCurrentSessionId() === sessionId ) {
							dispatch.fetchSessions();
						}
					}
				} catch {
					// Network blip — keep polling with backoff.
					await new Promise( ( resolve ) =>
						setTimeout( resolve, getInterval( attempts ) )
					);
					poll();
					return;
				}

				// Job finished (complete or error).
				unsubscribeVisibility();
				clearActiveJob( sessionId );
				if ( select.getCurrentSessionId() === sessionId ) {
					dispatch.setSending( false );
					dispatch.setLiveToolCalls( [] );
					// Auto-drain the message queue.
					dispatch.drainMessageQueue();
				}
				dispatch.setCurrentJobId( null );
				dispatch.setSessionJob( sessionId, null );
			};

			// Persist to sessionStorage so the poll loop survives same-tab
			// wp-admin page navigation (Phase 4 / t206).
			setActiveJob( sessionId, jobId );

			// Initial delay before first poll.
			setTimeout( poll, 2000 );
		};
	},
};

export const selectors = {
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
	 * @return {Object|null} Pending tool confirmation, or null.
	 */
	getPendingConfirmation( state ) {
		return state.pendingConfirmation;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Object|null} Pending action card data, or null.
	 */
	getPendingActionCard( state ) {
		return state.pendingActionCard;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_CURRENT_JOB_ID':
			return {
				...state,
				currentJobId: action.jobId,
				// Clear live tool calls when job is cleared.
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
		case 'SET_PENDING_CONFIRMATION':
			return { ...state, pendingConfirmation: action.confirmation };
		case 'SET_PENDING_ACTION_CARD':
			return { ...state, pendingActionCard: action.card };
		default:
			return state;
	}
}
