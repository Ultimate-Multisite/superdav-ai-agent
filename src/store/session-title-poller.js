import apiFetch from '@wordpress/api-fetch';

const activeTitlePollers = new Set();
const MAX_POLL_ATTEMPTS = 20;
const MAX_POLL_DELAY_MS = 8000;
const INITIAL_POLL_DELAY_MS = 2000;

/**
 * Refresh a session title independently of the main agent job.
 *
 * @param {number} sessionId        Session identifier.
 * @param {Object} context          WordPress data context.
 * @param {Object} context.dispatch Store dispatchers.
 * @param {Object} context.select   Store selectors.
 * @return {Promise<void>} Resolves when title generation finishes or polling fails.
 */
export default async function pollSessionTitle(
	sessionId,
	{ dispatch, select }
) {
	if ( activeTitlePollers.has( sessionId ) ) {
		return;
	}
	activeTitlePollers.add( sessionId );
	const currentTitle = () =>
		select.getSessions().find( ( session ) => session.id === sessionId )
			?.title;
	let expectedTitle = currentTitle();
	try {
		for ( let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt++ ) {
			const { title, pending } = await apiFetch( {
				path: `/sd-ai-agent/v1/sessions/${ sessionId }/title`,
			} );
			if ( expectedTitle && currentTitle() !== expectedTitle ) {
				// Preserve a rename made while this request was in flight.
				return;
			}
			dispatch.updateSessionTitle( sessionId, title );
			expectedTitle = title;
			if ( ! pending ) {
				return;
			}
			if ( attempt < MAX_POLL_ATTEMPTS - 1 ) {
				const delay = Math.min(
					INITIAL_POLL_DELAY_MS * 1.5 ** attempt,
					MAX_POLL_DELAY_MS
				);
				await new Promise( ( resolve ) =>
					setTimeout( resolve, delay )
				);
			}
		}
	} catch {
		// A missing title or expired session must not affect the main job.
	} finally {
		activeTitlePollers.delete( sessionId );
	}
}
