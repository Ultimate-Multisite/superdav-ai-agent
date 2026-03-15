/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ErrorBoundary from './error-boundary';
import ProviderSelector from './provider-selector';
import MessageList from './message-list';
import MessageInput from './message-input';
import ContextIndicator from './context-indicator';
import ToolConfirmationDialog from './tool-confirmation-dialog';

/**
 * Main chat panel component.
 *
 * Renders the provider selector, context indicator, message list, message
 * input, and (when required) the tool confirmation dialog.
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
	const { confirmToolCall, rejectToolCall } = useDispatch( STORE_NAME );
	const { pendingConfirmation, debugMode } = useSelect(
		( select ) => ( {
			pendingConfirmation: select( STORE_NAME ).getPendingConfirmation(),
			debugMode: select( STORE_NAME ).isDebugMode(),
		} ),
		[]
	);

	return (
		<ErrorBoundary label={ __( 'Chat', 'gratis-ai-agent' ) }>
			<div
				className={ `gratis-ai-agent-chat-panel ${
					compact ? 'is-compact' : ''
				}` }
			>
				<div className="gratis-ai-agent-header">
					<ProviderSelector compact={ compact } />
					{ debugMode && (
						<span className="gratis-ai-agent-debug-badge">
							{ __( 'DEBUG', 'gratis-ai-agent' ) }
						</span>
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
				</ErrorBoundary>
				{ pendingConfirmation && (
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
			</div>
		</ErrorBoundary>
	);
}
