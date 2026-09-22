/**
 * Unit tests for the page-local browser ability registry.
 */

/**
 * Load an isolated registry module instance.
 *
 * @return {Object} Registry exports.
 */
function loadRegistry() {
	let mod;
	jest.isolateModules( () => {
		// eslint-disable-next-line global-require
		mod = require( '../registry' );
	} );
	return mod;
}

/**
 * Load refresh-page and its matching isolated registry instance.
 *
 * @return {{refreshPage: Object, registry: Object}} Module exports.
 */
function loadRefreshPageAndRegistry() {
	let refreshPage;
	let registry;
	jest.isolateModules( () => {
		// eslint-disable-next-line global-require
		refreshPage = require( '../refresh-page' );
		// eslint-disable-next-line global-require
		registry = require( '../registry' );
	} );
	return { refreshPage, registry };
}

const WIN_REGISTRY_KEY = '__sdAiAgentClientAbilityRegistry';
const WIN_API_KEY = 'sdAiAgentClientAbilities';

describe( 'browser ability registry', () => {
	let originalWp;

	beforeEach( () => {
		originalWp = global.wp;
		delete window[ WIN_REGISTRY_KEY ];
		delete window[ WIN_API_KEY ];
	} );

	afterEach( () => {
		global.wp = originalWp;
		delete window[ WIN_REGISTRY_KEY ];
		delete window[ WIN_API_KEY ];
	} );

	test( 'shares callbacks and descriptors between webpack module instances', async () => {
		const firstBundle = loadRegistry();
		const secondBundle = loadRegistry();
		const callback = jest.fn().mockResolvedValue( { shared: true } );

		await firstBundle.registerClientAbility( {
			name: 'sd-ai-agent-js/cross-bundle',
			label: 'Cross Bundle',
			description: 'Registered by another bundle',
			inputSchema: { type: 'object' },
			outputSchema: { type: 'object' },
			annotations: { readonly: true },
			callback,
		} );

		await expect(
			secondBundle.executeClientAbility( 'sd-ai-agent-js/cross-bundle', {
				from: 'second-bundle',
			} )
		).resolves.toEqual( { shared: true } );
		expect( callback ).toHaveBeenCalledWith( { from: 'second-bundle' } );
		await expect( secondBundle.snapshotDescriptors() ).resolves.toEqual( [
			expect.objectContaining( {
				name: 'sd-ai-agent-js/cross-bundle',
				annotations: { readonly: true },
			} ),
		] );
	} );

	test( 'does not hydrate or write to the shared WordPress abilities store', async () => {
		const abilities = {
			registerAbility: jest.fn(),
			registerAbilityCategory: jest.fn(),
			getAbilities: jest.fn(),
			executeAbility: jest.fn(),
		};
		global.wp = { abilities };
		const registry = loadRegistry();
		const callback = jest.fn().mockResolvedValue( { local: true } );

		await registry.registerCategory();
		await registry.registerClientAbility( {
			name: 'sd-ai-agent-js/local-only',
			label: 'Local Only',
			description: 'Never hydrates providers',
			inputSchema: { type: 'object' },
			outputSchema: { type: 'object' },
			annotations: { readonly: true },
			callback,
		} );
		await registry.snapshotDescriptors();
		await registry.executeClientAbility( 'sd-ai-agent-js/local-only', {} );

		expect( abilities.registerAbilityCategory ).not.toHaveBeenCalled();
		expect( abilities.registerAbility ).not.toHaveBeenCalled();
		expect( abilities.getAbilities ).not.toHaveBeenCalled();
		expect( abilities.executeAbility ).not.toHaveBeenCalled();
	} );

	test( 'deduplicates local registration between bundle instances', async () => {
		const firstBundle = loadRegistry();
		const secondBundle = loadRegistry();
		const firstCallback = jest.fn();
		const secondCallback = jest.fn();
		const definition = {
			name: 'sd-ai-agent-js/deduplicated',
			label: 'Deduplicated',
			description: 'Shared registration state',
			inputSchema: { type: 'object' },
			outputSchema: { type: 'object' },
			annotations: {},
			callback: firstCallback,
		};

		await firstBundle.registerClientAbility( definition );
		await secondBundle.registerClientAbility( {
			...definition,
			callback: secondCallback,
		} );
		await firstBundle.executeClientAbility(
			'sd-ai-agent-js/deduplicated',
			{}
		);

		expect( firstCallback ).toHaveBeenCalledTimes( 1 );
		expect( secondCallback ).not.toHaveBeenCalled();
	} );

	test( 'publishes a diagnostic API without replacing wp.abilities', async () => {
		const coreAbilities = { getAbilities: jest.fn() };
		global.wp = { abilities: coreAbilities };
		const registry = loadRegistry();

		await registry.registerClientAbility( {
			name: 'sd-ai-agent-js/diagnostic',
			label: 'Diagnostic',
			description: 'Visible through the local API',
			inputSchema: { type: 'object' },
			outputSchema: { type: 'object' },
			annotations: { readonly: true },
			callback: jest.fn().mockResolvedValue( { ok: true } ),
		} );

		expect( global.wp.abilities ).toBe( coreAbilities );
		await expect( window[ WIN_API_KEY ].getAbilities() ).resolves.toEqual( [
			expect.objectContaining( {
				name: 'sd-ai-agent-js/diagnostic',
				meta: { annotations: { readonly: true } },
			} ),
		] );
		await expect(
			window[ WIN_API_KEY ].getAbilityCategory( 'sd-ai-agent-js' )
		).resolves.toEqual(
			expect.objectContaining( {
				slug: 'sd-ai-agent-js',
				label: 'SD AI Agent',
			} )
		);
	} );

	test( 'throws for unknown browser abilities without using the core store', async () => {
		const executeAbility = jest.fn();
		global.wp = { abilities: { executeAbility } };
		const { executeClientAbility } = loadRegistry();

		await expect(
			executeClientAbility( 'sd-ai-agent-js/never-registered', {} )
		).rejects.toThrow( /is not registered on this page/ );
		expect( executeAbility ).not.toHaveBeenCalled();
	} );

	test( 'registers refresh-page without an empty required array', async () => {
		const { refreshPage, registry } = loadRefreshPageAndRegistry();
		await refreshPage.registerRefreshPageAbility();
		const descriptor = ( await registry.snapshotDescriptors() ).find(
			( candidate ) => candidate.name === 'sd-ai-agent-js/refresh-page'
		);

		expect( descriptor.input_schema ).toMatchObject( {
			type: 'object',
			properties: {},
		} );
		expect( descriptor.input_schema ).not.toHaveProperty( 'required' );
	} );
} );
