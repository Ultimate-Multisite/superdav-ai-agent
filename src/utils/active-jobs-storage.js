/**
 * Active-jobs sessionStorage utility — cross-page navigation survival (t206).
 *
 * Persists active job IDs to sessionStorage on poll start and restores them
 * on FloatingWidget mount, allowing background job polling to survive same-tab
 * wp-admin page navigation (e.g. user navigates away and back while a job runs).
 *
 * Scope: sessionStorage is cleared when the tab is closed, so this utility
 * only covers same-tab navigation. Cross-session reconnection (tab close/reopen
 * or browser restart) requires the DB-backed endpoint from Phase 1c (t202).
 *
 * Silently tolerates storage unavailability (private mode, quota exceeded) —
 * cross-page survival is best-effort; the UI degrades gracefully.
 */

/** @type {string} sessionStorage key for the active-jobs map. */
const STORAGE_KEY = 'sdAiAgent_activeJobs';

/**
 * Parse and normalise raw sessionStorage content into a plain-object map.
 *
 * JSON.parse() can legitimately return null, an array, or a primitive — all of
 * which violate the `{}` contract expected by callers using Object.entries().
 * This helper always returns a plain object.
 *
 * @param {string|null} raw - Raw string from sessionStorage.getItem(), or null.
 * @return {Object} Validated plain-object map, or {} when invalid/empty.
 */
function parseActiveJobs( raw ) {
	if ( ! raw ) {
		return {};
	}
	const parsed = JSON.parse( raw );
	return parsed && typeof parsed === 'object' && ! Array.isArray( parsed )
		? parsed
		: {};
}

/**
 * Register or update an active job in sessionStorage.
 *
 * Called when a poll loop starts so the job survives navigation.
 *
 * @param {number} sessionId - Session the job belongs to.
 * @param {string} jobId     - Background job identifier.
 */
export function setActiveJob( sessionId, jobId ) {
	try {
		const raw = sessionStorage.getItem( STORAGE_KEY );
		const jobs = parseActiveJobs( raw );
		jobs[ sessionId ] = jobId;
		sessionStorage.setItem( STORAGE_KEY, JSON.stringify( jobs ) );
	} catch {
		// sessionStorage unavailable or quota exceeded — silently degrade.
	}
}

/**
 * Remove a session's job entry from sessionStorage when polling ends.
 *
 * Called on all poll-loop exit paths (complete, error, timeout,
 * awaiting_confirmation).
 *
 * @param {number} sessionId - Session identifier to clear.
 */
export function clearActiveJob( sessionId ) {
	try {
		const raw = sessionStorage.getItem( STORAGE_KEY );
		if ( ! raw ) {
			return;
		}
		const jobs = parseActiveJobs( raw );
		delete jobs[ sessionId ];
		if ( Object.keys( jobs ).length === 0 ) {
			sessionStorage.removeItem( STORAGE_KEY );
		} else {
			sessionStorage.setItem( STORAGE_KEY, JSON.stringify( jobs ) );
		}
	} catch {
		// Silently degrade.
	}
}

/**
 * Read all active jobs persisted in sessionStorage.
 *
 * Used by FloatingWidget on mount to restore interrupted poll loops
 * after same-tab wp-admin navigation.
 *
 * @return {Object} Map of sessionId (string key) → jobId (string value).
 *                  Returns an empty object when storage is unavailable or empty.
 */
export function getActiveJobs() {
	try {
		const raw = sessionStorage.getItem( STORAGE_KEY );
		return parseActiveJobs( raw );
	} catch {
		return {};
	}
}
