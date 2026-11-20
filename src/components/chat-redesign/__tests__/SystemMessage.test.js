/**
 * Unit tests for SystemMessage account-action rendering.
 */

import { createElement } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import { act } from 'react';

import AccountActionMessage from '../../chat-messages/account-action-message';

global.IS_REACT_ACT_ENVIRONMENT = true;

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/icons', () => ( {
	Icon: () => null,
	copy: 'copy-icon',
	check: 'check-icon',
	pencil: 'pencil-icon',
	thumbsDown: 'thumbs-down-icon',
} ) );

jest.mock( '../../../store', () => 'sd-ai-agent' );
jest.mock( '../../markdown-message', () => () => null );
jest.mock( '../icons', () => ( {
	AiIcon: () => null,
} ) );
jest.mock( '../ToolCard', () => () => null );
jest.mock( '../../../utils/linkify', () => ( {
	linkifyText: ( s ) => s,
} ) );

/**
 * Render an account-action notice for DOM assertions.
 *
 * @param {Object} props Component props.
 * @return {Promise<Object>} Render result.
 */
async function renderAccountActionMessage( props ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	await act( async () => {
		root.render( createElement( AccountActionMessage, props ) );
	} );
	return { container, root };
}

describe( 'AccountActionMessage', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		const data = require( '@wordpress/data' );
		data.useDispatch.mockReset();
		data.useSelect.mockReset();
	} );

	test( 'renders managed-credit notices with an Add credit action that opens the popup', async () => {
		const data = require( '@wordpress/data' );
		const openCreditPrompt = jest.fn();
		data.useDispatch.mockReturnValue( { openCreditPrompt } );
		data.useSelect.mockReturnValue( 42 );

		const { container, root } = await renderAccountActionMessage( {
			notice: {
				type: 'account_action',
				reason: 'credit_exhausted',
				action: 'purchase_credits',
				actionUrl: 'https://account.example.test/login',
			},
		} );

		expect(
			container.querySelector(
				'.sd-ai-agent-cr-msg-system--account-action'
			)
		).not.toBeNull();
		expect( container.textContent ).toContain(
			'You’ve used your SD AI credit.'
		);
		expect( container.textContent ).not.toContain( 'EARLY' );
		expect( container.textContent ).not.toMatch(
			/\b(error|rejected|insufficient)\b/i
		);

		const actions = container.querySelectorAll(
			'.sd-ai-agent-cr-msg-system-action'
		);
		expect( actions ).toHaveLength( 1 );
		expect( actions[ 0 ].tagName ).toBe( 'BUTTON' );
		expect( actions[ 0 ].textContent ).toBe( 'Add credit' );

		await act( async () => {
			actions[ 0 ].click();
		} );
		expect( openCreditPrompt ).toHaveBeenCalledWith( 42 );

		await act( async () => {
			root.unmount();
		} );
	} );

	test( 'falls back to the account link when the popup action is unavailable', async () => {
		const data = require( '@wordpress/data' );
		data.useDispatch.mockReturnValue( {} );
		data.useSelect.mockReturnValue( null );

		const { container, root } = await renderAccountActionMessage( {
			notice: {
				type: 'account_action',
				reason: 'credit_exhausted',
				action: 'purchase_credits',
				actionUrl: 'https://account.example.test/login',
			},
		} );

		const links = container.querySelectorAll( 'a' );
		expect( links ).toHaveLength( 1 );
		expect( links[ 0 ].textContent ).toBe( 'Add credit' );
		expect( links[ 0 ].getAttribute( 'href' ) ).toBe(
			'https://account.example.test/login'
		);
		expect( links[ 0 ].getAttribute( 'rel' ) ).toBe(
			'noopener noreferrer'
		);

		await act( async () => {
			root.unmount();
		} );
	} );
} );
