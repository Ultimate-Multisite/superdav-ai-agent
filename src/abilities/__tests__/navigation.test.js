/**
 * Unit tests for browser-executed site navigation.
 */
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

/**
 * Load isolated navigation and registry modules.
 *
 * @return {{ navigation: Object, registry: Object }} Isolated module exports.
 */
function loadNavigationAndRegistry() {
	let navigation;
	let registry;
	jest.isolateModules( () => {
		// eslint-disable-next-line global-require
		navigation = require( '../navigation' );
		// eslint-disable-next-line global-require
		registry = require( '../registry' );
	} );
	return { navigation, registry };
}

describe( 'browser site navigation', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		delete window._sdAiAgentPendingNavigation;
		delete global.wp;
	} );

	afterEach( () => {
		delete window._sdAiAgentPendingNavigation;
	} );

	test( 'advertises path or url navigation inputs', async () => {
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();

		const [ ability ] = await registry.snapshotDescriptors();
		expect( ability.input_schema.properties ).toMatchObject( {
			path: { type: 'string' },
			url: { type: 'string' },
		} );
		expect( ability.input_schema.anyOf ).toEqual( [
			{ required: [ 'path' ] },
			{ required: [ 'url' ] },
		] );
	} );

	test( 'schedules a same-site URL after the client tool result is posted', async () => {
		apiFetch.mockResolvedValue( {
			action: 'navigate',
			url: `${ window.location.origin }/portfolio/`,
		} );
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();

		await expect(
			registry.executeClientAbility( 'sd-ai-agent-js/navigate-to', {
				url: '/portfolio/',
			} )
		).resolves.toEqual( { navigated: true, path: '' } );
		expect( window._sdAiAgentPendingNavigation ).toBe(
			`${ window.location.origin }/portfolio/`
		);
	} );

	test( 'rejects an external URL so the agent can provide its fallback link', async () => {
		apiFetch.mockResolvedValue( {
			action: 'navigate',
			url: 'https://example.com/',
		} );
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();

		await expect(
			registry.executeClientAbility( 'sd-ai-agent-js/navigate-to', {
				url: 'https://example.com/',
			} )
		).rejects.toThrow( 'Invalid URL.' );
		expect( window._sdAiAgentPendingNavigation ).toBeUndefined();
	} );

	test( 'resolves admin filenames on the server instead of concatenating site paths', async () => {
		apiFetch.mockResolvedValue( {
			action: 'navigate',
			url: `${ window.location.origin }/woo/wp-admin/plugins.php`,
		} );
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();
		await registry.executeClientAbility( 'sd-ai-agent-js/navigate-to', {
			path: 'plugins.php',
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				data: { input: { path: 'plugins.php' } },
			} )
		);
		expect( window._sdAiAgentPendingNavigation ).toBe(
			`${ window.location.origin }/woo/wp-admin/plugins.php`
		);
	} );

	test.each( [
		'/woo/wp-admin/plugins.php',
		'https://woo.example.com/wp-admin/plugins.php',
	] )( 'keeps cross-blog link %s user-operated', async ( url ) => {
		window._sdAiAgentPendingNavigation = '/stale-target/';
		apiFetch.mockResolvedValue( {
			action: 'link',
			url,
			message: 'Open in a new tab.',
		} );
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();
		await expect(
			registry.executeClientAbility( 'sd-ai-agent-js/navigate-to', {
				path: 'plugins.php',
				blog_id: 2,
			} )
		).resolves.toMatchObject( { navigated: false, url } );
		expect( window._sdAiAgentPendingNavigation ).toBeUndefined();
	} );

	test( 'does not schedule navigation when server validation fails', async () => {
		window._sdAiAgentPendingNavigation = '/stale-target/';
		apiFetch.mockRejectedValue( new Error( 'Invalid URL.' ) );
		const { navigation, registry } = loadNavigationAndRegistry();
		await navigation.registerNavigationAbility();
		await expect(
			registry.executeClientAbility( 'sd-ai-agent-js/navigate-to', {
				url: '/wp-admin/woo/wp-admin/plugins.php',
			} )
		).rejects.toThrow( 'Invalid URL.' );
		expect( window._sdAiAgentPendingNavigation ).toBeUndefined();
	} );
} );
