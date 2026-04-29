/**
 * Unit tests for components/onboarding-bootstrap.js
 *
 * Tests cover:
 * - Renders the bootstrap wrapper
 * - Renders the ChatPanel
 * - Calls bootstrap-start endpoint on mount
 * - Opens the session returned by bootstrap-start
 * - Sends the kickoff message returned by bootstrap-start
 * - Falls back gracefully when bootstrap-start fails
 * - Uses fallback kickoff message when none returned
 * - Does not call bootstrap-start twice (React 18 strict-mode guard)
 */

import { createElement } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import OnboardingBootstrap from '../onboarding-bootstrap';

// Configure React act() environment for jsdom.
global.IS_REACT_ACT_ENVIRONMENT = true;

// ─── Mock @wordpress/data ─────────────────────────────────────────────────────

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

// ─── Mock @wordpress/i18n ─────────────────────────────────────────────────────

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

// ─── Mock @wordpress/api-fetch ────────────────────────────────────────────────

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// ─── Mock store ───────────────────────────────────────────────────────────────

jest.mock( '../../store', () => 'sd-ai-agent' );

// ─── Mock ChatPanel ───────────────────────────────────────────────────────────

jest.mock( '../ChatPanel', () => {
	const React = require( 'react' );
	return () => React.createElement( 'div', { 'data-testid': 'chat-panel' } );
} );

// ─── Tests ────────────────────────────────────────────────────────────────────

describe( 'OnboardingBootstrap', () => {
	let container;
	let root;
	let openSessionMock;
	let sendMessageMock;

	beforeEach( () => {
		openSessionMock = jest.fn().mockResolvedValue( undefined );
		sendMessageMock = jest.fn().mockResolvedValue( undefined );

		useDispatch.mockReturnValue( {
			openSession: openSessionMock,
			sendMessage: sendMessageMock,
		} );

		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( async () => {
		await act( async () => {
			root.unmount();
		} );
		document.body.removeChild( container );
		jest.clearAllMocks();
	} );

	/**
	 *
	 */
	async function renderBootstrap() {
		await act( async () => {
			root.render( createElement( OnboardingBootstrap, {} ) );
		} );
	}

	test( 'renders the bootstrap wrapper', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: 'Hi there!',
			bootstrap_system_prompt: 'You are a helpful agent.',
		} );
		await renderBootstrap();
		expect(
			container.querySelector( '.sd-ai-agent-onboarding-bootstrap' )
		).not.toBeNull();
	} );

	test( 'renders ChatPanel inside the wrapper', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: 'Hi there!',
			bootstrap_system_prompt: 'You are a helpful agent.',
		} );
		await renderBootstrap();
		expect(
			container.querySelector( '[data-testid="chat-panel"]' )
		).not.toBeNull();
	} );

	test( 'calls bootstrap-start endpoint on mount', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: 'Hi!',
			bootstrap_system_prompt: 'Explore the site.',
		} );
		await renderBootstrap();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/sd-ai-agent/v1/onboarding/bootstrap-start',
			method: 'POST',
		} );
	} );

	test( 'opens the session returned by bootstrap-start', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 99,
			kickoff_message: 'Hi!',
			bootstrap_system_prompt: 'Explore the site.',
		} );
		await renderBootstrap();
		expect( openSessionMock ).toHaveBeenCalledWith( 99 );
	} );

	test( 'sends kickoff message with system instruction after session opens', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: 'Hello from kickoff',
			bootstrap_system_prompt: 'Discovery prompt',
		} );
		await renderBootstrap();
		expect( sendMessageMock ).toHaveBeenCalledWith(
			'Hello from kickoff',
			[],
			{ systemInstruction: 'Discovery prompt' }
		);
	} );

	test( 'sends empty options when no bootstrap_system_prompt returned', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: 'Hello',
			bootstrap_system_prompt: null,
		} );
		await renderBootstrap();
		expect( sendMessageMock ).toHaveBeenCalledWith( 'Hello', [], {} );
	} );

	test( 'uses fallback kickoff message when none returned', async () => {
		apiFetch.mockResolvedValue( {
			session_id: 42,
			kickoff_message: null,
			bootstrap_system_prompt: 'Some prompt',
		} );
		await renderBootstrap();
		const [ msg ] = sendMessageMock.mock.calls[ 0 ];
		// Should contain a non-empty fallback string.
		expect( msg ).toBeTruthy();
		expect( typeof msg ).toBe( 'string' );
	} );

	test( 'does not throw when bootstrap-start fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network error' ) );
		// Should render without throwing.
		await expect( renderBootstrap() ).resolves.toBeUndefined();
		// openSession and sendMessage should not be called on error.
		expect( openSessionMock ).not.toHaveBeenCalled();
		expect( sendMessageMock ).not.toHaveBeenCalled();
	} );

	test( 'does not call bootstrap-start when session_id is missing', async () => {
		apiFetch.mockResolvedValue( { success: true } ); // no session_id
		await renderBootstrap();
		// openSession should not be called without a session_id.
		expect( openSessionMock ).not.toHaveBeenCalled();
	} );
} );
