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
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { trash, pencil, plus } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 *
 */
function emptyForm() {
	return {
		name: '',
		description: '',
		hook_name: '',
		prompt_template: '',
		conditions: '{}',
		max_iterations: 10,
		enabled: true,
	};
}

/**
 *
 */
export default function EventsManager() {
	const [ events, setEvents ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const [ triggers, setTriggers ] = useState( [] );
	const [ showForm, setShowForm ] = useState( false );
	const [ editId, setEditId ] = useState( null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ logs, setLogs ] = useState( [] );
	const [ viewLogsId, setViewLogsId ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const fetchAll = useCallback( async () => {
		try {
			const [ result, trigs ] = await Promise.all( [
				apiFetch( { path: '/sd-ai-agent/v1/event-automations' } ),
				apiFetch( { path: '/sd-ai-agent/v1/event-triggers' } ),
			] );
			setEvents( result );
			setTriggers( trigs );
		} catch {
			setEvents( [] );
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
		if (
			! form.name.trim() ||
			! form.hook_name ||
			! form.prompt_template.trim()
		) {
			return;
		}
		setNotice( null );
		try {
			const data = {
				...form,
				conditions:
					typeof form.conditions === 'string'
						? JSON.parse( form.conditions )
						: form.conditions,
			};

			if ( editId ) {
				await apiFetch( {
					path: `/sd-ai-agent/v1/event-automations/${ editId }`,
					method: 'PATCH',
					data,
				} );
			} else {
				await apiFetch( {
					path: '/sd-ai-agent/v1/event-automations',
					method: 'POST',
					data,
				} );
			}
			resetForm();
			fetchAll();
			setNotice( {
				status: 'success',
				message: __( 'Event automation saved.', 'sd-ai-agent' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message: err.message || __( 'Failed to save.', 'sd-ai-agent' ),
			} );
		}
	}, [ form, editId, resetForm, fetchAll ] );

	const handleEdit = useCallback( ( ev ) => {
		setEditId( ev.id );
		setForm( {
			name: ev.name,
			description: ev.description || '',
			hook_name: ev.hook_name,
			prompt_template: ev.prompt_template,
			conditions: JSON.stringify( ev.conditions || {}, null, 2 ),
			max_iterations: ev.max_iterations || 10,
			enabled: ev.enabled,
		} );
		setShowForm( true );
	}, [] );

	const handleDelete = useCallback(
		async ( id ) => {
			if (
				// eslint-disable-next-line no-alert
				window.confirm(
					__( 'Delete this event automation?', 'sd-ai-agent' )
				)
			) {
				await apiFetch( {
					path: `/sd-ai-agent/v1/event-automations/${ id }`,
					method: 'DELETE',
				} );
				fetchAll();
			}
		},
		[ fetchAll ]
	);

	const handleToggle = useCallback(
		async ( ev ) => {
			await apiFetch( {
				path: `/sd-ai-agent/v1/event-automations/${ ev.id }`,
				method: 'PATCH',
				data: { enabled: ! ev.enabled },
			} );
			fetchAll();
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
					path: '/sd-ai-agent/v1/automation-logs?trigger_type=event&limit=20',
				} );
				setLogs( result );
				setViewLogsId( id );
			} catch {
				setLogs( [] );
			}
		},
		[ viewLogsId ]
	);

	// Group triggers by category.
	const triggersByCategory = triggers.reduce( ( acc, t ) => {
		if ( ! acc[ t.category ] ) {
			acc[ t.category ] = [];
		}
		acc[ t.category ].push( t );
		return acc;
	}, {} );

	const triggerOptions = [
		{ label: __( 'Select a trigger…', 'sd-ai-agent' ), value: '' },
		...Object.entries( triggersByCategory ).flatMap(
			( [ category, items ] ) => [
				{
					label: `--- ${
						category.charAt( 0 ).toUpperCase() + category.slice( 1 )
					} ---`,
					value: `__group_${ category }`,
					disabled: true,
				},
				...items.map( ( t ) => ( {
					label: `${ t.label } (${ t.hook_name })`,
					value: t.hook_name,
				} ) ),
			]
		),
	];

	const selectedTrigger = triggers.find(
		( t ) => t.hook_name === form.hook_name
	);

	return (
		<div className="sd-ai-agent-events-manager">
			<div className="sd-ai-agent-skill-header">
				<div>
					<h3>{ __( 'Event-Driven Automations', 'sd-ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Trigger AI actions when WordPress hooks fire — post published, user registered, order placed, etc.',
							'sd-ai-agent'
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
						{ __( 'Add Event', 'sd-ai-agent' ) }
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

			{ showForm && (
				<div className="sd-ai-agent-skill-form">
					<TextControl
						label={ __( 'Name', 'sd-ai-agent' ) }
						value={ form.name }
						onChange={ ( v ) => updateForm( 'name', v ) }
						placeholder={ __(
							'e.g., "Auto-tag new posts"',
							'sd-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'sd-ai-agent' ) }
						value={ form.description }
						onChange={ ( v ) => updateForm( 'description', v ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Trigger Hook', 'sd-ai-agent' ) }
						value={ form.hook_name }
						options={ triggerOptions }
						onChange={ ( v ) => {
							if ( v.startsWith( '__group_' ) ) {
								return;
							}
							updateForm( 'hook_name', v );
						} }
						__nextHasNoMarginBottom
					/>

					{ selectedTrigger && (
						<div className="sd-ai-agent-trigger-info">
							<p className="description">
								{ selectedTrigger.description }
							</p>
							{ ( () => {
								// The REST API returns placeholders as a
								// key→label object; normalise to an array of
								// keys so the component works with both the
								// real API and array-format mocks.
								const ph = selectedTrigger.placeholders;
								const keys = Array.isArray( ph )
									? ph.map( ( p ) => p.key )
									: Object.keys( ph || {} );
								return keys.length > 0 ? (
									<p className="description">
										<strong>
											{ __(
												'Available placeholders:',
												'sd-ai-agent'
											) }
										</strong>{ ' ' }
										{ keys
											.map( ( k ) => `{{${ k }}}` )
											.join( ', ' ) }
									</p>
								) : null;
							} )() }
							{ ( () => {
								// Same normalisation for conditions.
								const cond = selectedTrigger.conditions;
								const keys = Array.isArray( cond )
									? cond.map( ( c ) => c.key )
									: Object.keys( cond || {} );
								return keys.length > 0 ? (
									<p className="description">
										<strong>
											{ __(
												'Available conditions:',
												'sd-ai-agent'
											) }
										</strong>{ ' ' }
										{ keys.join( ', ' ) }
									</p>
								) : null;
							} )() }
						</div>
					) }

					<TextareaControl
						label={ __( 'Prompt Template', 'sd-ai-agent' ) }
						value={ form.prompt_template }
						onChange={ ( v ) => updateForm( 'prompt_template', v ) }
						rows={ 6 }
						help={ __(
							'Use {{placeholders}} for dynamic data from the triggering event.',
							'sd-ai-agent'
						) }
					/>
					<TextareaControl
						label={ __( 'Conditions (JSON)', 'sd-ai-agent' ) }
						value={ form.conditions }
						onChange={ ( v ) => updateForm( 'conditions', v ) }
						rows={ 3 }
						help={ __(
							'Optional. e.g., {"post_type":"post","new_status":"publish"}',
							'sd-ai-agent'
						) }
					/>
					<TextControl
						label={ __( 'Max Iterations', 'sd-ai-agent' ) }
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
					<div className="sd-ai-agent-skill-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={
								! form.name.trim() ||
								! form.hook_name ||
								! form.prompt_template.trim()
							}
							size="compact"
						>
							{ editId
								? __( 'Update', 'sd-ai-agent' )
								: __( 'Create', 'sd-ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ resetForm }
							size="compact"
						>
							{ __( 'Cancel', 'sd-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }

			{ ! loaded && (
				<p className="description">
					{ __( 'Loading…', 'sd-ai-agent' ) }
				</p>
			) }

			{ loaded && events.length === 0 && ! showForm && (
				<p className="description">
					{ __(
						'No event automations configured yet.',
						'sd-ai-agent'
					) }
				</p>
			) }

			{ events.length > 0 && (
				<div
					className="sd-ai-agent-skill-cards"
					style={ { marginTop: '16px' } }
				>
					{ events.map( ( ev ) => (
						<div
							key={ ev.id }
							className={ `sd-ai-agent-skill-card ${
								! ev.enabled
									? 'sd-ai-agent-skill-card--disabled'
									: ''
							}` }
						>
							<div className="sd-ai-agent-skill-card-header">
								<ToggleControl
									checked={ ev.enabled }
									onChange={ () => handleToggle( ev ) }
									__nextHasNoMarginBottom
								/>
								<div className="sd-ai-agent-skill-card-title">
									<strong>{ ev.name }</strong>
									<span className="sd-ai-agent-skill-badge">
										{ ev.hook_name }
									</span>
								</div>
							</div>
							<p className="sd-ai-agent-skill-card-description">
								{ ev.description ||
									ev.prompt_template.slice( 0, 100 ) + '...' }
							</p>
							<div className="sd-ai-agent-skill-card-footer">
								<span className="sd-ai-agent-skill-word-count">
									{ ev.run_count }{ ' ' }
									{ __( 'runs', 'sd-ai-agent' ) }
									{ ev.last_run_at && (
										<>
											{ ' ' }
											&middot;{ ' ' }
											{ __(
												'Last:',
												'sd-ai-agent'
											) }{ ' ' }
											{ ev.last_run_at }
										</>
									) }
								</span>
								<div className="sd-ai-agent-skill-card-actions">
									<Button
										variant="tertiary"
										size="small"
										onClick={ () =>
											handleViewLogs( ev.id )
										}
									>
										{ viewLogsId === ev.id
											? __( 'Hide Logs', 'sd-ai-agent' )
											: __( 'Logs', 'sd-ai-agent' ) }
									</Button>
									<Button
										icon={ pencil }
										size="small"
										label={ __( 'Edit', 'sd-ai-agent' ) }
										onClick={ () => handleEdit( ev ) }
									/>
									<Button
										icon={ trash }
										size="small"
										label={ __( 'Delete', 'sd-ai-agent' ) }
										isDestructive
										onClick={ () => handleDelete( ev.id ) }
									/>
								</div>
							</div>

							{ viewLogsId === ev.id && (
								<div className="sd-ai-agent-automation-logs">
									{ logs.length === 0 && (
										<p className="description">
											{ __(
												'No logs yet.',
												'sd-ai-agent'
											) }
										</p>
									) }
									{ logs.map( ( log ) => (
										<div
											key={ log.id }
											className={ `sd-ai-agent-log-entry sd-ai-agent-log--${ log.status }` }
										>
											<div className="sd-ai-agent-log-meta">
												<span
													className={ `sd-ai-agent-log-status sd-ai-agent-log-status--${ log.status }` }
												>
													{ log.status }
												</span>
												<span>
													{ log.trigger_name ||
														log.hook_name }
												</span>
												<span>{ log.created_at }</span>
												<span>
													{ log.duration_ms }ms
												</span>
											</div>
											{ log.error_message && (
												<p className="sd-ai-agent-log-error">
													{ log.error_message }
												</p>
											) }
											{ log.reply && (
												<details>
													<summary>
														{ __(
															'Response',
															'sd-ai-agent'
														) }
													</summary>
													<pre className="sd-ai-agent-log-reply">
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
