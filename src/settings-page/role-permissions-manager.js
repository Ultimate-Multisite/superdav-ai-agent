/**
 * Role Permissions Manager component.
 *
 * Allows administrators to configure which WordPress user roles can access
 * the AI chat and which specific abilities are available per role.
 */

/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * A single role row showing chat access toggle and ability restrictions.
 *
 * @param {Object}   props
 * @param {string}   props.roleSlug  WordPress role slug.
 * @param {string}   props.roleLabel Human-readable role name.
 * @param {Object}   props.config    Current config for this role.
 * @param {Array}    props.abilities All registered abilities.
 * @param {Function} props.onChange  Called with (roleSlug, newConfig).
 * @return {JSX.Element} The role row element.
 */
function RoleRow( { roleSlug, roleLabel, config, abilities, onChange } ) {
	const chatAccess = config?.chat_access ?? false;

	const allowedAbilities = useMemo(
		() => config?.allowed_abilities ?? [],
		[ config ]
	);

	const allAllowed = allowedAbilities.length === 0;

	const handleChatToggle = useCallback(
		( value ) => {
			onChange( roleSlug, {
				...( config || {} ),
				chat_access: value,
				allowed_abilities: allowedAbilities,
			} );
		},
		[ roleSlug, config, allowedAbilities, onChange ]
	);

	const handleAllAbilitiesToggle = useCallback(
		( value ) => {
			onChange( roleSlug, {
				...( config || {} ),
				chat_access: chatAccess,
				// Empty array = all abilities allowed; populated = restricted list.
				allowed_abilities: value
					? []
					: abilities.map( ( a ) => a.name ),
			} );
		},
		[ roleSlug, config, chatAccess, abilities, onChange ]
	);

	const handleAbilityToggle = useCallback(
		( abilityName, checked ) => {
			const updated = checked
				? [ ...allowedAbilities, abilityName ]
				: allowedAbilities.filter( ( n ) => n !== abilityName );
			onChange( roleSlug, {
				...( config || {} ),
				chat_access: chatAccess,
				allowed_abilities: updated,
			} );
		},
		[ roleSlug, config, chatAccess, allowedAbilities, onChange ]
	);

	return (
		<div className="gratis-ai-agent-role-row">
			<div className="gratis-ai-agent-role-header">
				<h3 className="gratis-ai-agent-role-name">{ roleLabel }</h3>
				<ToggleControl
					label={ __( 'Chat Access', 'gratis-ai-agent' ) }
					checked={ chatAccess }
					onChange={ handleChatToggle }
					help={ __(
						'Allow this role to use the AI chat.',
						'gratis-ai-agent'
					) }
					__nextHasNoMarginBottom
				/>
			</div>

			{ chatAccess && (
				<div className="gratis-ai-agent-role-abilities">
					<ToggleControl
						label={ __(
							'All abilities (unrestricted)',
							'gratis-ai-agent'
						) }
						checked={ allAllowed }
						onChange={ handleAllAbilitiesToggle }
						help={ __(
							'When enabled, this role can use all available abilities. Disable to select specific abilities.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>

					{ ! allAllowed && abilities.length > 0 && (
						<div className="gratis-ai-agent-ability-list">
							<p className="description">
								{ __(
									'Select which abilities this role can use:',
									'gratis-ai-agent'
								) }
							</p>
							{ abilities.map( ( ability ) => (
								<CheckboxControl
									key={ ability.name }
									label={ ability.label || ability.name }
									help={ ability.description || '' }
									checked={ allowedAbilities.includes(
										ability.name
									) }
									onChange={ ( checked ) =>
										handleAbilityToggle(
											ability.name,
											checked
										)
									}
									__nextHasNoMarginBottom
								/>
							) ) }
						</div>
					) }
				</div>
			) }
		</div>
	);
}

/**
 * Main Role Permissions Manager component.
 *
 * @return {JSX.Element} The role permissions manager element.
 */
export default function RolePermissionsManager() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ roles, setRoles ] = useState( {} );
	const [ permissions, setPermissions ] = useState( {} );
	const [ alwaysAllowed, setAlwaysAllowed ] = useState( [] );
	const [ abilities, setAbilities ] = useState( [] );

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/gratis-ai-agent/v1/role-permissions' } ),
			apiFetch( { path: '/gratis-ai-agent/v1/role-permissions/roles' } ),
			apiFetch( { path: '/gratis-ai-agent/v1/abilities' } ).catch(
				() => []
			),
		] )
			.then( ( [ permData, rolesData, abilitiesData ] ) => {
				setPermissions( permData.permissions || {} );
				setAlwaysAllowed( permData.always_allowed || [] );
				setRoles( rolesData || {} );
				setAbilities( abilitiesData || [] );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __(
						'Failed to load role permissions.',
						'gratis-ai-agent'
					),
				} );
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleRoleChange = useCallback( ( roleSlug, newConfig ) => {
		setPermissions( ( prev ) => ( { ...prev, [ roleSlug ]: newConfig } ) );
	}, [] );

	const handleSave = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			const result = await apiFetch( {
				path: '/gratis-ai-agent/v1/role-permissions',
				method: 'POST',
				data: { permissions },
			} );
			setPermissions( result.permissions || {} );
			setNotice( {
				status: 'success',
				message: __( 'Role permissions saved.', 'gratis-ai-agent' ),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to save role permissions.',
					'gratis-ai-agent'
				),
			} );
		}
		setSaving( false );
	}, [ permissions ] );

	if ( loading ) {
		return (
			<div className="gratis-ai-agent-role-permissions-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="gratis-ai-agent-role-permissions">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<p className="description">
				{ __(
					'Configure which WordPress user roles can access the AI chat and which abilities are available per role. Administrators always have full access.',
					'gratis-ai-agent'
				) }
			</p>

			{ alwaysAllowed.length > 0 && (
				<p className="description">
					<em>
						{ __(
							'Always allowed (cannot be restricted):',
							'gratis-ai-agent'
						) }{ ' ' }
						{ alwaysAllowed.join( ', ' ) }
					</em>
				</p>
			) }

			<div className="gratis-ai-agent-role-list">
				{ Object.entries( roles )
					.filter( ( [ slug ] ) => ! alwaysAllowed.includes( slug ) )
					.map( ( [ slug, label ] ) => (
						<RoleRow
							key={ slug }
							roleSlug={ slug }
							roleLabel={ label }
							config={ permissions[ slug ] }
							abilities={ abilities }
							onChange={ handleRoleChange }
						/>
					) ) }
			</div>

			<div className="gratis-ai-agent-role-permissions-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save Permissions', 'gratis-ai-agent' ) }
				</Button>
			</div>
		</div>
	);
}
