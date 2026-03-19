/**
 * WordPress dependencies
 */
import {
	createReduxStore,
	register,
	select as wpSelect,
} from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * @typedef {import('../types').StoreState} StoreState
 * @typedef {import('../types').Provider} Provider
 * @typedef {import('../types').Session} Session
 * @typedef {import('../types').Message} Message
 * @typedef {import('../types').ToolCall} ToolCall
 * @typedef {import('../types').TokenUsage} TokenUsage
 * @typedef {import('../types').PendingConfirmation} PendingConfirmation
 * @typedef {import('../types').Settings} Settings
 * @typedef {import('../types').Memory} Memory
 * @typedef {import('../types').Skill} Skill
 */

const STORE_NAME = 'gratis-ai-agent';

// Migrate localStorage keys from old "aiAgent" prefix to "gratisAiAgent".
[ 'Provider', 'Model', 'DebugMode' ].forEach( ( key ) => {
	const oldKey = `aiAgent${ key }`;
	const newKey = `gratisAiAgent${ key }`;
	if (
		localStorage.getItem( oldKey ) !== null &&
		localStorage.getItem( newKey ) === null
	) {
		localStorage.setItem( newKey, localStorage.getItem( oldKey ) );
		localStorage.removeItem( oldKey );
	}
} );

/**
 * Known model context windows (tokens).
 */
const MODEL_CONTEXT_WINDOWS = {
	'claude-sonnet-4-20250514': 200000,
	'claude-opus-4-20250115': 200000,
	'gpt-4.1': 1000000,
	'gpt-4.1-mini': 1000000,
	'gpt-4.1-nano': 1000000,
	'gpt-4o': 128000,
	'gpt-4o-mini': 128000,
};

const DEFAULT_STATE = {
	providers: [],
	providersLoaded: false,
	sessions: [],
	sessionsLoaded: false,
	currentSessionId: null,
	currentSessionMessages: [],
	currentSessionToolCalls: [],
	sending: false,
	currentJobId: null,
	selectedProviderId: localStorage.getItem( 'gratisAiAgentProvider' ) || '',
	selectedModelId: localStorage.getItem( 'gratisAiAgentModel' ) || '',
	floatingOpen: false,
	floatingMinimized: false,
	pageContext: '',

	// Session filters
	sessionFilter: 'active',
	sessionFolder: '',
	sessionSearch: '',
	folders: [],
	foldersLoaded: false,

	// Settings
	settings: null,
	settingsLoaded: false,

	// Memory
	memories: [],
	memoriesLoaded: false,

	// Skills
	skills: [],
	skillsLoaded: false,

	// Conversation templates
	conversationTemplates: [],
	conversationTemplatesLoaded: false,

	// Agents
	agents: [],
	agentsLoaded: false,
	selectedAgentId: null,

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

	// Debug mode
	debugMode: localStorage.getItem( 'gratisAiAgentDebugMode' ) === 'true',
	sendTimestamp: 0,

	// Streaming state — token buffer for the in-progress assistant message.
	streamingText: '',
	isStreaming: false,

	// Stream error state — true when the last stream attempt failed.
	// Used to show a "Try again" button in the message list.
	streamError: false,

	// Last user message text — stored so retryLastMessage can resend it.
	lastUserMessage: '',

	// Proactive alerts — count of issues surfaced as a badge on the FAB.
	alertCount: 0,

	// Site builder mode — true when a fresh WordPress install is detected.
	// Seeded from the PHP-injected global so the widget can open immediately
	// without waiting for a REST round-trip.
	siteBuilderMode: window.gratisAiAgentSiteBuilder?.siteBuilderMode ?? false,
	isFreshInstall: window.gratisAiAgentSiteBuilder?.isFreshInstall ?? false,
	siteBuilderStep: 0,
	siteBuilderTotalSteps: 0,

	// Text-to-speech (t084) — persisted to localStorage.
	ttsEnabled: localStorage.getItem( 'gratisAiAgentTtsEnabled' ) === 'true',
	ttsVoiceURI: localStorage.getItem( 'gratisAiAgentTtsVoiceURI' ) || '',
	ttsRate: parseFloat(
		localStorage.getItem( 'gratisAiAgentTtsRate' ) || '1'
	),
	ttsPitch: parseFloat(
		localStorage.getItem( 'gratisAiAgentTtsPitch' ) || '1'
	),

	// Shared sessions — sessions shared with all admins (t077).
	sharedSessions: [],
	sharedSessionsLoaded: false,
};

const actions = {
	/**
	 * Replace the providers list.
	 *
	 * @param {Provider[]} providers - Available AI providers.
	 * @return {Object} Redux action.
	 */
	setProviders( providers ) {
		return { type: 'SET_PROVIDERS', providers };
	},

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
	 * Select an AI provider and persist the choice to localStorage.
	 *
	 * @param {string} providerId - Provider identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedProvider( providerId ) {
		localStorage.setItem( 'gratisAiAgentProvider', providerId );
		return { type: 'SET_SELECTED_PROVIDER', providerId };
	},

	/**
	 * Select a model and persist the choice to localStorage.
	 *
	 * @param {string} modelId - Model identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedModel( modelId ) {
		localStorage.setItem( 'gratisAiAgentModel', modelId );
		return { type: 'SET_SELECTED_MODEL', modelId };
	},

	/**
	 * Open or close the floating panel.
	 *
	 * @param {boolean} open - Whether the panel should be open.
	 * @return {Object} Redux action.
	 */
	setFloatingOpen( open ) {
		return { type: 'SET_FLOATING_OPEN', open };
	},

	/**
	 * Minimize or expand the floating panel.
	 *
	 * @param {boolean} minimized - Whether the panel should be minimized.
	 * @return {Object} Redux action.
	 */
	setFloatingMinimized( minimized ) {
		return { type: 'SET_FLOATING_MINIMIZED', minimized };
	},

	/**
	 * Enable or disable site builder mode.
	 *
	 * @param {boolean} enabled - Whether site builder mode should be active.
	 * @return {Object} Redux action.
	 */
	setSiteBuilderMode( enabled ) {
		return { type: 'SET_SITE_BUILDER_MODE', enabled };
	},

	/**
	 * Set structured page context for the AI.
	 *
	 * @param {string|Object} context - Page context object or string.
	 * @return {Object} Redux action.
	 */
	setPageContext( context ) {
		return { type: 'SET_PAGE_CONTEXT', context };
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
	 * Replace the plugin settings.
	 *
	 * @param {Settings} settings - Plugin settings object.
	 * @return {Object} Redux action.
	 */
	setSettings( settings ) {
		return { type: 'SET_SETTINGS', settings };
	},

	/**
	 * Replace the memories list.
	 *
	 * @param {Memory[]} memories - Memory entries.
	 * @return {Object} Redux action.
	 */
	setMemories( memories ) {
		return { type: 'SET_MEMORIES', memories };
	},

	/**
	 * Replace the skills list.
	 *
	 * @param {Skill[]} skills - Skill entries.
	 * @return {Object} Redux action.
	 */
	setSkills( skills ) {
		return { type: 'SET_SKILLS', skills };
	},
	setConversationTemplates( templates ) {
		return { type: 'SET_CONVERSATION_TEMPLATES', templates };
	},
	setAgents( agents ) {
		return { type: 'SET_AGENTS', agents };
	},
	setSelectedAgentId( agentId ) {
		return { type: 'SET_SELECTED_AGENT_ID', agentId };
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
	 * Set the active session filter tab.
	 *
	 * @param {string} filter - Filter key: 'active', 'archived', or 'trash'.
	 * @return {Object} Redux action.
	 */
	setSessionFilter( filter ) {
		return { type: 'SET_SESSION_FILTER', filter };
	},

	/**
	 * Set the active folder filter.
	 *
	 * @param {string} folder - Folder name, or empty string for all.
	 * @return {Object} Redux action.
	 */
	setSessionFolder( folder ) {
		return { type: 'SET_SESSION_FOLDER', folder };
	},

	/**
	 * Set the session search query.
	 *
	 * @param {string} search - Search string.
	 * @return {Object} Redux action.
	 */
	setSessionSearch( search ) {
		return { type: 'SET_SESSION_SEARCH', search };
	},

	/**
	 * Replace the folders list.
	 *
	 * @param {string[]} folders - Available folder names.
	 * @return {Object} Redux action.
	 */
	setFolders( folders ) {
		return { type: 'SET_FOLDERS', folders };
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
	 * Enable or disable debug mode and persist the choice to localStorage.
	 *
	 * @param {boolean} enabled - Whether debug mode should be active.
	 * @return {Object} Redux action.
	 */
	setDebugMode( enabled ) {
		localStorage.setItem(
			'gratisAiAgentDebugMode',
			enabled ? 'true' : 'false'
		);
		return { type: 'SET_DEBUG_MODE', enabled };
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
	setAlertCount( count ) {
		return { type: 'SET_ALERT_COUNT', count };
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
	 * Set the current step number in the site builder progress indicator.
	 *
	 * @param {number} step - Current step (0-based).
	 * @return {Object} Redux action.
	 */
	setSiteBuilderStep( step ) {
		return { type: 'SET_SITE_BUILDER_STEP', step };
	},

	/**
	 * Set the total number of steps in the site builder progress indicator.
	 *
	 * @param {number} total - Total step count.
	 * @return {Object} Redux action.
	 */
	setSiteBuilderTotalSteps( total ) {
		return { type: 'SET_SITE_BUILDER_TOTAL_STEPS', total };
	},

	// ─── Text-to-speech (t084) ───────────────────────────────────

	/**
	 * Enable or disable text-to-speech and persist the choice to localStorage.
	 *
	 * @param {boolean} enabled - Whether TTS should be active.
	 * @return {Object} Redux action.
	 */
	setTtsEnabled( enabled ) {
		localStorage.setItem(
			'gratisAiAgentTtsEnabled',
			enabled ? 'true' : 'false'
		);
		return { type: 'SET_TTS_ENABLED', enabled };
	},

	/**
	 * Set the TTS voice URI and persist to localStorage.
	 *
	 * @param {string} voiceURI - SpeechSynthesisVoice.voiceURI value.
	 * @return {Object} Redux action.
	 */
	setTtsVoiceURI( voiceURI ) {
		localStorage.setItem( 'gratisAiAgentTtsVoiceURI', voiceURI );
		return { type: 'SET_TTS_VOICE_URI', voiceURI };
	},

	/**
	 * Set the TTS speech rate and persist to localStorage.
	 *
	 * @param {number} rate - Speech rate (0.1–10).
	 * @return {Object} Redux action.
	 */
	setTtsRate( rate ) {
		localStorage.setItem( 'gratisAiAgentTtsRate', String( rate ) );
		return { type: 'SET_TTS_RATE', rate };
	},

	/**
	 * Set the TTS speech pitch and persist to localStorage.
	 *
	 * @param {number} pitch - Speech pitch (0–2).
	 * @return {Object} Redux action.
	 */
	setTtsPitch( pitch ) {
		localStorage.setItem( 'gratisAiAgentTtsPitch', String( pitch ) );
		return { type: 'SET_TTS_PITCH', pitch };
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
	 * Fetch available AI providers from the REST API and populate the store.
	 * Auto-selects the first provider/model when none is saved or the saved
	 * provider is no longer available.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchProviders() {
		return async ( { dispatch } ) => {
			try {
				const providers = await apiFetch( {
					path: '/gratis-ai-agent/v1/providers',
				} );
				dispatch.setProviders( providers );

				// Auto-select first provider if none saved or saved one is unavailable.
				const saved = localStorage.getItem( 'gratisAiAgentProvider' );
				if (
					( ! saved ||
						! providers.find( ( p ) => p.id === saved ) ) &&
					providers.length
				) {
					dispatch.setSelectedProvider( providers[ 0 ].id );
					if ( providers[ 0 ].models?.length ) {
						dispatch.setSelectedModel(
							providers[ 0 ].models[ 0 ].id
						);
					} else {
						dispatch.setSelectedModel( '' );
					}
				}
			} catch {
				dispatch.setProviders( [] );
			}
		};
	},

	fetchAlerts() {
		return async ( { dispatch } ) => {
			try {
				const data = await apiFetch( {
					path: '/gratis-ai-agent/v1/alerts',
				} );
				dispatch.setAlertCount( data.count || 0 );
			} catch {
				// Non-fatal — badge simply stays at 0 on error.
				dispatch.setAlertCount( 0 );
			}
		};
	},

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
	 * Fetch the list of folder names from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchFolders() {
		return async ( { dispatch } ) => {
			try {
				const folders = await apiFetch( {
					path: '/gratis-ai-agent/v1/sessions/folders',
				} );
				dispatch.setFolders( folders );
			} catch {
				dispatch.setFolders( [] );
			}
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
	 * @param {string} message The user message to send.
	 */
	streamMessage( message ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
			dispatch.setStreamError( false );
			dispatch.setLastUserMessage( message );

			// Append user message immediately.
			dispatch.appendMessage( {
				role: 'user',
				parts: [ { text: message } ],
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

			dispatch.setSendTimestamp( Date.now() );

			// Build the URL with nonce for authentication.
			const streamUrl =
				( window.wpApiSettings?.root || '/wp-json/' ) +
				'gratis-ai-agent/v1/stream';

			const abortController = new AbortController();
			dispatch.setStreamAbortController( abortController );

			// 120-second timeout: abort the stream if no completion within limit.
			const STREAM_TIMEOUT_MS = 120000;
			let timeoutId = setTimeout( () => {
				abortController.abort( 'timeout' );
			}, STREAM_TIMEOUT_MS );

			let response;
			try {
				response = await fetch( streamUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.wpApiSettings?.nonce || '',
					},
					body: JSON.stringify( body ),
					signal: abortController.signal,
				} );
			} catch ( err ) {
				clearTimeout( timeoutId );
				if ( err.name === 'AbortError' ) {
					const isTimeout =
						err.message === 'timeout' ||
						String( abortController.signal?.reason ) === 'timeout';
					if ( isTimeout ) {
						dispatch.appendMessage( {
							role: 'system',
							parts: [
								{
									text: __(
										'Error: The request timed out after 120 seconds. The server may be overloaded. Please try again.',
										'gratis-ai-agent'
									),
								},
							],
						} );
						dispatch.setStreamError( true );
					}
					dispatch.setSending( false );
					dispatch.setIsStreaming( false );
					dispatch.setStreamingText( '' );
					dispatch.setStreamAbortController( null );
					return;
				}
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `${ __( 'Error:', 'gratis-ai-agent' ) } ${
								err.message ||
								__(
									'Failed to connect to stream',
									'gratis-ai-agent'
								)
							}`,
						},
					],
				} );
				dispatch.setStreamError( true );
				dispatch.setSending( false );
				dispatch.setStreamAbortController( null );
				return;
			}

			if ( ! response.ok ) {
				clearTimeout( timeoutId );
				// Detect non-SSE responses (e.g. PHP fatal error HTML pages).
				const contentType =
					response.headers.get( 'Content-Type' ) || '';
				const isHtml = contentType.includes( 'text/html' );
				const errorText = isHtml
					? __(
							'Error: The server returned an unexpected response (possibly a PHP error). Check your server logs.',
							'gratis-ai-agent'
					  )
					: `${ __( 'Error: HTTP', 'gratis-ai-agent' ) } ${
							response.status
					  } ${ __( 'from stream endpoint', 'gratis-ai-agent' ) }`;
				dispatch.appendMessage( {
					role: 'system',
					parts: [ { text: errorText } ],
				} );
				dispatch.setStreamError( true );
				dispatch.setSending( false );
				dispatch.setStreamAbortController( null );
				return;
			}

			// Verify the response is actually an SSE stream before reading.
			const responseContentType =
				response.headers.get( 'Content-Type' ) || '';
			if ( ! responseContentType.includes( 'text/event-stream' ) ) {
				clearTimeout( timeoutId );
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: __(
								'Error: The server did not return a streaming response. This may indicate a PHP error or misconfiguration.',
								'gratis-ai-agent'
							),
						},
					],
				} );
				dispatch.setStreamError( true );
				dispatch.setSending( false );
				dispatch.setStreamAbortController( null );
				return;
			}

			dispatch.setIsStreaming( true );

			const reader = response.body.getReader();
			const decoder = new TextDecoder();
			let buffer = '';
			let accumulatedText = '';
			let doneMetadata = null;
			let pendingConfirmationData = null;

			try {
				// eslint-disable-next-line no-constant-condition
				while ( true ) {
					const { done, value } = await reader.read();
					if ( done ) {
						clearTimeout( timeoutId );
						break;
					}
					// Reset timeout on each received chunk — stream is alive.
					clearTimeout( timeoutId );
					timeoutId = setTimeout( () => {
						abortController.abort( 'timeout' );
					}, STREAM_TIMEOUT_MS );

					buffer += decoder.decode( value, { stream: true } );

					// Process complete SSE messages (terminated by \n\n).
					const parts = buffer.split( '\n\n' );
					// Keep the last (possibly incomplete) chunk in the buffer.
					buffer = parts.pop() || '';

					for ( const part of parts ) {
						const lines = part.split( '\n' );
						let eventName = 'message';
						let dataLine = '';

						for ( const line of lines ) {
							if ( line.startsWith( 'event: ' ) ) {
								eventName = line.slice( 7 );
							} else if ( line.startsWith( 'data: ' ) ) {
								dataLine = line.slice( 6 );
							}
						}

						if ( ! dataLine ) {
							continue;
						}

						let payload;
						try {
							payload = JSON.parse( dataLine );
						} catch {
							continue;
						}

						switch ( eventName ) {
							case 'token':
								accumulatedText += payload.token || '';
								dispatch.appendStreamingText(
									payload.token || ''
								);
								break;

							case 'tool_call':
								// Tool calls are surfaced in the done metadata.
								break;

							case 'tool_result':
								// Tool results are surfaced in the done metadata.
								break;

							case 'confirmation_required':
								pendingConfirmationData = payload;
								break;

							case 'done':
								doneMetadata = payload;
								break;

							case 'error':
								clearTimeout( timeoutId );
								dispatch.setIsStreaming( false );
								dispatch.setStreamingText( '' );
								dispatch.appendMessage( {
									role: 'system',
									parts: [
										{
											text: `${ __(
												'Error:',
												'gratis-ai-agent'
											) } ${
												payload.message ||
												__(
													'Unknown error',
													'gratis-ai-agent'
												)
											}`,
										},
									],
								} );
								dispatch.setStreamError( true );
								dispatch.setSending( false );
								dispatch.setStreamAbortController( null );
								return;
						}
					}
				}
			} catch ( err ) {
				clearTimeout( timeoutId );
				if ( err.name === 'AbortError' ) {
					const isTimeout =
						err.message === 'timeout' ||
						String( abortController.signal?.reason ) === 'timeout';
					if ( isTimeout ) {
						dispatch.appendMessage( {
							role: 'system',
							parts: [
								{
									text: __(
										'Error: The response timed out after 120 seconds. Please try again.',
										'gratis-ai-agent'
									),
								},
							],
						} );
						dispatch.setStreamError( true );
					}
				} else {
					dispatch.appendMessage( {
						role: 'system',
						parts: [
							{
								text: `${ __( 'Error:', 'gratis-ai-agent' ) } ${
									err.message ||
									__( 'Stream read error', 'gratis-ai-agent' )
								}`,
							},
						],
					} );
					dispatch.setStreamError( true );
				}
				dispatch.setIsStreaming( false );
				dispatch.setStreamingText( '' );
				dispatch.setSending( false );
				dispatch.setStreamAbortController( null );
				return;
			}

			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );
			dispatch.setStreamAbortController( null );
			dispatch.setStreamError( false );

			// Handle tool confirmation pause.
			if ( pendingConfirmationData ) {
				dispatch.setPendingConfirmation( {
					jobId: pendingConfirmationData.job_id,
					tools: pendingConfirmationData.pending_tools || [],
				} );
				// Keep sending=true — we're still waiting for user input.
				return;
			}

			// Commit the streamed text as a proper message.
			if ( accumulatedText ) {
				const msg = {
					role: 'model',
					parts: [ { text: accumulatedText } ],
					toolCalls: doneMetadata?.tool_calls || [],
				};

				if ( select.isDebugMode() && doneMetadata ) {
					const sendTs = select.getSendTimestamp();
					const elapsed = sendTs ? Date.now() - sendTs : 0;
					const tu = doneMetadata.token_usage || {};
					const completionTokens = tu.completion || 0;
					const promptTokens = tu.prompt || 0;
					const tokPerSec =
						elapsed > 0 ? completionTokens / ( elapsed / 1000 ) : 0;
					const tc = doneMetadata.tool_calls || [];
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
						modelId: doneMetadata.model_id || '',
						costEstimate: doneMetadata.cost_estimate || 0,
						iterationsUsed: doneMetadata.iterations_used || 0,
						toolCallCount: toolCalls.length,
						toolNames,
					};
				}

				dispatch.appendMessage( msg );
			}

			if ( doneMetadata?.session_id ) {
				dispatch.setCurrentSession(
					doneMetadata.session_id,
					select.getCurrentSessionMessages(),
					select.getCurrentSessionToolCalls()
				);
			}

			if ( doneMetadata?.token_usage ) {
				const current = select.getTokenUsage();
				dispatch.setTokenUsage( {
					prompt:
						current.prompt +
						( doneMetadata.token_usage.prompt || 0 ),
					completion:
						current.completion +
						( doneMetadata.token_usage.completion || 0 ),
				} );

				// Live token counter (t111).
				const tu = doneMetadata.token_usage;
				const totalTokens = ( tu.prompt || 0 ) + ( tu.completion || 0 );
				const cost = doneMetadata.cost_estimate || 0;
				dispatch.accumulateSessionTokens( totalTokens, cost );

				// Record per-message token data at the index of the message
				// we just appended (last in the list after appendMessage).
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

			// Optimistically update the session title in the sidebar when the
			// server generated one (first message only — title is empty before).
			if ( doneMetadata?.generated_title && doneMetadata?.session_id ) {
				dispatch.updateSessionTitle(
					doneMetadata.session_id,
					doneMetadata.generated_title
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
	 * Send a message via the polling (non-streaming) endpoint.
	 * Creates a session lazily on the first message.
	 *
	 * @param {string} message - User message text.
	 * @return {Function} Redux thunk.
	 */
	sendMessage( message ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );

			// Append user message to UI immediately.
			dispatch.appendMessage( {
				role: 'user',
				parts: [ { text: message } ],
			} );

			let sessionId = select.getCurrentSessionId();

			// Lazy create session on first message.
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
						parts: [
							{
								text: 'Error: Failed to create session.',
							},
						],
					} );
					dispatch.setSending( false );
					return;
				}
			}

			// Build the request body.
			const body = {
				message,
				session_id: sessionId,
				provider_id: select.getSelectedProviderId(),
				model_id: select.getSelectedModelId(),
			};

			// Include structured page context if available.
			const pageContext = select.getPageContext();
			if ( pageContext ) {
				// Normalise to object — screen-meta may set a string.
				body.page_context =
					typeof pageContext === 'string'
						? { summary: pageContext }
						: pageContext;
			}

			// Include selected agent if set.
			const selectedAgentId = select.getSelectedAgentId();
			if ( selectedAgentId ) {
				body.agent_id = selectedAgentId;
			}

			dispatch.setSendTimestamp( Date.now() );

			try {
				const result = await apiFetch( {
					path: '/gratis-ai-agent/v1/run',
					method: 'POST',
					data: body,
				} );

				if ( ! result.job_id ) {
					throw new Error( 'No job_id returned' );
				}

				dispatch.setCurrentJobId( result.job_id );
				dispatch.pollJob( result.job_id );
			} catch ( err ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `Error: ${
								err.message || 'Failed to start job'
							}`,
						},
					],
				} );
				dispatch.setSending( false );
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

	// ─── Settings thunks ─────────────────────────────────────────

	/**
	 * Fetch plugin settings from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSettings() {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
				} );
				dispatch.setSettings( settings );
			} catch {
				dispatch.setSettings( {} );
			}
		};
	},

	/**
	 * Save plugin settings via the REST API.
	 *
	 * @param {Partial<Settings>} data - Settings fields to update.
	 * @return {Function} Redux thunk that resolves with the saved settings.
	 */
	saveSettings( data ) {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
					method: 'POST',
					data,
				} );
				dispatch.setSettings( settings );
				return settings;
			} catch ( err ) {
				throw err;
			}
		};
	},

	// ─── Memory thunks ───────────────────────────────────────────

	/**
	 * Fetch all memory entries from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchMemories() {
		return async ( { dispatch } ) => {
			try {
				const memories = await apiFetch( {
					path: '/gratis-ai-agent/v1/memory',
				} );
				dispatch.setMemories( memories );
			} catch {
				dispatch.setMemories( [] );
			}
		};
	},

	/**
	 * Create a new memory entry.
	 *
	 * @param {string} category - Memory category (e.g. 'general').
	 * @param {string} content  - Memory content text.
	 * @return {Function} Redux thunk.
	 */
	createMemory( category, content ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/memory',
				method: 'POST',
				data: { category, content },
			} );
			dispatch.fetchMemories();
		};
	},

	/**
	 * Update an existing memory entry.
	 *
	 * @param {number}          id   - Memory identifier.
	 * @param {Partial<Memory>} data - Fields to update.
	 * @return {Function} Redux thunk.
	 */
	updateMemory( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/memory/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchMemories();
		};
	},

	/**
	 * Delete a memory entry.
	 *
	 * @param {number} id - Memory identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteMemory( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/memory/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchMemories();
		};
	},

	// ─── Skills thunks ──────────────────────────────────────────

	/**
	 * Fetch all skill entries from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSkills() {
		return async ( { dispatch } ) => {
			try {
				const skills = await apiFetch( {
					path: '/gratis-ai-agent/v1/skills',
				} );
				dispatch.setSkills( skills );
			} catch {
				dispatch.setSkills( [] );
			}
		};
	},

	/**
	 * Create a new skill.
	 *
	 * @param {Partial<Skill>} data - Skill fields.
	 * @return {Function} Redux thunk.
	 */
	createSkill( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/skills',
				method: 'POST',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Update an existing skill.
	 *
	 * @param {number}         id   - Skill identifier.
	 * @param {Partial<Skill>} data - Fields to update.
	 * @return {Function} Redux thunk.
	 */
	updateSkill( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Delete a skill.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Reset a skill to its built-in defaults.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	resetSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }/reset`,
				method: 'POST',
			} );
			dispatch.fetchSkills();
		};
	},

	// ─── Conversation Templates thunks ───────────────────────────

	fetchConversationTemplates( category = null ) {
		return async ( { dispatch } ) => {
			try {
				const path = category
					? `/gratis-ai-agent/v1/conversation-templates?category=${ encodeURIComponent(
							category
					  ) }`
					: '/gratis-ai-agent/v1/conversation-templates';
				const templates = await apiFetch( { path } );
				dispatch.setConversationTemplates( templates );
			} catch {
				dispatch.setConversationTemplates( [] );
			}
		};
	},

	createConversationTemplate( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/conversation-templates',
				method: 'POST',
				data,
			} );
			dispatch.fetchConversationTemplates();
		};
	},

	updateConversationTemplate( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/conversation-templates/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchConversationTemplates();
		};
	},

	deleteConversationTemplate( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/conversation-templates/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchConversationTemplates();
		};
	},

	// ─── Agents thunks ──────────────────────────────────────────

	fetchAgents() {
		return async ( { dispatch } ) => {
			try {
				const agents = await apiFetch( {
					path: '/gratis-ai-agent/v1/agents',
				} );
				dispatch.setAgents( agents );
			} catch {
				dispatch.setAgents( [] );
			}
		};
	},

	createAgent( data ) {
		return async ( { dispatch } ) => {
			const agent = await apiFetch( {
				path: '/gratis-ai-agent/v1/agents',
				method: 'POST',
				data,
			} );
			dispatch.fetchAgents();
			return agent;
		};
	},

	updateAgent( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/agents/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchAgents();
		};
	},

	deleteAgent( id ) {
		return async ( { dispatch, select } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/agents/${ id }`,
				method: 'DELETE',
			} );
			// Clear selection if the deleted agent was selected.
			if ( select.getSelectedAgentId() === id ) {
				dispatch.setSelectedAgentId( null );
			}
			dispatch.fetchAgents();
		};
	},

	// ─── Compact thunk ───────────────────────────────────────────

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

	// ─── Shared Sessions thunks ──────────────────────────────────

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

const selectors = {
	/**
	 * @param {StoreState} state
	 * @return {Provider[]} Available AI providers.
	 */
	getProviders( state ) {
		return state.providers;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether providers have been fetched.
	 */
	getProvidersLoaded( state ) {
		return state.providersLoaded;
	},

	/**
	 * @param {StoreState} state
	 * @return {Session[]} Session list.
	 */
	getSessions( state ) {
		return state.sessions;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether sessions have been fetched.
	 */
	getSessionsLoaded( state ) {
		return state.sessionsLoaded;
	},

	/**
	 * @param {StoreState} state
	 * @return {number|null} Active session ID, or null.
	 */
	getCurrentSessionId( state ) {
		return state.currentSessionId;
	},

	/**
	 * @param {StoreState} state
	 * @return {Message[]} Messages in the active session.
	 */
	getCurrentSessionMessages( state ) {
		return state.currentSessionMessages;
	},

	/**
	 * @param {StoreState} state
	 * @return {ToolCall[]} Tool calls in the active session.
	 */
	getCurrentSessionToolCalls( state ) {
		return state.currentSessionToolCalls;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether a message is in-flight.
	 */
	isSending( state ) {
		return state.sending;
	},

	/**
	 * @param {StoreState} state
	 * @return {string|null} Active polling job ID, or null.
	 */
	getCurrentJobId( state ) {
		return state.currentJobId;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Currently selected provider ID.
	 */
	getSelectedProviderId( state ) {
		return state.selectedProviderId;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Currently selected model ID.
	 */
	getSelectedModelId( state ) {
		return state.selectedModelId;
	},

	/**
	 * @param {StoreState} state
	 * @return {import('../types').ProviderModel[]} Models for the selected provider.
	 */
	getSelectedProviderModels( state ) {
		const provider = state.providers.find(
			( p ) => p.id === state.selectedProviderId
		);
		return provider?.models || [];
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the floating panel is open.
	 */
	isFloatingOpen( state ) {
		return state.floatingOpen;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the floating panel is minimized.
	 */
	isFloatingMinimized( state ) {
		return state.floatingMinimized;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether site builder mode is active.
	 */
	isSiteBuilderMode( state ) {
		return state.siteBuilderMode;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the current site is a fresh WordPress install.
	 */
	isFreshInstall( state ) {
		return state.isFreshInstall;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} Current step in the site builder progress indicator.
	 */
	getSiteBuilderStep( state ) {
		return state.siteBuilderStep ?? 0;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} Total steps in the site builder progress indicator.
	 */
	getSiteBuilderTotalSteps( state ) {
		return state.siteBuilderTotalSteps ?? 0;
	},

	/**
	 * @param {StoreState} state
	 * @return {string|Object} Structured page context for the AI.
	 */
	getPageContext( state ) {
		return state.pageContext;
	},

	// Settings

	/**
	 * @param {StoreState} state
	 * @return {Settings|null} Plugin settings, or null if not yet loaded.
	 */
	getSettings( state ) {
		return state.settings;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether settings have been fetched.
	 */
	getSettingsLoaded( state ) {
		return state.settingsLoaded;
	},

	// Memory

	/**
	 * @param {StoreState} state
	 * @return {Memory[]} Memory entries.
	 */
	getMemories( state ) {
		return state.memories;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether memories have been fetched.
	 */
	getMemoriesLoaded( state ) {
		return state.memoriesLoaded;
	},

	// Skills

	/**
	 * @param {StoreState} state
	 * @return {Skill[]} Skill entries.
	 */
	getSkills( state ) {
		return state.skills;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether skills have been fetched.
	 */
	getSkillsLoaded( state ) {
		return state.skillsLoaded;
	},

	// Conversation templates
	getConversationTemplates( state ) {
		return state.conversationTemplates;
	},
	getConversationTemplatesLoaded( state ) {
		return state.conversationTemplatesLoaded;
	},

	// Agents
	getAgents( state ) {
		return state.agents;
	},
	getAgentsLoaded( state ) {
		return state.agentsLoaded;
	},
	getSelectedAgentId( state ) {
		return state.selectedAgentId;
	},
	getSelectedAgent( state ) {
		if ( ! state.selectedAgentId ) {
			return null;
		}
		return (
			state.agents.find( ( a ) => a.id === state.selectedAgentId ) || null
		);
	},

	// Session filters

	/**
	 * @param {StoreState} state
	 * @return {string} Active session filter tab ('active', 'archived', 'trash').
	 */
	getSessionFilter( state ) {
		return state.sessionFilter;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Active folder filter, or '' for all.
	 */
	getSessionFolder( state ) {
		return state.sessionFolder;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Active search query.
	 */
	getSessionSearch( state ) {
		return state.sessionSearch;
	},

	/**
	 * @param {StoreState} state
	 * @return {string[]} Available folder names.
	 */
	getFolders( state ) {
		return state.folders;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether folders have been fetched.
	 */
	getFoldersLoaded( state ) {
		return state.foldersLoaded;
	},

	// Pending confirmation

	/**
	 * @param {StoreState} state
	 * @return {PendingConfirmation|null} Pending tool confirmation, or null.
	 */
	getPendingConfirmation( state ) {
		return state.pendingConfirmation;
	},

	// Pending action card (inline confirmation in message list, t074)
	getPendingActionCard( state ) {
		return state.pendingActionCard;
	},

	// YOLO mode (skip all confirmations)
	isYoloMode( state ) {
		return state.settings?.yolo_mode ?? false;
	},

	// Debug mode

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether debug mode is active.
	 */
	isDebugMode( state ) {
		return state.debugMode;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} Timestamp of the last send in ms since epoch.
	 */
	getSendTimestamp( state ) {
		return state.sendTimestamp;
	},

	// Token usage

	/**
	 * @param {StoreState} state
	 * @return {TokenUsage} Cumulative token usage for the current session.
	 */
	getTokenUsage( state ) {
		return state.tokenUsage;
	},

	// Live token counter (t111)

	/**
	 * @param {StoreState} state
	 * @return {number} Accumulated session token count (prompt + completion).
	 */
	getSessionTokens( state ) {
		return state.sessionTokens;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} Accumulated session cost estimate in USD.
	 */
	getSessionCost( state ) {
		return state.sessionCost;
	},

	/**
	 * @param {StoreState} state
	 * @return {Array} Per-message token data array.
	 */
	getMessageTokens( state ) {
		return state.messageTokens;
	},

	// Streaming

	/**
	 * @param {StoreState} state
	 * @return {string} Accumulated streaming text buffer.
	 */
	getStreamingText( state ) {
		return state.streamingText;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether an SSE stream is currently active.
	 */
	isStreamingActive( state ) {
		return state.isStreaming;
	},

	/**
	 * @param {StoreState} state
	 * @return {AbortController|null} Controller for the active stream, or null.
	 */
	getStreamAbortController( state ) {
		return state.streamAbortController || null;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the last stream attempt failed with an error.
	 */
	hasStreamError( state ) {
		return state.streamError;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} The last user message text (for retry).
	 */
	getLastUserMessage( state ) {
		return state.lastUserMessage;
	},

	getAlertCount( state ) {
		return state.alertCount;
	},

	/**
	 * Calculate the context window usage as a percentage (0–100+).
	 *
	 * @param {StoreState} state
	 * @return {number} Percentage of context window consumed by prompt tokens.
	 */
	getContextPercentage( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100;
	},

	/**
	 * Whether the context window usage exceeds the 80% warning threshold.
	 *
	 * @param {StoreState} state
	 * @return {boolean} True when context usage is above 80%.
	 */
	isContextWarning( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100 > 80;
	},

	// Text-to-speech (t084)

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether text-to-speech is enabled.
	 */
	isTtsEnabled( state ) {
		return state.ttsEnabled;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Selected TTS voice URI (empty = browser default).
	 */
	getTtsVoiceURI( state ) {
		return state.ttsVoiceURI;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} TTS speech rate.
	 */
	getTtsRate( state ) {
		return state.ttsRate;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} TTS speech pitch.
	 */
	getTtsPitch( state ) {
		return state.ttsPitch;
	},

	/**
	 * @param {StoreState} state
	 * @return {Session[]} Sessions shared with all admins.
	 */
	getSharedSessions( state ) {
		return state.sharedSessions;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether shared sessions have been fetched.
	 */
	getSharedSessionsLoaded( state ) {
		return state.sharedSessionsLoaded;
	},
};

/**
 * Redux reducer for the Gratis AI Agent store.
 *
 * @param {StoreState} state  - Current state (defaults to DEFAULT_STATE).
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_PROVIDERS':
			return {
				...state,
				providers: action.providers,
				providersLoaded: true,
			};
		case 'SET_SESSIONS':
			return {
				...state,
				sessions: action.sessions,
				sessionsLoaded: true,
			};
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
		case 'SET_SELECTED_PROVIDER':
			return { ...state, selectedProviderId: action.providerId };
		case 'SET_SELECTED_MODEL':
			return { ...state, selectedModelId: action.modelId };
		case 'SET_FLOATING_OPEN':
			return { ...state, floatingOpen: action.open };
		case 'SET_FLOATING_MINIMIZED':
			return { ...state, floatingMinimized: action.minimized };
		case 'SET_SITE_BUILDER_MODE':
			return { ...state, siteBuilderMode: action.enabled };
		case 'SET_PAGE_CONTEXT':
			return { ...state, pageContext: action.context };
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
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings,
				settingsLoaded: true,
			};
		case 'SET_MEMORIES':
			return {
				...state,
				memories: action.memories,
				memoriesLoaded: true,
			};
		case 'SET_SKILLS':
			return {
				...state,
				skills: action.skills,
				skillsLoaded: true,
			};
		case 'SET_CONVERSATION_TEMPLATES':
			return {
				...state,
				conversationTemplates: action.templates,
				conversationTemplatesLoaded: true,
			};
		case 'SET_AGENTS':
			return {
				...state,
				agents: action.agents,
				agentsLoaded: true,
			};
		case 'SET_SELECTED_AGENT_ID':
			return { ...state, selectedAgentId: action.agentId };
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
		case 'SET_SESSION_FILTER':
			return { ...state, sessionFilter: action.filter };
		case 'SET_SESSION_FOLDER':
			return { ...state, sessionFolder: action.folder };
		case 'SET_SESSION_SEARCH':
			return { ...state, sessionSearch: action.search };
		case 'SET_FOLDERS':
			return { ...state, folders: action.folders, foldersLoaded: true };
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
		case 'SET_DEBUG_MODE':
			return { ...state, debugMode: action.enabled };
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
		case 'SET_ALERT_COUNT':
			return { ...state, alertCount: action.count };
		case 'SET_SITE_BUILDER_STEP':
			return { ...state, siteBuilderStep: action.step };
		case 'SET_SITE_BUILDER_TOTAL_STEPS':
			return { ...state, siteBuilderTotalSteps: action.total };
		case 'SET_TTS_ENABLED':
			return { ...state, ttsEnabled: action.enabled };
		case 'SET_TTS_VOICE_URI':
			return { ...state, ttsVoiceURI: action.voiceURI };
		case 'SET_TTS_RATE':
			return { ...state, ttsRate: action.rate };
		case 'SET_TTS_PITCH':
			return { ...state, ttsPitch: action.pitch };
		case 'SET_SHARED_SESSIONS':
			return {
				...state,
				sharedSessions: action.sessions,
				sharedSessionsLoaded: true,
			};
		case 'UPDATE_SESSION_TITLE':
			return {
				...state,
				sessions: state.sessions.map( ( s ) =>
					parseInt( s.id, 10 ) === action.sessionId
						? { ...s, title: action.title }
						: s
				),
			};
		default:
			return state;
	}
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

// Guard against double-registration: both floating-widget.js and
// screen-meta.js import this module. The first bundle to load registers
// the store; subsequent bundles on the same page skip registration so
// the existing store instance (and its state) is preserved.
if ( ! wpSelect( STORE_NAME ) ) {
	register( store );
}

export default STORE_NAME;
