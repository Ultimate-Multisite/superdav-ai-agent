/**
 * Unit tests for utils/active-jobs-storage.js
 *
 * Tests cover:
 * - setActiveJob: persists jobId for a session
 * - clearActiveJob: removes session from storage, cleans up empty map
 * - getActiveJobs: reads all persisted entries
 * - Graceful degradation when sessionStorage throws (quota, private mode)
 */

import {
	setActiveJob,
	clearActiveJob,
	getActiveJobs,
} from '../active-jobs-storage';

// ─── sessionStorage mock ──────────────────────────────────────────────────────

const sessionStorageMock = ( () => {
	let store = {};
	return {
		getItem: ( key ) => store[ key ] ?? null,
		setItem: ( key, value ) => {
			store[ key ] = String( value );
		},
		removeItem: ( key ) => {
			delete store[ key ];
		},
		clear: () => {
			store = {};
		},
	};
} )();

Object.defineProperty( global, 'sessionStorage', {
	value: sessionStorageMock,
	writable: true,
} );

// ─── helpers ──────────────────────────────────────────────────────────────────

beforeEach( () => {
	sessionStorageMock.clear();
} );

// ─── setActiveJob ─────────────────────────────────────────────────────────────

describe( 'setActiveJob', () => {
	test( 'persists a job entry to sessionStorage', () => {
		setActiveJob( 42, 'job_abc' );
		const raw = sessionStorage.getItem( 'gratisAiAgent_activeJobs' );
		expect( JSON.parse( raw ) ).toEqual( { 42: 'job_abc' } );
	} );

	test( 'adds a second session without overwriting the first', () => {
		setActiveJob( 1, 'job_a' );
		setActiveJob( 2, 'job_b' );
		const raw = sessionStorage.getItem( 'gratisAiAgent_activeJobs' );
		expect( JSON.parse( raw ) ).toEqual( { 1: 'job_a', 2: 'job_b' } );
	} );

	test( 'updates an existing session entry', () => {
		setActiveJob( 1, 'job_old' );
		setActiveJob( 1, 'job_new' );
		const raw = sessionStorage.getItem( 'gratisAiAgent_activeJobs' );
		expect( JSON.parse( raw ) ).toEqual( { 1: 'job_new' } );
	} );

	test( 'does not throw when sessionStorage is unavailable', () => {
		const original = global.sessionStorage;
		Object.defineProperty( global, 'sessionStorage', {
			get() {
				throw new Error( 'storage unavailable' );
			},
			configurable: true,
		} );
		expect( () => setActiveJob( 1, 'job_x' ) ).not.toThrow();
		Object.defineProperty( global, 'sessionStorage', {
			value: original,
			configurable: true,
			writable: true,
		} );
	} );
} );

// ─── clearActiveJob ───────────────────────────────────────────────────────────

describe( 'clearActiveJob', () => {
	test( 'removes the entry for the given session', () => {
		setActiveJob( 5, 'job_five' );
		setActiveJob( 6, 'job_six' );
		clearActiveJob( 5 );
		expect( getActiveJobs() ).toEqual( { 6: 'job_six' } );
	} );

	test( 'removes the key entirely when the map becomes empty', () => {
		setActiveJob( 7, 'job_seven' );
		clearActiveJob( 7 );
		expect(
			sessionStorage.getItem( 'gratisAiAgent_activeJobs' )
		).toBeNull();
	} );

	test( 'is a no-op when sessionStorage is empty', () => {
		expect( () => clearActiveJob( 99 ) ).not.toThrow();
	} );

	test( 'is a no-op when the session was not registered', () => {
		setActiveJob( 10, 'job_ten' );
		clearActiveJob( 999 ); // Unrelated session.
		expect( getActiveJobs() ).toEqual( { 10: 'job_ten' } );
	} );
} );

// ─── getActiveJobs ────────────────────────────────────────────────────────────

describe( 'getActiveJobs', () => {
	test( 'returns an empty object when storage is empty', () => {
		expect( getActiveJobs() ).toEqual( {} );
	} );

	test( 'returns all persisted entries', () => {
		setActiveJob( 20, 'job_twenty' );
		setActiveJob( 21, 'job_twentyone' );
		expect( getActiveJobs() ).toEqual( {
			20: 'job_twenty',
			21: 'job_twentyone',
		} );
	} );

	test( 'returns an empty object when sessionStorage is unavailable', () => {
		const original = global.sessionStorage;
		Object.defineProperty( global, 'sessionStorage', {
			get() {
				throw new Error( 'storage unavailable' );
			},
			configurable: true,
		} );
		expect( getActiveJobs() ).toEqual( {} );
		Object.defineProperty( global, 'sessionStorage', {
			value: original,
			configurable: true,
			writable: true,
		} );
	} );

	test( 'returns an empty object when storage contains malformed JSON', () => {
		sessionStorage.setItem( 'gratisAiAgent_activeJobs', 'not-json{' );
		expect( getActiveJobs() ).toEqual( {} );
	} );
} );
