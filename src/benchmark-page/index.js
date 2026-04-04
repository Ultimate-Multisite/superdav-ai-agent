/**
 * WordPress dependencies
 */
import { createRoot, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	Notice,
	TabPanel,
	ProgressBar,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.css';
import ModelSelector from './model-selector';
import RunList from './run-list';
import RunDetails from './run-details';

/**
 * Root benchmark page application component.
 *
 * @return {JSX.Element} Benchmark page app element.
 */
function BenchmarkPageApp() {
	const [ activeTab, setActiveTab ] = useState( 'new-run' );
	const [ suites, setSuites ] = useState( [] );
	const [ providers, setProviders ] = useState( [] );
	const [ runs, setRuns ] = useState( [] );
	const [ selectedRun, setSelectedRun ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	// New run form state
	const [ runName, setRunName ] = useState( '' );
	const [ runDescription, setRunDescription ] = useState( '' );
	const [ selectedSuite, setSelectedSuite ] = useState( 'wp-core-v1' );
	const [ selectedModels, setSelectedModels ] = useState( [] );

	// Running state
	const [ runProgress, setRunProgress ] = useState( null );
	const [ isRunning, setIsRunning ] = useState( false );

	// Load initial data
	useEffect( () => {
		loadSuites();
		loadProviders();
		loadRuns();
	}, [] );

	const loadSuites = async () => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/benchmark/suites',
			} );
			setSuites( data );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to load benchmark suites.',
					'gratis-ai-agent'
				),
			} );
		}
	};

	const loadProviders = async () => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/providers',
			} );
			setProviders( data.providers || [] );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: __( 'Failed to load providers.', 'gratis-ai-agent' ),
			} );
		}
	};

	const loadRuns = async () => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/benchmark/runs',
			} );
			setRuns( data.runs || [] );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to load benchmark runs.',
					'gratis-ai-agent'
				),
			} );
		}
	};

	const handleCreateRun = async () => {
		if ( ! runName.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Please enter a run name.', 'gratis-ai-agent' ),
			} );
			return;
		}

		if ( selectedModels.length === 0 ) {
			setNotice( {
				status: 'error',
				message: __(
					'Please select at least one model.',
					'gratis-ai-agent'
				),
			} );
			return;
		}

		setIsLoading( true );
		setNotice( null );

		try {
			const run = await apiFetch( {
				path: '/gratis-ai-agent/v1/benchmark/runs',
				method: 'POST',
				data: {
					name: runName,
					description: runDescription,
					test_suite: selectedSuite,
					models: selectedModels,
				},
			} );

			setIsRunning( true );
			setRunProgress( {
				completed: 0,
				total: run.questions_count,
			} );

			setNotice( {
				status: 'success',
				message: __(
					'Benchmark run created. Starting tests…',
					'gratis-ai-agent'
				),
			} );

			// Start running questions
			runBenchmarkQuestions( run.id );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Failed to create benchmark run.', 'gratis-ai-agent' ),
			} );
			setIsLoading( false );
		}
	};

	const runBenchmarkQuestions = async ( runId ) => {
		const runNext = async () => {
			try {
				const result = await apiFetch( {
					path: `/gratis-ai-agent/v1/benchmark/runs/${ runId }/run-next`,
					method: 'POST',
				} );

				if ( result.status === 'completed' ) {
					setIsRunning( false );
					setIsLoading( false );
					setRunProgress( result.progress );
					setNotice( {
						status: 'success',
						message: __(
							'Benchmark completed!',
							'gratis-ai-agent'
						),
					} );
					loadRuns();
					return;
				}

				setRunProgress( result.progress );

				// Continue to next question
				runNext();
			} catch ( error ) {
				setIsRunning( false );
				setIsLoading( false );
				setNotice( {
					status: 'error',
					message:
						error.message ||
						__( 'Benchmark failed.', 'gratis-ai-agent' ),
				} );
				loadRuns();
			}
		};

		runNext();
	};

	const handleViewRun = async ( runId ) => {
		setIsLoading( true );
		try {
			const run = await apiFetch( {
				path: `/gratis-ai-agent/v1/benchmark/runs/${ runId }`,
			} );
			setSelectedRun( run );
			setActiveTab( 'view-run' );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: __( 'Failed to load run details.', 'gratis-ai-agent' ),
			} );
		}
		setIsLoading( false );
	};

	const handleDeleteRun = async ( runId ) => {
		if (
			// eslint-disable-next-line no-alert -- Intentional confirmation dialog for destructive delete action.
			! window.confirm(
				__(
					'Are you sure you want to delete this benchmark run?',
					'gratis-ai-agent'
				)
			)
		) {
			return;
		}

		setIsLoading( true );
		try {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/benchmark/runs/${ runId }`,
				method: 'DELETE',
			} );
			setRuns( runs.filter( ( r ) => r.id !== runId ) );
			setNotice( {
				status: 'success',
				message: __( 'Benchmark run deleted.', 'gratis-ai-agent' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: __( 'Failed to delete run.', 'gratis-ai-agent' ),
			} );
		}
		setIsLoading( false );
	};

	const tabs = [
		{
			name: 'new-run',
			title: __( 'New Benchmark', 'gratis-ai-agent' ),
		},
		{
			name: 'history',
			title: __( 'History', 'gratis-ai-agent' ),
		},
	];

	if ( selectedRun ) {
		tabs.push( {
			name: 'view-run',
			title: __( 'Run Details', 'gratis-ai-agent' ),
		} );
	}

	return (
		<div className="gratis-ai-agent-benchmark-page">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<TabPanel
				className="gratis-ai-agent-benchmark-tabs"
				activeClass="is-active"
				tabs={ tabs }
				initialTabName={ activeTab }
				onSelect={ setActiveTab }
			>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'new-run':
							return (
								<NewRunTab
									runName={ runName }
									setRunName={ setRunName }
									runDescription={ runDescription }
									setRunDescription={ setRunDescription }
									suites={ suites }
									selectedSuite={ selectedSuite }
									setSelectedSuite={ setSelectedSuite }
									providers={ providers }
									selectedModels={ selectedModels }
									setSelectedModels={ setSelectedModels }
									onCreateRun={ handleCreateRun }
									isLoading={ isLoading }
									isRunning={ isRunning }
									runProgress={ runProgress }
								/>
							);
						case 'history':
							return (
								<RunList
									runs={ runs }
									onViewRun={ handleViewRun }
									onDeleteRun={ handleDeleteRun }
									isLoading={ isLoading }
								/>
							);
						case 'view-run':
							return selectedRun ? (
								<RunDetails
									run={ selectedRun }
									onBack={ () => {
										setSelectedRun( null );
										setActiveTab( 'history' );
									} }
								/>
							) : null;
						default:
							return null;
					}
				} }
			</TabPanel>
		</div>
	);
}

/**
 * New Run Tab Component
 *
 * @param {Object}   props                   Component props.
 * @param {string}   props.runName           Run name.
 * @param {Function} props.setRunName        Set run name callback.
 * @param {string}   props.runDescription    Run description.
 * @param {Function} props.setRunDescription Set description callback.
 * @param {Array}    props.suites            Available suites.
 * @param {string}   props.selectedSuite     Selected suite.
 * @param {Function} props.setSelectedSuite  Set suite callback.
 * @param {Array}    props.providers         Available providers.
 * @param {Array}    props.selectedModels    Selected models.
 * @param {Function} props.setSelectedModels Set models callback.
 * @param {Function} props.onCreateRun       Create run callback.
 * @param {boolean}  props.isLoading         Loading state.
 * @param {boolean}  props.isRunning         Running state.
 * @param {Object}   props.runProgress       Progress object.
 * @return {JSX.Element} Component element.
 */
function NewRunTab( {
	runName,
	setRunName,
	runDescription,
	setRunDescription,
	suites,
	selectedSuite,
	setSelectedSuite,
	providers,
	selectedModels,
	setSelectedModels,
	onCreateRun,
	isLoading,
	isRunning,
	runProgress,
} ) {
	return (
		<div className="gratis-ai-agent-benchmark-new-run">
			<Card>
				<CardHeader>
					<h2>{ __( 'Configure Benchmark', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					{ isRunning && runProgress && (
						<div className="gratis-ai-agent-benchmark-progress">
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Benchmark is running…',
									'gratis-ai-agent'
								) }
							</Notice>
							<ProgressBar
								value={
									( runProgress.completed /
										runProgress.total ) *
									100
								}
							/>
							<p>
								{ runProgress.completed } /{ ' ' }
								{ runProgress.total }{ ' ' }
								{ __(
									'questions completed',
									'gratis-ai-agent'
								) }
							</p>
						</div>
					) }

					<TextControl
						label={ __( 'Run Name', 'gratis-ai-agent' ) }
						value={ runName }
						onChange={ setRunName }
						placeholder={ __(
							'e.g., Claude vs GPT-4 Comparison',
							'gratis-ai-agent'
						) }
						disabled={ isRunning }
					/>

					<TextareaControl
						label={ __( 'Description', 'gratis-ai-agent' ) }
						value={ runDescription }
						onChange={ setRunDescription }
						placeholder={ __(
							'Optional description of this benchmark run',
							'gratis-ai-agent'
						) }
						rows={ 3 }
						disabled={ isRunning }
					/>

					<SelectControl
						label={ __( 'Test Suite', 'gratis-ai-agent' ) }
						value={ selectedSuite }
						options={ suites.map( ( suite ) => ( {
							value: suite.slug,
							label: `${ suite.name } (${ suite.question_count } questions)`,
						} ) ) }
						onChange={ setSelectedSuite }
						disabled={ isRunning }
					/>

					<div className="gratis-ai-agent-benchmark-models">
						<h3>{ __( 'Select Models', 'gratis-ai-agent' ) }</h3>
						<ModelSelector
							providers={ providers }
							selectedModels={ selectedModels }
							onChange={ setSelectedModels }
							disabled={ isRunning }
						/>
					</div>

					<Button
						variant="primary"
						onClick={ onCreateRun }
						disabled={ isLoading || isRunning }
						isBusy={ isLoading || isRunning }
					>
						{ isRunning
							? __( 'Running…', 'gratis-ai-agent' )
							: __( 'Start Benchmark', 'gratis-ai-agent' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
}

const container = document.getElementById( 'gratis-ai-agent-benchmark-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <BenchmarkPageApp /> );
}
