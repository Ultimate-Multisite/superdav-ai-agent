/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Compare View Component
 *
 * @param {Object} props            Component props.
 * @param {Object} props.comparison Comparison data.
 * @return {JSX.Element} Component element.
 */
export default function CompareView( { comparison } ) {
	if ( ! comparison || ! comparison.summary ) {
		return (
			<Card>
				<CardBody>
					<p>
						{ __( 'No comparison data available.', 'sd-ai-agent' ) }
					</p>
				</CardBody>
			</Card>
		);
	}

	const { summary, by_model: byModel, by_category: byCategory } = comparison;

	return (
		<div className="sd-ai-agent-benchmark-compare">
			<Card>
				<CardHeader>
					<h2>{ __( 'Benchmark Comparison', 'sd-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<h3>{ __( 'Summary', 'sd-ai-agent' ) }</h3>
					<div className="sd-ai-agent-benchmark-compare-table">
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Run', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Total', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Correct', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Accuracy', 'sd-ai-agent' ) }</th>
									<th>
										{ __( 'Avg Latency', 'sd-ai-agent' ) }
									</th>
									<th>
										{ __( 'Total Tokens', 'sd-ai-agent' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ summary.map( ( row ) => (
									<tr key={ row.run_id }>
										<td>{ row.run_name }</td>
										<td>{ row.total }</td>
										<td>{ row.correct }</td>
										<td>{ row.accuracy }%</td>
										<td>{ row.avg_latency }ms</td>
										<td>
											{ row.total_tokens.toLocaleString() }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>

					{ byModel && byModel.length > 0 && (
						<>
							<h3 style={ { marginTop: '24px' } }>
								{ __( 'By Model', 'sd-ai-agent' ) }
							</h3>
							<div className="sd-ai-agent-benchmark-compare-table">
								<table className="wp-list-table widefat fixed striped">
									<thead>
										<tr>
											<th>
												{ __( 'Model', 'sd-ai-agent' ) }
											</th>
											<th>
												{ __( 'Total', 'sd-ai-agent' ) }
											</th>
											<th>
												{ __(
													'Correct',
													'sd-ai-agent'
												) }
											</th>
											<th>
												{ __(
													'Accuracy',
													'sd-ai-agent'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ byModel.map( ( row ) => (
											<tr key={ row.model_id }>
												<td>{ row.model_id }</td>
												<td>{ row.total }</td>
												<td>{ row.correct }</td>
												<td>{ row.accuracy }%</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						</>
					) }

					{ byCategory && byCategory.length > 0 && (
						<>
							<h3 style={ { marginTop: '24px' } }>
								{ __( 'By Category', 'sd-ai-agent' ) }
							</h3>
							<div className="sd-ai-agent-benchmark-compare-table">
								<table className="wp-list-table widefat fixed striped">
									<thead>
										<tr>
											<th>
												{ __(
													'Category',
													'sd-ai-agent'
												) }
											</th>
											<th>
												{ __( 'Total', 'sd-ai-agent' ) }
											</th>
											<th>
												{ __(
													'Correct',
													'sd-ai-agent'
												) }
											</th>
											<th>
												{ __(
													'Accuracy',
													'sd-ai-agent'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ byCategory.map( ( row ) => (
											<tr key={ row.category }>
												<td>{ row.category }</td>
												<td>{ row.total }</td>
												<td>{ row.correct }</td>
												<td>{ row.accuracy }%</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						</>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
