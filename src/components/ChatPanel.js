/**
 * WordPress dependencies
 */
import { useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Button, Tooltip } from '@wordpress/components';

/**
 * External dependencies
 */
import { createPortal } from 'react-dom';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ErrorBoundary from './error-boundary';
import ProviderSelector from './provider-selector';
import AgentSelector from './agent-selector';
import MessageList from './message-list';
import MessageInput from './message-input';
import ContextIndicator from './context-indicator';
import ToolConfirmationDialog from './tool-confirmation-dialog';
import BudgetIndicator from './budget-indicator';
import { isTTSSupported } from './use-text-to-speech';
import TokenCounter from './token-counter';

/**
 * Speaker icon SVG for the TTS toggle button.
 *
 * @param {Object}  props         - Component props.
 * @param {boolean} props.enabled - Whether TTS is currently enabled.
 * @return {JSX.Element} SVG icon.
 */
function SpeakerIcon( { enabled } ) {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="18"
			height="18"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			{ enabled ? (
				/* Speaker with sound waves */
				<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z" />
			) : (
				/* Speaker muted (with slash) */
				<path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" />
			) }
		</svg>
	);
}

/**
 * Main chat panel component.
 *
 * Renders the provider selector, context indicator, message list, message
 * input, and (when required) the tool confirmation dialog.
 * Also renders a TTS toggle button in the header when the browser supports
 * the Web Speech API SpeechSynthesis interface.
 *
 * @param {Object}   props                  - Component props.
 * @param {boolean}  [props.compact=false]  - Whether to render in compact mode
 *                                          (used inside the floating widget).
 * @param {Function} [props.onSlashCommand] - Callback invoked when a slash
 *                                          command produces a notice or opens
 *                                          the help dialog.
 *                                          Signature: (type: string, message?: string) => void
 * @return {JSX.Element} The chat panel element.
 */
export default function ChatPanel( { compact = false, onSlashCommand } ) {
	const { confirmToolCall, rejectToolCall, setTtsEnabled } =
		useDispatch( STORE_NAME );
	const { pendingConfirmation, debugMode, yoloMode, ttsEnabled } = useSelect(
		( select ) => ( {
			pendingConfirmation: select( STORE_NAME ).getPendingConfirmation(),
			debugMode: select( STORE_NAME ).isDebugMode(),
			yoloMode: select( STORE_NAME ).isYoloMode(),
			ttsEnabled: select( STORE_NAME ).isTtsEnabled(),
		} ),
		[]
	);

	// When YOLO mode is active, auto-confirm any pending tool call immediately.
	useEffect( () => {
		if ( yoloMode && pendingConfirmation ) {
			confirmToolCall( pendingConfirmation.jobId, false );
		}
	}, [ yoloMode, pendingConfirmation, confirmToolCall ] );

	const handleTtsToggle = useCallback( () => {
		setTtsEnabled( ! ttsEnabled );
	}, [ ttsEnabled, setTtsEnabled ] );

	return (
		<ErrorBoundary label={ __( 'Chat', 'gratis-ai-agent' ) }>
			<div
				className={ `gratis-ai-agent-chat-panel ${
					compact ? 'is-compact' : ''
				}` }
			>
				<div className="gratis-ai-agent-header">
					<ProviderSelector compact={ compact } />
					<AgentSelector compact={ compact } />
					<BudgetIndicator />
					{ isTTSSupported && (
						<Button
							onClick={ handleTtsToggle }
							className={ `gratis-ai-agent-tts-btn${
								ttsEnabled ? ' is-active' : ''
							}` }
							label={
								ttsEnabled
									? __(
											'Disable text-to-speech',
											'gratis-ai-agent'
									  )
									: __(
											'Enable text-to-speech',
											'gratis-ai-agent'
									  )
							}
							showTooltip
							icon={ <SpeakerIcon enabled={ ttsEnabled } /> }
						/>
					) }
					{ debugMode && (
						<Tooltip
							text={ __(
								'Debug mode is active — extra metadata is shown below each response.',
								'gratis-ai-agent'
							) }
						>
							<span className="gratis-ai-agent-debug-badge">
								{ __( 'DEBUG', 'gratis-ai-agent' ) }
							</span>
						</Tooltip>
					) }
				</div>
				<ContextIndicator />
				<ErrorBoundary
					label={ __( 'Message list', 'gratis-ai-agent' ) }
				>
					<MessageList />
				</ErrorBoundary>
				<ErrorBoundary
					label={ __( 'Message input', 'gratis-ai-agent' ) }
				>
					<MessageInput
						compact={ compact }
						onSlashCommand={ onSlashCommand }
					/>
					<TokenCounter />
				</ErrorBoundary>
				{ /* In compact (floating widget) mode the panel has
				   overflow:hidden which clips position:fixed children.
				   Portal to document.body so the overlay covers the
				   full viewport instead of being clipped inline. */ }
				{ pendingConfirmation &&
					! yoloMode &&
					( ( dialog ) =>
						compact
							? createPortal( dialog, document.body )
							: dialog )(
						<ToolConfirmationDialog
							confirmation={ pendingConfirmation }
							onConfirm={ ( alwaysAllow ) =>
								confirmToolCall(
									pendingConfirmation.jobId,
									alwaysAllow
								)
							}
							onReject={ () =>
								rejectToolCall( pendingConfirmation.jobId )
							}
						/>
					) }
				{ yoloMode && (
					<Tooltip
						text={ __(
							'YOLO mode is active — all tool confirmations are skipped automatically.',
							'gratis-ai-agent'
						) }
					>
						<span className="gratis-ai-agent-yolo-badge">
							{ __( 'YOLO', 'gratis-ai-agent' ) }
						</span>
					</Tooltip>
				) }
			</div>
		</ErrorBoundary>
	);
}
