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
	Notice,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { trash, pencil, plus, backup, update } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Format a UTC MySQL datetime string (YYYY-MM-DD HH:MM:SS) for display.
 *
 * Returns a short relative label ("2 days ago", "just now") based on the
 * delta to the current time.
 *
 * @param {string} mysqlDatetime MySQL UTC datetime string.
 * @return {string} Human-readable relative time.
 */
function formatRelativeTime( mysqlDatetime ) {
	if ( ! mysqlDatetime ) {
		return '';
	}
	// MySQL datetimes are UTC — append 'Z' so Date.parse treats them as UTC.
	const ts = Date.parse( mysqlDatetime.replace( ' ', 'T' ) + 'Z' );
	if ( isNaN( ts ) ) {
		return mysqlDatetime;
	}
	const diffSeconds = Math.floor( ( Date.now() - ts ) / 1000 );
	if ( diffSeconds < 60 ) {
		return __( 'just now', 'superdav-ai-agent' );
	}
	if ( diffSeconds < 3600 ) {
		const mins = Math.floor( diffSeconds / 60 );
		if ( mins === 1 ) {
			return __( '1 minute ago', 'superdav-ai-agent' );
		}
		/* translators: %d: number of minutes */
		return sprintf( __( '%d minutes ago', 'superdav-ai-agent' ), mins );
	}
	if ( diffSeconds < 86400 ) {
		const hours = Math.floor( diffSeconds / 3600 );
		if ( hours === 1 ) {
			return __( '1 hour ago', 'superdav-ai-agent' );
		}
		/* translators: %d: number of hours */
		return sprintf( __( '%d hours ago', 'superdav-ai-agent' ), hours );
	}
	const days = Math.floor( diffSeconds / 86400 );
	if ( days === 1 ) {
		return __( '1 day ago', 'superdav-ai-agent' );
	}
	/* translators: %d: number of days */
	return sprintf( __( '%d days ago', 'superdav-ai-agent' ), days );
}

/**
 *
 */
export default function SkillManager() {
	const {
		fetchSkills,
		fetchSkillStats,
		checkSkillUpdates,
		createSkill,
		updateSkill,
		deleteSkill,
		resetSkill,
		saveSettings,
		fetchSettings,
	} = useDispatch( STORE_NAME );

	const {
		skills,
		skillsLoaded,
		skillStats,
		skillStatsLoaded,
		skillUpdates,
		skillUpdatesChecking,
		settings,
		settingsLoaded,
	} = useSelect(
		( select ) => ( {
			skills: select( STORE_NAME ).getSkills(),
			skillsLoaded: select( STORE_NAME ).getSkillsLoaded(),
			skillStats: select( STORE_NAME ).getSkillStats(),
			skillStatsLoaded: select( STORE_NAME ).getSkillStatsLoaded(),
			skillUpdates: select( STORE_NAME ).getSkillUpdates(),
			skillUpdatesChecking:
				select( STORE_NAME ).getSkillUpdatesChecking(),
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
		} ),
		[]
	);

	const [ showForm, setShowForm ] = useState( false );
	const [ editId, setEditId ] = useState( null );
	const [ formSlug, setFormSlug ] = useState( '' );
	const [ formName, setFormName ] = useState( '' );
	const [ formDescription, setFormDescription ] = useState( '' );
	const [ formContent, setFormContent ] = useState( '' );
	const [ updateNotice, setUpdateNotice ] = useState( null );

	useEffect( () => {
		if ( ! skillsLoaded ) {
			fetchSkills();
		}
		if ( ! skillStatsLoaded ) {
			fetchSkillStats();
		}
		if ( ! settingsLoaded ) {
			fetchSettings();
		}
	}, [
		fetchSkills,
		fetchSkillStats,
		fetchSettings,
		skillsLoaded,
		skillStatsLoaded,
		settingsLoaded,
	] );

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
				window.confirm(
					__( 'Delete this skill?', 'superdav-ai-agent' )
				)
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
						'superdav-ai-agent'
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

	const handleAutoUpdateToggle = useCallback(
		async ( value ) => {
			await saveSettings( { skill_auto_update: value } );
		},
		[ saveSettings ]
	);

	const handleCheckUpdates = useCallback( async () => {
		setUpdateNotice( null );
		const results = await checkSkillUpdates();
		if ( results === null ) {
			setUpdateNotice( {
				status: 'error',
				message: __(
					'Could not check for updates. Ensure a manifest URL is configured in settings.',
					'superdav-ai-agent'
				),
			} );
			return;
		}
		const updateEntries = Object.values( results );
		const updateCount = updateEntries.filter(
			( r ) => r.has_update
		).length;
		const appliedCount = updateEntries.filter( ( r ) => r.applied ).length;
		if ( appliedCount > 0 ) {
			const appliedMsg = sprintf(
				/* translators: %d: number of skills updated */
				_n(
					'%d skill updated automatically.',
					'%d skills updated automatically.',
					appliedCount,
					'superdav-ai-agent'
				),
				appliedCount
			);
			setUpdateNotice( { status: 'success', message: appliedMsg } );
		} else if ( updateCount > 0 ) {
			const updateMsg = sprintf(
				/* translators: %d: number of skills with available updates */
				_n(
					'%d update available. Auto-update is disabled or skills have been customised.',
					'%d updates available. Auto-update is disabled or skills have been customised.',
					updateCount,
					'superdav-ai-agent'
				),
				updateCount
			);
			setUpdateNotice( { status: 'warning', message: updateMsg } );
		} else {
			setUpdateNotice( {
				status: 'success',
				message: __(
					'All skills are up to date.',
					'superdav-ai-agent'
				),
			} );
		}
	}, [ checkSkillUpdates ] );

	const autoUpdateEnabled = settings?.skill_auto_update ?? true;
	const manifestUrlSet = !! settings?.skill_manifest_url;

	return (
		<div className="sdaa-skill-manager">
			<div className="sdaa-skill-header">
				<div>
					<h3>{ __( 'Agent Skills', 'superdav-ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Skills are instruction guides loaded on-demand when the AI encounters a relevant task.',
							'superdav-ai-agent'
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
						{ __( 'Add Skill', 'superdav-ai-agent' ) }
					</Button>
				) }
			</div>

			{ /* Auto-update controls */ }
			{ settingsLoaded && (
				<div className="sdaa-skill-update-controls">
					<ToggleControl
						label={ __(
							'Automatic skill updates',
							'superdav-ai-agent'
						) }
						help={ __(
							'When enabled, built-in skills are updated automatically from the remote manifest whenever a newer version is available (unless you have customised them).',
							'superdav-ai-agent'
						) }
						checked={ autoUpdateEnabled }
						onChange={ handleAutoUpdateToggle }
						__nextHasNoMarginBottom
					/>
					{ manifestUrlSet && (
						<Button
							variant="secondary"
							icon={ update }
							onClick={ handleCheckUpdates }
							isBusy={ skillUpdatesChecking }
							disabled={ skillUpdatesChecking }
							size="compact"
							className="sdaa-check-updates-btn"
						>
							{ skillUpdatesChecking
								? __( 'Checking…', 'superdav-ai-agent' )
								: __(
										'Check for Updates',
										'superdav-ai-agent'
								  ) }
						</Button>
					) }
				</div>
			) }

			{ updateNotice && (
				<Notice
					status={ updateNotice.status }
					isDismissible
					onRemove={ () => setUpdateNotice( null ) }
				>
					{ updateNotice.message }
				</Notice>
			) }

			{ showForm && (
				<div className="sdaa-skill-form">
					{ ! editId && (
						<TextControl
							label={ __( 'Slug', 'superdav-ai-agent' ) }
							value={ formSlug }
							onChange={ setFormSlug }
							help={ __(
								'Unique identifier (lowercase, hyphens). Cannot be changed after creation.',
								'superdav-ai-agent'
							) }
							__nextHasNoMarginBottom
						/>
					) }
					<TextControl
						label={ __( 'Name', 'superdav-ai-agent' ) }
						value={ formName }
						onChange={ setFormName }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Description', 'superdav-ai-agent' ) }
						value={ formDescription }
						onChange={ setFormDescription }
						help={ __(
							'One-line summary shown in the skill index.',
							'superdav-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Instructions', 'superdav-ai-agent' ) }
						value={ formContent }
						onChange={ setFormContent }
						rows={ 12 }
						help={ __(
							'Full markdown instructions loaded when the AI requests this skill.',
							'superdav-ai-agent'
						) }
					/>
					<div className="sdaa-skill-form-actions">
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
								? __( 'Update', 'superdav-ai-agent' )
								: __( 'Create', 'superdav-ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ resetForm }
							size="compact"
						>
							{ __( 'Cancel', 'superdav-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }

			{ ! skillsLoaded && (
				<p className="description">
					{ __( 'Loading…', 'superdav-ai-agent' ) }
				</p>
			) }

			{ skillsLoaded && skills.length === 0 && (
				<p className="description">
					{ __(
						'No skills found. Deactivate and reactivate the plugin to seed built-in skills.',
						'superdav-ai-agent'
					) }
				</p>
			) }

			{ skills.length > 0 && (
				<div className="sdaa-skill-cards">
					{ skills.map( ( skill ) => {
						const stats = skillStats[ skill.id ] ?? null;
						const updateInfo = skillUpdates[ skill.id ] ?? null;
						const hasUpdate =
							updateInfo?.has_update && ! skill.user_modified;

						return (
							<div
								key={ skill.id }
								className={ `sdaa-skill-card ${
									! skill.enabled
										? 'sdaa-skill-card--disabled'
										: ''
								}` }
							>
								<div className="sdaa-skill-card-header">
									<ToggleControl
										checked={ skill.enabled }
										onChange={ () => handleToggle( skill ) }
										__nextHasNoMarginBottom
									/>
									<div className="sdaa-skill-card-title">
										<strong>{ skill.name }</strong>
										{ skill.is_builtin && (
											<span className="sdaa-skill-badge">
												{ __(
													'Built-in',
													'superdav-ai-agent'
												) }
											</span>
										) }
										{ skill.user_modified && (
											<span className="sdaa-skill-badge sdaa-skill-badge--modified">
												{ __(
													'Modified',
													'superdav-ai-agent'
												) }
											</span>
										) }
										{ hasUpdate && (
											<span className="sdaa-skill-badge sdaa-skill-badge--update">
												{ __(
													'Update Available',
													'superdav-ai-agent'
												) }
											</span>
										) }
										{ skill.version && (
											<span className="sdaa-skill-version">
												v{ skill.version }
											</span>
										) }
									</div>
								</div>
								<p className="sdaa-skill-card-description">
									{ skill.description }
								</p>
								<div className="sdaa-skill-card-footer">
									<div className="sdaa-skill-meta">
										<span className="sdaa-skill-word-count">
											{ skill.word_count }{ ' ' }
											{ __(
												'words',
												'superdav-ai-agent'
											) }
										</span>
										{ stats && (
											<span className="sdaa-skill-usage-stats">
												<span className="sdaa-skill-usage-count">
													{ stats.total_loads > 0 ? (
														<>
															{ sprintf(
																/* translators: %d: number of times the skill was loaded */
																_n(
																	'Used %d time',
																	'Used %d times',
																	stats.total_loads,
																	'superdav-ai-agent'
																),
																stats.total_loads
															) }
															{ stats.last_used_at && (
																<>
																	{ ' · ' }
																	{ formatRelativeTime(
																		stats.last_used_at
																	) }
																</>
															) }
														</>
													) : (
														__(
															'Never used',
															'superdav-ai-agent'
														)
													) }
												</span>
												{ stats.total_loads > 0 &&
													stats.helpful_count > 0 && (
														<span className="sdaa-skill-helpful">
															{ sprintf(
																/* translators: %d: number of helpful feedback responses */
																_n(
																	'%d helpful',
																	'%d helpful',
																	stats.helpful_count,
																	'superdav-ai-agent'
																),
																stats.helpful_count
															) }
														</span>
													) }
											</span>
										) }
									</div>
									<div className="sdaa-skill-card-actions">
										<Button
											icon={ pencil }
											size="small"
											label={ __(
												'Edit',
												'superdav-ai-agent'
											) }
											onClick={ () =>
												handleEdit( skill )
											}
										/>
										{ skill.is_builtin ? (
											<Button
												icon={ backup }
												size="small"
												label={ __(
													'Reset to Default',
													'superdav-ai-agent'
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
													'superdav-ai-agent'
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
						);
					} ) }
				</div>
			) }
		</div>
	);
}
