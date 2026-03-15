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
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { trash, pencil, plus } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

export default function ToolProfilesManager() {
	const { saveSettings } = useDispatch( STORE_NAME );
	const { settings } = useSelect(
		( select ) => ( { settings: select( STORE_NAME ).getSettings() } ),
		[]
	);

	const [ profiles, setProfiles ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const [ abilities, setAbilities ] = useState( [] );
	const [ showForm, setShowForm ] = useState( false );
	const [ editSlug, setEditSlug ] = useState( null );
	const [ formName, setFormName ] = useState( '' );
	const [ formDescription, setFormDescription ] = useState( '' );
	const [ formToolNames, setFormToolNames ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const fetchProfiles = useCallback( async () => {
		try {
			const result = await apiFetch( {
				path: '/ai-agent/v1/tool-profiles',
			} );
			setProfiles( result );
		} catch {
			setProfiles( [] );
		}
		setLoaded( true );
	}, [] );

	useEffect( () => {
		fetchProfiles();
		apiFetch( { path: '/ai-agent/v1/abilities' } )
			.then( setAbilities )
			.catch( () => {} );
	}, [ fetchProfiles ] );

	const resetForm = useCallback( () => {
		setShowForm( false );
		setEditSlug( null );
		setFormName( '' );
		setFormDescription( '' );
		setFormToolNames( '' );
	}, [] );

	const handleSubmit = useCallback( async () => {
		if ( ! formName.trim() ) {
			return;
		}
		setNotice( null );
		try {
			const toolNames = formToolNames
				.split( '\n' )
				.map( ( s ) => s.trim() )
				.filter( Boolean );

			const data = {
				name: formName,
				description: formDescription,
				tool_names: toolNames,
			};

			if ( editSlug ) {
				await apiFetch( {
					path: `/ai-agent/v1/tool-profiles/${ editSlug }`,
					method: 'PATCH',
					data,
				} );
			} else {
				await apiFetch( {
					path: '/ai-agent/v1/tool-profiles',
					method: 'POST',
					data,
				} );
			}
			resetForm();
			fetchProfiles();
			setNotice( {
				status: 'success',
				message: __( 'Profile saved.', 'ai-agent' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message: err.message || __( 'Failed to save.', 'ai-agent' ),
			} );
		}
	}, [
		formName,
		formDescription,
		formToolNames,
		editSlug,
		resetForm,
		fetchProfiles,
	] );

	const handleEdit = useCallback( ( profile ) => {
		setEditSlug( profile.slug );
		setFormName( profile.name );
		setFormDescription( profile.description );
		setFormToolNames( ( profile.tool_names || [] ).join( '\n' ) );
		setShowForm( true );
	}, [] );

	const handleDelete = useCallback(
		async ( slug ) => {
			// eslint-disable-next-line no-alert
			if ( window.confirm( __( 'Delete this profile?', 'ai-agent' ) ) ) {
				await apiFetch( {
					path: `/ai-agent/v1/tool-profiles/${ slug }`,
					method: 'DELETE',
				} );
				fetchProfiles();
			}
		},
		[ fetchProfiles ]
	);

	const handleActivate = useCallback(
		async ( slug ) => {
			const newValue = settings?.active_tool_profile === slug ? '' : slug;
			await saveSettings( { active_tool_profile: newValue } );
			setNotice( {
				status: 'success',
				message: newValue
					? __( 'Profile activated.', 'ai-agent' )
					: __(
							'Profile deactivated. All tools are now available.',
							'ai-agent'
					  ),
			} );
		},
		[ settings, saveSettings ]
	);

	const activeProfile = settings?.active_tool_profile || '';

	// Build active profile options for the dropdown.
	const profileOptions = [
		{ label: __( 'None (all tools)', 'ai-agent' ), value: '' },
		...profiles.map( ( p ) => ( { label: p.name, value: p.slug } ) ),
	];

	return (
		<div className="ai-agent-tool-profiles-manager">
			<div className="ai-agent-skill-header">
				<div>
					<h3>{ __( 'Tool Profiles', 'ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Profiles restrict which tools the AI can access. Useful for security (read-only mode) or token savings.',
							'ai-agent'
						) }
					</p>
				</div>
				{ ! showForm && (
					<Button
						variant="secondary"
						icon={ plus }
						onClick={ () => setShowForm( true ) }
						size="compact"
					>
						{ __( 'Add Profile', 'ai-agent' ) }
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

			<SelectControl
				label={ __( 'Active Profile', 'ai-agent' ) }
				value={ activeProfile }
				options={ profileOptions }
				onChange={ handleActivate }
				help={ __(
					'Select a profile to restrict the AI to a specific set of tools.',
					'ai-agent'
				) }
				__nextHasNoMarginBottom
			/>

			{ showForm && (
				<div
					className="ai-agent-skill-form"
					style={ { marginTop: '16px' } }
				>
					<TextControl
						label={ __( 'Name', 'ai-agent' ) }
						value={ formName }
						onChange={ setFormName }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'ai-agent' ) }
						value={ formDescription }
						onChange={ setFormDescription }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __(
							'Tool Name Prefixes (one per line)',
							'ai-agent'
						) }
						value={ formToolNames }
						onChange={ setFormToolNames }
						rows={ 8 }
						help={ sprintf(
							/* translators: %s: comma-separated list of available tool names */
							__(
								'Enter tool name prefixes, one per line. Use partial names for matching (e.g., "wp_read" matches all read tools). Available tools: %s',
								'ai-agent'
							),
							abilities.map( ( a ) => a.name ).join( ', ' )
						) }
					/>
					<div className="ai-agent-skill-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={ ! formName.trim() }
							size="compact"
						>
							{ editSlug
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

			{ loaded && profiles.length > 0 && (
				<div
					className="ai-agent-skill-cards"
					style={ { marginTop: '16px' } }
				>
					{ profiles.map( ( profile ) => (
						<div
							key={ profile.slug }
							className={ `ai-agent-skill-card ${
								activeProfile === profile.slug
									? 'ai-agent-skill-card--active'
									: ''
							}` }
						>
							<div className="ai-agent-skill-card-header">
								<div className="ai-agent-skill-card-title">
									<strong>{ profile.name }</strong>
									{ profile.is_builtin && (
										<span className="ai-agent-skill-badge">
											{ __( 'Built-in', 'ai-agent' ) }
										</span>
									) }
									{ activeProfile === profile.slug && (
										<span
											className="ai-agent-skill-badge"
											style={ {
												background: '#00a32a',
												color: '#fff',
											} }
										>
											{ __( 'Active', 'ai-agent' ) }
										</span>
									) }
								</div>
							</div>
							<p className="ai-agent-skill-card-description">
								{ profile.description }
							</p>
							<div className="ai-agent-skill-card-footer">
								<span className="ai-agent-skill-word-count">
									{ ( profile.tool_names || [] ).length }{ ' ' }
									{ __( 'tool prefixes', 'ai-agent' ) }
								</span>
								<div className="ai-agent-skill-card-actions">
									{ ! profile.is_builtin && (
										<>
											<Button
												icon={ pencil }
												size="small"
												label={ __(
													'Edit',
													'ai-agent'
												) }
												onClick={ () =>
													handleEdit( profile )
												}
											/>
											<Button
												icon={ trash }
												size="small"
												label={ __(
													'Delete',
													'ai-agent'
												) }
												isDestructive
												onClick={ () =>
													handleDelete( profile.slug )
												}
											/>
										</>
									) }
								</div>
							</div>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
