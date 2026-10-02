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
	} );

	afterEach( () => {
		jest.clearAllTimers();
		jest.useRealTimers();
	} );

	it( 'preserves a newer session job when an older processing response arrives', async () => {
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
		resolvePoll( { status: 'processing', tool_calls: [] } );
		await jest.advanceTimersByTimeAsync( 20000 );
		expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
		expect( dispatch.setLiveToolCalls ).not.toHaveBeenCalled();
		expect( getActiveJobs() ).toEqual( { 12: 'newer-job' } );
		expect( unsubscribe ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

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
