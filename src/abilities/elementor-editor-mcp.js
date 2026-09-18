/**
 * Capability-detected bridge to Elementor V4's public in-editor MCP registry.
 *
 * Elementor documents `window.elementorV2.editorMcp.registerMcpAdapter()` as
 * the durable observer surface for editor-package contributors. This module
 * uses that adapter contract only: it never reads Elementor private globals,
 * invokes server-side ability-call, or writes Elementor post meta.
 */

import { getByteLength } from './editor';
import { registerClientAbility } from './registry';

const BRIDGE_STATE_KEY = '__sdAiAgentElementorEditorMcpBridge';
const MAX_TOOLS = 50;
const MAX_RESOURCES = 50;
const MAX_SELECTION_RESOURCES = 10;
const MAX_TOOL_NAME_BYTES = 200;
const MAX_RESOURCE_URI_BYTES = 2 * 1024;
const MAX_ARGUMENT_BYTES = 64 * 1024;
const MAX_INPUT_DEPTH = 12;
const MAX_OUTPUT_BYTES = 60 * 1024;
const MAX_OUTPUT_STRING_BYTES = 8 * 1024;
const MAX_OUTPUT_DEPTH = 6;
const MAX_OUTPUT_ENTRIES = 50;
const MAX_OUTPUT_NODES = 500;

/**
 * Return a safe fixed-shape document object for unavailable results.
 *
 * @return {{id: string, origin: string, path: string}} Empty document context.
 */
function emptyDocument() {
	return { id: '', origin: '', path: '' };
}

/**
 * Create a page-local opaque token that models can copy without normalizing it
 * as a URL. The token is a stale-document guard, not an authentication secret.
 *
 * @return {string} Opaque document fingerprint.
 */
function createOpaqueDocumentFingerprint() {
	const values = new Uint32Array( 4 );
	if ( window.crypto?.getRandomValues ) {
		window.crypto.getRandomValues( values );
	} else {
		for ( let index = 0; index < values.length; index++ ) {
			values[ index ] = Math.floor( Math.random() * 0x100000000 );
		}
	}

	return `elementor-document-${ Array.from( values )
		.map( ( value ) => value.toString( 16 ).padStart( 8, '0' ) )
		.join( '' ) }`;
}

/**
 * Determine whether a value can be handled as a JSON object.
 *
 * @param {*} value Candidate value.
 * @return {boolean} Whether the value is a non-array object.
 */
function isRecord( value ) {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}

/**
 * Reject property names that can change an object prototype when forwarded.
 *
 * @param {string} key Object key.
 * @return {boolean} Whether the key is unsafe.
 */
function isUnsafeObjectKey( key ) {
	return key === '__proto__' || key === 'constructor' || key === 'prototype';
}

/**
 * Truncate text without cutting a UTF-8 codepoint partway through.
 *
 * @param {string} value    Text to bound.
 * @param {number} maxBytes Maximum UTF-8 bytes.
 * @return {{value: string, truncated: boolean}} Bounded text.
 */
function truncateText( value, maxBytes ) {
	if ( getByteLength( value ) <= maxBytes ) {
		return { value, truncated: false };
	}

	let end = Math.min( value.length, maxBytes );
	while ( end > 0 && getByteLength( value.slice( 0, end ) ) > maxBytes ) {
		end--;
	}

	return { value: value.slice( 0, end ), truncated: true };
}

/**
 * Bound arbitrary browser-adapter output before it reaches a model transcript.
 *
 * @param {*} value Value returned by an Elementor MCP tool or resource.
 * @return {{value: *, truncated: boolean}} Bounded serializable result.
 */
function boundOutput( value ) {
	const budget = {
		remaining: MAX_OUTPUT_BYTES,
		remainingNodes: MAX_OUTPUT_NODES,
		truncated: false,
	};

	try {
		const boundedValue = boundValue( value, budget, 0 );
		const serialized = JSON.stringify( boundedValue );
		if (
			typeof serialized !== 'string' ||
			getByteLength( serialized ) > MAX_OUTPUT_BYTES
		) {
			return {
				value: { error: 'output_truncated' },
				truncated: true,
			};
		}

		return {
			value: boundedValue,
			truncated: budget.truncated,
		};
	} catch ( _error ) {
		return {
			value: { error: 'output_unavailable' },
			truncated: true,
		};
	}
}

/**
 * Recursively bound an adapter result using one shared byte budget.
 *
 * @param {*}      value  Candidate result value.
 * @param {Object} budget Shared remaining-byte budget.
 * @param {number} depth  Current object depth.
 * @return {*} Bounded result value.
 */
function boundValue( value, budget, depth ) {
	if ( budget.remainingNodes <= 0 ) {
		budget.truncated = true;
		return '[entry_limit]';
	}
	budget.remainingNodes--;

	if ( depth >= MAX_OUTPUT_DEPTH ) {
		budget.truncated = true;
		return '[depth_limit]';
	}

	if ( typeof value === 'string' ) {
		const limit = Math.max(
			0,
			Math.min( MAX_OUTPUT_STRING_BYTES, budget.remaining )
		);
		const bounded = truncateText( value, limit );
		budget.remaining -= getByteLength( bounded.value );
		budget.truncated = budget.truncated || bounded.truncated;
		return bounded.value;
	}

	if (
		value === null ||
		typeof value === 'boolean' ||
		( typeof value === 'number' && Number.isFinite( value ) )
	) {
		return value;
	}

	if ( typeof value === 'number' ) {
		budget.truncated = true;
		return String( value );
	}

	if ( Array.isArray( value ) ) {
		const values = value.slice( 0, MAX_OUTPUT_ENTRIES );
		if ( values.length !== value.length ) {
			budget.truncated = true;
		}
		return values.map( ( item ) => boundValue( item, budget, depth + 1 ) );
	}

	if ( isRecord( value ) ) {
		const result = {};
		const entries = Object.entries( value ).slice( 0, MAX_OUTPUT_ENTRIES );
		if ( entries.length !== Object.keys( value ).length ) {
			budget.truncated = true;
		}
		for ( const [ key, item ] of entries ) {
			if ( isUnsafeObjectKey( key ) ) {
				budget.truncated = true;
				continue;
			}
			const boundedKey = truncateText( key, 200 );
			budget.truncated = budget.truncated || boundedKey.truncated;
			result[ boundedKey.value ] = boundValue( item, budget, depth + 1 );
		}
		return result;
	}

	budget.truncated = true;
	return `[unsupported:${ typeof value }]`;
}

/**
 * Resolve Elementor's documented V2 editor MCP API without falling back to
 * unversioned or private Elementor globals.
 *
 * @return {{api: Object|null, reason: string}} Public API or unavailability reason.
 */
function getPublicApi() {
	if ( typeof window === 'undefined' || ! window.elementorV2 ) {
		return { api: null, reason: 'outside_elementor_editor' };
	}

	const api = window.elementorV2.editorMcp;
	if ( ! api ) {
		return { api: null, reason: 'unsupported_elementor_editor' };
	}

	if ( typeof api.registerMcpAdapter !== 'function' ) {
		return { api: null, reason: 'public_bridge_unavailable' };
	}

	return { api, reason: '' };
}

/**
 * Return current document identity without exposing query values such as nonces.
 *
 * @return {{available: boolean, reason: string, document: Object, fingerprint: string}} Current editor context.
 */
function getDocumentContext() {
	const publicApi = getPublicApi();
	if ( ! publicApi.api ) {
		return {
			available: false,
			reason: publicApi.reason,
			document: emptyDocument(),
			fingerprint: '',
		};
	}

	try {
		const url = new URL( window.location.href );
		const id =
			url.searchParams.get( 'post' ) ||
			url.searchParams.get( 'post_id' ) ||
			url.searchParams.get( 'document' ) ||
			'';
		const document = {
			id,
			origin: url.origin,
			path: url.pathname,
		};
		const identity = `${ document.origin }\n${ document.path }\n${ document.id }`;
		const state = getBridgeState();
		if ( ! state ) {
			throw new Error( 'bridge_state_unavailable' );
		}
		if (
			state.issuedDocumentIdentity !== identity ||
			! state.issuedDocumentFingerprint
		) {
			state.issuedDocumentIdentity = identity;
			state.issuedDocumentFingerprint = createOpaqueDocumentFingerprint();
		}

		return {
			available: true,
			reason: '',
			document,
			fingerprint: state.issuedDocumentFingerprint,
		};
	} catch ( _error ) {
		return {
			available: false,
			reason: 'document_unavailable',
			document: emptyDocument(),
			fingerprint: '',
		};
	}
}

/**
 * Create the page-lifetime bridge state shared by all plugin bundles.
 *
 * @return {Object} Empty bridge state.
 */
function createBridgeState() {
	return {
		active: false,
		adapter: null,
		api: null,
		documentFingerprint: '',
		failureReason: '',
		installed: false,
		issuedDocumentFingerprint: '',
		issuedDocumentIdentity: '',
		listenersAttached: false,
		resources: new Map(),
		tools: new Map(),
		truncatedResources: false,
		truncatedTools: false,
	};
}

/**
 * Return the validated page-global state, replacing a malformed value safely.
 *
 * @return {Object|null} Bridge state when a browser window is available.
 */
function getBridgeState() {
	if ( typeof window === 'undefined' ) {
		return null;
	}

	const existing = window[ BRIDGE_STATE_KEY ];
	if (
		existing &&
		typeof existing === 'object' &&
		existing.tools instanceof Map &&
		existing.resources instanceof Map
	) {
		return existing;
	}

	const state = createBridgeState();
	window[ BRIDGE_STATE_KEY ] = state;
	return state;
}

/**
 * Clear registrations captured for another active document.
 *
 * @param {Object} state       Page-global bridge state.
 * @param {string} fingerprint Current document fingerprint.
 * @return {void}
 */
function synchronizeDocument( state, fingerprint ) {
	if (
		state.documentFingerprint &&
		state.documentFingerprint !== fingerprint
	) {
		state.tools.clear();
		state.resources.clear();
		state.truncatedTools = false;
		state.truncatedResources = false;
	}

	state.documentFingerprint = fingerprint;
}

/**
 * Clear active registrations during browser navigation or teardown.
 *
 * @param {Object}  state      Page-global bridge state.
 * @param {boolean} deactivate Whether the editor page is unloading.
 * @return {void}
 */
function clearRegistrations( state, deactivate = false ) {
	state.tools.clear();
	state.resources.clear();
	state.documentFingerprint = '';
	state.issuedDocumentFingerprint = '';
	state.issuedDocumentIdentity = '';
	state.truncatedTools = false;
	state.truncatedResources = false;
	if ( deactivate ) {
		state.active = false;
	}
}

/**
 * Attach browser-standard lifecycle handlers once; Elementor does not need a
 * private event bridge for safe cleanup.
 *
 * @param {Object} state Page-global bridge state.
 * @return {void}
 */
function attachLifecycleHandlers( state ) {
	if ( state.listenersAttached || typeof window === 'undefined' ) {
		return;
	}

	window.addEventListener( 'pagehide', () =>
		clearRegistrations( state, true )
	);
	window.addEventListener( 'beforeunload', () =>
		clearRegistrations( state, true )
	);
	window.addEventListener( 'popstate', () => clearRegistrations( state ) );
	state.listenersAttached = true;
}

/**
 * Convert a public resource registration into a matchable local descriptor.
 *
 * @param {*} uriOrTemplate Elementor MCP URI or URI-template object.
 * @return {{uri: string, match: Function}|null} Matchable resource details.
 */
function createResourceMatcher( uriOrTemplate ) {
	if ( typeof uriOrTemplate === 'string' ) {
		return {
			uri: uriOrTemplate,
			match: ( uri ) => ( uri === uriOrTemplate ? {} : null ),
		};
	}

	const template = uriOrTemplate?.uriTemplate;
	if (
		! template ||
		typeof template.toString !== 'function' ||
		typeof template.match !== 'function'
	) {
		return null;
	}

	try {
		return {
			uri: template.toString(),
			match: ( uri ) => template.match( uri ),
		};
	} catch ( _error ) {
		return null;
	}
}

/**
 * Store one dynamically advertised Elementor tool for the active document.
 *
 * @param {Object} state Page-global bridge state.
 * @param {Object} tool  Public adapter tool descriptor.
 * @return {void}
 */
function captureTool( state, tool ) {
	if ( ! state.active || ! tool || typeof tool.execute !== 'function' ) {
		return;
	}

	const context = getDocumentContext();
	if ( ! context.available || typeof tool.name !== 'string' || ! tool.name ) {
		return;
	}
	if ( getByteLength( tool.name ) > MAX_TOOL_NAME_BYTES ) {
		state.truncatedTools = true;
		return;
	}

	synchronizeDocument( state, context.fingerprint );
	if ( ! state.tools.has( tool.name ) && state.tools.size >= MAX_TOOLS ) {
		state.truncatedTools = true;
		return;
	}

	const description = truncateText(
		typeof tool.description === 'string' ? tool.description : '',
		2 * 1024
	);
	const inputSchema = boundOutput(
		isRecord( tool.inputSchema ) ? tool.inputSchema : {}
	);
	if ( description.truncated || inputSchema.truncated ) {
		state.truncatedTools = true;
	}
	state.tools.set( tool.name, {
		description: description.value,
		descriptorTruncated: description.truncated || inputSchema.truncated,
		documentFingerprint: context.fingerprint,
		execute: tool.execute,
		inputSchema: inputSchema.value,
		name: tool.name,
	} );
}

/**
 * Store one dynamically advertised Elementor resource for the active document.
 *
 * @param {Object}   state         Page-global bridge state.
 * @param {string}   name          Public resource name.
 * @param {*}        uriOrTemplate Public URI or URI template.
 * @param {Function} handler       Public resource handler.
 * @return {void}
 */
function captureResource( state, name, uriOrTemplate, handler ) {
	if ( ! state.active || typeof handler !== 'function' ) {
		return;
	}

	const context = getDocumentContext();
	const matcher = createResourceMatcher( uriOrTemplate );
	if ( ! context.available || ! matcher || ! matcher.uri ) {
		return;
	}
	if ( getByteLength( matcher.uri ) > MAX_RESOURCE_URI_BYTES ) {
		state.truncatedResources = true;
		return;
	}

	synchronizeDocument( state, context.fingerprint );
	if (
		! state.resources.has( matcher.uri ) &&
		state.resources.size >= MAX_RESOURCES
	) {
		state.truncatedResources = true;
		return;
	}

	const resourceName = truncateText(
		typeof name === 'string' ? name : '',
		512
	);
	if ( resourceName.truncated ) {
		state.truncatedResources = true;
	}

	state.resources.set( matcher.uri, {
		documentFingerprint: context.fingerprint,
		handler,
		match: matcher.match,
		name: resourceName.value,
		uri: matcher.uri,
	} );
}

/**
 * Return an active, current bridge context or a structured unavailable state.
 *
 * @return {Object} Current bridge context with an optional state property.
 */
function getActiveContext() {
	const context = getDocumentContext();
	if ( ! context.available ) {
		return context;
	}

	const state = getBridgeState();
	if ( ! state || ! state.installed ) {
		return {
			...context,
			available: false,
			reason: 'bridge_not_initialized',
		};
	}
	if ( ! state.active ) {
		return {
			...context,
			available: false,
			reason: state.failureReason || 'bridge_torn_down',
		};
	}

	synchronizeDocument( state, context.fingerprint );
	return { ...context, state };
}

/**
 * Produce a bounded, serializable public view of one captured tool.
 *
 * @param {Object} tool Captured tool descriptor.
 * @return {Object} Safe descriptor for an AI tool result.
 */
function snapshotTool( tool ) {
	const description = truncateText( tool.description, 2 * 1024 );
	const schema = boundOutput( tool.inputSchema );

	return {
		confirmationRequired: true,
		description: description.value,
		inputSchema: schema.value,
		name: tool.name,
		truncated:
			Boolean( tool.descriptorTruncated ) ||
			description.truncated ||
			schema.truncated,
	};
}

/**
 * Produce a bounded, serializable public view of one captured resource.
 *
 * @param {Object} resource Captured resource descriptor.
 * @return {Object} Safe resource descriptor.
 */
function snapshotResource( resource ) {
	return {
		name: truncateText( resource.name, 512 ).value,
		uri: resource.uri,
	};
}

/**
 * Return selection-related resources only when Elementor explicitly advertises
 * them, rather than inferring editor state from a private DOM or global.
 *
 * @param {Object[]} resources Captured resource snapshots.
 * @return {Object[]} Bounded selection-resource hints.
 */
function getSelectionResources( resources ) {
	return resources
		.filter( ( resource ) =>
			/(selection|selected|context)/i.test(
				`${ resource.name } ${ resource.uri }`
			)
		)
		.slice( 0, MAX_SELECTION_RESOURCES );
}

/**
 * Return bounded current document context and advertised selection resources.
 *
 * @return {Promise<Object>} Current browser-only Elementor context.
 */
export async function getElementorEditorMcpContext() {
	const context = getActiveContext();
	const resources = context.available
		? Array.from( context.state.resources.values() )
				.filter(
					( resource ) =>
						resource.documentFingerprint === context.fingerprint
				)
				.map( snapshotResource )
		: [];
	const selectionResources = getSelectionResources( resources );

	return {
		available: context.available,
		document: context.document,
		fingerprint: context.fingerprint,
		reason: context.reason || '',
		selection: {
			available: selectionResources.length > 0,
			reason: selectionResources.length
				? 'advertised_resource_available'
				: 'selection_not_advertised',
			resources: selectionResources,
		},
	};
}

/**
 * List the currently advertised public Elementor editor tools and resources.
 *
 * Dynamic tools are deliberately returned as data instead of becoming a static
 * server-side catalog: only the bounded bridge entry points are catalogued.
 *
 * @return {Promise<Object>} Bounded live capability manifest.
 */
export async function listElementorEditorMcpCapabilities() {
	const context = getActiveContext();
	const state = context.state;
	const tools = context.available
		? Array.from( state.tools.values() )
				.filter(
					( tool ) => tool.documentFingerprint === context.fingerprint
				)
				.map( snapshotTool )
		: [];
	const resources = context.available
		? Array.from( state.resources.values() )
				.filter(
					( resource ) =>
						resource.documentFingerprint === context.fingerprint
				)
				.map( snapshotResource )
		: [];

	return {
		available: context.available,
		document: context.document,
		fingerprint: context.fingerprint,
		reason: context.reason || '',
		resources,
		tools,
		truncated: Boolean(
			context.available &&
				( state.truncatedTools || state.truncatedResources )
		),
	};
}

/**
 * Return whether an input fingerprint remains bound to the active document.
 *
 * @param {Object} args    Client ability input.
 * @param {Object} context Active bridge context.
 * @return {boolean} Whether the request is current.
 */
function hasCurrentFingerprint( args, context ) {
	return (
		typeof args?.expectedDocumentFingerprint === 'string' &&
		args.expectedDocumentFingerprint === context.fingerprint
	);
}

/**
 * Reject unsafe property names and deeply nested input before forwarding it.
 *
 * @param {*}      value Candidate JSON value.
 * @param {number} depth Current nesting depth.
 * @return {boolean} Whether the value contains only safe JSON values.
 */
function hasSafeJsonKeys( value, depth = 0 ) {
	if ( depth > MAX_INPUT_DEPTH ) {
		return false;
	}

	if (
		value === null ||
		typeof value === 'string' ||
		typeof value === 'boolean' ||
		typeof value === 'number'
	) {
		return true;
	}

	if ( Array.isArray( value ) ) {
		return value.every( ( item ) => hasSafeJsonKeys( item, depth + 1 ) );
	}

	if ( isRecord( value ) ) {
		return Object.entries( value ).every(
			( [ key, item ] ) =>
				! isUnsafeObjectKey( key ) && hasSafeJsonKeys( item, depth + 1 )
		);
	}

	return false;
}

/**
 * Return whether tool arguments are a bounded JSON record.
 *
 * @param {*} args Tool argument candidate.
 * @return {boolean} Whether arguments are safe to forward to Elementor.
 */
function hasBoundedArguments( args ) {
	if ( ! isRecord( args ) ) {
		return false;
	}

	try {
		return (
			hasSafeJsonKeys( args ) &&
			getByteLength( JSON.stringify( args ) ) <= MAX_ARGUMENT_BYTES
		);
	} catch ( _error ) {
		return false;
	}
}

/**
 * Find a resource entry and its URI-template variables for an exact request.
 *
 * @param {Object} state Current bridge state.
 * @param {string} uri   Requested URI.
 * @return {{resource: Object, variables: Object}|null} Matching resource.
 */
function findResource( state, uri ) {
	for ( const resource of state.resources.values() ) {
		try {
			const variables = resource.match( uri );
			if ( isRecord( variables ) ) {
				return { resource, variables };
			}
		} catch ( _error ) {
			// A malformed third-party matcher must not break other advertised resources.
		}
	}

	return null;
}

/**
 * Read only a resource explicitly advertised by the active Elementor editor.
 *
 * @param {Object} args Resource arguments.
 * @return {Promise<Object>} Bounded resource result.
 */
export async function readElementorEditorMcpResource( args = {} ) {
	const context = getActiveContext();
	const uri = typeof args.uri === 'string' ? args.uri : '';
	const base = {
		documentFingerprint: context.fingerprint || '',
		error: '',
		reason: context.reason || '',
		result: {},
		success: false,
		truncated: false,
		uri,
	};

	if ( ! context.available ) {
		return base;
	}
	if ( ! hasCurrentFingerprint( args, context ) ) {
		return { ...base, reason: 'stale_document' };
	}
	if ( ! uri || getByteLength( uri ) > MAX_RESOURCE_URI_BYTES ) {
		return { ...base, reason: 'invalid_resource_uri' };
	}

	const found = findResource( context.state, uri );
	if (
		! found ||
		found.resource.documentFingerprint !== context.fingerprint
	) {
		return { ...base, reason: 'resource_unavailable' };
	}

	try {
		const result = await found.resource.handler(
			new URL( uri ),
			found.variables
		);
		const after = getActiveContext();
		if ( ! after.available || after.fingerprint !== context.fingerprint ) {
			return { ...base, reason: 'document_changed' };
		}
		const bounded = boundOutput( result );

		return {
			...base,
			documentFingerprint: after.fingerprint,
			reason: '',
			result: bounded.value,
			success: true,
			truncated: bounded.truncated,
		};
	} catch ( error ) {
		const message = truncateText(
			error instanceof Error ? error.message : 'resource_failed',
			512
		);
		return {
			...base,
			error: message.value,
			reason: 'resource_failed',
			truncated: message.truncated,
		};
	}
}

/**
 * Execute only an explicitly advertised Elementor editor tool.
 *
 * This bridge ability is intentionally mutating (`readonly: false`) for every
 * dynamic tool invocation. The public adapter descriptor exposes no stable
 * destructive annotation, so routing every call through Superdav's existing
 * confirmation UI is the safe conservative contract.
 *
 * @param {Object} args Tool invocation arguments.
 * @return {Promise<Object>} Bounded tool result.
 */
export async function callElementorEditorMcpTool( args = {} ) {
	const context = getActiveContext();
	const toolName = typeof args.toolName === 'string' ? args.toolName : '';
	const base = {
		documentFingerprint: context.fingerprint || '',
		error: '',
		reason: context.reason || '',
		result: {},
		success: false,
		toolName,
		truncated: false,
		mutationPossible: false,
		outcome: 'not_started',
	};

	if ( ! context.available ) {
		return base;
	}
	if ( ! hasCurrentFingerprint( args, context ) ) {
		return { ...base, reason: 'stale_document' };
	}
	if ( ! toolName || getByteLength( toolName ) > MAX_TOOL_NAME_BYTES ) {
		return { ...base, reason: 'invalid_tool_name' };
	}
	if ( ! hasBoundedArguments( args.arguments ) ) {
		return { ...base, reason: 'invalid_tool_arguments' };
	}

	const tool = context.state.tools.get( toolName );
	if ( ! tool || tool.documentFingerprint !== context.fingerprint ) {
		return { ...base, reason: 'tool_unavailable' };
	}

	try {
		const result = await tool.execute( args.arguments );
		const after = getActiveContext();
		if ( ! after.available || after.fingerprint !== context.fingerprint ) {
			return { ...base, reason: 'document_changed' };
		}
		const bounded = boundOutput( result );
		const isError = isRecord( result ) && result.isError === true;

		return {
			...base,
			documentFingerprint: after.fingerprint,
			reason: isError ? 'tool_error' : '',
			result: bounded.value,
			success: ! isError,
			truncated: bounded.truncated,
			mutationPossible: isError,
			outcome: isError ? 'unknown' : 'completed',
		};
	} catch ( error ) {
		const message = truncateText(
			error instanceof Error ? error.message : 'tool_failed',
			512
		);
		return {
			...base,
			error: message.value,
			reason: 'tool_outcome_unknown',
			truncated: message.truncated,
			mutationPossible: true,
			outcome: 'unknown',
		};
	}
}

/**
 * Install the public Elementor adapter from the dedicated V2 editor package.
 *
 * @return {boolean} Whether the public bridge was installed.
 */
export function installElementorEditorMcpBridge() {
	const publicApi = getPublicApi();
	if ( ! publicApi.api ) {
		return false;
	}

	const state = getBridgeState();
	if ( ! state ) {
		return false;
	}

	const context = getDocumentContext();
	if ( ! context.available ) {
		return false;
	}

	if ( state.installed && state.api === publicApi.api ) {
		state.active = true;
		state.failureReason = '';
		synchronizeDocument( state, context.fingerprint );
		attachLifecycleHandlers( state );
		return true;
	}

	state.active = true;
	state.api = publicApi.api;
	state.failureReason = '';
	state.adapter = {
		activate: () => Promise.resolve(),
		onResourceRegistered: ( name, uriOrTemplate, handler ) =>
			captureResource( state, name, uriOrTemplate, handler ),
		onToolRegistered: ( tool ) => captureTool( state, tool ),
		sendResourceUpdated: () => undefined,
	};

	try {
		publicApi.api.registerMcpAdapter( state.adapter );
		state.installed = true;
		synchronizeDocument( state, context.fingerprint );
		attachLifecycleHandlers( state );
		return true;
	} catch ( _error ) {
		state.active = false;
		state.failureReason = 'bridge_registration_failed';
		return false;
	}
}

/**
 * Register the bounded Superdav client abilities that expose live Elementor
 * registrations. Callbacks remain safely unavailable on non-Elementor pages.
 *
 * @return {Promise<void>} Registration completion.
 */
export async function registerElementorEditorMcpAbilities() {
	await registerClientAbility( {
		name: 'sd-ai-agent-js/get-elementor-editor-mcp-context',
		label: 'Get Elementor Editor Context',
		description:
			'Return bounded current Elementor editor document identity and explicitly advertised selection-resource hints without changing editor state.',
		inputSchema: {
			type: 'object',
			properties: {},
		},
		outputSchema: {
			type: 'object',
			properties: {
				available: { type: 'boolean' },
				document: { type: 'object' },
				fingerprint: { type: 'string' },
				reason: { type: 'string' },
				selection: { type: 'object' },
			},
		},
		annotations: { readonly: true },
		callback: getElementorEditorMcpContext,
	} );

	await registerClientAbility( {
		name: 'sd-ai-agent-js/list-elementor-editor-mcp-capabilities',
		label: 'List Elementor Editor MCP Capabilities',
		description:
			"List bounded tools and resources currently advertised by Elementor's active public in-editor MCP bridge.",
		inputSchema: {
			type: 'object',
			properties: {},
		},
		outputSchema: {
			type: 'object',
			properties: {
				available: { type: 'boolean' },
				document: { type: 'object' },
				fingerprint: { type: 'string' },
				reason: { type: 'string' },
				resources: { type: 'array', items: { type: 'object' } },
				tools: { type: 'array', items: { type: 'object' } },
				truncated: { type: 'boolean' },
			},
		},
		annotations: { readonly: true },
		callback: listElementorEditorMcpCapabilities,
	} );

	await registerClientAbility( {
		name: 'sd-ai-agent-js/read-elementor-editor-mcp-resource',
		label: 'Read Elementor Editor MCP Resource',
		description:
			'Read a bounded resource only when it is currently advertised by the active Elementor editor and the supplied document fingerprint still matches.',
		inputSchema: {
			type: 'object',
			properties: {
				uri: { type: 'string' },
				expectedDocumentFingerprint: { type: 'string' },
			},
			required: [ 'uri', 'expectedDocumentFingerprint' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				documentFingerprint: { type: 'string' },
				error: { type: 'string' },
				reason: { type: 'string' },
				result: {
					type: [
						'object',
						'array',
						'string',
						'number',
						'boolean',
						'null',
					],
				},
				success: { type: 'boolean' },
				truncated: { type: 'boolean' },
				uri: { type: 'string' },
			},
		},
		annotations: { readonly: true },
		callback: readElementorEditorMcpResource,
	} );

	await registerClientAbility( {
		name: 'sd-ai-agent-js/call-elementor-editor-mcp-tool',
		label: 'Call Elementor Editor MCP Tool',
		description:
			'Call a currently advertised Elementor editor MCP tool only after Superdav confirmation and only when the supplied document fingerprint still matches. If outcome is unknown, inspect the current document before retrying because the tool may have mutated it before failing.',
		inputSchema: {
			type: 'object',
			properties: {
				arguments: { type: 'object' },
				expectedDocumentFingerprint: { type: 'string' },
				toolName: { type: 'string' },
			},
			required: [
				'toolName',
				'arguments',
				'expectedDocumentFingerprint',
			],
		},
		outputSchema: {
			type: 'object',
			properties: {
				documentFingerprint: { type: 'string' },
				error: { type: 'string' },
				reason: { type: 'string' },
				result: {
					type: [
						'object',
						'array',
						'string',
						'number',
						'boolean',
						'null',
					],
				},
				success: { type: 'boolean' },
				toolName: { type: 'string' },
				truncated: { type: 'boolean' },
				mutationPossible: { type: 'boolean' },
				outcome: {
					type: 'string',
					enum: [ 'not_started', 'completed', 'unknown' ],
				},
			},
		},
		annotations: { readonly: false },
		callback: callElementorEditorMcpTool,
	} );
}
