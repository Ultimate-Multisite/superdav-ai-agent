/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Format a token count with thousands separators.
 *
 * @param {number} n - Token count.
 * @return {string} Formatted string (e.g. "1,234").
 */
function formatTokens( n ) {
	return n.toLocaleString();
}

/**
 * Format a cost estimate in USD.
 *
 * @param {number} cost - Cost in USD.
 * @return {string} Formatted string (e.g. "~$0.02" or "<$0.01").
 */
function formatCost( cost ) {
	if ( cost <= 0 ) {
		return '';
	}
	if ( cost < 0.01 ) {
		return __( '<$0.01', 'sd-ai-agent' );
	}
	return '~$' + cost.toFixed( 2 );
}

/**
 * Compact session token counter displayed below the chat input.
 *
 * Shows cumulative session token count and estimated cost, updating in
 * real-time as each `done` SSE event arrives. Hidden when no tokens have
 * been consumed yet, or when the "Show token costs" setting is disabled.
 *
 * @return {JSX.Element|null} The counter element, or null when hidden.
 */
export default function TokenCounter() {
	const { sessionTokens, sessionCost, showTokenCosts } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				sessionTokens: store.getSessionTokens(),
				sessionCost: store.getSessionCost(),
				showTokenCosts: store.getSettings()?.show_token_costs !== false,
			};
		},
		[]
	);

	if ( ! showTokenCosts || sessionTokens === 0 ) {
		return null;
	}

	const costStr = formatCost( sessionCost );

	return (
		<div
			className="sd-ai-agent-token-counter"
			title={ __(
				'Session token usage and estimated cost',
				'sd-ai-agent'
			) }
		>
			<span className="sd-ai-agent-token-counter__tokens">
				{ formatTokens( sessionTokens ) }{ ' ' }
				{ __( 'tokens', 'sd-ai-agent' ) }
			</span>
			{ costStr && (
				<>
					<span
						className="sd-ai-agent-token-counter__sep"
						aria-hidden="true"
					>
						{ ' · ' }
					</span>
					<span className="sd-ai-agent-token-counter__cost">
						{ costStr }
					</span>
				</>
			) }
		</div>
	);
}

/**
 * Inline token annotation shown below an assistant message bubble.
 *
 * @param {Object} props           - Component props.
 * @param {Object} props.tokenData - { prompt, completion, cost } for this message.
 * @return {JSX.Element|null} The annotation element, or null when hidden.
 */
export function MessageTokenAnnotation( { tokenData } ) {
	const showTokenCosts = useSelect( ( select ) => {
		return select( STORE_NAME ).getSettings()?.show_token_costs !== false;
	}, [] );

	if ( ! showTokenCosts || ! tokenData ) {
		return null;
	}

	const total = ( tokenData.prompt || 0 ) + ( tokenData.completion || 0 );
	if ( total === 0 ) {
		return null;
	}

	const costStr = formatCost( tokenData.cost || 0 );

	return (
		<div className="sd-ai-agent-message-token-annotation">
			{ formatTokens( total ) } { __( 'tokens', 'sd-ai-agent' ) }
			{ costStr && (
				<>
					{ ' · ' }
					{ costStr }
				</>
			) }
		</div>
	);
}
