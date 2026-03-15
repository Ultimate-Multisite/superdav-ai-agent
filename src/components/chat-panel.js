/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ProviderSelector from './provider-selector';
import MessageList from './message-list';
import MessageInput from './message-input';
import ContextIndicator from './context-indicator';
import ToolConfirmationDialog from './tool-confirmation-dialog';

/**
 * Main chat panel component. Renders the provider selector, message list,
 * message input, and tool confirmation dialog.
 *
 * @param {Object}   props                  - Component props.
 * @param {boolean}  [props.compact=false]  - Whether to render in compact mode
 *                                          (used inside the floating widget).
 * @param {Function} [props.onSlashCommand] - Callback for slash command actions.
 *                                          Receives (action: string, data?: string).
 * @return {JSX.Element} Chat panel element.
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
			<MessageList />
			<MessageInput
				compact={ compact }
				onSlashCommand={ onSlashCommand }
			/>
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
	);
}
