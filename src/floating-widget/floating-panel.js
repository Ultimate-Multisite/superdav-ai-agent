/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { close, plus, reset, lineSolid } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ChatPanel from '../components/ChatPanel';
import SessionTabs from './session-tabs';
import useDrag from './use-drag';
import useResize from './use-resize';
import { getBranding } from '../utils/branding';

/**
 * Draggable, resizable floating chat panel.
 *
 * Renders a title bar (drag handle + controls), session tabs, and the
 * compact ChatPanel. Supports minimize/expand, custom positioning via
 * the useDrag hook, and resizing via the useResize hook. Both position
 * and size are persisted to localStorage.
 *
 * @return {JSX.Element} The floating panel element.
 */
export default function FloatingPanel() {
	const { setFloatingOpen, setFloatingMinimized, clearCurrentSession } =
		useDispatch( STORE_NAME );

	const { currentSessionId, isMinimized } = useSelect(
		( select ) => ( {
			currentSessionId: select( STORE_NAME ).getCurrentSessionId(),
			isMinimized: select( STORE_NAME ).isFloatingMinimized(),
		} ),
		[]
	);

	const { position, isDragging, handleMouseDown, resetPosition } = useDrag();
	const { size, isResizing, handleResizeMouseDown, resetSize } = useResize();

	const branding = getBranding();
	const displayName =
		branding.agentName || __( 'AI Agent', 'gratis-ai-agent' );

	// Build inline styles for custom position and size.
	const panelStyle = {};
	if ( position ) {
		panelStyle.left = position.x + 'px';
		panelStyle.top = position.y + 'px';
		panelStyle.right = 'auto';
		panelStyle.bottom = 'auto';
	}
	if ( size && ! isMinimized ) {
		panelStyle.width = size.width + 'px';
		panelStyle.height = size.height + 'px';
	}

	const classNames = [
		'gratis-ai-agent-floating-panel',
		isDragging ? 'is-dragging' : '',
		isResizing ? 'is-resizing' : '',
		isMinimized ? 'is-minimized' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const hasCustomState = position || size;

	return (
		<div className={ classNames } style={ panelStyle }>
			<div
				role="presentation"
				className="gratis-ai-agent-floating-titlebar"
				onMouseDown={ handleMouseDown }
			>
				{ branding.logoUrl && (
					<img
						src={ branding.logoUrl }
						alt=""
						className="gratis-ai-agent-floating-titlebar-logo"
						aria-hidden="true"
					/>
				) }
				<span className="gratis-ai-agent-floating-title">
					{ displayName }
				</span>
				<div className="gratis-ai-agent-floating-titlebar-actions">
					{ currentSessionId && (
						<Button
							icon={ plus }
							size="small"
							label={ __( 'New Chat', 'gratis-ai-agent' ) }
							onClick={ clearCurrentSession }
						/>
					) }
					{ hasCustomState && (
						<Button
							icon={ reset }
							size="small"
							label={ __(
								'Reset Position & Size',
								'gratis-ai-agent'
							) }
							onClick={ () => {
								resetPosition();
								resetSize();
							} }
						/>
					) }
					<Button
						icon={ lineSolid }
						size="small"
						label={
							isMinimized
								? __( 'Expand', 'gratis-ai-agent' )
								: __( 'Minimize', 'gratis-ai-agent' )
						}
						onClick={ () => setFloatingMinimized( ! isMinimized ) }
					/>
					<Button
						icon={ close }
						size="small"
						label={ __( 'Close', 'gratis-ai-agent' ) }
						onClick={ () => setFloatingOpen( false ) }
					/>
				</div>
			</div>
			{ ! isMinimized && (
				<>
					<SessionTabs />
					<ChatPanel compact />
					{ /* Resize handles — right edge, bottom edge, corner */ }
					<div
						className="gratis-ai-agent-resize-handle gratis-ai-agent-resize-handle--right"
						role="presentation"
						onMouseDown={ ( e ) =>
							handleResizeMouseDown( e, 'right' )
						}
					/>
					<div
						className="gratis-ai-agent-resize-handle gratis-ai-agent-resize-handle--bottom"
						role="presentation"
						onMouseDown={ ( e ) =>
							handleResizeMouseDown( e, 'bottom' )
						}
					/>
					<div
						className="gratis-ai-agent-resize-handle gratis-ai-agent-resize-handle--corner"
						role="presentation"
						onMouseDown={ ( e ) =>
							handleResizeMouseDown( e, 'corner' )
						}
					/>
				</>
			) }
		</div>
	);
}
