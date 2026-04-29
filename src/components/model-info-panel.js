/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @typedef {import('../types').MessageDebug} MessageDebug
 */

/**
 * Format a duration in milliseconds as a human-readable string.
 *
 * @param {number} ms - Duration in milliseconds.
 * @return {string} Formatted string (e.g. '450ms', '1.2s').
 */
function formatTime( ms ) {
	if ( ms < 1000 ) {
		return ms + 'ms';
	}
	return ( ms / 1000 ).toFixed( 1 ) + 's';
}

/**
 * Format a cost in USD as a human-readable string.
 *
 * @param {number} cost - Cost in USD.
 * @return {string} Formatted string (e.g. '$0', '$0.0012', '$1.23').
 */
function formatCost( cost ) {
	if ( ! cost || cost === 0 ) {
		return '$0';
	}
	if ( cost < 0.01 ) {
		return '$' + cost.toFixed( 4 );
	}
	return '$' + cost.toFixed( 2 );
}

/**
 * Collapsible panel shown below model messages with response metrics.
 *
 * Displays response time, token counts, speed, cost, iteration count, and
 * tool call summary. Collapsed by default; click the summary row to expand.
 *
 * @param {Object}            props       - Component props.
 * @param {MessageDebug|null} props.debug - Debug metadata from the store message.
 * @return {JSX.Element|null} The panel, or null when debug is falsy.
 */
export default function ModelInfoPanel( { debug } ) {
	const [ expanded, setExpanded ] = useState( false );

	if ( ! debug ) {
		return null;
	}

	const {
		responseTimeMs = 0,
		tokenUsage = {},
		tokensPerSecond = 0,
		modelId = '',
		costEstimate = 0,
		iterationsUsed = 0,
		toolCallCount = 0,
		toolNames = [],
	} = debug;

	const totalTokens =
		( tokenUsage.prompt || 0 ) + ( tokenUsage.completion || 0 );

	const summaryParts = [];
	if ( responseTimeMs > 0 ) {
		summaryParts.push( formatTime( responseTimeMs ) );
	}
	if ( tokensPerSecond > 0 ) {
		summaryParts.push( tokensPerSecond + ' tok/s' );
	}
	if ( costEstimate > 0 ) {
		summaryParts.push( formatCost( costEstimate ) );
	}
	const summary =
		summaryParts.join( ' / ' ) || __( 'No metrics', 'sd-ai-agent' );

	return (
		<div className="sd-ai-agent-debug-panel">
			<button
				className="sd-ai-agent-debug-toggle"
				onClick={ () => setExpanded( ! expanded ) }
				type="button"
			>
				<span className="sd-ai-agent-debug-summary">{ summary }</span>
				<span className="sd-ai-agent-debug-caret">
					{ expanded ? '\u25B4' : '\u25BE' }
				</span>
			</button>
			{ expanded && (
				<div className="sd-ai-agent-debug-details">
					{ modelId && (
						<div className="sd-ai-agent-debug-row">
							<span className="sd-ai-agent-debug-label">
								{ __( 'Model', 'sd-ai-agent' ) }
							</span>
							<span className="sd-ai-agent-debug-value">
								{ modelId }
							</span>
						</div>
					) }
					<div className="sd-ai-agent-debug-row">
						<span className="sd-ai-agent-debug-label">
							{ __( 'Response time', 'sd-ai-agent' ) }
						</span>
						<span className="sd-ai-agent-debug-value">
							{ formatTime( responseTimeMs ) }
						</span>
					</div>
					<div className="sd-ai-agent-debug-row">
						<span className="sd-ai-agent-debug-label">
							{ __( 'Tokens', 'sd-ai-agent' ) }
						</span>
						<span className="sd-ai-agent-debug-value">
							{ totalTokens.toLocaleString() }
							<span className="sd-ai-agent-debug-detail">
								({ ( tokenUsage.prompt || 0 ).toLocaleString() }{ ' ' }
								in /{ ' ' }
								{ (
									tokenUsage.completion || 0
								).toLocaleString() }{ ' ' }
								out)
							</span>
						</span>
					</div>
					{ tokensPerSecond > 0 && (
						<div className="sd-ai-agent-debug-row">
							<span className="sd-ai-agent-debug-label">
								{ __( 'Speed', 'sd-ai-agent' ) }
							</span>
							<span className="sd-ai-agent-debug-value">
								{ tokensPerSecond } tok/s
							</span>
						</div>
					) }
					{ costEstimate > 0 && (
						<div className="sd-ai-agent-debug-row">
							<span className="sd-ai-agent-debug-label">
								{ __( 'Cost', 'sd-ai-agent' ) }
							</span>
							<span className="sd-ai-agent-debug-value">
								{ formatCost( costEstimate ) }
							</span>
						</div>
					) }
					<div className="sd-ai-agent-debug-row">
						<span className="sd-ai-agent-debug-label">
							{ __( 'Iterations', 'sd-ai-agent' ) }
						</span>
						<span className="sd-ai-agent-debug-value">
							{ iterationsUsed }
						</span>
					</div>
					{ toolCallCount > 0 && (
						<div className="sd-ai-agent-debug-row">
							<span className="sd-ai-agent-debug-label">
								{ __( 'Tool calls', 'sd-ai-agent' ) }
							</span>
							<span className="sd-ai-agent-debug-value">
								{ toolCallCount }
								{ toolNames.length > 0 && (
									<span className="sd-ai-agent-debug-detail">
										({ toolNames.join( ', ' ) })
									</span>
								) }
							</span>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
