/**
 * Tests for capability-gated alerts polling.
 */

import { startAlertsPolling } from '../alerts-polling';

describe( 'startAlertsPolling', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	test( 'makes no request when canViewAlerts is false', () => {
		const fetchAlerts = jest.fn();
		const stop = startAlertsPolling( fetchAlerts, false );
		jest.advanceTimersByTime( 11 * 60 * 1000 );
		stop();
		expect( fetchAlerts ).not.toHaveBeenCalled();
	} );

	test( 'fetches on start and every 5 minutes when canViewAlerts is true', () => {
		const fetchAlerts = jest.fn();
		const stop = startAlertsPolling( fetchAlerts, true );
		expect( fetchAlerts ).toHaveBeenCalledTimes( 1 );
		jest.advanceTimersByTime( 5 * 60 * 1000 );
		expect( fetchAlerts ).toHaveBeenCalledTimes( 2 );
		stop();
		jest.advanceTimersByTime( 5 * 60 * 1000 );
		expect( fetchAlerts ).toHaveBeenCalledTimes( 2 );
	} );
} );
