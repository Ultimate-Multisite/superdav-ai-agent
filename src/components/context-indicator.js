/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Format a token count as a human-readable string (e.g. '12.3K', '1.5M').
 *
 * @param {number} n - Token count.
 * @return {string} Formatted string.
 */
function formatTokens( n ) {
	if ( n >= 1_000_000 ) {
		return ( n / 1_000_000 ).toFixed( 1 ) + 'M';
	}
	if ( n >= 1_000 ) {
		return ( n / 1_000 ).toFixed( 1 ) + 'K';
	}
	return n.toString();
}

/**
 * Context window usage indicator bar.
 *
 * Shows total token count, a colour-coded progress bar (green → yellow → red),
 * and a warning with Compact/New Chat actions when usage exceeds 80%.
 * Hidden when no tokens have been tracked yet.
 *
 * @return {JSX.Element|null} The context indicator, or null when hidden.
 */
export default function ContextIndicator() {
	const { percentage, isWarning, tokenUsage } = useSelect(
		( select ) => ( {
			percentage: select( STORE_NAME ).getContextPercentage(),
			isWarning: select( STORE_NAME ).isContextWarning(),
			tokenUsage: select( STORE_NAME ).getTokenUsage(),
		} ),
		[]
	);
	const { clearCurrentSession, compactConversation } =
		useDispatch( STORE_NAME );

	// Don't show if no tokens tracked yet.
	if ( tokenUsage.prompt === 0 && tokenUsage.completion === 0 ) {
		return null;
	}

	const clampedPct = Math.min( percentage, 100 );
	let barColor = '#00a32a'; // green
	if ( clampedPct > 80 ) {
		barColor = '#d63638'; // red
	} else if ( clampedPct > 70 ) {
		barColor = '#dba617'; // yellow
	}

	const totalTokens = tokenUsage.prompt + tokenUsage.completion;

	return (
		<div className="sdaa-context-indicator">
			<div className="sdaa-context-stats">
				<span className="sdaa-context-tokens">
					{ formatTokens( totalTokens ) }{ ' ' }
					{ __( 'tokens', 'superdav-ai-agent' ) }
					<span className="sdaa-context-detail">
						({ formatTokens( tokenUsage.prompt ) }{ ' ' }
						{ __( 'in', 'superdav-ai-agent' ) } /{ ' ' }
						{ formatTokens( tokenUsage.completion ) }{ ' ' }
						{ __( 'out', 'superdav-ai-agent' ) })
					</span>
				</span>
				<span className="sdaa-context-pct">
					{ Math.round( clampedPct ) }%
				</span>
			</div>
			<div className="sdaa-context-bar-track">
				<div
					className="sdaa-context-bar-fill"
					style={ {
						width: clampedPct + '%',
						backgroundColor: barColor,
					} }
				/>
			</div>
			{ isWarning && (
				<div className="sdaa-context-warning">
					<span>
						{ __(
							'Context window is getting full.',
							'superdav-ai-agent'
						) }
					</span>
					<div className="sdaa-context-warning-actions">
						<Button
							variant="secondary"
							size="small"
							onClick={ compactConversation }
						>
							{ __( 'Compact', 'superdav-ai-agent' ) }
						</Button>
						<Button
							variant="secondary"
							size="small"
							onClick={ clearCurrentSession }
						>
							{ __( 'New Chat', 'superdav-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}
