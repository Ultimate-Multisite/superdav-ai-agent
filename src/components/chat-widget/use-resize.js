/**
 * Resize hook for the floating widget panel.
 *
 * Exposes:
 *   const { size, isResizing, handleResizeMouseDown, resetSize } = useResize( {
 *     storageKey,
 *     min: { w: 320, h: 400 },
 *     max: { w: 900, h: 1000 },
 *     defaultSize: { w: 400, h: 640 },
 *   } );
 *
 *   <div onMouseDown={ (e) => handleResizeMouseDown(e, 'right') } />
 *
 * Direction is one of 'right', 'bottom', 'corner', 'left', 'top'. The
 * hook clamps the new size to the min/max bounds and to the viewport,
 * persisting to localStorage under `storageKey`.
 */

import { useState, useRef, useCallback, useEffect } from '@wordpress/element';

/**
 *
 * @param {string} storageKey
 */
function readSize( storageKey ) {
	try {
		const saved = localStorage.getItem( storageKey );
		return saved ? JSON.parse( saved ) : null;
	} catch {
		return null;
	}
}

/**
 *
 * @param {string} storageKey
 * @param {Object} size
 */
function writeSize( storageKey, size ) {
	try {
		localStorage.setItem( storageKey, JSON.stringify( size ) );
	} catch {
		// ignore storage errors
	}
}

/**
 *
 * @param {Object} root0
 * @param {string} root0.storageKey
 * @param {Object} root0.min
 * @param {Object} root0.max
 * @param {Object} root0.defaultSize
 */
export default function useResize( {
	storageKey,
	min = { w: 320, h: 400 },
	max = { w: 900, h: 1000 },
	defaultSize = { w: 400, h: 640 },
} ) {
	const [ size, setSize ] = useState( () => readSize( storageKey ) );
	const [ isResizing, setIsResizing ] = useState( false );
	const directionRef = useRef( 'corner' );
	const startRef = useRef( null );

	const clampSize = useCallback(
		( w, h ) => ( {
			w: Math.max( min.w, Math.min( max.w, w ) ),
			h: Math.max( min.h, Math.min( max.h, h ) ),
		} ),
		[ min.w, min.h, max.w, max.h ]
	);

	const handleResizeMouseDown = useCallback(
		( e, direction ) => {
			if ( e.button !== 0 ) {
				return;
			}
			directionRef.current = direction;
			const starting = size || defaultSize;
			startRef.current = {
				x: e.clientX,
				y: e.clientY,
				w: starting.w,
				h: starting.h,
			};
			setIsResizing( true );
			document.body.style.userSelect = 'none';
			e.preventDefault();
			e.stopPropagation();
		},
		[ size, defaultSize ]
	);

	useEffect( () => {
		if ( ! isResizing ) {
			return undefined;
		}
		const handleMouseMove = ( e ) => {
			const start = startRef.current;
			if ( ! start ) {
				return;
			}
			const dir = directionRef.current;
			let nextW = start.w;
			let nextH = start.h;
			if ( dir === 'right' || dir === 'corner' ) {
				nextW = start.w + ( e.clientX - start.x );
			}
			if ( dir === 'bottom' || dir === 'corner' ) {
				nextH = start.h + ( e.clientY - start.y );
			}
			if ( dir === 'left' ) {
				nextW = start.w - ( e.clientX - start.x );
			}
			if ( dir === 'top' ) {
				nextH = start.h - ( e.clientY - start.y );
			}
			setSize( clampSize( nextW, nextH ) );
		};
		const handleMouseUp = () => {
			setIsResizing( false );
			document.body.style.userSelect = '';
			setSize( ( prev ) => {
				if ( prev ) {
					writeSize( storageKey, prev );
				}
				return prev;
			} );
		};
		window.addEventListener( 'mousemove', handleMouseMove );
		window.addEventListener( 'mouseup', handleMouseUp );
		return () => {
			window.removeEventListener( 'mousemove', handleMouseMove );
			window.removeEventListener( 'mouseup', handleMouseUp );
		};
	}, [ isResizing, storageKey, clampSize ] );

	const resetSize = useCallback( () => {
		setSize( null );
		try {
			localStorage.removeItem( storageKey );
		} catch {
			// ignore
		}
	}, [ storageKey ] );

	return { size, isResizing, handleResizeMouseDown, resetSize };
}
