/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Icon,
} from '@wordpress/components';
import { check, closeSmall, arrowLeft } from '@wordpress/icons';

/**
 * Stat Card Component — wraps a single metric in a wp-admin Card.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label Metric label.
 * @param {*}      props.value Metric value.
 * @return {JSX.Element} Stat card element.
 */
function StatCard( { label, value } ) {
	return (
		<Card className="sd-ai-agent-benchmark-stat-card">
			<CardBody>
				<h4>{ label }</h4>
				<div className="stat-value">{ value }</div>
			</CardBody>
		</Card>
	);
}

/**
 * Run Details Component
 *
 * @param {Object}   props        Component props.
 * @param {Object}   props.run    Run data.
 * @param {Function} props.onBack Back callback.
 * @return {JSX.Element} Component element.
 */
export default function RunDetails( { run, onBack } ) {
	const results = run.results || [];

	const formatDate = ( dateString ) => {
		if ( ! dateString ) {
			return __( 'N/A', 'sd-ai-agent' );
		}
		const date = new Date( dateString );
		return date.toLocaleString();
	};

	const correctCount = results.filter( ( r ) => r.is_correct ).length;
	const accuracy =
		results.length > 0
			? Math.round( ( correctCount / results.length ) * 100 )
			: 0;

	const avgLatency =
		results.length > 0
			? Math.round(
					results.reduce(
						( sum, r ) => sum + ( r.latency_ms || 0 ),
						0
					) / results.length
			  )
			: 0;

	const totalTokens = results.reduce(
		( sum, r ) =>
			sum + ( r.prompt_tokens || 0 ) + ( r.completion_tokens || 0 ),
		0
	);

	// Group results by model
	const byModel = {};
	results.forEach( ( result ) => {
		const key = result.model_id;
		if ( ! byModel[ key ] ) {
			byModel[ key ] = {
				model_id: result.model_id,
				provider_id: result.provider_id,
				total: 0,
				correct: 0,
			};
		}
		byModel[ key ].total++;
		if ( result.is_correct ) {
			byModel[ key ].correct++;
		}
	} );

	// Group results by category
	const byCategory = {};
	results.forEach( ( result ) => {
		const key = result.question_category;
		if ( ! byCategory[ key ] ) {
			byCategory[ key ] = {
				category: key,
				total: 0,
				correct: 0,
			};
		}
		byCategory[ key ].total++;
		if ( result.is_correct ) {
			byCategory[ key ].correct++;
		}
	} );

	return (
		<div className="sd-ai-agent-benchmark-run-details">
			<Button
				variant="tertiary"
				onClick={ onBack }
				icon={ arrowLeft }
				style={ { marginBottom: '16px' } }
			>
				{ __( 'Back to History', 'sd-ai-agent' ) }
			</Button>

			<Card>
				<CardHeader>
					<h2>{ run.name }</h2>
				</CardHeader>
				<CardBody>
					{ run.description && <p>{ run.description }</p> }
					<p>
						<strong>{ __( 'Test Suite:', 'sd-ai-agent' ) }</strong>{ ' ' }
						{ run.test_suite }
					</p>
					<p>
						<strong>{ __( 'Started:', 'sd-ai-agent' ) }</strong>{ ' ' }
						{ formatDate( run.started_at ) }
					</p>
					{ run.completed_at && (
						<p>
							<strong>
								{ __( 'Completed:', 'sd-ai-agent' ) }
							</strong>{ ' ' }
							{ formatDate( run.completed_at ) }
						</p>
					) }
				</CardBody>
			</Card>

			<div className="sd-ai-agent-benchmark-summary">
				<StatCard
					label={ __( 'Total Questions', 'sd-ai-agent' ) }
					value={ results.length }
				/>
				<StatCard
					label={ __( 'Accuracy', 'sd-ai-agent' ) }
					value={ `${ accuracy }%` }
				/>
				<StatCard
					label={ __( 'Correct', 'sd-ai-agent' ) }
					value={ correctCount }
				/>
				<StatCard
					label={ __( 'Avg Latency', 'sd-ai-agent' ) }
					value={ `${ avgLatency }ms` }
				/>
				<StatCard
					label={ __( 'Total Tokens', 'sd-ai-agent' ) }
					value={ totalTokens.toLocaleString() }
				/>
			</div>

			{ Object.keys( byModel ).length > 0 && (
				<Card style={ { marginTop: '20px' } }>
					<CardHeader>
						<h3>{ __( 'Results by Model', 'sd-ai-agent' ) }</h3>
					</CardHeader>
					<CardBody>
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Model', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Provider', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Total', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Correct', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Accuracy', 'sd-ai-agent' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ Object.values( byModel ).map( ( model ) => (
									<tr key={ model.model_id }>
										<td>{ model.model_id }</td>
										<td>
											{ model.provider_id ||
												__( 'Default', 'sd-ai-agent' ) }
										</td>
										<td>{ model.total }</td>
										<td>{ model.correct }</td>
										<td>
											{ Math.round(
												( model.correct /
													model.total ) *
													100
											) }
											%
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</CardBody>
				</Card>
			) }

			{ Object.keys( byCategory ).length > 0 && (
				<Card style={ { marginTop: '20px' } }>
					<CardHeader>
						<h3>{ __( 'Results by Category', 'sd-ai-agent' ) }</h3>
					</CardHeader>
					<CardBody>
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Category', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Total', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Correct', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Accuracy', 'sd-ai-agent' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ Object.values( byCategory ).map( ( cat ) => (
									<tr key={ cat.category }>
										<td>{ cat.category }</td>
										<td>{ cat.total }</td>
										<td>{ cat.correct }</td>
										<td>
											{ Math.round(
												( cat.correct / cat.total ) *
													100
											) }
											%
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</CardBody>
				</Card>
			) }

			<Card style={ { marginTop: '20px' } }>
				<CardHeader>
					<h3>{ __( 'Detailed Results', 'sd-ai-agent' ) }</h3>
				</CardHeader>
				<CardBody>
					<div className="sd-ai-agent-benchmark-results-table">
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style={ { width: '30px' } }></th>
									<th>
										{ __( 'Question ID', 'sd-ai-agent' ) }
									</th>
									<th>{ __( 'Category', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Model', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Correct', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Answer', 'sd-ai-agent' ) }</th>
									<th>{ __( 'Latency', 'sd-ai-agent' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ results.map( ( result, index ) => (
									<tr key={ index }>
										<td>
											{ result.is_correct ? (
												<Icon
													icon={ check }
													style={ {
														color: '#1a7f37',
													} }
												/>
											) : (
												<Icon
													icon={ closeSmall }
													style={ {
														color: '#cf222e',
													} }
												/>
											) }
										</td>
										<td>{ result.question_id }</td>
										<td>{ result.question_category }</td>
										<td>{ result.model_id }</td>
										<td>{ result.correct_answer }</td>
										<td
											className={
												result.is_correct
													? 'is-correct'
													: 'is-incorrect'
											}
										>
											{ result.model_answer.substring(
												0,
												50
											) }
											{ result.model_answer.length > 50
												? '...'
												: '' }
										</td>
										<td>{ result.latency_ms }ms</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}
