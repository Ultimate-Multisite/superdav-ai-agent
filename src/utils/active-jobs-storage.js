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
const STORAGE_KEY = 'gratisAiAgent_activeJobs';

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
		const jobs = raw ? JSON.parse( raw ) : {};
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
		const jobs = JSON.parse( raw );
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
		return raw ? JSON.parse( raw ) : {};
	} catch {
		return {};
	}
}
