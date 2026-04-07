/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	Notice,
	Spinner,
	Card,
	CardHeader,
	CardBody,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { trash, pencil, plus } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

const EMPTY_FORM = {
	slug: '',
	name: '',
	description: '',
	system_prompt: '',
	provider_id: '',
	model_id: '',
	temperature: '',
	max_iterations: '',
	greeting: '',
	avatar_icon: '',
};

/**
 *
 */
export default function AgentBuilder() {
	const {
		fetchAgents,
		createAgent,
		updateAgent,
		deleteAgent,
		fetchProviders,
	} = useDispatch( STORE_NAME );

	const { agents, agentsLoaded, providers } = useSelect(
		( select ) => ( {
			agents: select( STORE_NAME ).getAgents(),
			agentsLoaded: select( STORE_NAME ).getAgentsLoaded(),
			providers: select( STORE_NAME ).getProviders(),
		} ),
		[]
	);

	const [ showForm, setShowForm ] = useState( false );
	const [ editId, setEditId ] = useState( null );
	const [ form, setForm ] = useState( { ...EMPTY_FORM } );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	useEffect( () => {
		fetchAgents();
		fetchProviders();
	}, [ fetchAgents, fetchProviders ] );

	const resetForm = useCallback( () => {
		setShowForm( false );
		setEditId( null );
		setForm( { ...EMPTY_FORM } );
		setNotice( null );
	}, [] );

	const updateField = useCallback( ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	const handleEdit = useCallback( ( agent ) => {
		setEditId( agent.id );
		setForm( {
			slug: agent.slug || '',
			name: agent.name || '',
			description: agent.description || '',
			system_prompt: agent.system_prompt || '',
			provider_id: agent.provider_id || '',
			model_id: agent.model_id || '',
			temperature:
				null !== agent.temperature ? String( agent.temperature ) : '',
			max_iterations:
				null !== agent.max_iterations
					? String( agent.max_iterations )
					: '',
			greeting: agent.greeting || '',
			avatar_icon: agent.avatar_icon || '',
		} );
		setShowForm( true );
		setNotice( null );
	}, [] );

	const handleSubmit = useCallback( async () => {
		if ( ! form.name.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Agent name is required.', 'gratis-ai-agent' ),
			} );
			return;
		}
		if ( ! editId && ! form.slug.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Agent slug is required.', 'gratis-ai-agent' ),
			} );
			return;
		}

		setSaving( true );
		setNotice( null );

		try {
			const payload = {
				name: form.name,
				description: form.description,
				system_prompt: form.system_prompt,
				provider_id: form.provider_id,
				model_id: form.model_id,
				greeting: form.greeting,
				avatar_icon: form.avatar_icon,
			};

			if ( form.temperature !== '' ) {
				payload.temperature = parseFloat( form.temperature );
			}
			if ( form.max_iterations !== '' ) {
				payload.max_iterations = parseInt( form.max_iterations, 10 );
			}

			if ( editId ) {
				await updateAgent( editId, payload );
				setNotice( {
					status: 'success',
					message: __( 'Agent updated.', 'gratis-ai-agent' ),
				} );
			} else {
				payload.slug = form.slug;
				await createAgent( payload );
				setNotice( {
					status: 'success',
					message: __( 'Agent created.', 'gratis-ai-agent' ),
				} );
				resetForm();
			}
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to save agent.', 'gratis-ai-agent' ),
			} );
		}

		setSaving( false );
	}, [ form, editId, createAgent, updateAgent, resetForm ] );

	const handleDelete = useCallback(
		async ( agent ) => {
			if (
				// eslint-disable-next-line no-alert
				! window.confirm(
					sprintf(
						/* translators: %s: agent name */
						__(
							'Delete agent "%s"? This cannot be undone.',
							'gratis-ai-agent'
						),
						agent.name
					)
				)
			) {
				return;
			}
			await deleteAgent( agent.id );
		},
		[ deleteAgent ]
	);

	// Build provider options for the dropdown.
	const providerOptions = [
		{
			label: __( '(use global default)', 'gratis-ai-agent' ),
			value: '',
		},
		...providers.map( ( p ) => ( { label: p.name, value: p.id } ) ),
	];

	// Build model options based on selected provider.
	const selectedProvider = providers.find(
		( p ) => p.id === form.provider_id
	);
	const modelOptions = [
		{
			label: __( '(use global default)', 'gratis-ai-agent' ),
			value: '',
		},
		...( selectedProvider?.models || [] ).map( ( m ) => ( {
			label: m.name || m.id,
			value: m.id,
		} ) ),
	];

	if ( ! agentsLoaded ) {
		return (
			<div className="gratis-ai-agent-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="gratis-ai-agent-builder">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ ! showForm && (
				<>
					<p className="description">
						{ __(
							'Create specialized agents with custom system prompts, models, and tool profiles. Select an agent in the chat to use it.',
							'gratis-ai-agent'
						) }
					</p>

					{ agents.length === 0 && (
						<p>
							{ __(
								'No agents yet. Create your first agent below.',
								'gratis-ai-agent'
							) }
						</p>
					) }

					{ agents.map( ( agent ) => (
						<Card key={ agent.id } className="gratis-ai-agent-card">
							<CardHeader>
								<div className="gratis-ai-agent-card-header">
									<div className="gratis-ai-agent-card-title">
										<strong>{ agent.name }</strong>
										{ agent.description && (
											<span className="gratis-ai-agent-card-desc">
												{ agent.description }
											</span>
										) }
									</div>
									<div className="gratis-ai-agent-card-actions">
										<Button
											icon={ pencil }
											label={ __(
												'Edit agent',
												'gratis-ai-agent'
											) }
											onClick={ () =>
												handleEdit( agent )
											}
											size="small"
										/>
										<Button
											icon={ trash }
											label={ __(
												'Delete agent',
												'gratis-ai-agent'
											) }
											onClick={ () =>
												handleDelete( agent )
											}
											isDestructive
											size="small"
										/>
									</div>
								</div>
							</CardHeader>
							<CardBody>
								<div className="gratis-ai-agent-card-meta">
									{ agent.provider_id && (
										<span>
											<strong>
												{ __(
													'Provider:',
													'gratis-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.provider_id }
										</span>
									) }
									{ agent.model_id && (
										<span>
											<strong>
												{ __(
													'Model:',
													'gratis-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.model_id }
										</span>
									) }
									{ null !== agent.temperature && (
										<span>
											<strong>
												{ __(
													'Temp:',
													'gratis-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.temperature }
										</span>
									) }
								</div>
								{ agent.system_prompt && (
									<p className="gratis-ai-agent-prompt-preview">
										{ agent.system_prompt.length > 120
											? agent.system_prompt.slice(
													0,
													120
											  ) + '…'
											: agent.system_prompt }
									</p>
								) }
							</CardBody>
						</Card>
					) ) }

					<Button
						variant="secondary"
						icon={ plus }
						onClick={ () => {
							setShowForm( true );
							setEditId( null );
							setForm( { ...EMPTY_FORM } );
						} }
					>
						{ __( 'Add Agent', 'gratis-ai-agent' ) }
					</Button>
				</>
			) }

			{ showForm && (
				<div className="gratis-ai-agent-form">
					<h3>
						{ editId
							? __( 'Edit Agent', 'gratis-ai-agent' )
							: __( 'New Agent', 'gratis-ai-agent' ) }
					</h3>

					{ ! editId && (
						<TextControl
							label={ __( 'Slug', 'gratis-ai-agent' ) }
							value={ form.slug }
							onChange={ ( v ) => updateField( 'slug', v ) }
							help={ __(
								'Unique identifier (lowercase, hyphens). Cannot be changed after creation.',
								'gratis-ai-agent'
							) }
							__nextHasNoMarginBottom
						/>
					) }

					<TextControl
						label={ __( 'Name', 'gratis-ai-agent' ) }
						value={ form.name }
						onChange={ ( v ) => updateField( 'name', v ) }
						__nextHasNoMarginBottom
					/>

					<TextareaControl
						label={ __( 'Description', 'gratis-ai-agent' ) }
						value={ form.description }
						onChange={ ( v ) => updateField( 'description', v ) }
						rows={ 2 }
						help={ __(
							'Short description shown in the agent list.',
							'gratis-ai-agent'
						) }
					/>

					<TextareaControl
						label={ __( 'System Prompt', 'gratis-ai-agent' ) }
						value={ form.system_prompt }
						onChange={ ( v ) => updateField( 'system_prompt', v ) }
						rows={ 8 }
						help={ __(
							'Custom instructions for this agent. Replaces the global system prompt. Leave empty to use the global default.',
							'gratis-ai-agent'
						) }
					/>

					<TextareaControl
						label={ __( 'Greeting Message', 'gratis-ai-agent' ) }
						value={ form.greeting }
						onChange={ ( v ) => updateField( 'greeting', v ) }
						rows={ 2 }
						help={ __(
							'Message shown when this agent starts a conversation. Leave empty for the global default.',
							'gratis-ai-agent'
						) }
					/>

					<SelectControl
						label={ __( 'Provider', 'gratis-ai-agent' ) }
						value={ form.provider_id }
						options={ providerOptions }
						onChange={ ( v ) => {
							updateField( 'provider_id', v );
							updateField( 'model_id', '' );
						} }
						help={ __(
							'Override the AI provider for this agent.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={ __( 'Model', 'gratis-ai-agent' ) }
						value={ form.model_id }
						options={ modelOptions }
						onChange={ ( v ) => updateField( 'model_id', v ) }
						help={ __(
							'Override the AI model for this agent.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={ __( 'Temperature', 'gratis-ai-agent' ) }
						type="number"
						min={ 0 }
						max={ 2 }
						step={ 0.1 }
						value={ form.temperature }
						onChange={ ( v ) => updateField( 'temperature', v ) }
						help={ __(
							'Override temperature (0–2). Leave empty to use the global default.',
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
						onChange={ ( v ) => updateField( 'max_iterations', v ) }
						help={ __(
							'Override max tool-call iterations. Leave empty to use the global default.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={ __( 'Avatar Icon', 'gratis-ai-agent' ) }
						value={ form.avatar_icon }
						onChange={ ( v ) => updateField( 'avatar_icon', v ) }
						help={ __(
							'Dashicon name or emoji for the agent avatar (e.g. "dashicons-admin-users" or "🤖").',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>

					<div className="gratis-ai-agent-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							isBusy={ saving }
							disabled={ saving }
						>
							{ editId
								? __( 'Update Agent', 'gratis-ai-agent' )
								: __( 'Create Agent', 'gratis-ai-agent' ) }
						</Button>
						<Button variant="tertiary" onClick={ resetForm }>
							{ __( 'Cancel', 'gratis-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}
