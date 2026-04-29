/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 *
 * @param {number|string} cost
 */
function formatCost( cost ) {
	const num = parseFloat( cost ) || 0;
	if ( num < 0.01 ) {
		return '$' + num.toFixed( 4 );
	}
	return '$' + num.toFixed( 2 );
}

/**
 *
 * @param {number|string} tokens
 */
function formatTokens( tokens ) {
	const num = parseInt( tokens, 10 ) || 0;
	if ( num >= 1_000_000 ) {
		return ( num / 1_000_000 ).toFixed( 1 ) + 'M';
	}
	if ( num >= 1_000 ) {
		return ( num / 1_000 ).toFixed( 1 ) + 'K';
	}
	return num.toString();
}

/**
 *
 */
export default function UsageDashboard() {
	const [ period, setPeriod ] = useState( '30d' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const fetchUsage = useCallback( async () => {
		setLoading( true );
		try {
			const result = await apiFetch( {
				path: `/sd-ai-agent/v1/usage?period=${ period }`,
			} );
			setData( result );
		} catch {
			setData( null );
		}
		setLoading( false );
	}, [ period ] );

	useEffect( () => {
		fetchUsage();
	}, [ fetchUsage ] );

	if ( loading ) {
		return (
			<div className="sd-ai-agent-usage-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! data ) {
		return <p>{ __( 'Failed to load usage data.', 'sd-ai-agent' ) }</p>;
	}

	const totals = data.totals || {};
	const byModel = data.by_model || [];

	const maxCost = byModel.reduce(
		( max, m ) => Math.max( max, parseFloat( m.cost_usd ) || 0 ),
		0
	);

	return (
		<div className="sd-ai-agent-usage-dashboard">
			<div className="sd-ai-agent-usage-header">
				<h3>{ __( 'Usage Summary', 'sd-ai-agent' ) }</h3>
				<SelectControl
					value={ period }
					options={ [
						{
							label: __( 'Last 7 days', 'sd-ai-agent' ),
							value: '7d',
						},
						{
							label: __( 'Last 30 days', 'sd-ai-agent' ),
							value: '30d',
						},
						{
							label: __( 'Last 90 days', 'sd-ai-agent' ),
							value: '90d',
						},
						{
							label: __( 'All time', 'sd-ai-agent' ),
							value: 'all',
						},
					] }
					onChange={ setPeriod }
					__nextHasNoMarginBottom
				/>
			</div>

			<div className="sd-ai-agent-usage-cards">
				<div className="sd-ai-agent-usage-card">
					<div className="sd-ai-agent-usage-card-label">
						{ __( 'Total Cost', 'sd-ai-agent' ) }
					</div>
					<div className="sd-ai-agent-usage-card-value">
						{ formatCost( totals.cost_usd ) }
					</div>
				</div>
				<div className="sd-ai-agent-usage-card">
					<div className="sd-ai-agent-usage-card-label">
						{ __( 'Requests', 'sd-ai-agent' ) }
					</div>
					<div className="sd-ai-agent-usage-card-value">
						{ totals.request_count || 0 }
					</div>
				</div>
				<div className="sd-ai-agent-usage-card">
					<div className="sd-ai-agent-usage-card-label">
						{ __( 'Input Tokens', 'sd-ai-agent' ) }
					</div>
					<div className="sd-ai-agent-usage-card-value">
						{ formatTokens( totals.prompt_tokens ) }
					</div>
				</div>
				<div className="sd-ai-agent-usage-card">
					<div className="sd-ai-agent-usage-card-label">
						{ __( 'Output Tokens', 'sd-ai-agent' ) }
					</div>
					<div className="sd-ai-agent-usage-card-value">
						{ formatTokens( totals.completion_tokens ) }
					</div>
				</div>
			</div>

			{ byModel.length > 0 && (
				<div className="sd-ai-agent-usage-breakdown">
					<h4>{ __( 'By Model', 'sd-ai-agent' ) }</h4>
					<table className="sd-ai-agent-usage-table">
						<thead>
							<tr>
								<th>{ __( 'Model', 'sd-ai-agent' ) }</th>
								<th>{ __( 'Requests', 'sd-ai-agent' ) }</th>
								<th>{ __( 'Input Tokens', 'sd-ai-agent' ) }</th>
								<th>
									{ __( 'Output Tokens', 'sd-ai-agent' ) }
								</th>
								<th>{ __( 'Cost', 'sd-ai-agent' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ byModel.map( ( m, i ) => {
								const cost = parseFloat( m.cost_usd ) || 0;
								const pct =
									maxCost > 0 ? ( cost / maxCost ) * 100 : 0;
								return (
									<tr key={ i }>
										<td>
											<strong>
												{ m.model_id || '—' }
											</strong>
										</td>
										<td>{ m.request_count }</td>
										<td>
											{ formatTokens( m.prompt_tokens ) }
										</td>
										<td>
											{ formatTokens(
												m.completion_tokens
											) }
										</td>
										<td>{ formatCost( m.cost_usd ) }</td>
										<td>
											<div className="sd-ai-agent-usage-bar">
												<div
													className="sd-ai-agent-usage-bar-fill"
													style={ {
														width: pct + '%',
													} }
												/>
											</div>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			) }
		</div>
	);
}
