import { createElement } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import McpIntegrationsManager from '../mcp-integrations-manager';

global.IS_REACT_ACT_ENVIRONMENT = true;
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		Button: ( { children, disabled, onClick } ) =>
			React.createElement(
				'button',
				{ type: 'button', disabled, onClick },
				children
			),
		Notice: ( { children } ) =>
			React.createElement( 'div', { role: 'status' }, children ),
		Spinner: () => React.createElement( 'span', null, 'Loading' ),
		TextControl: ( { label, onChange, type = 'text', value, readOnly } ) =>
			React.createElement(
				'label',
				null,
				label,
				React.createElement( 'input', {
					type,
					value,
					readOnly,
					onChange: ( event ) => onChange?.( event.target.value ),
				} )
			),
		SelectControl: ( { label, onChange, options, value } ) =>
			React.createElement(
				'label',
				null,
				label,
				React.createElement(
					'select',
					{
						value,
						onChange: ( event ) => onChange( event.target.value ),
					},
					options.map( ( option ) =>
						React.createElement(
							'option',
							{ key: option.value, value: option.value },
							option.label
						)
					)
				)
			),
		ToggleControl: ( { checked, label, onChange, disabled } ) =>
			React.createElement(
				'label',
				null,
				label,
				React.createElement( 'input', {
					type: 'checkbox',
					checked,
					disabled,
					onChange: ( event ) => onChange( event.target.checked ),
				} )
			),
	};
} );

const server = {
	id: 'fixture-server',
	name: 'Fixture',
	endpoint: 'https://fixture.mcp.test/mcp',
	auth_type: 'none',
	enabled: true,
	status: 'ready',
	tools: [ { name: 'greet', description: 'Greet someone.' } ],
};

describe( 'MCP connection manager', () => {
	let container;
	let root;
	let rows;
	let failDiscovery;

	const button = ( text ) =>
		Array.from( container.querySelectorAll( 'button' ) ).find(
			( node ) => node.textContent === text
		);
	const input = ( text ) =>
		Array.from( container.querySelectorAll( 'label' ) )
			.find(
				( node ) =>
					node.textContent.startsWith( text ) &&
					node.querySelector( 'input' )
			)
			.querySelector( 'input' );
	const fill = async ( label, value ) => {
		await act( async () => {
			const node = input( label );
			Object.getOwnPropertyDescriptor(
				window.HTMLInputElement.prototype,
				'value'
			).set.call( node, value );
			node.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	};
	const click = async ( text ) => act( async () => button( text ).click() );
	const render = async () =>
		act( async () =>
			root.render( createElement( McpIntegrationsManager ) )
		);

	beforeEach( () => {
		rows = [];
		failDiscovery = false;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		apiFetch.mockImplementation( ( { path, method, data } ) => {
			if ( path === '/sd-ai-agent/v1/mcp-connections' && ! method ) {
				return Promise.resolve( { connections: rows } );
			}
			if (
				path === '/sd-ai-agent/v1/mcp-connections' &&
				method === 'POST'
			) {
				rows = [ { ...server, ...data, enabled: false } ];
				return Promise.resolve( { connection: rows[ 0 ] } );
			}
			if ( path.endsWith( '/test' ) ) {
				return failDiscovery
					? Promise.reject( {
							code: 'sd_ai_agent_remote_mcp_authentication_required',
							message: 'Authentication required.',
					  } )
					: Promise.resolve( { discovery: { tools: server.tools } } );
			}
			if ( path.endsWith( '/enable' ) ) {
				rows[ 0 ].enabled = true;
				return Promise.resolve( { connection: rows[ 0 ] } );
			}
			if ( path.endsWith( '/authorize' ) ) {
				return Promise.reject( {
					code: 'sd_ai_agent_remote_mcp_oauth_https_required',
					message: 'Sign-in requires HTTPS.',
				} );
			}
			return Promise.reject( new Error( 'Unexpected request' ) );
		} );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		apiFetch.mockReset();
	} );

	test( 'renders existing tools and their safe status', async () => {
		rows = [ server ];
		await render();
		expect( container.textContent ).toContain( 'Connected' );
		expect( container.textContent ).toContain( '1 tool available' );
		expect( container.textContent ).toContain( 'greet' );
	} );

	test( 'discovers once before enabling and keeps new request IDs stable', async () => {
		await render();
		await click( 'Add server' );
		await fill( 'Server URL', server.endpoint );
		await act( async () => {
			button( 'Connect' ).click();
			button( 'Connect' ).click();
		} );
		expect( container.textContent ).toContain( 'MCP server connected.' );
		const saves = apiFetch.mock.calls.filter(
			( [ request ] ) =>
				request.method === 'POST' &&
				request.path === '/sd-ai-agent/v1/mcp-connections'
		);
		expect( saves ).toHaveLength( 1 );
		expect( saves[ 0 ][ 0 ].data.id ).toMatch( /^[a-f0-9]{32}$/ );
		expect(
			apiFetch.mock.calls.filter( ( [ request ] ) =>
				request.path.endsWith( '/test' )
			)
		).toHaveLength( 1 );
		expect(
			apiFetch.mock.calls.filter( ( [ request ] ) =>
				request.path.endsWith( '/refresh' )
			)
		).toHaveLength( 0 );
	} );

	test( 'failed discovery never enables and clears the token field', async () => {
		failDiscovery = true;
		await render();
		await click( 'Add server' );
		await fill( 'Server URL', server.endpoint );
		await act( async () => {
			const select = container.querySelector( 'select' );
			select.value = 'bearer';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		await fill( 'Access token', 'fixture-token' );
		await click( 'Connect' );
		expect( input( 'Access token' ).value ).toBe( '' );
		expect( rows[ 0 ].enabled ).toBe( false );
		expect(
			apiFetch.mock.calls.some( ( [ request ] ) =>
				request.path.endsWith( '/enable' )
			)
		).toBe( false );
		expect( container.textContent ).toContain( 'Authentication required.' );
	} );

	test( 'OAuth uses sign-in instead of a copied access token and explains setup failures', async () => {
		await render();
		await click( 'Add server' );
		await fill( 'Server URL', server.endpoint );
		await act( async () => {
			const select = container.querySelector( 'select' );
			select.value = 'oauth';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		expect(
			container.querySelector( 'input[type="password"]' )
		).toBeNull();
		await click( 'Sign in' );
		expect(
			apiFetch.mock.calls.some( ( [ request ] ) =>
				request.path.endsWith( '/authorize' )
			)
		).toBe( true );
		expect( container.textContent ).toContain( 'Sign-in requires HTTPS.' );
	} );
} );
