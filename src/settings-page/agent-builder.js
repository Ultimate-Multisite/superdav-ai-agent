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
import { trash, pencil, plus, reset as resetIcon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
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
	tool_profile: '',
	temperature: '',
	max_iterations: '',
	greeting: '',
	avatar_icon: '',
	tier_1_tools: [],
};

/**
 * Tier 1 Tools editor — shows current tools as removable chips and an
 * autocomplete search input to add new ones.
 *
 * @param {Object}   props              Component props.
 * @param {string[]} props.tools        Current tier 1 tool IDs.
 * @param {Function} props.onChange     Callback when tools change.
 * @param {Array}    props.allAbilities Full abilities catalog for autocomplete.
 */
function Tier1ToolsEditor( { tools, onChange, allAbilities } ) {
	const [ search, setSearch ] = useState( '' );
	const [ showResults, setShowResults ] = useState( false );

	const filtered = search.trim()
		? allAbilities.filter(
				( a ) =>
					! tools.includes( a.id ) &&
					( a.id.toLowerCase().includes( search.toLowerCase() ) ||
						a.label
							.toLowerCase()
							.includes( search.toLowerCase() ) ||
						a.category
							.toLowerCase()
							.includes( search.toLowerCase() ) )
		  )
		: [];

	const handleRemove = useCallback(
		( toolId ) => {
			onChange( tools.filter( ( t ) => t !== toolId ) );
		},
		[ tools, onChange ]
	);

	const handleAdd = useCallback(
		( toolId ) => {
			if ( ! tools.includes( toolId ) ) {
				onChange( [ ...tools, toolId ] );
			}
			setSearch( '' );
			setShowResults( false );
		},
		[ tools, onChange ]
	);

	const getLabel = useCallback(
		( toolId ) => {
			const ability = allAbilities.find( ( a ) => a.id === toolId );
			return ability ? ability.label : toolId;
		},
		[ allAbilities ]
	);

	return (
		<div className="gratis-ai-agent-tier1-editor">
			<div className="gratis-ai-agent-tier1-list">
				{ tools.map( ( toolId ) => (
					<span key={ toolId } className="gratis-ai-agent-tier1-chip">
						<span className="gratis-ai-agent-tier1-chip-label">
							{ getLabel( toolId ) }
						</span>
						<button
							type="button"
							className="gratis-ai-agent-tier1-chip-remove"
							onClick={ () => handleRemove( toolId ) }
							aria-label={ sprintf(
								/* translators: %s: tool name */
								__( 'Remove %s', 'gratis-ai-agent' ),
								getLabel( toolId )
							) }
						>
							&times;
						</button>
					</span>
				) ) }
			</div>
			{ tools.length > 10 && (
				<p className="gratis-ai-agent-tier1-warning">
					{ __(
						'Tip: Keep tier 1 tools to around 10 to minimize context size and cost.',
						'gratis-ai-agent'
					) }
				</p>
			) }
			<div className="gratis-ai-agent-tier1-add">
				<TextControl
					placeholder={ __(
						'Search abilities to add…',
						'gratis-ai-agent'
					) }
					value={ search }
					onChange={ ( v ) => {
						setSearch( v );
						setShowResults( v.trim().length > 0 );
					} }
					onFocus={ () => {
						if ( search.trim() ) {
							setShowResults( true );
						}
					} }
					__nextHasNoMarginBottom
				/>
				{ showResults && filtered.length > 0 && (
					<ul className="gratis-ai-agent-tier1-results">
						{ filtered.slice( 0, 10 ).map( ( ability ) => (
							<li key={ ability.id }>
								<button
									type="button"
									className="gratis-ai-agent-tier1-result-item"
									onClick={ () => handleAdd( ability.id ) }
								>
									<span className="gratis-ai-agent-tier1-result-name">
										{ ability.label }
									</span>
									<span className="gratis-ai-agent-tier1-result-id">
										{ ability.id }
									</span>
								</button>
							</li>
						) ) }
					</ul>
				) }
				{ showResults && search.trim() && filtered.length === 0 && (
					<p className="gratis-ai-agent-tier1-no-results">
						{ __(
							'No matching abilities found.',
							'gratis-ai-agent'
						) }
					</p>
				) }
			</div>
		</div>
	);
}

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
	const [ resetting, setResetting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ allAbilities, setAllAbilities ] = useState( [] );

	useEffect( () => {
		fetchAgents();
		fetchProviders();
		// Fetch abilities catalog for the tier 1 tool picker.
		apiFetch( { path: '/gratis-ai-agent/v1/abilities' } )
			.then( setAllAbilities )
			.catch( () => {} );
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
			tool_profile: agent.tool_profile || '',
			temperature:
				null !== agent.temperature ? String( agent.temperature ) : '',
			max_iterations:
				null !== agent.max_iterations
					? String( agent.max_iterations )
					: '',
			greeting: agent.greeting || '',
			avatar_icon: agent.avatar_icon || '',
			tier_1_tools: agent.tier_1_tools || [],
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
				tool_profile: form.tool_profile,
				greeting: form.greeting,
				avatar_icon: form.avatar_icon,
				tier_1_tools: form.tier_1_tools,
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
			if ( agent.slug === 'general' ) {
				setNotice( {
					status: 'error',
					message: __(
						'The General agent cannot be deleted.',
						'gratis-ai-agent'
					),
				} );
				return;
			}
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
			try {
				await deleteAgent( agent.id );
			} catch ( err ) {
				setNotice( {
					status: 'error',
					message:
						err?.message ||
						__( 'Failed to delete agent.', 'gratis-ai-agent' ),
				} );
			}
		},
		[ deleteAgent ]
	);

	const handleResetDefaults = useCallback( async () => {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				__(
					'Reset all built-in agents to their factory defaults? Your customizations to built-in agents will be lost.',
					'gratis-ai-agent'
				)
			)
		) {
			return;
		}

		setResetting( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/agents/reset-defaults',
				method: 'POST',
			} );
			fetchAgents();
			setNotice( {
				status: 'success',
				message: __(
					'Built-in agents reset to factory defaults.',
					'gratis-ai-agent'
				),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to reset agents.', 'gratis-ai-agent' ),
			} );
		}

		setResetting( false );
	}, [ fetchAgents ] );

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
							'Agents have specialized system prompts, tools, and settings. Select an agent in the chat to use it. The General agent is used by default.',
							'gratis-ai-agent'
						) }
					</p>

					{ agents.map( ( agent ) => (
						<Card key={ agent.id } className="gratis-ai-agent-card">
							<CardHeader>
								<div className="gratis-ai-agent-card-header">
									<div className="gratis-ai-agent-card-title">
										<strong>{ agent.name }</strong>
										{ agent.is_builtin && (
											<span className="gratis-ai-agent-card-badge">
												{ __(
													'Built-in',
													'gratis-ai-agent'
												) }
											</span>
										) }
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
										{ agent.slug !== 'general' && (
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
										) }
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
									{ agent.tier_1_tools?.length > 0 && (
										<span>
											<strong>
												{ __(
													'Tools:',
													'gratis-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.tier_1_tools.length }
										</span>
									) }
								</div>
								{ agent.system_prompt && (
									<p className="gratis-ai-agent-prompt-preview">
										{ agent.system_prompt.length > 120
											? agent.system_prompt.slice(
													0,
													120
											  ) + '...'
											: agent.system_prompt }
									</p>
								) }
							</CardBody>
						</Card>
					) ) }

					<div className="gratis-ai-agent-builder-actions">
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
						<Button
							variant="tertiary"
							icon={ resetIcon }
							onClick={ handleResetDefaults }
							isBusy={ resetting }
							disabled={ resetting }
						>
							{ __( 'Reset to Defaults', 'gratis-ai-agent' ) }
						</Button>
					</div>
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
							'Instructions that define how this agent behaves. Each agent has its own system prompt.',
							'gratis-ai-agent'
						) }
					/>

					<TextareaControl
						label={ __( 'Greeting Message', 'gratis-ai-agent' ) }
						value={ form.greeting }
						onChange={ ( v ) => updateField( 'greeting', v ) }
						rows={ 2 }
						help={ __(
							'Message shown when this agent starts a conversation.',
							'gratis-ai-agent'
						) }
					/>

					<div className="gratis-ai-agent-form-field">
						<h4 className="gratis-ai-agent-form-label">
							{ __( 'Tier 1 Tools', 'gratis-ai-agent' ) }
						</h4>
						<p className="description">
							{ __(
								'Tools immediately available to this agent. Other tools can still be discovered via search. Aim for about 10 tools to keep context size low.',
								'gratis-ai-agent'
							) }
						</p>
						<Tier1ToolsEditor
							tools={ form.tier_1_tools || [] }
							onChange={ ( v ) =>
								updateField( 'tier_1_tools', v )
							}
							allAbilities={ allAbilities }
						/>
					</div>

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
							'Dashicon name or emoji for the agent avatar.',
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
