/**
 * Client-side abilities entry point.
 *
 * Registers the gratis-ai-agent-js category and all client-side abilities
 * into the WP 7.0 `core/abilities` store. Import this module at the top of
 * each plugin entry point so registration happens before the chat UI mounts.
 *
 * This module is idempotent — safe to import multiple times.
 *
 * Order matters here: the category MUST be registered (and its async
 * `registerAbilityCategory` Promise MUST resolve) before any ability that
 * lives in it. Previously navigation.js and editor.js self-registered at
 * module-eval time and the registry helpers called the WP 7.0 API
 * synchronously without awaiting the returned Promises — leaving abilities
 * trying to register into a not-yet-resolved category and the
 * `@wordpress/abilities` library throwing
 * "Ability references non-existent category" errors. (Fix landed in t166.)
 *
 * Each entry-point bundle has its own webpack-bundled module instance of
 * this file (and therefore its own `registrationPromise`), so we additionally
 * dedupe at the `@wordpress/abilities` API level inside registry.js — both
 * `registerCategory()` and `registerClientAbility()` swallow "already
 * registered" errors as a safe no-op.
 */

import { registerCategory } from './registry';
import { registerNavigationAbility } from './navigation';
import { registerEditorAbility } from './editor';

/**
 * Single in-flight registration Promise for this module instance, so
 * concurrent callers (e.g. multiple components in the same bundle that
 * each call ensureRegistered()) await the same pipeline rather than
 * racing.
 *
 * @type {Promise<void>|null}
 */
let registrationPromise = null;

/**
 * Ensure all client-side abilities are registered.
 *
 * Idempotent — calling this multiple times within a single bundle returns
 * the same in-flight Promise. Safe to import from multiple entry points
 * because the underlying registry helpers in registry.js dedupe at the
 * `@wordpress/abilities` API level.
 *
 * If the abilities API was not available when the attempt ran (e.g. the
 * `@wordpress/core-abilities` script module hadn't loaded yet),
 * registerCategory() silently no-ops and the promise resolves without
 * registering anything. In that case registrationPromise is reset to null
 * AFTER the attempt completes so a future call can retry. Concurrent
 * callers during the in-flight attempt all receive the same promise —
 * the reset only happens once the promise has settled.
 *
 * @return {Promise<void>}
 */
export function ensureRegistered() {
	if ( registrationPromise ) {
		return registrationPromise;
	}

	registrationPromise = ( async () => {
		// Category MUST come first AND its Promise MUST resolve before
		// abilities can register into it.
		await registerCategory();
		// Now safe to register abilities — the category exists in the store.
		await registerNavigationAbility();
		await registerEditorAbility();

		// If the abilities API was not available (e.g. script module not
		// yet loaded), the registration calls above silently no-oped.
		// Reset the promise so a future call can retry after the API loads.
		// This MUST happen after the await chain settles — resetting during
		// the in-flight attempt would break concurrent callers' dedup.
		const apiAvailable =
			typeof wp !== 'undefined' &&
			!! wp.abilities &&
			typeof wp.abilities.getAbilities === 'function';
		if ( ! apiAvailable ) {
			registrationPromise = null;
		}
	} )();

	return registrationPromise;
}

// Auto-register on import so plugin entry points only need a side-effect
// import (`import '../abilities';`) without remembering to call
// ensureRegistered. Callers that need to wait for registration to finish
// (e.g. the chat send-message thunk before snapshotting descriptors) can
// import { ensureRegistered } and `await` it.
ensureRegistered();
