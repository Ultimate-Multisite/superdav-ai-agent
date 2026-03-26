/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardHeader,
	CardBody,
	TabPanel,
	Panel,
	PanelBody,
	PanelRow,
	Button,
	Notice,
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
	ProgressBar,
} from '@wordpress/components';
import { help, arrowLeft } from '@wordpress/icons';

/**
 * Settings Route Component
 *
 * @param {Object} props          Component props.
 * @param {string} props.subRoute Current sub-route.
 * @return {JSX.Element} Settings route element.
 */
export default function SettingsRoute( { subRoute } ) {
	const initialTab = subRoute === 'advanced' ? 'advanced' : 'general';

	const tabs = [
		{ name: 'general', title: __( 'General', 'gratis-ai-agent' ) },
		{ name: 'providers', title: __( 'Providers', 'gratis-ai-agent' ) },
		{ name: 'advanced', title: __( 'Advanced', 'gratis-ai-agent' ) },
	];

	return (
		<div className="gratis-ai-route gratis-ai-route-settings">
			<Card>
				<CardHeader>
					<h2>{ __( 'Settings', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<TabPanel
						className="gratis-ai-settings-tabs"
						activeClass="is-active"
						tabs={ tabs }
						initialTabName={ initialTab }
					>
						{ ( tab ) => {
							switch ( tab.name ) {
								case 'general':
									return <GeneralSettings />;
								case 'providers':
									return <ProviderSettings />;
								case 'advanced':
									return <AdvancedSettings />;
								default:
									return null;
							}
						} }
					</TabPanel>
				</CardBody>
			</Card>
		</div>
	);
}

/**
 * General Settings Tab
 *
 * @return {JSX.Element} General settings element.
 */
function GeneralSettings() {
	return (
		<Panel>
			<PanelBody title={ __( 'General Settings', 'gratis-ai-agent' ) }>
				<PanelRow>
					<p>
						{ __( 'General settings content.', 'gratis-ai-agent' ) }
					</p>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
}

/**
 * Provider Settings Tab
 *
 * @return {JSX.Element} Provider settings element.
 */
function ProviderSettings() {
	return (
		<Panel>
			<PanelBody title={ __( 'AI Providers', 'gratis-ai-agent' ) }>
				<PanelRow>
					<p>
						{ __(
							'Configure AI provider API keys and settings.',
							'gratis-ai-agent'
						) }
					</p>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
}

/**
 * Advanced Settings Tab — includes the benchmark feature.
 *
 * @return {JSX.Element} Advanced settings element.
 */
function AdvancedSettings() {
	const [ showBenchmark, setShowBenchmark ] = useState( false );

	return (
		<div className="gratis-ai-advanced-settings">
			{ ! showBenchmark ? (
				<Panel>
					<PanelBody
						title={ __( 'Advanced Features', 'gratis-ai-agent' ) }
					>
						<PanelRow>
							<div style={ { width: '100%' } }>
								<h4>
									{ __(
										'Model Benchmark',
										'gratis-ai-agent'
									) }
								</h4>
								<p className="description">
									{ __(
										'Benchmark AI models against WordPress knowledge tests. Compare performance, accuracy, and cost across different providers.',
										'gratis-ai-agent'
									) }
								</p>
								<Notice
									status="warning"
									isDismissible={ false }
									className="gratis-ai-notice-advanced"
								>
									{ __(
										'This feature is for advanced users and will consume API credits.',
										'gratis-ai-agent'
									) }
								</Notice>
								<Button
									variant="secondary"
									onClick={ () => setShowBenchmark( true ) }
									icon={ help }
									style={ { marginTop: '12px' } }
								>
									{ __(
										'Open Model Benchmark',
										'gratis-ai-agent'
									) }
								</Button>
							</div>
						</PanelRow>
					</PanelBody>
				</Panel>
			) : (
				<div className="gratis-ai-benchmark-section">
					<Button
						variant="tertiary"
						onClick={ () => setShowBenchmark( false ) }
						icon={ arrowLeft }
						style={ { marginBottom: '16px' } }
					>
						{ __( 'Back to Advanced Settings', 'gratis-ai-agent' ) }
					</Button>
					<EmbeddedBenchmark />
				</div>
			) }
		</div>
	);
}

/**
 * Embedded Benchmark Component
 *
 * @return {JSX.Element} Embedded benchmark element.
 */
function EmbeddedBenchmark() {
	const [ activeTab, setActiveTab ] = useState( 'new-run' );
	const [ suites, setSuites ] = useState( [] );
	const [ runs, setRuns ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const [ runName, setRunName ] = useState( '' );
	const [ runDescription, setRunDescription ] = useState( '' );
	const [ selectedSuite, setSelectedSuite ] = useState( 'wp-core-v1' );
	const [ selectedModels, setSelectedModels ] = useState( [] );
	const [ runProgress, setRunProgress ] = useState( null );
	const [ isRunning, setIsRunning ] = useState( false );

	const availableModels = [
		{
			id: 'claude-sonnet-4',
			name: 'Claude Sonnet 4',
			provider: 'anthropic',
		},
		{ id: 'gpt-4o', name: 'GPT-4o', provider: 'openai' },
		{ id: 'gpt-4o-mini', name: 'GPT-4o Mini', provider: 'openai' },
		{ id: 'gemini-2.5-pro', name: 'Gemini 2.5 Pro', provider: 'google' },
	];

	useEffect( () => {
		apiFetch( { path: '/gratis-ai-agent/v1/benchmark/suites' } )
			.then( setSuites )
			.catch( () => {} );
		apiFetch( { path: '/gratis-ai-agent/v1/benchmark/runs' } )
			.then( ( data ) => setRuns( data.runs || [] ) )
			.catch( () => {} );
	}, [] );

	const handleCreateRun = async () => {
		if ( ! runName.trim() || selectedModels.length === 0 ) {
			setNotice( {
				status: 'error',
				message: __(
					'Enter a name and select models.',
					'gratis-ai-agent'
				),
			} );
			return;
		}
		setIsLoading( true );
		try {
			const run = await apiFetch( {
				path: '/gratis-ai-agent/v1/benchmark/runs',
				method: 'POST',
				data: {
					name: runName,
					description: runDescription,
					test_suite: selectedSuite,
					models: selectedModels.map( ( m ) => ( {
						provider_id: m.provider,
						model_id: m.id,
					} ) ),
				},
			} );
			setIsRunning( true );
			setRunProgress( { completed: 0, total: run.questions_count } );
			runBenchmark( run.id );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: error.message || __( 'Failed.', 'gratis-ai-agent' ),
			} );
			setIsLoading( false );
		}
	};

	const runBenchmark = async ( runId ) => {
		try {
			const result = await apiFetch( {
				path: `/gratis-ai-agent/v1/benchmark/runs/${ runId }/run-next`,
				method: 'POST',
			} );
			if ( result.status === 'completed' ) {
				setIsRunning( false );
				setIsLoading( false );
				setRunProgress( result.progress );
				apiFetch( { path: '/gratis-ai-agent/v1/benchmark/runs' } )
					.then( ( data ) => setRuns( data.runs || [] ) )
					.catch( () => {} );
				return;
			}
			setRunProgress( result.progress );
			runBenchmark( runId );
		} catch {
			setIsRunning( false );
			setIsLoading( false );
		}
	};

	const toggleModel = ( model ) => {
		if ( selectedModels.find( ( m ) => m.id === model.id ) ) {
			setSelectedModels(
				selectedModels.filter( ( m ) => m.id !== model.id )
			);
		} else {
			setSelectedModels( [ ...selectedModels, model ] );
		}
	};

	const tabs = [
		{ name: 'new-run', title: __( 'New Benchmark', 'gratis-ai-agent' ) },
		{ name: 'history', title: __( 'History', 'gratis-ai-agent' ) },
	];

	return (
		<div className="gratis-ai-embedded-benchmark">
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
				tabs={ tabs }
				initialTabName={ activeTab }
				onSelect={ setActiveTab }
			>
				{ ( tab ) => {
					if ( tab.name === 'new-run' ) {
						return (
							<div style={ { padding: '16px 0' } }>
								{ isRunning && runProgress && (
									<div className="gratis-ai-benchmark-progress">
										<Notice
											status="info"
											isDismissible={ false }
										>
											{ __(
												'Benchmark running…',
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
												'completed',
												'gratis-ai-agent'
											) }
										</p>
									</div>
								) }
								<TextControl
									label={ __(
										'Run Name',
										'gratis-ai-agent'
									) }
									value={ runName }
									onChange={ setRunName }
									disabled={ isRunning }
								/>
								<TextareaControl
									label={ __(
										'Description',
										'gratis-ai-agent'
									) }
									value={ runDescription }
									onChange={ setRunDescription }
									disabled={ isRunning }
								/>
								<SelectControl
									label={ __(
										'Test Suite',
										'gratis-ai-agent'
									) }
									value={ selectedSuite }
									options={ suites.map( ( s ) => ( {
										label: s.name,
										value: s.slug,
									} ) ) }
									onChange={ setSelectedSuite }
									disabled={ isRunning }
								/>
								<div className="gratis-ai-benchmark-models">
									<h4>
										{ __(
											'Select Models',
											'gratis-ai-agent'
										) }
									</h4>
									{ availableModels.map( ( model ) => (
										<CheckboxControl
											key={ model.id }
											label={ `${ model.name } (${ model.provider })` }
											checked={
												!! selectedModels.find(
													( m ) => m.id === model.id
												)
											}
											onChange={ () =>
												toggleModel( model )
											}
											disabled={ isRunning }
										/>
									) ) }
								</div>
								<Button
									variant="primary"
									onClick={ handleCreateRun }
									disabled={ isLoading || isRunning }
									isBusy={ isLoading }
								>
									{ isRunning
										? __( 'Running…', 'gratis-ai-agent' )
										: __(
												'Start Benchmark',
												'gratis-ai-agent'
										  ) }
								</Button>
							</div>
						);
					}
					return (
						<div style={ { padding: '16px 0' } }>
							{ runs.length === 0 ? (
								<p>
									{ __(
										'No benchmark runs yet.',
										'gratis-ai-agent'
									) }
								</p>
							) : (
								<table className="wp-list-table widefat fixed striped">
									<thead>
										<tr>
											<th>
												{ __(
													'Name',
													'gratis-ai-agent'
												) }
											</th>
											<th>
												{ __(
													'Status',
													'gratis-ai-agent'
												) }
											</th>
											<th>
												{ __(
													'Progress',
													'gratis-ai-agent'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ runs.map( ( run ) => (
											<tr key={ run.id }>
												<td>{ run.name }</td>
												<td>{ run.status }</td>
												<td>
													{ run.completed_count } /{ ' ' }
													{ run.questions_count }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							) }
						</div>
					);
				} }
			</TabPanel>
		</div>
	);
}
