/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	Card,
	CardBody,
	CardHeader,
} from '@wordpress/components';

/**
 * Run List Component
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.runs        Benchmark runs.
 * @param {Function} props.onViewRun   View run callback.
 * @param {Function} props.onDeleteRun Delete run callback.
 * @param {boolean}  props.isLoading   Loading state.
 * @return {JSX.Element} Component element.
 */
export default function RunList( { runs, onViewRun, onDeleteRun, isLoading } ) {
	const formatDate = ( dateString ) => {
		if ( ! dateString ) {
			return __( 'N/A', 'gratis-ai-agent' );
		}
		const date = new Date( dateString );
		return date.toLocaleString();
	};

	const formatDuration = ( startedAt, completedAt ) => {
		if ( ! startedAt || ! completedAt ) {
			return __( 'N/A', 'gratis-ai-agent' );
		}
		const start = new Date( startedAt );
		const end = new Date( completedAt );
		const diff = Math.round( ( end - start ) / 1000 );

		if ( diff < 60 ) {
			return `${ diff }s`;
		} else if ( diff < 3600 ) {
			return `${ Math.round( diff / 60 ) }m`;
		}
		return `${ Math.round( diff / 3600 ) }h ${ Math.round(
			( diff % 3600 ) / 60
		) }m`;
	};

	const getStatusClass = ( status ) => {
		switch ( status ) {
			case 'pending':
				return 'pending';
			case 'running':
				return 'running';
			case 'completed':
				return 'completed';
			case 'failed':
				return 'failed';
			default:
				return '';
		}
	};

	if ( isLoading && runs.length === 0 ) {
		return (
			<div className="gratis-ai-agent-benchmark-loading">
				<Spinner />
			</div>
		);
	}

	if ( runs.length === 0 ) {
		return (
			<Card>
				<CardBody>
					<div className="gratis-ai-agent-benchmark-empty">
						<p>
							{ __(
								'No benchmark runs yet.',
								'gratis-ai-agent'
							) }
						</p>
						<p>
							{ __(
								'Create a new benchmark to get started.',
								'gratis-ai-agent'
							) }
						</p>
					</div>
				</CardBody>
			</Card>
		);
	}

	return (
		<div className="gratis-ai-agent-benchmark-run-list">
			<Card>
				<CardHeader>
					<h2>{ __( 'Benchmark History', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<table className="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>{ __( 'Name', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Suite', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Status', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Progress', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Started', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Duration', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Actions', 'gratis-ai-agent' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ runs.map( ( run ) => (
								<tr key={ run.id }>
									<td>
										<strong>{ run.name }</strong>
										{ run.description && (
											<p
												className="description"
												style={ { margin: '4px 0 0' } }
											>
												{ run.description }
											</p>
										) }
									</td>
									<td>{ run.test_suite }</td>
									<td>
										<span
											className={ `gratis-ai-agent-benchmark-status ${ getStatusClass(
												run.status
											) }` }
										>
											{ run.status }
										</span>
									</td>
									<td>
										{ run.questions_count > 0
											? `${ run.completed_count } / ${ run.questions_count }`
											: __( 'N/A', 'gratis-ai-agent' ) }
									</td>
									<td>{ formatDate( run.started_at ) }</td>
									<td>
										{ formatDuration(
											run.started_at,
											run.completed_at
										) }
									</td>
									<td>
										<Button
											variant="secondary"
											size="small"
											onClick={ () =>
												onViewRun( run.id )
											}
											style={ { marginRight: '8px' } }
										>
											{ __( 'View', 'gratis-ai-agent' ) }
										</Button>
										<Button
											variant="tertiary"
											isDestructive
											size="small"
											onClick={ () =>
												onDeleteRun( run.id )
											}
										>
											{ __(
												'Delete',
												'gratis-ai-agent'
											) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</CardBody>
			</Card>
		</div>
	);
}
