/**
 * Client-side abilities entry point.
 *
 * Registers all client-side abilities in the shared page-local registry.
 * Import this module at the top of each plugin entry point so registration
 * happens before the chat UI mounts.
 *
 * This module is idempotent — safe to import multiple times.
 *
 * The category step remains in the pipeline for compatibility, but the
 * category and discoverable ability stubs are registered server-side. Browser
 * callbacks stay local so malformed third-party providers cannot break the
 * shared WordPress abilities store during plugin bootstrap.
 *
 * Cross-bundle deduplication:
 * Each webpack entry-point bundle has its own module scope and therefore its
 * own `registrationPromise`. When multiple bundles (e.g. floating-widget.js
 * and admin-page.js) are enqueued on the same admin page, each bundle
 * can otherwise run the full local registration pipeline independently.
 * `ensureRegistered()` checks a page-level window global
 * (`window.__sdAiAgentAbilitiesRegistering`) before creating a new
 * Promise. If another bundle on the same page has already started or
 * completed the pipeline, the second bundle awaits the same Promise instead
 * of launching a duplicate registration.
 */

import { registerCategory } from './registry';
import { registerNavigationAbility } from './navigation';
import { registerRefreshPageAbility } from './refresh-page';
import { registerEditorAbility } from './editor';
import { registerEditorCapabilitiesAbility } from './editor-capabilities';
import {
	registerCaptureScreenshotAbility,
	registerScreenshotUrlAbility,
} from './screenshot';

/**
 * Window-global key used to share the registration Promise across all
 * webpack bundles on the same page. The first bundle to run
 * `ensureRegistered()` creates and stores the Promise; every subsequent
 * bundle (in any other webpack scope) reads and awaits it instead of
 * starting a new registration pipeline.
 *
 * @type {string}
 */
const WIN_REGISTRATION_KEY = '__sdAiAgentAbilitiesRegistering';

/**
 * Page-global Promise published by the optional Elementor editor package.
 *
 * Keeping this boundary as a string avoids loading the Elementor bridge into
 * the normal floating/admin chat bundles.
 *
 * @type {string}
 */
const WIN_ELEMENTOR_REGISTRATION_KEY =
	'__sdAiAgentElementorEditorMcpRegistration';

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
 * Await optional Elementor bridge registration when its editor-only package
 * is active on this page. Outside Elementor this resolves immediately.
 *
 * @return {Promise<void>} Optional package registration completion.
 */
function waitForElementorEditorMcpRegistration() {
	const registration = window[ WIN_ELEMENTOR_REGISTRATION_KEY ];
	if ( registration && typeof registration.then === 'function' ) {
		return registration.catch( () => undefined );
	}

	return Promise.resolve();
}

/**
 * Ensure all client-side abilities are registered.
 *
 * Idempotent — calling this multiple times within a single bundle returns
 * the same in-flight Promise. Cross-bundle dedup is provided by the
 * page-level `window.__sdAiAgentAbilitiesRegistering` key: if another
 * bundle on the same page has already started or completed registration,
 * this call returns the existing Promise without re-running the pipeline.
 *
 * @return {Promise<void>}
 */
export function ensureRegistered() {
	// Cross-bundle dedup: another webpack bundle on this page may have
	// already started or completed the registration pipeline.
	if ( window[ WIN_REGISTRATION_KEY ] ) {
		// The registry module keeps callback execution state page-global, so a
		// bundle that reuses this Promise can execute abilities registered by
		// the bundle that created it even without wp.abilities.executeAbility().
		registrationPromise = window[ WIN_REGISTRATION_KEY ];
		return Promise.all( [
			registrationPromise,
			waitForElementorEditorMcpRegistration(),
		] ).then( () => undefined );
	}

	// Same-bundle dedup: a concurrent caller within this bundle.
	if ( registrationPromise ) {
		return registrationPromise;
	}

	// Set both caches before any await so concurrent callers from this
	// bundle and from other bundles that load immediately after see the
	// in-flight Promise rather than starting a new one.
	registrationPromise = window[ WIN_REGISTRATION_KEY ] = ( async () => {
		// Preserve category-first ordering for the registration contract.
		await registerCategory();
		// Register callbacks and descriptors in the page-local store.
		await registerNavigationAbility();
		await registerRefreshPageAbility();
		await registerEditorAbility();
		await (
			await import( './editor-mutations' )
		).registerEditorMutationAbilities();
		await registerEditorCapabilitiesAbility();
		await (
			await import( './block-examples' )
		).registerCanonicalBlockExamplesAbility();
		await registerCaptureScreenshotAbility();
		await registerScreenshotUrlAbility();
		try {
			await ( await import( './page-quality-validator' ) ).default();
		} catch {
			// Keep the already-registered browser abilities available.
		}

		await waitForElementorEditorMcpRegistration();
	} )();

	return registrationPromise;
}

// Auto-register on import so plugin entry points only need a side-effect
// import (`import '../abilities';`) without remembering to call
// ensureRegistered. Callers that need to wait for registration to finish
// (e.g. the chat send-message thunk before snapshotting descriptors) can
// import { ensureRegistered } and `await` it.
ensureRegistered();
