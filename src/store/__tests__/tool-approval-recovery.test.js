/** Regression coverage using the real job poller after ambiguous approvals. */
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '../../abilities/registry', () => ( {
	snapshotDescriptors: jest.fn(),
} ) );
jest.mock( '../../abilities', () => ( { ensureRegistered: jest.fn() } ) );
jest.mock( '../../utils/notification-manager', () => ( {
	clearNotification: jest.fn(),
	notifyConfirmationNeeded: jest.fn(),
} ) );
jest.mock( '../../utils/visibility-manager', () => ( {
	onVisibilityChange: jest.fn( () => jest.fn() ),
} ) );
jest.mock( '../../utils/sound-manager', () => ( {
	playDing: jest.fn(),
	playDong: jest.fn(),
	playThinking: jest.fn(),
} ) );

const apiFetch = require( '@wordpress/api-fetch' );
const { actions } = require( '../slices/sessionsSlice' );
const { actions: jobActions } = require( '../slices/jobSlice' );
const {
	setActiveJob,
	getActiveJobs,
} = require( '../../utils/active-jobs-storage' );
const { onVisibilityChange } = require( '../../utils/visibility-manager' );

describe( 'Tool approval recovery', () => {
	const select = {
		getCurrentSessionId: jest.fn(),
		getCurrentJobId: jest.fn(),
		getSessionJob: jest.fn(),
		getSessionJobs: jest.fn(),
	};
	const makeDispatch = () => {
		const dispatch = {
			setPendingConfirmation: jest.fn(),
			setPendingActionCard: jest.fn(),
			setPendingProposal: jest.fn(),
			appendMessage: jest.fn(),
			setSending: jest.fn(),
			setCurrentJobId: jest.fn(),
			setSessionJob: jest.fn(),
			setCurrentSession: jest.fn(),
			setLiveToolCalls: jest.fn(),
			setStreamError: jest.fn(),
			setFeedbackBanner: jest.fn(),
			fetchSessions: jest.fn(),
			drainMessageQueue: jest.fn(),
			resetSessionTokens: jest.fn(),
			addOpenTab: jest.fn(),
		};
		dispatch.pollJob = jest.fn( ( jobId, sessionId ) =>
			jobActions.pollJob( jobId, sessionId )( { dispatch, select } )
		);
		return dispatch;
	};
	const approvalError = {
		code: 'sd_ai_agent_invalid_job',
		message: 'Job not found or not awaiting confirmation.',
		data: { status: 404 },
	};

	beforeEach( () => {
		jest.useFakeTimers();
		apiFetch.mockReset();
		sessionStorage.clear();
		select.getCurrentSessionId.mockReset().mockReturnValue( 12 );
		select.getCurrentJobId.mockReset().mockReturnValue( 'job-1' );
		select.getSessionJob.mockReset().mockReturnValue( { jobId: 'job-1' } );
		select.getSessionJobs.mockReset().mockReturnValue( {
			12: { jobId: 'job-1' },
		} );
	} );

	afterEach( () => {
		jest.clearAllTimers();
		jest.useRealTimers();
	} );

	it.each( [ false, true ] )(
		'keeps a saved terminal explanation without duplicating it (reload fails: %s)',
		async ( reloadFails ) => {
			apiFetch.mockResolvedValueOnce( {
				status: 'error',
				session_id: 12,
				message: 'The pending approval expired. Start a continuation.',
				diagnostic: {
					reason: 'approval_expired',
					next_action: 'continuation',
					correlation_id: 'job-123456abcdef',
				},
				failure_message_persisted: true,
			} );
			if ( reloadFails ) {
				apiFetch.mockRejectedValueOnce(
					new Error( 'Reload unavailable' )
				);
			} else {
				apiFetch.mockResolvedValueOnce( {
					id: 12,
					messages: [
						{ role: 'model', parts: [ { text: 'Saved stop' } ] },
					],
					tool_calls: [],
				} );
			}
			const dispatch = makeDispatch();
			await dispatch.pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( dispatch.appendMessage ).toHaveBeenCalledTimes(
				reloadFails ? 1 : 0
			);
			expect( dispatch.setPendingActionCard ).toHaveBeenCalledWith(
				expect.objectContaining( {
					type: 'active_job_failure',
					sessionId: 12,
				} )
			);
		}
	);

	it.each( [
		'processing',
		'awaiting_confirmation',
		'pending_proposal',
		'awaiting_client_tools',
		'complete',
		'error',
	] )(
		'preserves a newer session job when an older %s response arrives',
		async ( status ) => {
			const unsubscribe = jest.fn();
			onVisibilityChange.mockReturnValueOnce( unsubscribe );
			let resolvePoll;
			apiFetch.mockImplementationOnce(
				() => new Promise( ( resolve ) => ( resolvePoll = resolve ) )
			);
			const dispatch = makeDispatch();
			await dispatch.pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
			setActiveJob( 12, 'newer-job' );
			resolvePoll( { status, tool_calls: [] } );
			await jest.advanceTimersByTimeAsync( 20000 );
			expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
			expect( dispatch.setLiveToolCalls ).not.toHaveBeenCalled();
			expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
			expect( unsubscribe ).toHaveBeenCalledTimes( 1 );
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			expect( dispatch.setPendingConfirmation ).not.toHaveBeenCalled();
			expect( dispatch.setPendingActionCard ).not.toHaveBeenCalled();
			expect( dispatch.setSending ).not.toHaveBeenCalled();
		}
	);

	it.each( [ 404, 403, 500 ] )(
		'ignores an older HTTP %s failure after the session owner changes',
		async ( status ) => {
			let rejectPoll;
			apiFetch.mockImplementationOnce(
				() =>
					new Promise(
						( resolve, reject ) => ( rejectPoll = reject )
					)
			);
			const dispatch = makeDispatch();
			await dispatch.pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
			setActiveJob( 12, 'newer-job' );
			rejectPoll( { data: { status } } );
			await jest.advanceTimersByTimeAsync( 20000 );
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
			expect( dispatch.setCurrentSession ).not.toHaveBeenCalled();
			expect( dispatch.setSending ).not.toHaveBeenCalled();
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
			expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
		}
	);

	it.each(
		[ 'complete', 'error', 'missing', 'rejected' ].flatMap( ( status ) =>
			[ false, true ].map( ( reloadFails ) => [ status, reloadFails ] )
		)
	)(
		'preserves replacement state during %s session reload (failure: %s)',
		async ( status, reloadFails ) => {
			if ( status === 'missing' || status === 'rejected' ) {
				apiFetch.mockRejectedValueOnce( {
					data: { status: status === 'missing' ? 404 : 403 },
				} );
			} else {
				apiFetch.mockResolvedValueOnce( { status, session_id: 12 } );
			}
			apiFetch.mockImplementationOnce( async () => {
				select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
				setActiveJob( 12, 'newer-job' );
				if ( reloadFails ) {
					throw new Error( 'Reload unavailable' );
				}
				return { id: 12, messages: [], tool_calls: [] };
			} );
			const dispatch = makeDispatch();
			await dispatch.pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			expect( dispatch.setCurrentSession ).not.toHaveBeenCalled();
			expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
			expect( dispatch.setSending ).not.toHaveBeenCalled();
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
			expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
		}
	);

	it.each( [ 'restoreActiveJobs', 'openSession' ] )(
		'%s registers the discovered owner before its first poll',
		async ( action ) => {
			const discovered = {
				job_id: 'discovered-job',
				session_id: 12,
				status: 'processing',
			};
			if ( action === 'openSession' ) {
				apiFetch.mockResolvedValueOnce( { id: 12, messages: [] } );
			}
			apiFetch
				.mockResolvedValueOnce(
					action === 'openSession' ? discovered : [ discovered ]
				)
				.mockResolvedValueOnce( {
					status: 'processing',
					tool_calls: [],
				} );
			const dispatch = makeDispatch();
			dispatch.setSessionJob.mockImplementation( ( id, job ) =>
				select.getSessionJob.mockReturnValue( job )
			);
			await actions[ action ]( 12 )( { dispatch, select } );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, {
				jobId: 'discovered-job',
				toolCalls: [],
				status: 'processing',
			} );
			expect(
				dispatch.setSessionJob.mock.invocationCallOrder[ 0 ]
			).toBeLessThan( dispatch.pollJob.mock.invocationCallOrder[ 0 ] );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( apiFetch ).toHaveBeenLastCalledWith( {
				path: '/sd-ai-agent/v1/job/discovered-job',
			} );
			expect( getActiveJobs() ).toEqual( { 12: 'discovered-job' } );
		}
	);

	it.each( [ 'restoreActiveJobs', 'openSession' ] )(
		'%s does not replace a job started during discovery',
		async ( action ) => {
			if ( action === 'openSession' ) {
				apiFetch.mockResolvedValueOnce( { id: 12, messages: [] } );
			}
			apiFetch.mockImplementationOnce( async () => {
				select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
				setActiveJob( 12, 'newer-job' );
				const discovered = { job_id: 'old-discovery', session_id: 12 };
				return action === 'openSession' ? discovered : [ discovered ];
			} );
			const dispatch = makeDispatch();
			await actions[ action ]( 12 )( { dispatch, select } );
			expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
			expect( dispatch.pollJob ).not.toHaveBeenCalled();
			expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
		}
	);

	it( 'stops when session ownership changes during the polling delay', async () => {
		apiFetch.mockResolvedValue( { status: 'processing', tool_calls: [] } );
		await makeDispatch().pollJob( 'job-1', 12 );
		await jest.advanceTimersByTimeAsync( 2000 );
		select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
		setActiveJob( 12, 'newer-job' );
		await jest.advanceTimersByTimeAsync( 20000 );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
	} );

	it( 'keeps polling its session when another session has the current job', async () => {
		select.getCurrentSessionId.mockReturnValue( 99 );
		select.getCurrentJobId.mockReturnValue( 'other-session-job' );
		apiFetch
			.mockResolvedValueOnce( { status: 'processing', tool_calls: [] } )
			.mockResolvedValueOnce( { status: 'awaiting_confirmation' } );
		await makeDispatch().pollJob( 'job-1', 12 );
		await jest.advanceTimersByTimeAsync( 3000 );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it.each( [ null, { jobId: 'job-1' } ] )(
		'clears persisted polling state when the tracked session job is %s',
		async ( sessionJob ) => {
			select.getSessionJob.mockReturnValue( sessionJob );
			setActiveJob( 12, 'job-1' );
			apiFetch.mockResolvedValueOnce( {
				status: 'awaiting_confirmation',
				pending_tools: [],
			} );
			await makeDispatch().pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( getActiveJobs() ).toEqual( {} );
		}
	);

	it.each( [
		new Error( 'Lost response' ),
		approvalError,
		{ message: 'Gateway refused the response', data: { status: 403 } },
		{ message: 'Gateway timeout', data: { status: 503 } },
	] )(
		'recovers processing status before new activity arrives: %s',
		async ( error ) => {
			apiFetch.mockRejectedValueOnce( error ).mockResolvedValue( {
				status: 'processing',
				tool_calls: [],
			} );
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			expect( apiFetch ).toHaveBeenLastCalledWith( {
				path: '/sd-ai-agent/v1/job/job-1',
			} );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, {
				jobId: 'job-1',
				status: 'processing',
				toolCalls: [],
			} );
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
		}
	);

	it( 'restores only fresh pending tools without submitting another approval', async () => {
		const tools = [ { name: 'new-tool-batch' } ];
		apiFetch.mockRejectedValueOnce( approvalError ).mockResolvedValue( {
			status: 'awaiting_confirmation',
			pending_tools: tools,
		} );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1', true )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( dispatch.setPendingConfirmation ).toHaveBeenLastCalledWith( {
			jobId: 'job-1',
			tools,
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/sd-ai-agent/v1/job/job-1',
		} );
	} );

	it.each( [ 'complete', 'missing' ] )(
		'reloads saved work for a %s job rather than displaying the approval error',
		async ( status ) => {
			apiFetch.mockRejectedValueOnce( approvalError );
			if ( status === 'complete' ) {
				apiFetch.mockResolvedValueOnce( { status, session_id: 12 } );
			} else {
				apiFetch.mockRejectedValueOnce( {
					code: 'sd_ai_agent_job_not_found',
					data: { status: 404 },
				} );
			}
			const messages = [ { role: 'model', parts: [ { text: 'Done' } ] } ];
			apiFetch.mockResolvedValueOnce( {
				id: 12,
				messages,
				tool_calls: [],
			} );
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( dispatch.setCurrentSession ).toHaveBeenCalledWith(
				12,
				messages,
				[]
			);
			expect( dispatch.setSessionJob ).toHaveBeenLastCalledWith(
				12,
				null
			);
			expect( dispatch.setSending ).toHaveBeenLastCalledWith( false );
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
		}
	);

	it.each( [ 400, 401, 403 ] )(
		'stops after a definitively rejected HTTP %s status read',
		async ( status ) => {
			apiFetch.mockRejectedValue( {
				message: 'Status request refused.',
				data: { status },
			} );
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			await jest.advanceTimersByTimeAsync( 10000 );
			expect( apiFetch ).toHaveBeenCalledTimes( 3 );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, null );
			expect( dispatch.setSending ).toHaveBeenCalledWith( false );
			expect( dispatch.appendMessage ).toHaveBeenCalledWith( {
				role: 'system',
				parts: [ { text: 'Error: Status request refused.' } ],
			} );
		}
	);

	it( 'does not clear another session while recovering completed work', async () => {
		apiFetch
			.mockImplementationOnce( async () => {
				select.getCurrentSessionId.mockReturnValue( 99 );
				throw approvalError;
			} )
			.mockResolvedValueOnce( { status: 'complete', session_id: 12 } );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( dispatch.setCurrentJobId ).not.toHaveBeenCalled();
		expect( dispatch.setSending ).not.toHaveBeenCalled();
		expect( dispatch.appendMessage ).not.toHaveBeenCalled();
		expect( dispatch.setSessionJob ).toHaveBeenLastCalledWith( 12, null );
	} );

	it( 'does not poll an old job after a newer job starts during approval', async () => {
		apiFetch.mockImplementationOnce( async () => {
			select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
			throw approvalError;
		} );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
		expect( dispatch.pollJob ).not.toHaveBeenCalled();
		expect( dispatch.appendMessage ).not.toHaveBeenCalled();
	} );

	it( 'recovers a lost denial response without sending approval or retrying denial', async () => {
		apiFetch.mockRejectedValueOnce( approvalError ).mockResolvedValueOnce( {
			status: 'awaiting_confirmation',
			pending_tools: [],
		} );
		const dispatch = makeDispatch();
		await actions.rejectToolCall( 'job-1' )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toEqual( {
			path: '/sd-ai-agent/v1/job/job-1/reject',
			method: 'POST',
			data: undefined,
		} );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/sd-ai-agent/v1/job/job-1',
		} );
	} );

	it( 'does not resume an abandoned poller after the replacement finishes', async () => {
		apiFetch.mockResolvedValueOnce( {
			status: 'complete',
			session_id: 12,
		} );
		apiFetch.mockImplementationOnce( async () => {
			select.getSessionJob
				.mockReturnValueOnce( { jobId: 'newer-job' } )
				.mockReturnValue( null );
			return { id: 12, messages: [] };
		} );
		const dispatch = makeDispatch();
		await dispatch.pollJob( 'job-1', 12 );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( dispatch.setCurrentSession ).not.toHaveBeenCalled();
		expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
		expect( dispatch.setSending ).not.toHaveBeenCalled();
	} );

	it( 'releases the old owner before draining a queued replacement', async () => {
		apiFetch.mockResolvedValueOnce( { status: 'complete' } );
		const dispatch = makeDispatch();
		dispatch.setSessionJob.mockImplementation( ( id, job ) =>
			select.getSessionJob.mockReturnValue( job )
		);
		dispatch.drainMessageQueue.mockImplementation( () => {
			dispatch.setSessionJob( 12, { jobId: 'queued-job' } );
			setActiveJob( 12, 'queued-job' );
		} );
		await dispatch.pollJob( 'job-1', 12 );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( select.getSessionJob() ).toEqual( { jobId: 'queued-job' } );
		expect( getActiveJobs() ).toEqual( { 12: 'queued-job' } );
	} );

	it( 'submits background client-tool results to their owning session', async () => {
		select.getCurrentSessionId.mockReturnValue( 99 );
		apiFetch
			.mockResolvedValueOnce( { status: 'awaiting_client_tools' } )
			.mockResolvedValueOnce( { status: 'processing' } );
		await makeDispatch().pollJob( 'job-1', 12 );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/sd-ai-agent/v1/chat/tool-result',
			method: 'POST',
			data: { session_id: 12, job_id: 'job-1', tool_results: [] },
		} );
	} );

	it.each( [ 'error', 'rejected' ] )(
		'does not render %s recovery into a session opened during reload',
		async ( status ) => {
			if ( status === 'rejected' ) {
				apiFetch.mockRejectedValueOnce( { data: { status: 403 } } );
			} else {
				apiFetch.mockResolvedValueOnce( { status, session_id: 12 } );
			}
			apiFetch.mockImplementationOnce( async () => {
				select.getCurrentSessionId.mockReturnValue( 99 );
				return { id: 12, messages: [], tool_calls: [] };
			} );
			const dispatch = makeDispatch();
			await dispatch.pollJob( 'job-1', 12 );
			await jest.advanceTimersByTimeAsync( 2000 );
			expect( dispatch.setCurrentSession ).not.toHaveBeenCalled();
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
			expect( dispatch.setSending ).not.toHaveBeenCalled();
			expect( dispatch.setCurrentJobId ).not.toHaveBeenCalled();
			expect( dispatch.setSessionJob ).toHaveBeenLastCalledWith(
				12,
				null
			);
		}
	);

	it( 'keeps bounded polling recovery for a connection that remains unavailable', async () => {
		apiFetch.mockRejectedValue( new Error( 'Offline' ) );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 10000 );
		expect( dispatch.setStreamError ).toHaveBeenCalledWith( true, 12 );
		expect( dispatch.setSessionJob ).toHaveBeenLastCalledWith( 12, null );
		expect( dispatch.setSending ).toHaveBeenLastCalledWith( false );
		expect(
			apiFetch.mock.calls.filter(
				( [ request ] ) => request.method === 'POST'
			)
		).toHaveLength( 1 );
	} );
} );
