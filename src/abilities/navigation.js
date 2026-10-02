/**
 * Client-side navigation abilities.
 *
 * Navigates to a WordPress admin page. Uses window.location.assign() for now
 * (full-page nav) — can be upgraded to SPA navigation once core ships a router
 * primitive. Still a UX win because the model does not have to ask the user to click.
 *
 * Annotated readonly: true because it does not mutate site data.
 */

import apiFetch from '@wordpress/api-fetch';
import { registerClientAbility } from './registry';

/**
 * Execute the navigate-to ability.
 *
 * @param {Object} args
 * @param {string} args.path wp-admin-relative path (e.g. "plugins.php").
 * @return {{ navigated: boolean, path: string }} Navigation result.
 */
async function executeNavigateTo( args ) {
	// A refused navigation or link must not reuse an earlier deferred target.
	delete window._sdAiAgentPendingNavigation;
	const path = args?.path || '';
	const url = args?.url || '';
	if ( ! path && ! url ) {
		return { navigated: false, path: '' };
	}

	const validated = await apiFetch( {
		path: '/wp-abilities/v1/abilities/sd-ai-agent/navigate/run',
		method: 'POST',
		data: {
			input: {
				...( url ? { url } : { path } ),
				...( args?.blog_id ? { blog_id: args.blog_id } : {} ),
			},
		},
	} );
	if ( validated.action === 'link' ) {
		return {
			navigated: false,
			path,
			url: validated.url,
			message: validated.message,
		};
	}
	const target = new URL( validated.url );
	if (
		validated.action !== 'navigate' ||
		target.origin !== location.origin
	) {
		throw new Error( 'Invalid URL.' );
	}

	// Defer the actual navigation so jobSlice can POST the tool result back to
	// the server before the page unloads. Calling window.location.assign() here
	// would abort the in-flight fetch, leaving the job stuck in
	// `awaiting_client_tools` on the server. On the next page load the floating
	// widget restores the job from sessionStorage, finds the same pending call,
	// and navigates again — an infinite reload loop.
	//
	// jobSlice reads window._sdAiAgentPendingNavigation after the POST
	// succeeds, clears sessionStorage, and then triggers the navigation.
	window._sdAiAgentPendingNavigation = target.href;

	return { navigated: true, path };
}

/**
 * Register the navigate-to ability with the client-side abilities registry.
 *
 * Called by src/abilities/index.js after the sd-ai-agent-js category
 * has been registered. Must NOT self-register at module-eval time — ES
 * module imports are hoisted and would race the category registration
 * (the bug t166 fixes).
 *
 * @return {void}
 */
export async function registerNavigationAbility() {
	await registerClientAbility( {
		name: 'sd-ai-agent-js/navigate-to',
		label: 'Navigate',
		description:
			'Navigate within the current site. Other blogs return a validated link for the user to open in a new tab, preserving this chat.',
		inputSchema: {
			type: 'object',
			properties: {
				path: {
					type: 'string',
					description:
						'wp-admin-relative path, e.g. "plugins.php" or "edit.php?post_type=page".',
				},
				url: {
					type: 'string',
				},
				blog_id: {
					type: 'integer',
					description:
						'Known target blog ID, used with an admin-relative path.',
				},
			},
			anyOf: [ { required: [ 'path' ] }, { required: [ 'url' ] } ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				navigated: { type: 'boolean' },
				path: { type: 'string' },
				url: { type: 'string' },
				message: { type: 'string' },
			},
		},
		annotations: { readonly: true },
		callback: executeNavigateTo,
	} );
}
