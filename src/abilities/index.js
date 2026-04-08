/**
 * Client-side abilities entry point.
 *
 * Registers the gratis-ai-agent-js category and all client-side abilities.
 * Import this module at the top of each plugin entry point so registration
 * happens before the chat UI mounts.
 *
 * This module is idempotent — safe to import multiple times.
 */

import { registerCategory } from './registry';
import './navigation';
import './editor';

let registered = false;

/**
 * Ensure all client-side abilities are registered.
 *
 * Idempotent — calling this multiple times is safe.
 *
 * @return {void}
 */
export function ensureRegistered() {
	if ( registered ) {
		return;
	}
	registerCategory();
	// navigation.js and editor.js self-register on import above.
	registered = true;
}

// Auto-register on import.
ensureRegistered();
