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

	test( 'renders managed-credit notices as account actions', async () => {
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
			'Good news—as an early adopter, use coupon code EARLY'
		);
		expect( container.textContent ).toContain( '$200 in AI usage credits' );
		expect( container.textContent ).not.toMatch(
			/\b(error|rejected|insufficient)\b/i
		);

		const actions = container.querySelectorAll(
			'.sd-ai-agent-cr-msg-system-action'
		);
		expect( actions ).toHaveLength( 2 );
		expect( actions[ 0 ].getAttribute( 'href' ) ).toBe(
			'https://account.example.test/login'
		);
		expect( actions[ 0 ].getAttribute( 'target' ) ).toBe( '_blank' );
		expect( actions[ 0 ].getAttribute( 'rel' ) ).toBe(
			'noopener noreferrer'
		);
		expect( actions[ 0 ].textContent ).toBe( 'Redeem EARLY coupon' );
		expect( actions[ 1 ].textContent ).toBe( 'Leave a review' );
		expect( actions[ 1 ].getAttribute( 'href' ) ).toBe(
			'https://wordpress.org/support/plugin/superdav-ai-agent/reviews/#new-post'
		);

		const inlineActions = container.querySelectorAll(
			'.sd-ai-agent-cr-msg-system-inline-action'
		);
		expect( inlineActions ).toHaveLength( 1 );
		expect( inlineActions[ 0 ].textContent ).toBe( 'account settings' );
		expect( inlineActions[ 0 ].getAttribute( 'href' ) ).toBe(
			'https://account.example.test/login'
		);

		await act( async () => {
			root.unmount();
		} );
	} );

	test( 'renders readable translated copy when no action URL is available', async () => {
		const { container, root } = await renderAccountActionMessage( {
			notice: {
				type: 'account_action',
				reason: 'credit_exhausted',
				action: 'purchase_credits',
			},
		} );

		expect( container.textContent ).toContain(
			'Good news—as an early adopter, use coupon code EARLY'
		);
		expect( container.textContent ).not.toContain( '<settingsLink>' );
		expect(
			container.querySelectorAll( '.sd-ai-agent-cr-msg-system-action' )
		).toHaveLength( 1 );
		expect( container.querySelectorAll( 'a' ) ).toHaveLength( 1 );
		expect( container.querySelector( 'a' ).textContent ).toBe(
			'Leave a review'
		);

		await act( async () => {
			root.unmount();
		} );
	} );
} );
