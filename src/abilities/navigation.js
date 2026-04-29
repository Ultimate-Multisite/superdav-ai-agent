/**
 * Client-side navigate-to ability.
 *
 * Navigates to a WordPress admin page. Uses window.location.assign() for now
 * (full-page nav) — can be upgraded to SPA navigation once core ships a router
 * primitive. Still a UX win because the model does not have to ask the user to click.
 *
 * Annotated readonly: true because it does not mutate site data.
 */

import { registerClientAbility } from './registry';

/**
 * Execute the navigate-to ability.
 *
 * @param {Object} args
 * @param {string} args.path wp-admin-relative path (e.g. "plugins.php").
 * @return {{ navigated: boolean, path: string }} Navigation result.
 */
function executeNavigateTo( args ) {
	const path = args?.path || '';
	if ( ! path ) {
		return { navigated: false, path: '' };
	}

	// Build the full admin URL.
	const adminUrl =
		typeof window.wpApiSettings?.root !== 'undefined'
			? window.location.origin + '/wp-admin/' + path.replace( /^\//, '' )
			: '/wp-admin/' + path.replace( /^\//, '' );

	// Defer the actual navigation so jobSlice can POST the tool result back to
	// the server before the page unloads. Calling window.location.assign() here
	// would abort the in-flight fetch, leaving the job stuck in
	// `awaiting_client_tools` on the server. On the next page load the floating
	// widget restores the job from sessionStorage, finds the same pending call,
	// and navigates again — an infinite reload loop.
	//
	// jobSlice reads window._gratisAiAgentPendingNavigation after the POST
	// succeeds, clears sessionStorage, and then triggers the navigation.
	window._gratisAiAgentPendingNavigation = adminUrl;

	return { navigated: true, path };
}

/**
 * Register the navigate-to ability with the client-side abilities registry.
 *
 * Called by src/abilities/index.js after the gratis-ai-agent-js category
 * has been registered. Must NOT self-register at module-eval time — ES
 * module imports are hoisted and would race the category registration
 * (the bug t166 fixes).
 *
 * @return {void}
 */
export async function registerNavigationAbility() {
	await registerClientAbility( {
		name: 'gratis-ai-agent-js/navigate-to',
		label: 'Navigate to Admin Page',
		description:
			'Navigate to a WordPress admin page without a full page reload when inside the admin SPA.',
		inputSchema: {
			type: 'object',
			properties: {
				path: {
					type: 'string',
					description:
						'wp-admin-relative path, e.g. "plugins.php" or "edit.php?post_type=page".',
				},
			},
			required: [ 'path' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				navigated: { type: 'boolean' },
				path: { type: 'string' },
			},
		},
		annotations: { readonly: true },
		callback: executeNavigateTo,
	} );
}
