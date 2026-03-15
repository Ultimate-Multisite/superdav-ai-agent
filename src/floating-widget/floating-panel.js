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
import ChatPanel from '../components/chat-panel';
import SessionTabs from './session-tabs';
import useDrag from './use-drag';

/**
 * Draggable floating chat panel.
 *
 * Renders a title bar (drag handle + controls), session tabs, and the
 * compact ChatPanel. Supports minimize/expand and custom positioning via
 * the useDrag hook. Position is persisted to localStorage.
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

	// Build inline styles for custom position.
	const panelStyle = {};
	if ( position ) {
		panelStyle.left = position.x + 'px';
		panelStyle.top = position.y + 'px';
		panelStyle.right = 'auto';
		panelStyle.bottom = 'auto';
	}

	const classNames = [
		'ai-agent-floating-panel',
		isDragging ? 'is-dragging' : '',
		isMinimized ? 'is-minimized' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classNames } style={ panelStyle }>
			<div
				role="presentation"
				className="ai-agent-floating-titlebar"
				onMouseDown={ handleMouseDown }
			>
				<span className="ai-agent-floating-title">
					{ __( 'AI Agent', 'ai-agent' ) }
				</span>
				<div className="ai-agent-floating-titlebar-actions">
					{ currentSessionId && (
						<Button
							icon={ plus }
							size="small"
							label={ __( 'New Chat', 'ai-agent' ) }
							onClick={ clearCurrentSession }
						/>
					) }
					{ position && (
						<Button
							icon={ reset }
							size="small"
							label={ __( 'Reset Position', 'ai-agent' ) }
							onClick={ resetPosition }
						/>
					) }
					<Button
						icon={ lineSolid }
						size="small"
						label={
							isMinimized
								? __( 'Expand', 'ai-agent' )
								: __( 'Minimize', 'ai-agent' )
						}
						onClick={ () => setFloatingMinimized( ! isMinimized ) }
					/>
					<Button
						icon={ close }
						size="small"
						label={ __( 'Close', 'ai-agent' ) }
						onClick={ () => setFloatingOpen( false ) }
					/>
				</div>
			</div>
			{ ! isMinimized && (
				<>
					<SessionTabs />
					<ChatPanel compact />
				</>
			) }
		</div>
	);
}
