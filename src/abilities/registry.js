/**
 * Page-local registry for browser-executed SD AI Agent abilities.
 *
 * Browser abilities are deliberately kept out of WordPress's shared
 * `core/abilities` data store. Registering them there causes the store to
 * hydrate every provider's categories and abilities. One malformed provider
 * can then reject the shared hydration request, while server-registered
 * `sd-ai-agent-js/*` stubs make client category registration a duplicate.
 *
 * The server catalog remains the discovery source. This registry owns only
 * page-lifetime callbacks and the descriptors posted with chat requests.
 */

import { __ } from '@wordpress/i18n';

const CATEGORY_SLUG = 'sd-ai-agent-js';
const CATEGORY_LABEL = __( 'SD AI Agent', 'superdav-ai-agent' );
const CATEGORY_DESCRIPTION = __(
	'SD AI Agent browser abilities',
	'superdav-ai-agent'
);
const WIN_REGISTRY_KEY = '__sdAiAgentClientAbilityRegistry';
const WIN_API_KEY = 'sdAiAgentClientAbilities';

/**
 * Return the shared page-lifetime registry used by every webpack bundle.
 *
 * @return {{n: Set<string>, c: Map<string, Function>, d: Map<string, Object>}} Shared registry.
 */
function getPageRegistry() {
	const page = window;

	if ( page[ WIN_REGISTRY_KEY ] ) {
		return page[ WIN_REGISTRY_KEY ];
	}

	page[ WIN_REGISTRY_KEY ] = {
		n: new Set(),
		c: new Map(),
		d: new Map(),
	};

	return page[ WIN_REGISTRY_KEY ];
}

/**
 * Preserve the registration pipeline contract.
 *
 * The category is registered server-side by ToolDiscoveryHandler. No client
 * registration is needed, and avoiding the shared store prevents unrelated
 * provider hydration failures from reaching the browser console.
 *
 * @return {Promise<void>} Resolved registration step.
 */
export async function registerCategory() {}

/**
 * Register one browser ability in the page-local registry.
 *
 * @param {Object}   def              Ability definition.
 * @param {string}   def.name         Fully-qualified ability name.
 * @param {string}   def.label        Human-readable label.
 * @param {string}   def.description  Ability description.
 * @param {Object}   def.inputSchema  JSON Schema for input.
 * @param {Object}   def.outputSchema JSON Schema for output.
 * @param {Object}   def.annotations  Ability annotations.
 * @param {Function} def.callback     Browser execution callback.
 * @return {Promise<void>} Registration completion.
 */
export async function registerClientAbility( def ) {
	if ( ! def || typeof def.name !== 'string' || def.name === '' ) {
		return;
	}

	const registry = getPageRegistry();
	if ( registry.n.has( def.name ) ) {
		return;
	}

	registry.n.add( def.name );
	if ( typeof def.callback === 'function' ) {
		registry.c.set( def.name, def.callback );
	}
	registry.d.set( def.name, {
		name: def.name,
		label: def.label || def.name,
		description: def.description || '',
		input_schema: def.inputSchema || {},
		output_schema: def.outputSchema || {},
		annotations: def.annotations || {},
	} );
}

/**
 * Snapshot descriptors for the chat request.
 *
 * @return {Promise<Array<Object>>} Locally registered descriptors.
 */
export async function snapshotDescriptors() {
	return Array.from( getPageRegistry().d.values() );
}

/**
 * Execute a registered browser ability by name.
 *
 * @param {string} name Fully-qualified ability name.
 * @param {Object} args Ability arguments.
 * @return {Promise<Object>} Ability result.
 * @throws {Error} When the ability is not registered on this page.
 */
export async function executeClientAbility( name, args ) {
	const callback = getPageRegistry().c.get( name );
	if ( callback ) {
		return callback( args );
	}

	throw new Error(
		`Client ability "${ name }" is not registered on this page. ` +
			'Ensure the ability was registered via registerClientAbility() before the job completed.'
	);
}

/**
 * Publish a small browser API for diagnostics and end-to-end tests.
 *
 * This intentionally does not write to `wp.abilities`; that namespace belongs
 * to WordPress and using it would restart shared provider hydration.
 */
window[ WIN_API_KEY ] = {
	getAbilities: async () =>
		( await snapshotDescriptors() ).map( ( descriptor ) => ( {
			...descriptor,
			meta: { annotations: descriptor.annotations },
		} ) ),
	getAbilityCategory: async ( slug ) =>
		slug === CATEGORY_SLUG
			? {
					slug: CATEGORY_SLUG,
					label: CATEGORY_LABEL,
					description: CATEGORY_DESCRIPTION,
			  }
			: null,
	executeAbility: executeClientAbility,
};
