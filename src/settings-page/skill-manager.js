/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	Button,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { trash, pencil, plus, backup } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 *
 */
export default function SkillManager() {
	const { fetchSkills, createSkill, updateSkill, deleteSkill, resetSkill } =
		useDispatch( STORE_NAME );
	const { skills, skillsLoaded } = useSelect(
		( select ) => ( {
			skills: select( STORE_NAME ).getSkills(),
			skillsLoaded: select( STORE_NAME ).getSkillsLoaded(),
		} ),
		[]
	);

	const [ showForm, setShowForm ] = useState( false );
	const [ editId, setEditId ] = useState( null );
	const [ formSlug, setFormSlug ] = useState( '' );
	const [ formName, setFormName ] = useState( '' );
	const [ formDescription, setFormDescription ] = useState( '' );
	const [ formContent, setFormContent ] = useState( '' );

	useEffect( () => {
		fetchSkills();
	}, [ fetchSkills ] );

	const resetForm = useCallback( () => {
		setShowForm( false );
		setEditId( null );
		setFormSlug( '' );
		setFormName( '' );
		setFormDescription( '' );
		setFormContent( '' );
	}, [] );

	const handleSubmit = useCallback( async () => {
		if ( ! formName.trim() || ! formContent.trim() ) {
			return;
		}

		if ( editId ) {
			await updateSkill( editId, {
				name: formName,
				description: formDescription,
				content: formContent,
			} );
		} else {
			if ( ! formSlug.trim() ) {
				return;
			}
			await createSkill( {
				slug: formSlug,
				name: formName,
				description: formDescription,
				content: formContent,
			} );
		}

		resetForm();
	}, [
		editId,
		formSlug,
		formName,
		formDescription,
		formContent,
		createSkill,
		updateSkill,
		resetForm,
	] );

	const handleEdit = useCallback( ( skill ) => {
		setEditId( skill.id );
		setFormSlug( skill.slug );
		setFormName( skill.name );
		setFormDescription( skill.description );
		setFormContent( skill.content );
		setShowForm( true );
	}, [] );

	const handleDelete = useCallback(
		async ( id ) => {
			if (
				// eslint-disable-next-line no-alert
				window.confirm( __( 'Delete this skill?', 'gratis-ai-agent' ) )
			) {
				await deleteSkill( id );
			}
		},
		[ deleteSkill ]
	);

	const handleReset = useCallback(
		async ( id ) => {
			if (
				// eslint-disable-next-line no-alert
				window.confirm(
					__(
						'Reset this skill to its default content?',
						'gratis-ai-agent'
					)
				)
			) {
				await resetSkill( id );
			}
		},
		[ resetSkill ]
	);

	const handleToggle = useCallback(
		async ( skill ) => {
			await updateSkill( skill.id, { enabled: ! skill.enabled } );
		},
		[ updateSkill ]
	);

	return (
		<div className="gratis-ai-agent-skill-manager">
			<div className="gratis-ai-agent-skill-header">
				<div>
					<h3>{ __( 'Agent Skills', 'gratis-ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Skills are instruction guides loaded on-demand when the AI encounters a relevant task.',
							'gratis-ai-agent'
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
						{ __( 'Add Skill', 'gratis-ai-agent' ) }
					</Button>
				) }
			</div>

			{ showForm && (
				<div className="gratis-ai-agent-skill-form">
					{ ! editId && (
						<TextControl
							label={ __( 'Slug', 'gratis-ai-agent' ) }
							value={ formSlug }
							onChange={ setFormSlug }
							help={ __(
								'Unique identifier (lowercase, hyphens). Cannot be changed after creation.',
								'gratis-ai-agent'
							) }
							__nextHasNoMarginBottom
						/>
					) }
					<TextControl
						label={ __( 'Name', 'gratis-ai-agent' ) }
						value={ formName }
						onChange={ setFormName }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'gratis-ai-agent' ) }
						value={ formDescription }
						onChange={ setFormDescription }
						help={ __(
							'One-line summary shown in the skill index.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Instructions', 'gratis-ai-agent' ) }
						value={ formContent }
						onChange={ setFormContent }
						rows={ 12 }
						help={ __(
							'Full markdown instructions loaded when the AI requests this skill.',
							'gratis-ai-agent'
						) }
					/>
					<div className="gratis-ai-agent-skill-form-actions">
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={
								! formName.trim() ||
								! formContent.trim() ||
								( ! editId && ! formSlug.trim() )
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

			{ ! skillsLoaded && (
				<p className="description">
					{ __( 'Loading…', 'gratis-ai-agent' ) }
				</p>
			) }

			{ skillsLoaded && skills.length === 0 && (
				<p className="description">
					{ __(
						'No skills found. Deactivate and reactivate the plugin to seed built-in skills.',
						'gratis-ai-agent'
					) }
				</p>
			) }

			{ skills.length > 0 && (
				<div className="gratis-ai-agent-skill-cards">
					{ skills.map( ( skill ) => (
						<div
							key={ skill.id }
							className={ `gratis-ai-agent-skill-card ${
								! skill.enabled
									? 'gratis-ai-agent-skill-card--disabled'
									: ''
							}` }
						>
							<div className="gratis-ai-agent-skill-card-header">
								<ToggleControl
									checked={ skill.enabled }
									onChange={ () => handleToggle( skill ) }
									__nextHasNoMarginBottom
								/>
								<div className="gratis-ai-agent-skill-card-title">
									<strong>{ skill.name }</strong>
									{ skill.is_builtin && (
										<span className="gratis-ai-agent-skill-badge">
											{ __(
												'Built-in',
												'gratis-ai-agent'
											) }
										</span>
									) }
								</div>
							</div>
							<p className="gratis-ai-agent-skill-card-description">
								{ skill.description }
							</p>
							<div className="gratis-ai-agent-skill-card-footer">
								<span className="gratis-ai-agent-skill-word-count">
									{ skill.word_count }{ ' ' }
									{ __( 'words', 'gratis-ai-agent' ) }
								</span>
								<div className="gratis-ai-agent-skill-card-actions">
									<Button
										icon={ pencil }
										size="small"
										label={ __(
											'Edit',
											'gratis-ai-agent'
										) }
										onClick={ () => handleEdit( skill ) }
									/>
									{ skill.is_builtin ? (
										<Button
											icon={ backup }
											size="small"
											label={ __(
												'Reset to Default',
												'gratis-ai-agent'
											) }
											onClick={ () =>
												handleReset( skill.id )
											}
										/>
									) : (
										<Button
											icon={ trash }
											size="small"
											label={ __(
												'Delete',
												'gratis-ai-agent'
											) }
											isDestructive
											onClick={ () =>
												handleDelete( skill.id )
											}
										/>
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
