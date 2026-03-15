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
 * Horizontal tab bar showing up to 5 recent sessions in the floating panel.
 * Returns null when there are no sessions.
 *
 * @return {JSX.Element|null} Session tabs element, or null if no sessions.
 */
export default function SessionTabs() {
	const { sessions, currentSessionId } = useSelect(
		( select ) => ( {
			sessions: select( STORE_NAME ).getSessions(),
			currentSessionId: select( STORE_NAME ).getCurrentSessionId(),
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
				const id = parseInt( session.id, 10 );
				const isActive = currentSessionId === id;
				return (
					<button
						key={ session.id }
						className={ `gratis-ai-agent-tab-item ${
							isActive ? 'is-active' : ''
						}` }
						onClick={ () => openSession( id ) }
						title={
							session.title || __( 'Untitled', 'gratis-ai-agent' )
						}
						type="button"
					>
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
