/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Parse a shortcut string like "alt+a" or "ctrl+shift+k" into a descriptor.
 *
 * @param {string} shortcut - Shortcut string (case-insensitive, e.g. "alt+a").
 * @return {{ key: string, alt: boolean, ctrl: boolean, shift: boolean, meta: boolean }|null}
 *   Parsed descriptor, or null if the shortcut is empty/invalid.
 */
function parseShortcut( shortcut ) {
	if ( ! shortcut ) {
		return null;
	}

	const parts = shortcut.toLowerCase().split( '+' );
	const key = parts[ parts.length - 1 ];

	if ( ! key ) {
		return null;
	}

	return {
		key,
		alt: parts.includes( 'alt' ),
		ctrl: parts.includes( 'ctrl' ),
		shift: parts.includes( 'shift' ),
		meta: parts.includes( 'meta' ) || parts.includes( 'cmd' ),
	};
}

/**
 * Custom hook that listens for a configurable keyboard shortcut and calls
 * the provided callback when it fires.
 *
 * The shortcut string format is modifier keys joined by `+`, with the
 * character key last. Examples: `"alt+a"`, `"ctrl+shift+k"`.
 *
 * The listener is attached to `document` and skipped when the active element
 * is an input, textarea, or contenteditable node to avoid interfering with
 * typing.
 *
 * @param {string}   shortcut - Shortcut string (e.g. "alt+a"). Empty string disables.
 * @param {Function} callback - Function to call when the shortcut fires.
 */
export default function useKeyboardShortcut( shortcut, callback ) {
	useEffect( () => {
		const descriptor = parseShortcut( shortcut );
		if ( ! descriptor ) {
			return;
		}

		const handleKeyDown = ( e ) => {
			// Skip when typing in an input or editable element.
			const tag = e.target?.tagName?.toLowerCase();
			if (
				tag === 'input' ||
				tag === 'textarea' ||
				tag === 'select' ||
				e.target?.isContentEditable
			) {
				return;
			}

			if (
				e.key.toLowerCase() === descriptor.key &&
				e.altKey === descriptor.alt &&
				e.ctrlKey === descriptor.ctrl &&
				e.shiftKey === descriptor.shift &&
				e.metaKey === descriptor.meta
			) {
				e.preventDefault();
				callback();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ shortcut, callback ] );
}
