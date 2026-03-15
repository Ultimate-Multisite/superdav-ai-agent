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
		summaryParts.join( ' / ' ) || __( 'No metrics', 'gratis-ai-agent' );

	return (
		<div className="gratis-ai-agent-debug-panel">
			<button
				className="gratis-ai-agent-debug-toggle"
				onClick={ () => setExpanded( ! expanded ) }
				type="button"
			>
				<span className="gratis-ai-agent-debug-summary">
					{ summary }
				</span>
				<span className="gratis-ai-agent-debug-caret">
					{ expanded ? '\u25B4' : '\u25BE' }
				</span>
			</button>
			{ expanded && (
				<div className="gratis-ai-agent-debug-details">
					{ modelId && (
						<div className="gratis-ai-agent-debug-row">
							<span className="gratis-ai-agent-debug-label">
								{ __( 'Model', 'gratis-ai-agent' ) }
							</span>
							<span className="gratis-ai-agent-debug-value">
								{ modelId }
							</span>
						</div>
					) }
					<div className="gratis-ai-agent-debug-row">
						<span className="gratis-ai-agent-debug-label">
							{ __( 'Response time', 'gratis-ai-agent' ) }
						</span>
						<span className="gratis-ai-agent-debug-value">
							{ formatTime( responseTimeMs ) }
						</span>
					</div>
					<div className="gratis-ai-agent-debug-row">
						<span className="gratis-ai-agent-debug-label">
							{ __( 'Tokens', 'gratis-ai-agent' ) }
						</span>
						<span className="gratis-ai-agent-debug-value">
							{ totalTokens.toLocaleString() }
							<span className="gratis-ai-agent-debug-detail">
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
						<div className="gratis-ai-agent-debug-row">
							<span className="gratis-ai-agent-debug-label">
								{ __( 'Speed', 'gratis-ai-agent' ) }
							</span>
							<span className="gratis-ai-agent-debug-value">
								{ tokensPerSecond } tok/s
							</span>
						</div>
					) }
					{ costEstimate > 0 && (
						<div className="gratis-ai-agent-debug-row">
							<span className="gratis-ai-agent-debug-label">
								{ __( 'Cost', 'gratis-ai-agent' ) }
							</span>
							<span className="gratis-ai-agent-debug-value">
								{ formatCost( costEstimate ) }
							</span>
						</div>
					) }
					<div className="gratis-ai-agent-debug-row">
						<span className="gratis-ai-agent-debug-label">
							{ __( 'Iterations', 'gratis-ai-agent' ) }
						</span>
						<span className="gratis-ai-agent-debug-value">
							{ iterationsUsed }
						</span>
					</div>
					{ toolCallCount > 0 && (
						<div className="gratis-ai-agent-debug-row">
							<span className="gratis-ai-agent-debug-label">
								{ __( 'Tool calls', 'gratis-ai-agent' ) }
							</span>
							<span className="gratis-ai-agent-debug-value">
								{ toolCallCount }
								{ toolNames.length > 0 && (
									<span className="gratis-ai-agent-debug-detail">
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
