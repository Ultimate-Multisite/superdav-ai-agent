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
					( ( a.id || '' )
						.toLowerCase()
						.includes( search.toLowerCase() ) ||
						( a.label || '' )
							.toLowerCase()
							.includes( search.toLowerCase() ) ||
						( a.category || '' )
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
		<div className="sd-ai-agent-tier1-editor">
			<div className="sd-ai-agent-tier1-list">
				{ tools.map( ( toolId ) => (
					<span key={ toolId } className="sd-ai-agent-tier1-chip">
						<span className="sd-ai-agent-tier1-chip-label">
							{ getLabel( toolId ) }
						</span>
						<button
							type="button"
							className="sd-ai-agent-tier1-chip-remove"
							onClick={ () => handleRemove( toolId ) }
							aria-label={ sprintf(
								/* translators: %s: tool name */
								__( 'Remove %s', 'sd-ai-agent' ),
								getLabel( toolId )
							) }
						>
							&times;
						</button>
					</span>
				) ) }
			</div>
			{ tools.length > 10 && (
				<p className="sd-ai-agent-tier1-warning">
					{ __(
						'Tip: Keep tier 1 tools to around 10 to minimize context size and cost.',
						'sd-ai-agent'
					) }
				</p>
			) }
			<div className="sd-ai-agent-tier1-add">
				<TextControl
					placeholder={ __(
						'Search abilities to add…',
						'sd-ai-agent'
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
					<ul className="sd-ai-agent-tier1-results">
						{ filtered.slice( 0, 10 ).map( ( ability ) => (
							<li key={ ability.id }>
								<button
									type="button"
									className="sd-ai-agent-tier1-result-item"
									onClick={ () => handleAdd( ability.id ) }
								>
									<span className="sd-ai-agent-tier1-result-name">
										{ ability.label }
									</span>
									<span className="sd-ai-agent-tier1-result-id">
										{ ability.id }
									</span>
								</button>
							</li>
						) ) }
					</ul>
				) }
				{ showResults && search.trim() && filtered.length === 0 && (
					<p className="sd-ai-agent-tier1-no-results">
						{ __( 'No matching abilities found.', 'sd-ai-agent' ) }
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
		apiFetch( { path: '/sd-ai-agent/v1/abilities' } )
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

	const handleEdit = useCallback( async ( agent ) => {
		// Immediately open the form with the list data so the UI responds
		// without waiting for the network.
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

		// Fetch the full agent record to ensure system_prompt and tier_1_tools
		// are populated — the list response may omit or truncate large fields.
		try {
			const full = await apiFetch( {
				path: `/sd-ai-agent/v1/agents/${ agent.id }`,
			} );
			setForm( ( prev ) => ( {
				...prev,
				system_prompt: full.system_prompt || prev.system_prompt || '',
				tier_1_tools: full.tier_1_tools || prev.tier_1_tools || [],
				greeting: full.greeting || prev.greeting || '',
			} ) );
		} catch {
			// Network error — keep whatever the list already gave us.
		}
	}, [] );

	const handleSubmit = useCallback( async () => {
		if ( ! form.name.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Agent name is required.', 'sd-ai-agent' ),
			} );
			return;
		}
		if ( ! editId && ! form.slug.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Agent slug is required.', 'sd-ai-agent' ),
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

			// Send null when the field is blank so the server clears the column
			// back to NULL (falls back to global default at runtime).
			// Also guard against NaN so garbled input never reaches the API.
			const rawTemp = String( form.temperature ?? '' ).trim();
			const parsedTemp = rawTemp === '' ? NaN : parseFloat( rawTemp );
			payload.temperature = isNaN( parsedTemp ) ? null : parsedTemp;

			const rawIter = String( form.max_iterations ?? '' ).trim();
			const parsedIter = rawIter === '' ? NaN : parseInt( rawIter, 10 );
			payload.max_iterations = isNaN( parsedIter ) ? null : parsedIter;

			if ( editId ) {
				await updateAgent( editId, payload );
				setNotice( {
					status: 'success',
					message: __( 'Agent updated.', 'sd-ai-agent' ),
				} );
			} else {
				payload.slug = form.slug;
				await createAgent( payload );
				setNotice( {
					status: 'success',
					message: __( 'Agent created.', 'sd-ai-agent' ),
				} );
				resetForm();
			}
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to save agent.', 'sd-ai-agent' ),
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
						'sd-ai-agent'
					),
				} );
				return;
			}
			if (
				// eslint-disable-next-line no-alert
				! window.confirm(
					sprintf(
						/* translators: %s: agent name */
						__( 'Delete agent "%s"?', 'sd-ai-agent' ),
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
						__( 'Failed to delete agent.', 'sd-ai-agent' ),
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
					'sd-ai-agent'
				)
			)
		) {
			return;
		}

		setResetting( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/sd-ai-agent/v1/agents/reset-defaults',
				method: 'POST',
			} );
			fetchAgents();
			setNotice( {
				status: 'success',
				message: __(
					'Built-in agents reset to factory defaults.',
					'sd-ai-agent'
				),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to reset agents.', 'sd-ai-agent' ),
			} );
		}

		setResetting( false );
	}, [ fetchAgents ] );

	// Build provider options for the dropdown.
	const providerOptions = [
		{
			label: __( '(use global default)', 'sd-ai-agent' ),
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
			label: __( '(use global default)', 'sd-ai-agent' ),
			value: '',
		},
		...( selectedProvider?.models || [] ).map( ( m ) => ( {
			label: m.name || m.id,
			value: m.id,
		} ) ),
	];

	if ( ! agentsLoaded ) {
		return (
			<div className="sd-ai-agent-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="sd-ai-agent-builder">
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
							'sd-ai-agent'
						) }
					</p>

					{ agents.map( ( agent ) => (
						<Card key={ agent.id } className="sd-ai-agent-card">
							<CardHeader>
								<div className="sd-ai-agent-card-header">
									<div className="sd-ai-agent-card-title">
										<div className="sd-ai-agent-card-title-row">
											<strong>{ agent.name }</strong>
											{ agent.is_builtin && (
												<span className="sd-ai-agent-card-badge">
													{ __(
														'Built-in',
														'sd-ai-agent'
													) }
												</span>
											) }
											<div className="sd-ai-agent-card-actions">
												<Button
													icon={ pencil }
													label={ __(
														'Edit agent',
														'sd-ai-agent'
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
															'sd-ai-agent'
														) }
														onClick={ () =>
															handleDelete(
																agent
															)
														}
														isDestructive
														size="small"
													/>
												) }
											</div>
										</div>
										{ agent.description && (
											<p className="sd-ai-agent-card-desc">
												{ agent.description }
											</p>
										) }
									</div>
								</div>
							</CardHeader>
							<CardBody>
								<div className="sd-ai-agent-card-meta">
									{ agent.provider_id && (
										<span>
											<strong>
												{ __(
													'Provider:',
													'sd-ai-agent'
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
													'sd-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.model_id }
										</span>
									) }
									{ agent.tier_1_tools?.length > 0 && (
										<span>
											<strong>
												{ __(
													'Tools:',
													'sd-ai-agent'
												) }
											</strong>{ ' ' }
											{ agent.tier_1_tools.length }
										</span>
									) }
								</div>
								{ agent.system_prompt && (
									<p className="sd-ai-agent-prompt-preview">
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

					<div className="sd-ai-agent-builder-actions">
						<Button
							variant="secondary"
							icon={ plus }
							onClick={ () => {
								setShowForm( true );
								setEditId( null );
								setForm( { ...EMPTY_FORM } );
							} }
						>
							{ __( 'Add Agent', 'sd-ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							icon={ resetIcon }
							onClick={ handleResetDefaults }
							isBusy={ resetting }
							disabled={ resetting }
						>
							{ __( 'Reset to Defaults', 'sd-ai-agent' ) }
						</Button>
					</div>
				</>
			) }

			{ showForm && (
				<div className="sd-ai-agent-form">
					<h3>
						{ editId
							? __( 'Edit Agent', 'sd-ai-agent' )
							: __( 'New Agent', 'sd-ai-agent' ) }
					</h3>

					<table className="form-table sd-ai-agent-form-table">
						<tbody>
							{ ! editId && (
								<tr>
									<th scope="row">
										<label htmlFor="agent-slug">
											{ __( 'Slug', 'sd-ai-agent' ) }
										</label>
									</th>
									<td>
										<TextControl
											id="agent-slug"
											value={ form.slug }
											onChange={ ( v ) =>
												updateField( 'slug', v )
											}
											__nextHasNoMarginBottom
										/>
										<p className="description">
											{ __(
												'Unique identifier (lowercase, hyphens). Cannot be changed after creation.',
												'sd-ai-agent'
											) }
										</p>
									</td>
								</tr>
							) }

							<tr>
								<th scope="row">
									<label htmlFor="agent-name">
										{ __( 'Name', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextControl
										id="agent-name"
										value={ form.name }
										onChange={ ( v ) =>
											updateField( 'name', v )
										}
										__nextHasNoMarginBottom
									/>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-description">
										{ __( 'Description', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextareaControl
										id="agent-description"
										value={ form.description }
										onChange={ ( v ) =>
											updateField( 'description', v )
										}
										rows={ 2 }
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Short description shown in the agent list.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-system-prompt">
										{ __( 'System Prompt', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextareaControl
										id="agent-system-prompt"
										value={ form.system_prompt }
										onChange={ ( v ) =>
											updateField( 'system_prompt', v )
										}
										rows={ 10 }
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Instructions that define how this agent behaves.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-greeting">
										{ __( 'Greeting', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextareaControl
										id="agent-greeting"
										value={ form.greeting }
										onChange={ ( v ) =>
											updateField( 'greeting', v )
										}
										rows={ 2 }
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Message shown when this agent starts a conversation.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									{ __( 'Tier 1 Tools', 'sd-ai-agent' ) }
								</th>
								<td>
									<p className="description">
										{ __(
											'Tools immediately available to this agent. Others can still be found via search. Aim for ~10 to keep context size low.',
											'sd-ai-agent'
										) }
									</p>
									<Tier1ToolsEditor
										tools={ form.tier_1_tools || [] }
										onChange={ ( v ) =>
											updateField( 'tier_1_tools', v )
										}
										allAbilities={ allAbilities }
									/>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-provider">
										{ __( 'Provider', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<SelectControl
										id="agent-provider"
										value={ form.provider_id }
										options={ providerOptions }
										onChange={ ( v ) => {
											updateField( 'provider_id', v );
											updateField( 'model_id', '' );
										} }
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Override the AI provider for this agent.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-model">
										{ __( 'Model', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<SelectControl
										id="agent-model"
										value={ form.model_id }
										options={ modelOptions }
										onChange={ ( v ) =>
											updateField( 'model_id', v )
										}
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Override the AI model for this agent.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-temperature">
										{ __( 'Temperature', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextControl
										id="agent-temperature"
										type="number"
										min={ 0 }
										max={ 2 }
										step={ 0.1 }
										value={ form.temperature }
										placeholder="0.2"
										onChange={ ( v ) =>
											updateField( 'temperature', v )
										}
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Override temperature (0–2). Leave empty to use the global default (0.2).',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-max-iterations">
										{ __(
											'Max Iterations',
											'sd-ai-agent'
										) }
									</label>
								</th>
								<td>
									<TextControl
										id="agent-max-iterations"
										type="number"
										min={ 1 }
										max={ 50 }
										value={ form.max_iterations }
										placeholder="50"
										onChange={ ( v ) =>
											updateField( 'max_iterations', v )
										}
										__nextHasNoMarginBottom
									/>
									<p className="description">
										{ __(
											'Override max tool-call iterations. Leave empty to use the global default (50).',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label htmlFor="agent-avatar-icon">
										{ __( 'Avatar Icon', 'sd-ai-agent' ) }
									</label>
								</th>
								<td>
									<TextControl
										id="agent-avatar-icon"
										value={ form.avatar_icon }
										placeholder="dashicons-admin-generic or 🤖"
										onChange={ ( v ) =>
											updateField( 'avatar_icon', v )
										}
										__nextHasNoMarginBottom
									/>
									{ form.avatar_icon && (
										<div className="sd-ai-agent-icon-preview">
											{ form.avatar_icon.startsWith(
												'dashicons-'
											) ? (
												<span
													className={ `dashicons ${ form.avatar_icon } sd-ai-agent-icon-preview-dash` }
													aria-hidden="true"
												/>
											) : (
												<span
													className="sd-ai-agent-icon-preview-emoji"
													aria-hidden="true"
												>
													{ form.avatar_icon }
												</span>
											) }
											<span className="sd-ai-agent-icon-preview-label">
												{ __(
													'Preview',
													'sd-ai-agent'
												) }
											</span>
										</div>
									) }
									<p className="description">
										{ __(
											'Dashicon name (e.g. dashicons-cart) or an emoji.',
											'sd-ai-agent'
										) }
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<div className="sd-ai-agent-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							isBusy={ saving }
							disabled={ saving }
						>
							{ editId
								? __( 'Update Agent', 'sd-ai-agent' )
								: __( 'Create Agent', 'sd-ai-agent' ) }
						</Button>
						<Button variant="tertiary" onClick={ resetForm }>
							{ __( 'Cancel', 'sd-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}
