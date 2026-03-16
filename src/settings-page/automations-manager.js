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
	BaseControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { trash, pencil, plus } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const CHANNEL_TYPE_OPTIONS = [
	{ label: __( 'Slack', 'ai-agent' ), value: 'slack' },
	{ label: __( 'Discord', 'ai-agent' ), value: 'discord' },
];

/**
 * Returns a default empty notification channel object.
 *
 * @return {Object} Default channel configuration.
 */
function emptyChannel() {
	return { type: 'slack', webhook_url: '', enabled: true };
}

const SCHEDULE_OPTIONS = [
	{ label: __( 'Hourly', 'ai-agent' ), value: 'hourly' },
	{ label: __( 'Twice Daily', 'ai-agent' ), value: 'twicedaily' },
	{ label: __( 'Daily', 'ai-agent' ), value: 'daily' },
	{ label: __( 'Weekly', 'ai-agent' ), value: 'weekly' },
];

/**
 *
 */
function emptyForm() {
	return {
		name: '',
		description: '',
		prompt: '',
		schedule: 'daily',
		tool_profile: '',
		max_iterations: 10,
		enabled: true,
		notification_channels: [],
	};
}

/**
 *
 */
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
	const [ testingChannel, setTestingChannel ] = useState( null );

	const fetchAll = useCallback( async () => {
		try {
			const [ result, tpl, prof ] = await Promise.all( [
				apiFetch( { path: '/ai-agent/v1/automations' } ),
				apiFetch( { path: '/ai-agent/v1/automation-templates' } ),
				apiFetch( { path: '/ai-agent/v1/tool-profiles' } ).catch(
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
					path: `/ai-agent/v1/automations/${ editId }`,
					method: 'PATCH',
					data: form,
				} );
			} else {
				await apiFetch( {
					path: '/ai-agent/v1/automations',
					method: 'POST',
					data: form,
				} );
			}
			resetForm();
			fetchAll();
			setNotice( {
				status: 'success',
				message: __( 'Automation saved.', 'ai-agent' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message: err.message || __( 'Failed to save.', 'ai-agent' ),
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
			notification_channels: auto.notification_channels || [],
		} );
		setShowForm( true );
	}, [] );

	const addChannel = useCallback( () => {
		setForm( ( prev ) => ( {
			...prev,
			notification_channels: [
				...( prev.notification_channels || [] ),
				emptyChannel(),
			],
		} ) );
	}, [] );

	const removeChannel = useCallback( ( idx ) => {
		setForm( ( prev ) => {
			const channels = [ ...( prev.notification_channels || [] ) ];
			channels.splice( idx, 1 );
			return { ...prev, notification_channels: channels };
		} );
	}, [] );

	const updateChannel = useCallback( ( idx, key, value ) => {
		setForm( ( prev ) => {
			const channels = [ ...( prev.notification_channels || [] ) ];
			channels[ idx ] = { ...channels[ idx ], [ key ]: value };
			return { ...prev, notification_channels: channels };
		} );
	}, [] );

	const handleTestChannel = useCallback(
		async ( idx ) => {
			const channel = form.notification_channels[ idx ];
			if ( ! channel?.webhook_url ) {
				return;
			}
			setTestingChannel( idx );
			try {
				const result = await apiFetch( {
					path: '/ai-agent/v1/automations/test-notification',
					method: 'POST',
					data: {
						type: channel.type,
						webhook_url: channel.webhook_url,
					},
				} );
				setNotice( {
					status: result.success ? 'success' : 'error',
					message: result.message,
				} );
			} catch ( err ) {
				setNotice( {
					status: 'error',
					message: err.message || __( 'Test failed.', 'ai-agent' ),
				} );
			}
			setTestingChannel( null );
		},
		[ form.notification_channels ]
	);

	const handleDelete = useCallback(
		async ( id ) => {
			if (
				// eslint-disable-next-line no-alert
				window.confirm( __( 'Delete this automation?', 'ai-agent' ) )
			) {
				await apiFetch( {
					path: `/ai-agent/v1/automations/${ id }`,
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
				path: `/ai-agent/v1/automations/${ auto.id }`,
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
					path: `/ai-agent/v1/automations/${ id }/run`,
					method: 'POST',
				} );
				setNotice( {
					status: result.success ? 'success' : 'warning',
					message: result.success
						? __( 'Automation ran successfully.', 'ai-agent' )
						: result.error ||
						  __( 'Automation completed with errors.', 'ai-agent' ),
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
					path: `/ai-agent/v1/automations/${ id }/logs`,
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
			description: tpl.description || '',
			prompt: tpl.prompt,
			schedule: tpl.schedule,
			notification_channels: [],
		} );
		setShowForm( true );
		setEditId( null );
	}, [] );

	const profileOptions = [
		{ label: __( 'None (all tools)', 'ai-agent' ), value: '' },
		...profiles.map( ( p ) => ( { label: p.name, value: p.slug } ) ),
	];

	return (
		<div className="ai-agent-automations-manager">
			<div className="ai-agent-skill-header">
				<div>
					<h3>{ __( 'Scheduled Automations', 'ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Cron-based AI tasks that run on a schedule.',
							'ai-agent'
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
						{ __( 'Add Automation', 'ai-agent' ) }
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
						<h4>{ __( 'Quick Start Templates', 'ai-agent' ) }</h4>
						<div className="ai-agent-skill-cards">
							{ templates.map( ( tpl, idx ) => (
								<div
									key={ idx }
									className="ai-agent-skill-card"
								>
									<div className="ai-agent-skill-card-header">
										<div className="ai-agent-skill-card-title">
											<strong>{ tpl.name }</strong>
										</div>
									</div>
									<p className="ai-agent-skill-card-description">
										{ tpl.description }
									</p>
									<div className="ai-agent-skill-card-footer">
										<span className="ai-agent-skill-word-count">
											{ tpl.schedule }
										</span>
										<Button
											variant="secondary"
											size="compact"
											onClick={ () =>
												handleUseTemplate( tpl )
											}
										>
											{ __( 'Use Template', 'ai-agent' ) }
										</Button>
									</div>
								</div>
							) ) }
						</div>
					</div>
				) }

			{ showForm && (
				<div className="ai-agent-skill-form">
					<TextControl
						label={ __( 'Name', 'ai-agent' ) }
						value={ form.name }
						onChange={ ( v ) => updateForm( 'name', v ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'ai-agent' ) }
						value={ form.description }
						onChange={ ( v ) => updateForm( 'description', v ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Prompt', 'ai-agent' ) }
						value={ form.prompt }
						onChange={ ( v ) => updateForm( 'prompt', v ) }
						rows={ 6 }
						help={ __(
							'The instruction sent to the AI when this automation runs.',
							'ai-agent'
						) }
					/>
					<SelectControl
						label={ __( 'Schedule', 'ai-agent' ) }
						value={ form.schedule }
						options={ SCHEDULE_OPTIONS }
						onChange={ ( v ) => updateForm( 'schedule', v ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Tool Profile', 'ai-agent' ) }
						value={ form.tool_profile }
						options={ profileOptions }
						onChange={ ( v ) => updateForm( 'tool_profile', v ) }
						help={ __(
							'Restrict which tools this automation can use.',
							'ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Max Iterations', 'ai-agent' ) }
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

					<BaseControl
						id="ai-agent-notification-channels"
						label={ __( 'Notification Channels', 'ai-agent' ) }
						help={ __(
							'Send Slack or Discord messages after each run.',
							'ai-agent'
						) }
						__nextHasNoMarginBottom
					>
						{ ( form.notification_channels || [] ).map(
							( channel, idx ) => (
								<div
									key={ idx }
									className="ai-agent-notification-channel"
									style={ {
										display: 'flex',
										gap: '8px',
										alignItems: 'flex-end',
										marginBottom: '8px',
										flexWrap: 'wrap',
									} }
								>
									<SelectControl
										label={
											idx === 0
												? __( 'Type', 'ai-agent' )
												: undefined
										}
										value={ channel.type }
										options={ CHANNEL_TYPE_OPTIONS }
										onChange={ ( v ) =>
											updateChannel( idx, 'type', v )
										}
										style={ { minWidth: '100px' } }
										__nextHasNoMarginBottom
									/>
									<TextControl
										label={
											idx === 0
												? __(
														'Webhook URL',
														'ai-agent'
												  )
												: undefined
										}
										value={ channel.webhook_url }
										onChange={ ( v ) =>
											updateChannel(
												idx,
												'webhook_url',
												v
											)
										}
										placeholder={
											'slack' === channel.type
												? 'https://hooks.slack.com/…'
												: 'https://discord.com/api/webhooks/…'
										}
										style={ { flex: 1, minWidth: '220px' } }
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={ __( 'On', 'ai-agent' ) }
										checked={ channel.enabled }
										onChange={ ( v ) =>
											updateChannel( idx, 'enabled', v )
										}
										__nextHasNoMarginBottom
									/>
									<Button
										variant="secondary"
										size="compact"
										onClick={ () =>
											handleTestChannel( idx )
										}
										disabled={
											! channel.webhook_url ||
											testingChannel === idx
										}
									>
										{ testingChannel === idx ? (
											<Spinner />
										) : (
											__( 'Test', 'ai-agent' )
										) }
									</Button>
									<Button
										icon={ trash }
										size="compact"
										isDestructive
										label={ __(
											'Remove channel',
											'ai-agent'
										) }
										onClick={ () => removeChannel( idx ) }
									/>
								</div>
							)
						) }
						<Button
							variant="tertiary"
							icon={ plus }
							size="compact"
							onClick={ addChannel }
						>
							{ __( 'Add Channel', 'ai-agent' ) }
						</Button>
					</BaseControl>

					<div className="ai-agent-skill-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={
								! form.name.trim() || ! form.prompt.trim()
							}
							size="compact"
						>
							{ editId
								? __( 'Update', 'ai-agent' )
								: __( 'Create', 'ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ resetForm }
							size="compact"
						>
							{ __( 'Cancel', 'ai-agent' ) }
						</Button>
					</div>
				</div>
			) }

			{ ! loaded && (
				<p className="description">{ __( 'Loading…', 'ai-agent' ) }</p>
			) }

			{ loaded && automations.length > 0 && (
				<div
					className="ai-agent-skill-cards"
					style={ { marginTop: '16px' } }
				>
					{ automations.map( ( auto ) => (
						<div
							key={ auto.id }
							className={ `ai-agent-skill-card ${
								! auto.enabled
									? 'ai-agent-skill-card--disabled'
									: ''
							}` }
						>
							<div className="ai-agent-skill-card-header">
								<ToggleControl
									checked={ auto.enabled }
									onChange={ () => handleToggle( auto ) }
									__nextHasNoMarginBottom
								/>
								<div className="ai-agent-skill-card-title">
									<strong>{ auto.name }</strong>
									<span className="ai-agent-skill-badge">
										{ auto.schedule }
									</span>
									{ auto.notification_channels?.filter(
										( c ) => c.enabled
									).length > 0 && (
										<span
											className="ai-agent-skill-badge"
											title={ __(
												'Notifications configured',
												'ai-agent'
											) }
										>
											{
												auto.notification_channels.filter(
													( c ) => c.enabled
												).length
											}{ ' ' }
											{ __( 'notification', 'ai-agent' ) }
											{ auto.notification_channels.filter(
												( c ) => c.enabled
											).length > 1
												? 's'
												: '' }
										</span>
									) }
								</div>
							</div>
							<p className="ai-agent-skill-card-description">
								{ auto.description ||
									auto.prompt.slice( 0, 100 ) + '...' }
							</p>
							<div className="ai-agent-skill-card-footer">
								<span className="ai-agent-skill-word-count">
									{ auto.run_count }{ ' ' }
									{ __( 'runs', 'ai-agent' ) }
									{ auto.last_run_at && (
										<>
											{ ' ' }
											&middot;{ ' ' }
											{ __( 'Last:', 'ai-agent' ) }{ ' ' }
											{ auto.last_run_at }
										</>
									) }
								</span>
								<div className="ai-agent-skill-card-actions">
									<Button
										variant="secondary"
										size="small"
										onClick={ () => handleRun( auto.id ) }
										disabled={ running === auto.id }
									>
										{ running === auto.id ? (
											<Spinner />
										) : (
											__( 'Run Now', 'ai-agent' )
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
											? __( 'Hide Logs', 'ai-agent' )
											: __( 'Logs', 'ai-agent' ) }
									</Button>
									<Button
										icon={ pencil }
										size="small"
										label={ __( 'Edit', 'ai-agent' ) }
										onClick={ () => handleEdit( auto ) }
									/>
									<Button
										icon={ trash }
										size="small"
										label={ __( 'Delete', 'ai-agent' ) }
										isDestructive
										onClick={ () =>
											handleDelete( auto.id )
										}
									/>
								</div>
							</div>

							{ viewLogsId === auto.id && (
								<div className="ai-agent-automation-logs">
									{ logs.length === 0 && (
										<p className="description">
											{ __( 'No logs yet.', 'ai-agent' ) }
										</p>
									) }
									{ logs.map( ( log ) => (
										<div
											key={ log.id }
											className={ `ai-agent-log-entry ai-agent-log--${ log.status }` }
										>
											<div className="ai-agent-log-meta">
												<span
													className={ `ai-agent-log-status ai-agent-log-status--${ log.status }` }
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
												<p className="ai-agent-log-error">
													{ log.error_message }
												</p>
											) }
											{ log.reply && (
												<details>
													<summary>
														{ __(
															'Response',
															'ai-agent'
														) }
													</summary>
													<pre className="ai-agent-log-reply">
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
