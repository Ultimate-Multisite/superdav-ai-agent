/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';

/**
 * @typedef {{ width: number, height: number }} PanelSize
 * @typedef {{ size: PanelSize, isResizing: boolean, handleResizeMouseDown: (e: MouseEvent, direction: string) => void, resetSize: () => void }} UseResizeReturn
 */

const STORAGE_KEY = 'aiAgentWidgetSize';

/** Minimum panel dimensions in pixels. */
const MIN_WIDTH = 280;
const MIN_HEIGHT = 200;

/** Maximum panel dimensions (clamped to viewport at runtime). */
const MAX_WIDTH = 900;
const MAX_HEIGHT = 900;

/**
 * Custom hook for making the floating panel resizable.
 *
 * Supports dragging from the right, bottom, and bottom-right corner handles.
 * Persists the panel size to localStorage under the key `aiAgentWidgetSize`.
 * Size is null when using the CSS default (420×600).
 *
 * @return {UseResizeReturn} Resize state and handlers.
 */
export default function useResize() {
	const [ size, setSize ] = useState( () => {
		try {
			const saved = localStorage.getItem( STORAGE_KEY );
			return saved ? JSON.parse( saved ) : null;
		} catch {
			return null;
		}
	} );

	const [ isResizing, setIsResizing ] = useState( false );
	const resizeRef = useRef( {
		direction: '',
		startX: 0,
		startY: 0,
		startWidth: 0,
		startHeight: 0,
	} );

	/**
	 * Clamp size to viewport and min/max bounds.
	 *
	 * @param {number} width  - Desired width in pixels.
	 * @param {number} height - Desired height in pixels.
	 * @return {PanelSize} Clamped size.
	 */
	const clampSize = useCallback( ( width, height ) => {
		const vw = window.innerWidth;
		const vh = window.innerHeight;
		return {
			width: Math.max(
				MIN_WIDTH,
				Math.min( width, Math.min( MAX_WIDTH, vw - 48 ) )
			),
			height: Math.max(
				MIN_HEIGHT,
				Math.min( height, Math.min( MAX_HEIGHT, vh - 80 ) )
			),
		};
	}, [] );

	/**
	 * Begin a resize operation.
	 *
	 * @param {MouseEvent} e         - The mousedown event on the resize handle.
	 * @param {string}     direction - One of 'right', 'bottom', 'corner'.
	 */
	const handleResizeMouseDown = useCallback( ( e, direction ) => {
		if ( e.button !== 0 ) {
			return;
		}

		const panel = e.target.closest( '.gratis-ai-agent-floating-panel' );
		if ( ! panel ) {
			return;
		}

		const rect = panel.getBoundingClientRect();
		resizeRef.current = {
			direction,
			startX: e.clientX,
			startY: e.clientY,
			startWidth: rect.width,
			startHeight: rect.height,
		};

		setIsResizing( true );
		document.body.style.userSelect = 'none';

		const cursors = {
			right: 'ew-resize',
			bottom: 'ns-resize',
			corner: 'nwse-resize',
		};
		document.body.style.cursor = cursors[ direction ] || 'nwse-resize';

		e.preventDefault();
		e.stopPropagation(); // Prevent drag from starting.
	}, [] );

	useEffect( () => {
		if ( ! isResizing ) {
			return;
		}

		const handleMouseMove = ( e ) => {
			const { direction, startX, startY, startWidth, startHeight } =
				resizeRef.current;

			const dx = e.clientX - startX;
			const dy = e.clientY - startY;

			let newWidth = startWidth;
			let newHeight = startHeight;

			if ( direction === 'right' || direction === 'corner' ) {
				newWidth = startWidth + dx;
			}
			if ( direction === 'bottom' || direction === 'corner' ) {
				newHeight = startHeight + dy;
			}

			setSize( clampSize( newWidth, newHeight ) );
		};

		const handleMouseUp = () => {
			setIsResizing( false );
			document.body.style.userSelect = '';
			document.body.style.cursor = '';

			// Persist size.
			setSize( ( s ) => {
				if ( s ) {
					try {
						localStorage.setItem(
							STORAGE_KEY,
							JSON.stringify( s )
						);
					} catch {
						// ignore
					}
				}
				return s;
			} );
		};

		document.addEventListener( 'mousemove', handleMouseMove );
		document.addEventListener( 'mouseup', handleMouseUp );

		return () => {
			document.removeEventListener( 'mousemove', handleMouseMove );
			document.removeEventListener( 'mouseup', handleMouseUp );
		};
	}, [ isResizing, clampSize ] );

	const resetSize = useCallback( () => {
		setSize( null );
		try {
			localStorage.removeItem( STORAGE_KEY );
		} catch {
			// ignore
		}
	}, [] );

	return { size, isResizing, handleResizeMouseDown, resetSize };
}
