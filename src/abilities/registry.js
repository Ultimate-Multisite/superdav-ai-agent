/**
 * Client-side abilities registry for gratis-ai-agent-js namespace.
 *
 * Thin wrapper around @wordpress/abilities that:
 * - Registers the gratis-ai-agent-js category (idempotent).
 * - Provides registerClientAbility() to register abilities with correct shape.
 * - Provides snapshotDescriptors() to capture current descriptors for posting
 *   to the server in the /chat request (used by sessionsSlice sendMessage thunk).
 */

const CATEGORY_SLUG = 'gratis-ai-agent-js';
const CATEGORY_LABEL = 'Gratis AI Agent (Client)';

let categoryRegistered = false;

/**
 * Register the gratis-ai-agent-js category (idempotent).
 *
 * @return {void}
 */
export function registerCategory() {
	if ( categoryRegistered ) {
		return;
	}
	if (
		typeof wp === 'undefined' ||
		! wp.abilities ||
		! wp.abilities.registerAbilityCategory
	) {
		return;
	}
	try {
		wp.abilities.registerAbilityCategory( CATEGORY_SLUG, {
			label: CATEGORY_LABEL,
		} );
		categoryRegistered = true;
	} catch {
		// Already registered or API unavailable — safe to ignore.
	}
}

/**
 * Register a single client-side ability.
 *
 * Shapes the definition with the correct category and meta.annotations,
 * and guards against double-registration.
 *
 * @param {Object}   def              Ability definition.
 * @param {string}   def.name         Fully-qualified ability name (gratis-ai-agent-js/...).
 * @param {string}   def.label        Human-readable label.
 * @param {string}   def.description  Description of what the ability does.
 * @param {Object}   def.inputSchema  JSON Schema for the ability's input.
 * @param {Object}   def.outputSchema JSON Schema for the ability's output.
 * @param {Object}   def.annotations  Annotations (e.g. { readonly: true }).
 * @param {Function} def.callback     The function to execute when the ability is called.
 * @return {void}
 */
export function registerClientAbility( def ) {
	if (
		typeof wp === 'undefined' ||
		! wp.abilities ||
		! wp.abilities.registerAbility
	) {
		return;
	}
	try {
		wp.abilities.registerAbility( def.name, {
			label: def.label,
			description: def.description,
			category: CATEGORY_SLUG,
			callback: def.callback,
			input_schema: def.inputSchema,
			output_schema: def.outputSchema,
			meta: {
				annotations: def.annotations || {},
			},
		} );
	} catch {
		// Already registered — safe to ignore.
	}
}

/**
 * Snapshot the current gratis-ai-agent-js/* ability descriptors as plain objects.
 *
 * Returns an array of descriptor objects suitable for posting to the server
 * as `client_abilities` in the /chat request body. The server validates each
 * name against JsAbilityCatalog::get_descriptors() and drops unknown names.
 *
 * @return {Array<{name: string, label: string, description: string, input_schema: Object, output_schema: Object, annotations: Object}>} Array of client ability descriptors.
 */
export function snapshotDescriptors() {
	if (
		typeof wp === 'undefined' ||
		! wp.data ||
		! wp.data.select( 'core/abilities' )
	) {
		return [];
	}

	try {
		const store = wp.data.select( 'core/abilities' );
		const allAbilities =
			typeof store.getAbilities === 'function'
				? store.getAbilities()
				: [];

		return allAbilities
			.filter(
				( ability ) =>
					ability.name &&
					ability.name.startsWith( CATEGORY_SLUG + '/' )
			)
			.map( ( ability ) => ( {
				name: ability.name,
				label: ability.label || ability.name,
				description: ability.description || '',
				input_schema: ability.input_schema || {},
				output_schema: ability.output_schema || {},
				annotations: ability.meta?.annotations || {},
			} ) );
	} catch {
		return [];
	}
}
