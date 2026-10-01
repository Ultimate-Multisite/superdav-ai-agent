/** Regression coverage for ambiguous approval responses. */
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

describe( 'Tool approval recovery', () => {
	const makeDispatch = () => ( {
		setPendingConfirmation: jest.fn(),
		setPendingActionCard: jest.fn(),
		pollJob: jest.fn(),
		appendMessage: jest.fn(),
		setSending: jest.fn(),
		setCurrentJobId: jest.fn(),
		setSessionJob: jest.fn(),
		setCurrentSession: jest.fn(),
		setLiveToolCalls: jest.fn(),
		fetchSessions: jest.fn(),
		drainMessageQueue: jest.fn(),
	} );
	const select = {
		getCurrentSessionId: jest.fn(),
		getSessionJob: jest.fn(),
	};
	const approvalError = {
		code: 'sd_ai_agent_invalid_job',
		message: 'Job not found or not awaiting confirmation.',
		data: { status: 404 },
	};

	beforeEach( () => {
		apiFetch.mockReset();
		select.getCurrentSessionId.mockReset().mockReturnValue( 12 );
		select.getSessionJob.mockReset().mockReturnValue( { jobId: 'job-1' } );
	} );

	afterEach( () => jest.useRealTimers() );

	it( 'the real poller restores only the fresh pending tools after an approval error', async () => {
		jest.useFakeTimers();
		const tools = [ { name: 'new-tool-batch' } ];
		apiFetch.mockRejectedValueOnce( approvalError ).mockResolvedValue( {
			status: 'awaiting_confirmation',
			pending_tools: tools,
		} );
		const dispatch = makeDispatch();
		dispatch.pollJob.mockImplementation( ( jobId, sessionId ) =>
			jobActions.pollJob( jobId, sessionId )( { dispatch, select } )
		);
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( dispatch.setPendingConfirmation ).toHaveBeenLastCalledWith( {
			jobId: 'job-1',
			tools,
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/sd-ai-agent/v1/job/job-1',
		} );
	} );

	it( 'the real poller reloads completed work instead of showing the approval error', async () => {
		jest.useFakeTimers();
		const complete = { status: 'complete', session_id: 12 };
		const messages = [
			{ role: 'model', parts: [ { text: 'Work done' } ] },
		];
		apiFetch
			.mockRejectedValueOnce( approvalError )
			.mockResolvedValueOnce( complete )
			.mockResolvedValueOnce( complete )
			.mockResolvedValueOnce( { id: 12, messages, tool_calls: [] } );
		const dispatch = makeDispatch();
		dispatch.pollJob.mockImplementation( ( jobId, sessionId ) =>
			jobActions.pollJob( jobId, sessionId )( { dispatch, select } )
		);
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		await jest.advanceTimersByTimeAsync( 2000 );
		expect( dispatch.setCurrentSession ).toHaveBeenCalledWith(
			12,
			messages,
			[]
		);
		expect( dispatch.setSessionJob ).toHaveBeenLastCalledWith( 12, null );
		expect( dispatch.setSending ).toHaveBeenLastCalledWith( false );
		expect( dispatch.appendMessage ).not.toHaveBeenCalled();
	} );

	it( 'polls the job after a successful approval response', async () => {
		apiFetch.mockResolvedValue( { status: 'processing', job_id: 'job-1' } );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
		expect( dispatch.appendMessage ).not.toHaveBeenCalled();
	} );

	it.each( [ new Error( 'Lost response' ), approvalError ] )(
		'recovers processing work after an ambiguous approval error: %s',
		async ( error ) => {
			apiFetch.mockRejectedValueOnce( error ).mockResolvedValueOnce( {
				status: 'processing',
				tool_calls: [],
			} );
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			expect( apiFetch ).toHaveBeenLastCalledWith( {
				path: '/sd-ai-agent/v1/job/job-1',
			} );
			expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, {
				jobId: 'job-1',
				status: 'processing',
				toolCalls: [],
			} );
			expect( dispatch.setSending ).toHaveBeenCalledWith( true );
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
		}
	);

	it.each( [ 'complete', 'error' ] )(
		'clears stale approval status and lets the poller handle %s',
		async ( status ) => {
			apiFetch
				.mockRejectedValueOnce( approvalError )
				.mockResolvedValueOnce( { status } );
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, null );
			expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
			expect( dispatch.appendMessage ).not.toHaveBeenCalled();
		}
	);

	it.each( [
		'awaiting_confirmation',
		'awaiting_client_tools',
		'pending_proposal',
	] )(
		'restores %s through polling without automatically approving another batch',
		async ( status ) => {
			apiFetch
				.mockRejectedValueOnce( approvalError )
				.mockResolvedValueOnce( { status } );
			const dispatch = makeDispatch();
			await actions.confirmToolCall(
				'job-1',
				true
			)( { dispatch, select } );
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, {
				jobId: 'job-1',
				status,
				toolCalls: [],
			} );
			expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
			expect(
				apiFetch.mock.calls.filter(
					( [ request ] ) => request.method === 'POST'
				)
			).toHaveLength( 1 );
		}
	);

	it( 'reloads saved results through the missing-job polling path', async () => {
		apiFetch
			.mockRejectedValueOnce( approvalError )
			.mockRejectedValueOnce( { data: { status: 404 } } );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, null );
		expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
	} );

	it.each( [
		{ data: { status: 403 } },
		new Error( 'Offline' ),
		{ status: 'unknown' },
	] )(
		'surfaces the original error when status cannot be reconciled: %s',
		async ( result ) => {
			apiFetch.mockRejectedValueOnce( approvalError );
			if ( result.status ) {
				apiFetch.mockResolvedValueOnce( result );
			} else {
				apiFetch.mockRejectedValueOnce( result );
			}
			const dispatch = makeDispatch();
			await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
			expect( dispatch.pollJob ).not.toHaveBeenCalled();
			expect( dispatch.setSessionJob ).toHaveBeenCalledWith( 12, null );
			expect( dispatch.setSending ).toHaveBeenCalledWith( false );
			expect( dispatch.appendMessage ).toHaveBeenCalledWith( {
				role: 'system',
				parts: [ { text: `Error: ${ approvalError.message }` } ],
			} );
		}
	);

	it( 'does not change another session after switching during approval', async () => {
		apiFetch
			.mockImplementationOnce( async () => {
				select.getCurrentSessionId.mockReturnValue( 99 );
				throw new Error( 'Lost response' );
			} )
			.mockResolvedValueOnce( { status: 'processing' } );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		expect( dispatch.pollJob ).toHaveBeenCalledWith( 'job-1', 12 );
		expect( dispatch.setCurrentJobId ).not.toHaveBeenCalled();
		expect( dispatch.setSending ).not.toHaveBeenCalled();
	} );

	it( 'does not overwrite a newer job started during reconciliation', async () => {
		apiFetch
			.mockRejectedValueOnce( approvalError )
			.mockImplementationOnce( async () => {
				select.getSessionJob.mockReturnValue( { jobId: 'newer-job' } );
				return { status: 'processing' };
			} );
		const dispatch = makeDispatch();
		await actions.confirmToolCall( 'job-1' )( { dispatch, select } );
		expect( dispatch.setSessionJob ).not.toHaveBeenCalled();
		expect( dispatch.pollJob ).not.toHaveBeenCalled();
		expect( dispatch.appendMessage ).not.toHaveBeenCalled();
	} );
} );
