/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';

const STORAGE_KEY = 'aiAgentWidgetPosition';

/**
 * Custom hook for making the floating panel draggable.
 *
 * Returns position, isDragging, handleMouseDown, and resetPosition.
 * Position is null when using CSS default (bottom-right).
 */
export default function useDrag() {
	const [ position, setPosition ] = useState( () => {
		try {
			const saved = localStorage.getItem( STORAGE_KEY );
			return saved ? JSON.parse( saved ) : null;
		} catch {
			return null;
		}
	} );

	const [ isDragging, setIsDragging ] = useState( false );
	const dragOffset = useRef( { x: 0, y: 0 } );
	const panelRef = useRef( null );

	const clampToViewport = useCallback( ( x, y ) => {
		const vw = window.innerWidth;
		const vh = window.innerHeight;
		const pw = panelRef.current?.offsetWidth || 420;
		const ph = panelRef.current?.offsetHeight || 600;

		return {
			x: Math.max( 0, Math.min( x, vw - pw ) ),
			y: Math.max( 0, Math.min( y, vh - ph ) ),
		};
	}, [] );

	const handleMouseDown = useCallback( ( e ) => {
		// Only left button.
		if ( e.button !== 0 ) {
			return;
		}

		const panel = e.target.closest( '.ai-agent-floating-panel' );
		if ( ! panel ) {
			return;
		}

		panelRef.current = panel;
		const rect = panel.getBoundingClientRect();
		dragOffset.current = {
			x: e.clientX - rect.left,
			y: e.clientY - rect.top,
		};

		setIsDragging( true );
		document.body.style.userSelect = 'none';
		e.preventDefault();
	}, [] );

	useEffect( () => {
		if ( ! isDragging ) {
			return;
		}

		const handleMouseMove = ( e ) => {
			const x = e.clientX - dragOffset.current.x;
			const y = e.clientY - dragOffset.current.y;
			setPosition( clampToViewport( x, y ) );
		};

		const handleMouseUp = () => {
			setIsDragging( false );
			document.body.style.userSelect = '';

			// Persist position.
			setPosition( ( pos ) => {
				if ( pos ) {
					try {
						localStorage.setItem(
							STORAGE_KEY,
							JSON.stringify( pos )
						);
					} catch {
						// ignore
					}
				}
				return pos;
			} );
		};

		document.addEventListener( 'mousemove', handleMouseMove );
		document.addEventListener( 'mouseup', handleMouseUp );

		return () => {
			document.removeEventListener( 'mousemove', handleMouseMove );
			document.removeEventListener( 'mouseup', handleMouseUp );
		};
	}, [ isDragging, clampToViewport ] );

	const resetPosition = useCallback( () => {
		setPosition( null );
		try {
			localStorage.removeItem( STORAGE_KEY );
		} catch {
			// ignore
		}
	}, [] );

	return { position, isDragging, handleMouseDown, resetPosition };
}
