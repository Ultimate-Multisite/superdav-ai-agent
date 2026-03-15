/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

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

	// Token usage (current session)
	tokenUsage: { prompt: 0, completion: 0 },

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
};

const actions = {
	setProviders( providers ) {
		return { type: 'SET_PROVIDERS', providers };
	},
	setSessions( sessions ) {
		return { type: 'SET_SESSIONS', sessions };
	},
	setCurrentSession( sessionId, messages, toolCalls ) {
		return {
			type: 'SET_CURRENT_SESSION',
			sessionId,
			messages,
			toolCalls,
		};
	},
	clearCurrentSession() {
		return { type: 'CLEAR_CURRENT_SESSION' };
	},
	setSending( sending ) {
		return { type: 'SET_SENDING', sending };
	},
	setCurrentJobId( jobId ) {
		return { type: 'SET_CURRENT_JOB_ID', jobId };
	},
	setSelectedProvider( providerId ) {
		localStorage.setItem( 'gratisAiAgentProvider', providerId );
		return { type: 'SET_SELECTED_PROVIDER', providerId };
	},
	setSelectedModel( modelId ) {
		localStorage.setItem( 'gratisAiAgentModel', modelId );
		return { type: 'SET_SELECTED_MODEL', modelId };
	},
	setFloatingOpen( open ) {
		return { type: 'SET_FLOATING_OPEN', open };
	},
	setFloatingMinimized( minimized ) {
		return { type: 'SET_FLOATING_MINIMIZED', minimized };
	},
	setPageContext( context ) {
		return { type: 'SET_PAGE_CONTEXT', context };
	},
	appendMessage( message ) {
		return { type: 'APPEND_MESSAGE', message };
	},
	removeLastMessage() {
		return { type: 'REMOVE_LAST_MESSAGE' };
	},
	setSettings( settings ) {
		return { type: 'SET_SETTINGS', settings };
	},
	setMemories( memories ) {
		return { type: 'SET_MEMORIES', memories };
	},
	setSkills( skills ) {
		return { type: 'SET_SKILLS', skills };
	},
	setTokenUsage( tokenUsage ) {
		return { type: 'SET_TOKEN_USAGE', tokenUsage };
	},
	setSessionFilter( filter ) {
		return { type: 'SET_SESSION_FILTER', filter };
	},
	setSessionFolder( folder ) {
		return { type: 'SET_SESSION_FOLDER', folder };
	},
	setSessionSearch( search ) {
		return { type: 'SET_SESSION_SEARCH', search };
	},
	setFolders( folders ) {
		return { type: 'SET_FOLDERS', folders };
	},
	setPendingConfirmation( confirmation ) {
		return { type: 'SET_PENDING_CONFIRMATION', confirmation };
	},
	setPendingActionCard( card ) {
		return { type: 'SET_PENDING_ACTION_CARD', card };
	},
	truncateMessagesTo( index ) {
		return { type: 'TRUNCATE_MESSAGES_TO', index };
	},
	setDebugMode( enabled ) {
		localStorage.setItem(
			'gratisAiAgentDebugMode',
			enabled ? 'true' : 'false'
		);
		return { type: 'SET_DEBUG_MODE', enabled };
	},
	setSendTimestamp( ts ) {
		return { type: 'SET_SEND_TIMESTAMP', ts };
	},
	setStreamingText( text ) {
		return { type: 'SET_STREAMING_TEXT', text };
	},
	appendStreamingText( token ) {
		return { type: 'APPEND_STREAMING_TEXT', token };
	},
	setIsStreaming( streaming ) {
		return { type: 'SET_IS_STREAMING', streaming };
	},
	setStreamAbortController( controller ) {
		return { type: 'SET_STREAM_ABORT_CONTROLLER', controller };
	},

	// ─── Thunks ──────────────────────────────────────────────────

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
			} catch {
				// ignore
			}
		};
	},

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

	editAndResend( index, newText ) {
		return async ( { dispatch } ) => {
			dispatch.truncateMessagesTo( index );
			dispatch.sendMessage( newText );
		};
	},

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
	 * Send a message and stream the response token-by-token via SSE.
	 *
	 * Uses the Fetch API with a ReadableStream reader to consume the
	 * text/event-stream response from POST /ai-agent/v1/stream.
	 *
	 * @param {string} message The user message to send.
	 */
	streamMessage( message ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );
			dispatch.setIsStreaming( false );
			dispatch.setStreamingText( '' );

			// Append user message immediately.
			dispatch.appendMessage( {
				role: 'user',
				parts: [ { text: message } ],
			} );

			let sessionId = select.getCurrentSessionId();

			// Lazy-create session on first message.
			if ( ! sessionId ) {
				try {
					const session = await apiFetch( {
						path: '/ai-agent/v1/sessions',
						method: 'POST',
						data: {
							provider_id: select.getSelectedProviderId(),
							model_id: select.getSelectedModelId(),
						},
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
				body.page_context = pageContext;
			}

			dispatch.setSendTimestamp( Date.now() );

			// Build the URL with nonce for authentication.
			const streamUrl =
				( window.wpApiSettings?.root || '/wp-json/' ) +
				'gratis-ai-agent/v1/stream';

			const abortController = new AbortController();
			dispatch.setStreamAbortController( abortController );

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
				if ( err.name === 'AbortError' ) {
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
							text: `Error: ${
								err.message || 'Failed to connect to stream'
							}`,
						},
					],
				} );
				dispatch.setSending( false );
				dispatch.setStreamAbortController( null );
				return;
			}

			if ( ! response.ok ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `Error: HTTP ${ response.status } from stream endpoint`,
						},
					],
				} );
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
						break;
					}

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
								dispatch.setIsStreaming( false );
								dispatch.setStreamingText( '' );
								dispatch.appendMessage( {
									role: 'system',
									parts: [
										{
											text: `Error: ${
												payload.message ||
												'Unknown error'
											}`,
										},
									],
								} );
								dispatch.setSending( false );
								dispatch.setStreamAbortController( null );
								return;
						}
					}
				}
			} catch ( err ) {
				if ( err.name !== 'AbortError' ) {
					dispatch.appendMessage( {
						role: 'system',
						parts: [
							{
								text: `Error: ${
									err.message || 'Stream read error'
								}`,
							},
						],
					} );
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
			}

			dispatch.fetchSessions();
			dispatch.setSending( false );
		};
	},

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
					const session = await apiFetch( {
						path: '/gratis-ai-agent/v1/sessions',
						method: 'POST',
						data: {
							provider_id: select.getSelectedProviderId(),
							model_id: select.getSelectedModelId(),
						},
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
				body.page_context = pageContext;
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

	deleteSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchSkills();
		};
	},

	resetSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }/reset`,
				method: 'POST',
			} );
			dispatch.fetchSkills();
		};
	},

	// ─── Compact thunk ───────────────────────────────────────────

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
};

const selectors = {
	getProviders( state ) {
		return state.providers;
	},
	getProvidersLoaded( state ) {
		return state.providersLoaded;
	},
	getSessions( state ) {
		return state.sessions;
	},
	getSessionsLoaded( state ) {
		return state.sessionsLoaded;
	},
	getCurrentSessionId( state ) {
		return state.currentSessionId;
	},
	getCurrentSessionMessages( state ) {
		return state.currentSessionMessages;
	},
	getCurrentSessionToolCalls( state ) {
		return state.currentSessionToolCalls;
	},
	isSending( state ) {
		return state.sending;
	},
	getCurrentJobId( state ) {
		return state.currentJobId;
	},
	getSelectedProviderId( state ) {
		return state.selectedProviderId;
	},
	getSelectedModelId( state ) {
		return state.selectedModelId;
	},
	getSelectedProviderModels( state ) {
		const provider = state.providers.find(
			( p ) => p.id === state.selectedProviderId
		);
		return provider?.models || [];
	},
	isFloatingOpen( state ) {
		return state.floatingOpen;
	},
	isFloatingMinimized( state ) {
		return state.floatingMinimized;
	},
	getPageContext( state ) {
		return state.pageContext;
	},

	// Settings
	getSettings( state ) {
		return state.settings;
	},
	getSettingsLoaded( state ) {
		return state.settingsLoaded;
	},

	// Memory
	getMemories( state ) {
		return state.memories;
	},
	getMemoriesLoaded( state ) {
		return state.memoriesLoaded;
	},

	// Skills
	getSkills( state ) {
		return state.skills;
	},
	getSkillsLoaded( state ) {
		return state.skillsLoaded;
	},

	// Session filters
	getSessionFilter( state ) {
		return state.sessionFilter;
	},
	getSessionFolder( state ) {
		return state.sessionFolder;
	},
	getSessionSearch( state ) {
		return state.sessionSearch;
	},
	getFolders( state ) {
		return state.folders;
	},
	getFoldersLoaded( state ) {
		return state.foldersLoaded;
	},

	// Pending confirmation
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
	isDebugMode( state ) {
		return state.debugMode;
	},
	getSendTimestamp( state ) {
		return state.sendTimestamp;
	},

	// Token usage
	getTokenUsage( state ) {
		return state.tokenUsage;
	},

	// Streaming
	getStreamingText( state ) {
		return state.streamingText;
	},
	isStreamingActive( state ) {
		return state.isStreaming;
	},
	getStreamAbortController( state ) {
		return state.streamAbortController || null;
	},

	getContextPercentage( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100;
	},
	isContextWarning( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100 > 80;
	},
};

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
		case 'SET_TOKEN_USAGE':
			return { ...state, tokenUsage: action.tokenUsage };
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
		default:
			return state;
	}
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export default STORE_NAME;
