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
		'gratis-ai-agent-floating-panel',
		isDragging ? 'is-dragging' : '',
		isMinimized ? 'is-minimized' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classNames } style={ panelStyle }>
			{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions */ }
			<div
				className="gratis-ai-agent-floating-titlebar"
				onMouseDown={ handleMouseDown }
			>
				<span className="gratis-ai-agent-floating-title">
					{ __( 'Gratis AI Agent', 'gratis-ai-agent' ) }
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
					{ position && (
						<Button
							icon={ reset }
							size="small"
							label={ __( 'Reset Position', 'gratis-ai-agent' ) }
							onClick={ resetPosition }
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
				</>
			) }
		</div>
	);
}
