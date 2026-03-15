/**
 * WordPress dependencies
 */
import { useEffect, useCallback } from '@wordpress/element';

/**
 * @typedef {import('../types').KeyboardShortcut} KeyboardShortcut
 */

/**
 * React hook to register document-level keyboard shortcuts.
 *
 * Attaches a single keydown listener that checks each registered combo.
 * The listener is re-registered whenever the shortcuts map or platform
 * detection changes.
 *
 * @param {Object.<string, Function>} shortcuts - Map of combo strings to handler
 *                                              functions. Keys use the format "mod+k", "mod+n", "escape", "mod+/".
 *                                              "mod" maps to Cmd (⌘) on macOS and Ctrl on all other platforms.
 * @return {void}
 */
export function useKeyboardShortcuts( shortcuts ) {
	const isMac =
		typeof navigator !== 'undefined' &&
		navigator.platform.indexOf( 'Mac' ) > -1;

	const handler = useCallback(
		( e ) => {
			for ( const [ combo, fn ] of Object.entries( shortcuts ) ) {
				if ( matchesCombo( e, combo, isMac ) ) {
					e.preventDefault();
					fn( e );
					return;
				}
			}
		},
		[ shortcuts, isMac ]
	);

	useEffect( () => {
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ handler ] );
}

/**
 * Test whether a KeyboardEvent matches a combo string.
 *
 * @param {KeyboardEvent} e     - The keyboard event to test.
 * @param {string}        combo - Combo string (e.g. 'mod+k', 'escape').
 * @param {boolean}       isMac - Whether the platform is macOS.
 * @return {boolean} True when the event matches the combo.
 */
function matchesCombo( e, combo, isMac ) {
	const parts = combo.toLowerCase().split( '+' );
	let needMod = false;
	let needShift = false;
	let key = '';

	for ( const part of parts ) {
		if ( part === 'mod' ) {
			needMod = true;
		} else if ( part === 'shift' ) {
			needShift = true;
		} else {
			key = part;
		}
	}

	if ( needMod ) {
		const modPressed = isMac ? e.metaKey : e.ctrlKey;
		if ( ! modPressed ) {
			return false;
		}
	}

	if ( needShift && ! e.shiftKey ) {
		return false;
	}

	// Map key names.
	const eventKey = e.key.toLowerCase();
	if ( key === 'escape' && eventKey === 'escape' ) {
		return true;
	}
	if ( key === '/' && ( eventKey === '/' || e.code === 'Slash' ) ) {
		return true;
	}

	return eventKey === key;
}

/**
 * All available keyboard shortcuts displayed in the help dialog.
 *
 * @type {KeyboardShortcut[]}
 */
export const SHORTCUTS = [
	{ combo: 'mod+n', label: 'New chat' },
	{ combo: 'mod+k', label: 'Search conversations' },
	{ combo: 'mod+/', label: 'Show shortcuts' },
	{ combo: 'Escape', label: 'Close dialog' },
];
