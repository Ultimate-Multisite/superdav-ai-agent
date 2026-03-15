/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function formatTime( ms ) {
	if ( ms < 1000 ) {
		return ms + 'ms';
	}
	return ( ms / 1000 ).toFixed( 1 ) + 's';
}

function formatCost( cost ) {
	if ( ! cost || cost === 0 ) {
		return '$0';
	}
	if ( cost < 0.01 ) {
		return '$' + cost.toFixed( 4 );
	}
	return '$' + cost.toFixed( 2 );
}

export default function DebugPanel( { debug } ) {
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
		summaryParts.join( ' / ' ) || __( 'No metrics', 'ai-agent' );

	return (
		<div className="ai-agent-debug-panel">
			<button
				className="ai-agent-debug-toggle"
				onClick={ () => setExpanded( ! expanded ) }
				type="button"
			>
				<span className="ai-agent-debug-summary">{ summary }</span>
				<span className="ai-agent-debug-caret">
					{ expanded ? '\u25B4' : '\u25BE' }
				</span>
			</button>
			{ expanded && (
				<div className="ai-agent-debug-details">
					{ modelId && (
						<div className="ai-agent-debug-row">
							<span className="ai-agent-debug-label">
								{ __( 'Model', 'ai-agent' ) }
							</span>
							<span className="ai-agent-debug-value">
								{ modelId }
							</span>
						</div>
					) }
					<div className="ai-agent-debug-row">
						<span className="ai-agent-debug-label">
							{ __( 'Response time', 'ai-agent' ) }
						</span>
						<span className="ai-agent-debug-value">
							{ formatTime( responseTimeMs ) }
						</span>
					</div>
					<div className="ai-agent-debug-row">
						<span className="ai-agent-debug-label">
							{ __( 'Tokens', 'ai-agent' ) }
						</span>
						<span className="ai-agent-debug-value">
							{ totalTokens.toLocaleString() }
							<span className="ai-agent-debug-detail">
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
						<div className="ai-agent-debug-row">
							<span className="ai-agent-debug-label">
								{ __( 'Speed', 'ai-agent' ) }
							</span>
							<span className="ai-agent-debug-value">
								{ tokensPerSecond } tok/s
							</span>
						</div>
					) }
					{ costEstimate > 0 && (
						<div className="ai-agent-debug-row">
							<span className="ai-agent-debug-label">
								{ __( 'Cost', 'ai-agent' ) }
							</span>
							<span className="ai-agent-debug-value">
								{ formatCost( costEstimate ) }
							</span>
						</div>
					) }
					<div className="ai-agent-debug-row">
						<span className="ai-agent-debug-label">
							{ __( 'Iterations', 'ai-agent' ) }
						</span>
						<span className="ai-agent-debug-value">
							{ iterationsUsed }
						</span>
					</div>
					{ toolCallCount > 0 && (
						<div className="ai-agent-debug-row">
							<span className="ai-agent-debug-label">
								{ __( 'Tool calls', 'ai-agent' ) }
							</span>
							<span className="ai-agent-debug-value">
								{ toolCallCount }
								{ toolNames.length > 0 && (
									<span className="ai-agent-debug-detail">
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
