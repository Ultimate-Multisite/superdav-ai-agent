/**
 * WordPress dependencies
 */
import { useEffect, useCallback } from '@wordpress/element';

/**
 * @typedef {Object} ShortcutEntry
 * @property {string} combo - Key combo string (e.g. 'mod+k', 'Escape').
 * @property {string} label - Human-readable label for the shortcut.
 */

/**
 * Hook to register keyboard shortcuts.
 *
 * @param {Object.<string, Function>} shortcuts - Map of key combo strings to
 *                                              handler functions. Keys use format: "mod+k", "mod+n", "escape", "mod+/".
 *                                              "mod" maps to Cmd on Mac, Ctrl on other platforms.
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
 * Check whether a keyboard event matches a shortcut combo string.
 *
 * @param {KeyboardEvent} e     - The keyboard event to test.
 * @param {string}        combo - Combo string (e.g. 'mod+k', 'escape').
 * @param {boolean}       isMac - Whether the current platform is macOS.
 * @return {boolean} True if the event matches the combo.
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
 * All available keyboard shortcuts for the help dialog.
 *
 * @type {ShortcutEntry[]}
 */
export const SHORTCUTS = [
	{ combo: 'mod+n', label: 'New chat' },
	{ combo: 'mod+k', label: 'Search conversations' },
	{ combo: 'mod+/', label: 'Show shortcuts' },
	{ combo: 'Escape', label: 'Close dialog' },
];
