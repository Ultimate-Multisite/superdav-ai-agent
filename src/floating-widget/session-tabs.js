/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Compact session tab strip shown at the top of the floating panel.
 *
 * Displays up to 5 most recent sessions as clickable tabs, plus a New Chat
 * button. Hidden when there are no sessions.
 *
 * @return {JSX.Element|null} The session tabs element, or null when empty.
 */
export default function SessionTabs() {
	const { sessions, currentSessionId, sessionJobs } = useSelect(
		( select ) => ( {
			sessions: select( STORE_NAME ).getSessions(),
			currentSessionId: select( STORE_NAME ).getCurrentSessionId(),
			sessionJobs: select( STORE_NAME ).getSessionJobs(),
		} ),
		[]
	);
	const { openSession, clearCurrentSession } = useDispatch( STORE_NAME );

	// Show up to 5 most recent sessions.
	const recentSessions = sessions.slice( 0, 5 );

	if ( recentSessions.length === 0 ) {
		return null;
	}

	const truncateTitle = ( title, maxLen = 20 ) => {
		if ( ! title ) {
			return __( 'Untitled', 'gratis-ai-agent' );
		}
		return title.length > maxLen
			? title.substring( 0, maxLen ) + '...'
			: title;
	};

	return (
		<div className="gratis-ai-agent-session-tabs">
			{ recentSessions.map( ( session ) => {
				const isActive = currentSessionId === session.id;
				const jobState = sessionJobs[ session.id ];
				const needsApproval =
					jobState?.status === 'awaiting_confirmation';
				return (
					<button
						key={ session.id }
						className={ `gratis-ai-agent-tab-item ${
							isActive ? 'is-active' : ''
						} ${ needsApproval ? 'needs-approval' : '' }` }
						onClick={ () => openSession( session.id ) }
						title={
							needsApproval
								? __( 'Approval needed', 'gratis-ai-agent' )
								: session.title ||
								  __( 'Untitled', 'gratis-ai-agent' )
						}
						type="button"
					>
						{ needsApproval && (
							<span
								className="gratis-ai-agent-tab-confirm-dot"
								aria-hidden="true"
							>
								{ '\u26A0' }
							</span>
						) }
						{ truncateTitle( session.title ) }
					</button>
				);
			} ) }
			<Button
				icon={ plus }
				size="small"
				label={ __( 'New Chat', 'gratis-ai-agent' ) }
				onClick={ clearCurrentSession }
				className="gratis-ai-agent-tab-new"
			/>
		</div>
	);
}
