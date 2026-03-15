/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { trash, pencil, plus } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const SCHEDULE_OPTIONS = [
	{ label: __( 'Hourly', 'gratis-ai-agent' ), value: 'hourly' },
	{ label: __( 'Twice Daily', 'gratis-ai-agent' ), value: 'twicedaily' },
	{ label: __( 'Daily', 'gratis-ai-agent' ), value: 'daily' },
	{ label: __( 'Weekly', 'gratis-ai-agent' ), value: 'weekly' },
];

function emptyForm() {
	return {
		name: '',
		description: '',
		prompt: '',
		schedule: 'daily',
		tool_profile: '',
		max_iterations: 10,
		enabled: true,
	};
}

export default function AutomationsManager() {
	const [ automations, setAutomations ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const [ templates, setTemplates ] = useState( [] );
	const [ profiles, setProfiles ] = useState( [] );
	const [ showForm, setShowForm ] = useState( false );
	const [ editId, setEditId ] = useState( null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ logs, setLogs ] = useState( [] );
	const [ viewLogsId, setViewLogsId ] = useState( null );
	const [ running, setRunning ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const fetchAll = useCallback( async () => {
		try {
			const [ result, tpl, prof ] = await Promise.all( [
				apiFetch( { path: '/gratis-ai-agent/v1/automations' } ),
				apiFetch( {
					path: '/gratis-ai-agent/v1/automation-templates',
				} ),
				apiFetch( { path: '/gratis-ai-agent/v1/tool-profiles' } ).catch(
					() => []
				),
			] );
			setAutomations( result );
			setTemplates( tpl );
			setProfiles( prof );
		} catch {
			setAutomations( [] );
		}
		setLoaded( true );
	}, [] );

	useEffect( () => {
		fetchAll();
	}, [ fetchAll ] );

	const resetForm = useCallback( () => {
		setShowForm( false );
		setEditId( null );
		setForm( emptyForm() );
	}, [] );

	const updateForm = useCallback( ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	const handleSubmit = useCallback( async () => {
		if ( ! form.name.trim() || ! form.prompt.trim() ) {
			return;
		}
		setNotice( null );
		try {
			if ( editId ) {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/automations/${ editId }`,
					method: 'PATCH',
					data: form,
				} );
			} else {
				await apiFetch( {
					path: '/gratis-ai-agent/v1/automations',
					method: 'POST',
					data: form,
				} );
			}
			resetForm();
			fetchAll();
			setNotice( {
				status: 'success',
				message: __( 'Automation saved.', 'gratis-ai-agent' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err.message || __( 'Failed to save.', 'gratis-ai-agent' ),
			} );
		}
	}, [ form, editId, resetForm, fetchAll ] );

	const handleEdit = useCallback( ( auto ) => {
		setEditId( auto.id );
		setForm( {
			name: auto.name,
			description: auto.description || '',
			prompt: auto.prompt,
			schedule: auto.schedule,
			tool_profile: auto.tool_profile || '',
			max_iterations: auto.max_iterations || 10,
			enabled: auto.enabled,
		} );
		setShowForm( true );
	}, [] );

	const handleDelete = useCallback(
		async ( id ) => {
			// eslint-disable-next-line no-alert
			const confirmed = window.confirm(
				__( 'Delete this automation?', 'gratis-ai-agent' )
			);
			if ( confirmed ) {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/automations/${ id }`,
					method: 'DELETE',
				} );
				fetchAll();
			}
		},
		[ fetchAll ]
	);

	const handleToggle = useCallback(
		async ( auto ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/automations/${ auto.id }`,
				method: 'PATCH',
				data: { enabled: ! auto.enabled },
			} );
			fetchAll();
		},
		[ fetchAll ]
	);

	const handleRun = useCallback(
		async ( id ) => {
			setRunning( id );
			setNotice( null );
			try {
				const result = await apiFetch( {
					path: `/gratis-ai-agent/v1/automations/${ id }/run`,
					method: 'POST',
				} );
				setNotice( {
					status: result.success ? 'success' : 'warning',
					message: result.success
						? __(
								'Automation ran successfully.',
								'gratis-ai-agent'
						  )
						: result.error ||
						  __(
								'Automation completed with errors.',
								'gratis-ai-agent'
						  ),
				} );
				fetchAll();
			} catch ( err ) {
				setNotice( { status: 'error', message: err.message } );
			}
			setRunning( null );
		},
		[ fetchAll ]
	);

	const handleViewLogs = useCallback(
		async ( id ) => {
			if ( viewLogsId === id ) {
				setViewLogsId( null );
				setLogs( [] );
				return;
			}
			try {
				const result = await apiFetch( {
					path: `/gratis-ai-agent/v1/automations/${ id }/logs`,
				} );
				setLogs( result );
				setViewLogsId( id );
			} catch {
				setLogs( [] );
			}
		},
		[ viewLogsId ]
	);

	const handleUseTemplate = useCallback( ( tpl ) => {
		setForm( {
			...emptyForm(),
			name: tpl.name,
			description: tpl.description,
			prompt: tpl.prompt,
			schedule: tpl.schedule,
		} );
		setShowForm( true );
		setEditId( null );
	}, [] );

	const profileOptions = [
		{ label: __( 'None (all tools)', 'gratis-ai-agent' ), value: '' },
		...profiles.map( ( p ) => ( { label: p.name, value: p.slug } ) ),
	];

	return (
		<div className="gratis-ai-agent-automations-manager">
			<div className="gratis-ai-agent-skill-header">
				<div>
					<h3>
						{ __( 'Scheduled Automations', 'gratis-ai-agent' ) }
					</h3>
					<p className="description">
						{ __(
							'Cron-based AI tasks that run on a schedule.',
							'gratis-ai-agent'
						) }
					</p>
				</div>
				{ ! showForm && (
					<Button
						variant="secondary"
						icon={ plus }
						onClick={ () => {
							resetForm();
							setShowForm( true );
						} }
						size="compact"
					>
						{ __( 'Add Automation', 'gratis-ai-agent' ) }
					</Button>
				) }
			</div>

			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ ! showForm &&
				templates.length > 0 &&
				automations.length === 0 && (
					<div style={ { marginBottom: '16px' } }>
						<h4>
							{ __( 'Quick Start Templates', 'gratis-ai-agent' ) }
						</h4>
						<div className="gratis-ai-agent-skill-cards">
							{ templates.map( ( tpl, idx ) => (
								<div
									key={ idx }
									className="gratis-ai-agent-skill-card"
								>
									<div className="gratis-ai-agent-skill-card-header">
										<div className="gratis-ai-agent-skill-card-title">
											<strong>{ tpl.name }</strong>
										</div>
									</div>
									<p className="gratis-ai-agent-skill-card-description">
										{ tpl.description }
									</p>
									<div className="gratis-ai-agent-skill-card-footer">
										<span className="gratis-ai-agent-skill-word-count">
											{ tpl.schedule }
										</span>
										<Button
											variant="secondary"
											size="compact"
											onClick={ () =>
												handleUseTemplate( tpl )
											}
										>
											{ __(
												'Use Template',
												'gratis-ai-agent'
											) }
										</Button>
									</div>
								</div>
							) ) }
						</div>
					</div>
				) }

			{ showForm && (
				<div className="gratis-ai-agent-skill-form">
					<TextControl
						label={ __( 'Name', 'gratis-ai-agent' ) }
						value={ form.name }
						onChange={ ( v ) => updateForm( 'name', v ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'gratis-ai-agent' ) }
						value={ form.description }
						onChange={ ( v ) => updateForm( 'description', v ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Prompt', 'gratis-ai-agent' ) }
						value={ form.prompt }
						onChange={ ( v ) => updateForm( 'prompt', v ) }
						rows={ 6 }
						help={ __(
							'The instruction sent to the AI when this automation runs.',
							'gratis-ai-agent'
						) }
					/>
					<SelectControl
						label={ __( 'Schedule', 'gratis-ai-agent' ) }
						value={ form.schedule }
						options={ SCHEDULE_OPTIONS }
						onChange={ ( v ) => updateForm( 'schedule', v ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Tool Profile', 'gratis-ai-agent' ) }
						value={ form.tool_profile }
						options={ profileOptions }
						onChange={ ( v ) => updateForm( 'tool_profile', v ) }
						help={ __(
							'Restrict which tools this automation can use.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Max Iterations', 'gratis-ai-agent' ) }
						type="number"
						min={ 1 }
						max={ 50 }
						value={ form.max_iterations }
						onChange={ ( v ) =>
							updateForm(
								'max_iterations',
								parseInt( v, 10 ) || 10
							)
						}
						__nextHasNoMarginBottom
					/>
					<div className="gratis-ai-agent-skill-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={
								! form.name.trim() || ! form.prompt.trim()
							}
							size="compact"
						>
							{ editId
								? __( 'Update', 'gratis-ai-agent' )
								: __( 'Create', 'gratis-ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ resetForm }
							size="compact"
						>
							{ __( 'Cancel', 'gratis-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }

			{ ! loaded && (
				<p className="description">
					{ __( 'Loading…', 'gratis-ai-agent' ) }
				</p>
			) }

			{ loaded && automations.length > 0 && (
				<div
					className="gratis-ai-agent-skill-cards"
					style={ { marginTop: '16px' } }
				>
					{ automations.map( ( auto ) => (
						<div
							key={ auto.id }
							className={ `gratis-ai-agent-skill-card ${
								! auto.enabled
									? 'gratis-ai-agent-skill-card--disabled'
									: ''
							}` }
						>
							<div className="gratis-ai-agent-skill-card-header">
								<ToggleControl
									checked={ auto.enabled }
									onChange={ () => handleToggle( auto ) }
									__nextHasNoMarginBottom
								/>
								<div className="gratis-ai-agent-skill-card-title">
									<strong>{ auto.name }</strong>
									<span className="gratis-ai-agent-skill-badge">
										{ auto.schedule }
									</span>
								</div>
							</div>
							<p className="gratis-ai-agent-skill-card-description">
								{ auto.description ||
									auto.prompt.slice( 0, 100 ) + '...' }
							</p>
							<div className="gratis-ai-agent-skill-card-footer">
								<span className="gratis-ai-agent-skill-word-count">
									{ auto.run_count }{ ' ' }
									{ __( 'runs', 'gratis-ai-agent' ) }
									{ auto.last_run_at && (
										<>
											{ ' ' }
											&middot;{ ' ' }
											{ __(
												'Last:',
												'gratis-ai-agent'
											) }{ ' ' }
											{ auto.last_run_at }
										</>
									) }
								</span>
								<div className="gratis-ai-agent-skill-card-actions">
									<Button
										variant="secondary"
										size="small"
										onClick={ () => handleRun( auto.id ) }
										disabled={ running === auto.id }
									>
										{ running === auto.id ? (
											<Spinner />
										) : (
											__( 'Run Now', 'gratis-ai-agent' )
										) }
									</Button>
									<Button
										variant="tertiary"
										size="small"
										onClick={ () =>
											handleViewLogs( auto.id )
										}
									>
										{ viewLogsId === auto.id
											? __(
													'Hide Logs',
													'gratis-ai-agent'
											  )
											: __( 'Logs', 'gratis-ai-agent' ) }
									</Button>
									<Button
										icon={ pencil }
										size="small"
										label={ __(
											'Edit',
											'gratis-ai-agent'
										) }
										onClick={ () => handleEdit( auto ) }
									/>
									<Button
										icon={ trash }
										size="small"
										label={ __(
											'Delete',
											'gratis-ai-agent'
										) }
										isDestructive
										onClick={ () =>
											handleDelete( auto.id )
										}
									/>
								</div>
							</div>

							{ viewLogsId === auto.id && (
								<div className="gratis-ai-agent-automation-logs">
									{ logs.length === 0 && (
										<p className="description">
											{ __(
												'No logs yet.',
												'gratis-ai-agent'
											) }
										</p>
									) }
									{ logs.map( ( log ) => (
										<div
											key={ log.id }
											className={ `gratis-ai-agent-log-entry gratis-ai-agent-log--${ log.status }` }
										>
											<div className="gratis-ai-agent-log-meta">
												<span
													className={ `gratis-ai-agent-log-status gratis-ai-agent-log-status--${ log.status }` }
												>
													{ log.status }
												</span>
												<span>{ log.created_at }</span>
												<span>
													{ log.duration_ms }ms
												</span>
												{ log.prompt_tokens > 0 && (
													<span>
														{ log.prompt_tokens +
															log.completion_tokens }{ ' ' }
														tokens
													</span>
												) }
											</div>
											{ log.error_message && (
												<p className="gratis-ai-agent-log-error">
													{ log.error_message }
												</p>
											) }
											{ log.reply && (
												<details>
													<summary>
														{ __(
															'Response',
															'gratis-ai-agent'
														) }
													</summary>
													<pre className="gratis-ai-agent-log-reply">
														{ log.reply }
													</pre>
												</details>
											) }
										</div>
									) ) }
								</div>
							) }
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
