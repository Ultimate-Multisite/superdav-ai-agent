/** Unit tests for the capability-detected Elementor editor MCP bridge. */

const BRIDGE_STATE_KEY = '__sdAiAgentElementorEditorMcpBridge';
const REGISTRY_KEY = '__sdAiAgentClientAbilityRegistry';
const BRIDGE_REGISTRATION_KEY = '__sdAiAgentElementorEditorMcpRegistration';

/**
 * Load isolated bridge and registry module instances.
 *
 * @return {{bridge: Object, registry: Object}} Module exports.
 */
function loadBridge() {
	let bridge;
	let registry;
	jest.isolateModules( () => {
		// eslint-disable-next-line global-require
		bridge = require( '../elementor-editor-mcp' );
		// eslint-disable-next-line global-require
		registry = require( '../registry' );
	} );
	return { bridge, registry };
}

/**
 * Install a minimal documented Elementor editorMcp API for a test.
 *
 * @return {jest.Mock} Public adapter registration mock.
 */
function provideElementorApi() {
	const registerMcpAdapter = jest.fn();
	window.elementorV2 = {
		editorMcp: { registerMcpAdapter },
	};
	return registerMcpAdapter;
}

/**
 * Return the first adapter registered through the documented public API.
 *
 * @param {jest.Mock} registerMcpAdapter Registration mock.
 * @return {Object} Registered adapter.
 */
function getAdapter( registerMcpAdapter ) {
	expect( registerMcpAdapter ).toHaveBeenCalledTimes( 1 );
	return registerMcpAdapter.mock.calls[ 0 ][ 0 ];
}

describe( 'Elementor editor MCP bridge', () => {
	let originalElementorV2;
	let originalRegistry;
	let originalBridgeRegistration;
	let originalState;
	let originalUrl;
	let originalWp;

	beforeEach( () => {
		originalElementorV2 = window.elementorV2;
		originalRegistry = window[ REGISTRY_KEY ];
		originalBridgeRegistration = window[ BRIDGE_REGISTRATION_KEY ];
		originalState = window[ BRIDGE_STATE_KEY ];
		originalUrl = window.location.href;
		originalWp = global.wp;
		delete global.wp;
		delete window.elementorV2;
		delete window[ REGISTRY_KEY ];
		delete window[ BRIDGE_REGISTRATION_KEY ];
		delete window[ BRIDGE_STATE_KEY ];
		window.history.replaceState(
			{},
			'',
			'/wp-admin/post.php?post=42&action=elementor'
		);
	} );

	afterEach( () => {
		if ( typeof originalElementorV2 === 'undefined' ) {
			delete window.elementorV2;
		} else {
			window.elementorV2 = originalElementorV2;
		}
		if ( typeof originalRegistry === 'undefined' ) {
			delete window[ REGISTRY_KEY ];
		} else {
			window[ REGISTRY_KEY ] = originalRegistry;
		}
		if ( typeof originalBridgeRegistration === 'undefined' ) {
			delete window[ BRIDGE_REGISTRATION_KEY ];
		} else {
			window[ BRIDGE_REGISTRATION_KEY ] = originalBridgeRegistration;
		}
		if ( typeof originalState === 'undefined' ) {
			delete window[ BRIDGE_STATE_KEY ];
		} else {
			window[ BRIDGE_STATE_KEY ] = originalState;
		}
		if ( typeof originalWp === 'undefined' ) {
			delete global.wp;
		} else {
			global.wp = originalWp;
		}
		window.history.replaceState( {}, '', originalUrl );
	} );

	test( 'returns structured unavailable context outside Elementor', async () => {
		const { bridge } = loadBridge();

		await expect(
			bridge.getElementorEditorMcpContext()
		).resolves.toMatchObject( {
			available: false,
			reason: 'outside_elementor_editor',
		} );
		await expect(
			bridge.listElementorEditorMcpCapabilities()
		).resolves.toMatchObject( {
			available: false,
			reason: 'outside_elementor_editor',
			tools: [],
			resources: [],
		} );
	} );

	test( 'captures public tools and resources, then invokes only advertised entries', async () => {
		const registerMcpAdapter = provideElementorApi();
		const { bridge } = loadBridge();
		expect( bridge.installElementorEditorMcpBridge() ).toBe( true );
		const adapter = getAdapter( registerMcpAdapter );
		const execute = jest.fn().mockResolvedValue( { updated: true } );
		const readSelection = jest.fn().mockResolvedValue( {
			content: 'Unsaved hero selection',
		} );

		adapter.onToolRegistered( {
			description: 'Update the selected hero section.',
			execute,
			inputSchema: {
				type: 'object',
				properties: { heading: { type: 'string' } },
			},
			name: 'elementor.update-selected-section',
		} );
		adapter.onResourceRegistered(
			'Current selection',
			'elementor://selection/current',
			readSelection
		);

		const manifest = await bridge.listElementorEditorMcpCapabilities();
		expect( manifest.fingerprint ).toMatch(
			/^elementor-document-[a-f0-9]{32}$/
		);
		expect( manifest ).toMatchObject( {
			available: true,
			tools: [
				{
					confirmationRequired: true,
					name: 'elementor.update-selected-section',
				},
			],
			resources: [
				{
					name: 'Current selection',
					uri: 'elementor://selection/current',
				},
			],
		} );

		const context = await bridge.getElementorEditorMcpContext();
		expect( context.selection ).toEqual( {
			available: true,
			reason: 'advertised_resource_available',
			resources: [
				{
					name: 'Current selection',
					uri: 'elementor://selection/current',
				},
			],
		} );

		const resourceResult = await bridge.readElementorEditorMcpResource( {
			expectedDocumentFingerprint: manifest.fingerprint,
			uri: 'elementor://selection/current',
		} );
		expect( resourceResult ).toMatchObject( {
			documentFingerprint: manifest.fingerprint,
			result: { content: 'Unsaved hero selection' },
			success: true,
		} );
		expect( readSelection ).toHaveBeenCalledTimes( 1 );

		const toolResult = await bridge.callElementorEditorMcpTool( {
			arguments: { heading: 'New hero heading' },
			expectedDocumentFingerprint: manifest.fingerprint,
			toolName: 'elementor.update-selected-section',
		} );
		expect( toolResult ).toMatchObject( {
			documentFingerprint: manifest.fingerprint,
			mutationPossible: false,
			outcome: 'completed',
			result: { updated: true },
			success: true,
		} );
		expect( execute ).toHaveBeenCalledWith( {
			heading: 'New hero heading',
		} );

		const stale = await bridge.callElementorEditorMcpTool( {
			arguments: {},
			expectedDocumentFingerprint: 'elementor:stale',
			toolName: 'elementor.update-selected-section',
		} );
		expect( stale.reason ).toBe( 'stale_document' );
		expect( execute ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reports thrown mutating tool outcomes as unknown', async () => {
		const registerMcpAdapter = provideElementorApi();
		const { bridge } = loadBridge();
		bridge.installElementorEditorMcpBridge();
		const adapter = getAdapter( registerMcpAdapter );
		const execute = jest
			.fn()
			.mockRejectedValue(
				new Error( 'Cannot convert undefined to object' )
			);
		adapter.onToolRegistered( {
			execute,
			name: 'elementor.partial-mutation',
		} );

		const manifest = await bridge.listElementorEditorMcpCapabilities();
		const result = await bridge.callElementorEditorMcpTool( {
			arguments: {},
			expectedDocumentFingerprint: manifest.fingerprint,
			toolName: 'elementor.partial-mutation',
		} );

		expect( result ).toMatchObject( {
			error: 'Cannot convert undefined to object',
			mutationPossible: true,
			outcome: 'unknown',
			reason: 'tool_outcome_unknown',
			success: false,
		} );
		expect( execute ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'rejects unsafe arguments and bounds dynamic tool output', async () => {
		const registerMcpAdapter = provideElementorApi();
		const { bridge } = loadBridge();
		bridge.installElementorEditorMcpBridge();
		const adapter = getAdapter( registerMcpAdapter );
		const execute = jest.fn().mockResolvedValue( {
			content: 'x'.repeat( 100_000 ),
		} );
		adapter.onToolRegistered( {
			execute,
			name: 'elementor.large-output',
		} );

		const manifest = await bridge.listElementorEditorMcpCapabilities();
		const unsafeArguments = JSON.parse( '{"__proto__":{"polluted":true}}' );
		const unsafeResult = await bridge.callElementorEditorMcpTool( {
			arguments: unsafeArguments,
			expectedDocumentFingerprint: manifest.fingerprint,
			toolName: 'elementor.large-output',
		} );
		expect( unsafeResult.reason ).toBe( 'invalid_tool_arguments' );
		expect( execute ).not.toHaveBeenCalled();

		const result = await bridge.callElementorEditorMcpTool( {
			arguments: {},
			expectedDocumentFingerprint: manifest.fingerprint,
			toolName: 'elementor.large-output',
		} );
		expect( result ).toMatchObject( { success: true, truncated: true } );
		expect( JSON.stringify( result ).length ).toBeLessThan( 64 * 1024 );
	} );

	test( 'clears old document registrations before accepting a new document', async () => {
		const registerMcpAdapter = provideElementorApi();
		const { bridge } = loadBridge();
		bridge.installElementorEditorMcpBridge();
		const adapter = getAdapter( registerMcpAdapter );
		adapter.onToolRegistered( {
			execute: jest.fn(),
			name: 'elementor.document-42',
		} );

		const initialManifest =
			await bridge.listElementorEditorMcpCapabilities();
		expect( initialManifest.tools ).toHaveLength( 1 );
		window.history.replaceState(
			{},
			'',
			'/wp-admin/post.php?post=43&action=elementor'
		);
		expect(
			( await bridge.listElementorEditorMcpCapabilities() ).tools
		).toEqual( [] );
		const nextFingerprint = ( await bridge.getElementorEditorMcpContext() )
			.fingerprint;
		expect( nextFingerprint ).not.toBe( initialManifest.fingerprint );
		await expect(
			bridge.callElementorEditorMcpTool( {
				arguments: {},
				expectedDocumentFingerprint: initialManifest.fingerprint,
				toolName: 'elementor.document-42',
			} )
		).resolves.toMatchObject( {
			documentFingerprint: nextFingerprint,
			reason: 'stale_document',
			success: false,
		} );

		adapter.onToolRegistered( {
			execute: jest.fn(),
			name: 'elementor.document-43',
		} );
		expect(
			( await bridge.listElementorEditorMcpCapabilities() ).tools
		).toEqual( [
			expect.objectContaining( { name: 'elementor.document-43' } ),
		] );
	} );

	test( 'registers canonical bridge descriptors with confirmed tool calls', async () => {
		const { bridge, registry } = loadBridge();
		await bridge.registerElementorEditorMcpAbilities();
		const descriptors = await registry.snapshotDescriptors();
		const tool = descriptors.find(
			( descriptor ) =>
				descriptor.name ===
				'sd-ai-agent-js/call-elementor-editor-mcp-tool'
		);

		expect( descriptors ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					name: 'sd-ai-agent-js/get-elementor-editor-mcp-context',
				} ),
				expect.objectContaining( {
					name: 'sd-ai-agent-js/list-elementor-editor-mcp-capabilities',
				} ),
				expect.objectContaining( {
					name: 'sd-ai-agent-js/read-elementor-editor-mcp-resource',
				} ),
			] )
		);
		expect( tool.annotations ).toEqual( { readonly: false } );
		expect( tool.output_schema.properties.result.type ).toContain(
			'string'
		);
	} );

	test( 'publishes, invokes, and registers through the external package init contract', async () => {
		const registerMcpAdapter = provideElementorApi();
		global.wp = {
			abilities: {
				getAbilities: jest.fn().mockResolvedValue( [] ),
				registerAbility: jest.fn().mockResolvedValue(),
				registerAbilityCategory: jest.fn().mockResolvedValue(),
			},
		};
		let registry;

		jest.isolateModules( () => {
			// eslint-disable-next-line global-require
			require( '../../elementor-editor-mcp' );
			// eslint-disable-next-line global-require
			registry = require( '../registry' );
		} );

		await window[ BRIDGE_REGISTRATION_KEY ];
		expect( window.elementorV2.sdAiAgentElementorMcp.init ).toEqual(
			expect.any( Function )
		);
		expect( registerMcpAdapter ).toHaveBeenCalledTimes( 1 );
		await expect( registry.snapshotDescriptors() ).resolves.toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					name: 'sd-ai-agent-js/call-elementor-editor-mcp-tool',
				} ),
			] )
		);
	} );
} );
