/**
 * Tabbed multi-session chat UI — tab bar component (t207).
 *
 * Renders a horizontal strip of open session tabs above the ChatPanel header.
 * Each tab shows the session title (truncated), a status indicator, and a
 * close button. A `+` button at the end creates a new chat.
 */

/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import './chat-tab-bar.css';

/**
 * Small status indicator dot shown inside a tab.
 *
 * @param {Object} props        - Component props.
 * @param {string} props.status - Job status: 'processing', 'awaiting_confirmation', or other.
 * @return {JSX.Element|null} Status dot, or null when idle.
 */
function StatusDot( { status } ) {
	if ( status === 'processing' ) {
		return (
			<span
				className="sd-ai-agent-tab-status-dot is-processing"
				aria-hidden="true"
			/>
		);
	}
	if ( status === 'awaiting_confirmation' ) {
		return (
			<span
				className="sd-ai-agent-tab-status-dot is-awaiting"
				aria-hidden="true"
			/>
		);
	}
	return null;
}

/**
 * Truncate a session title to the given max length.
 *
 * @param {string} title  - Session title, or falsy for untitled.
 * @param {number} maxLen - Maximum character count before truncating.
 * @return {string} Truncated title.
 */
function truncateTitle( title, maxLen = 20 ) {
	if ( ! title ) {
		return __( 'Untitled', 'sd-ai-agent' );
	}
	return title.length > maxLen ? title.substring( 0, maxLen ) + '…' : title;
}

/**
 * Chat tab bar — horizontal strip of open session tabs.
 *
 * Shown above the ChatPanel header whenever `openTabs` has entries.
 * Hidden (returns null) when the tab list is empty.
 *
 * @return {JSX.Element|null} The tab bar, or null when no tabs are open.
 */
export default function ChatTabBar() {
	const { openTabs, sessions, currentSessionId, sessionJobs } = useSelect(
		( select ) => ( {
			openTabs: select( STORE_NAME ).getOpenTabs(),
			sessions: select( STORE_NAME ).getSessions(),
			currentSessionId: select( STORE_NAME ).getCurrentSessionId(),
			sessionJobs: select( STORE_NAME ).getSessionJobs(),
		} ),
		[]
	);
	const { openSession, clearCurrentSession, removeOpenTab } =
		useDispatch( STORE_NAME );

	if ( ! openTabs || openTabs.length === 0 ) {
		return null;
	}

	// Build a session-id → session map for O(1) title lookups.
	const sessionMap = {};
	for ( const s of sessions ) {
		sessionMap[ parseInt( s.id, 10 ) ] = s;
	}

	return (
		<div
			className="sd-ai-agent-chat-tab-bar"
			role="tablist"
			aria-label={ __( 'Open chat sessions', 'sd-ai-agent' ) }
		>
			{ openTabs.map( ( tabId ) => {
				const session = sessionMap[ tabId ];
				const isActive = currentSessionId === tabId;
				const jobInfo = sessionJobs ? sessionJobs[ tabId ] : null;
				const status = jobInfo?.status || 'idle';
				const rawTitle =
					session?.title || __( 'Untitled', 'sd-ai-agent' );

				return (
					<div
						key={ tabId }
						className={ `sd-ai-agent-chat-tab${
							isActive ? ' is-active' : ''
						}` }
						role="tab"
						aria-selected={ isActive }
					>
						<button
							type="button"
							className="sd-ai-agent-chat-tab__label"
							onClick={ () => openSession( tabId ) }
							title={ rawTitle }
						>
							<StatusDot status={ status } />
							{ truncateTitle( session?.title ) }
						</button>
						<button
							type="button"
							className="sd-ai-agent-chat-tab__close"
							onClick={ ( e ) => {
								e.stopPropagation();
								removeOpenTab( tabId );
								if ( isActive ) {
									clearCurrentSession();
								}
							} }
							aria-label={ sprintf(
								/* translators: %s: session title */
								__( 'Close session tab: %s', 'sd-ai-agent' ),
								rawTitle
							) }
						>
							{ /* × character — visually clear, accessible via aria-label */ }
							&#xD7;
						</button>
					</div>
				);
			} ) }
			<Button
				icon={ plus }
				size="small"
				label={ __( 'New Chat', 'sd-ai-agent' ) }
				onClick={ clearCurrentSession }
				className="sd-ai-agent-chat-tab-bar__new"
			/>
		</div>
	);
}
