jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '../../abilities/registry', () => ( {
	snapshotDescriptors: jest.fn(),
} ) );
jest.mock( '../../abilities', () => ( { ensureRegistered: jest.fn() } ) );

const apiFetch = require( '@wordpress/api-fetch' );
const pollSessionTitle = require( '../session-title-poller' ).default;

describe( 'Independent session title delivery', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		apiFetch.mockReset();
	} );
	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'fills the title while chat runs and keeps watching if the answer finishes first', async () => {
		apiFetch
			.mockResolvedValueOnce( { title: 'Review plugins', pending: true } )
			.mockResolvedValueOnce( {
				title: '🔌 Plugin Review',
				pending: false,
			} );
		let title = 'Review plugins';
		const dispatch = {
			updateSessionTitle: jest.fn( ( sessionId, nextTitle ) => {
				title = nextTitle;
			} ),
			setSending: jest.fn(),
		};
		const select = { getSessions: () => [ { id: 12, title } ] };
		const watching = pollSessionTitle( 12, { dispatch, select } );
		await Promise.resolve();
		expect( dispatch.updateSessionTitle ).toHaveBeenCalledWith(
			12,
			'Review plugins'
		);
		dispatch.setSending( false );
		jest.advanceTimersByTime( 2000 );
		await watching;
		expect( dispatch.updateSessionTitle ).toHaveBeenLastCalledWith(
			12,
			'🔌 Plugin Review'
		);
		expect( dispatch.setSending ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'failed title polling leaves main job state alone', async () => {
		apiFetch.mockRejectedValueOnce( { code: 'rest_forbidden' } );
		const dispatch = {
			updateSessionTitle: jest.fn(),
			setSending: jest.fn(),
			setStreamError: jest.fn(),
		};
		const select = { getSessions: () => [] };
		await pollSessionTitle( 13, { dispatch, select } );
		expect( dispatch.updateSessionTitle ).not.toHaveBeenCalled();
		expect( dispatch.setSending ).not.toHaveBeenCalled();
		expect( dispatch.setStreamError ).not.toHaveBeenCalled();
	} );

	test( 'a stale title response does not overwrite a user rename', async () => {
		let resolveResponse;
		apiFetch.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveResponse = resolve;
				} )
		);
		let title = 'Review plugins';
		const select = { getSessions: () => [ { id: 14, title } ] };
		const dispatch = { updateSessionTitle: jest.fn() };
		const watching = pollSessionTitle( 14, { dispatch, select } );
		title = 'My manual title';
		resolveResponse( { title: '🔌 Plugin Review', pending: false } );
		await watching;
		expect( dispatch.updateSessionTitle ).not.toHaveBeenCalled();
	} );
} );
