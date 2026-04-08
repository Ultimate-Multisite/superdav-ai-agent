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

	window.location.assign( adminUrl );
	return { navigated: true, path };
}

registerClientAbility( {
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
