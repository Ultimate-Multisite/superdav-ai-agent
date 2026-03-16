/**
 * Abilities Explorer App
 *
 * Lists all registered WordPress abilities with name, description,
 * configuration status, required API keys, and meta flags.
 */

/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	SearchControl,
	SelectControl,
	Spinner,
	Notice,
	Badge,
	Card,
	CardHeader,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/* global gratisAiAgentAbilities */

/**
 * Badge colour helper — maps a category slug to a colour variant.
 *
 * @param {string} category Ability category slug.
 * @return {string} Badge intent string.
 */
function categoryIntent( category ) {
	const map = {
		'gratis-ai-agent': 'info',
		memory: 'info',
		seo: 'warning',
		content: 'success',
		marketing: 'success',
		database: 'error',
		file: 'warning',
		git: 'warning',
		woocommerce: 'info',
	};
	for ( const key of Object.keys( map ) ) {
		if ( category.includes( key ) ) {
			return map[ key ];
		}
	}
	return 'default';
}

/**
 * Single ability card component.
 *
 * @param {Object} props
 * @param {Object} props.ability Ability data object from the REST API.
 */
function AbilityCard( { ability } ) {
	const {
		name,
		label,
		description,
		category,
		param_count: paramCount,
		required_params: requiredParams,
		is_configured: isConfigured,
		required_api_keys: requiredApiKeys,
		is_readonly: isReadonly,
		is_destructive: isDestructive,
	} = ability;

	return (
		<Card className="gratis-ability-card">
			<CardHeader>
				<Flex justify="space-between" align="center" wrap={ true }>
					<FlexBlock>
						<div className="gratis-ability-title">
							{ label || name }
						</div>
						<div className="gratis-ability-name">{ name }</div>
					</FlexBlock>
					<FlexItem>
						<Flex gap={ 1 } wrap={ true }>
							<FlexItem>
								<Badge intent={ categoryIntent( category ) }>
									{ category }
								</Badge>
							</FlexItem>
							<FlexItem>
								{ isConfigured ? (
									<Badge intent="success">
										{ __(
											'Configured',
											'gratis-ai-agent'
										) }
									</Badge>
								) : (
									<Badge intent="warning">
										{ __(
											'Needs Setup',
											'gratis-ai-agent'
										) }
									</Badge>
								) }
							</FlexItem>
							{ isDestructive && (
								<FlexItem>
									<Badge intent="error">
										{ __(
											'Destructive',
											'gratis-ai-agent'
										) }
									</Badge>
								</FlexItem>
							) }
							{ isReadonly && (
								<FlexItem>
									<Badge intent="default">
										{ __( 'Read-only', 'gratis-ai-agent' ) }
									</Badge>
								</FlexItem>
							) }
						</Flex>
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				<p className="gratis-ability-description">
					{ description ||
						__( 'No description available.', 'gratis-ai-agent' ) }
				</p>
				<Flex gap={ 3 } wrap={ true } className="gratis-ability-meta">
					<FlexItem>
						<span className="gratis-ability-params">
							{ paramCount === 1
								? __( '1 parameter', 'gratis-ai-agent' )
								: sprintf(
										/* translators: %d: number of parameters */
										__(
											'%d parameters',
											'gratis-ai-agent'
										),
										paramCount
								  ) }
						</span>
					</FlexItem>
					{ requiredParams.length > 0 && (
						<FlexItem>
							<span className="gratis-ability-required">
								{ __( 'Required:', 'gratis-ai-agent' ) }{ ' ' }
								<code>{ requiredParams.join( ', ' ) }</code>
							</span>
						</FlexItem>
					) }
				</Flex>
				{ ! isConfigured && requiredApiKeys.length > 0 && (
					<Notice
						status="warning"
						isDismissible={ false }
						className="gratis-ability-notice"
					>
						{ __( 'Requires:', 'gratis-ai-agent' ) }{ ' ' }
						{ requiredApiKeys.join( ', ' ) }{ ' ' }
						{ gratisAiAgentAbilities?.settingsUrl && (
							<a href={ gratisAiAgentAbilities.settingsUrl }>
								{ __(
									'Configure in Settings',
									'gratis-ai-agent'
								) }
							</a>
						) }
					</Notice>
				) }
			</CardBody>
		</Card>
	);
}

/**
 * Main Abilities Explorer application component.
 */
export default function AbilitiesExplorerApp() {
	const [ abilities, setAbilities ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: '/gratis-ai-agent/v1/abilities/explorer' } )
			.then( ( data ) => {
				setAbilities( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ||
						__( 'Failed to load abilities.', 'gratis-ai-agent' )
				);
				setLoading( false );
			} );
	}, [] );

	// Derive unique categories for the filter dropdown.
	const categories = [
		{ label: __( 'All Categories', 'gratis-ai-agent' ), value: '' },
		...[ ...new Set( abilities.map( ( a ) => a.category ) ) ]
			.sort()
			.map( ( cat ) => ( { label: cat, value: cat } ) ),
	];

	const statusOptions = [
		{ label: __( 'All Statuses', 'gratis-ai-agent' ), value: '' },
		{
			label: __( 'Configured', 'gratis-ai-agent' ),
			value: 'configured',
		},
		{
			label: __( 'Needs Setup', 'gratis-ai-agent' ),
			value: 'needs-setup',
		},
	];

	const filtered = abilities.filter( ( ability ) => {
		const searchLower = search.toLowerCase();
		const matchesSearch =
			! search ||
			ability.label.toLowerCase().includes( searchLower ) ||
			ability.name.toLowerCase().includes( searchLower ) ||
			( ability.description || '' ).toLowerCase().includes( searchLower );

		const matchesCategory =
			! categoryFilter || ability.category === categoryFilter;

		const matchesStatus =
			! statusFilter ||
			( statusFilter === 'configured' && ability.is_configured ) ||
			( statusFilter === 'needs-setup' && ! ability.is_configured );

		return matchesSearch && matchesCategory && matchesStatus;
	} );

	const handleSearchChange = useCallback( ( value ) => {
		setSearch( value );
	}, [] );

	if ( loading ) {
		return (
			<div className="gratis-abilities-loading">
				<Spinner />
				<span>{ __( 'Loading abilities…', 'gratis-ai-agent' ) }</span>
			</div>
		);
	}

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	return (
		<div className="gratis-abilities-explorer">
			<div className="gratis-abilities-toolbar">
				<Flex gap={ 3 } wrap={ true } align="flex-end">
					<FlexBlock className="gratis-abilities-search">
						<SearchControl
							label={ __(
								'Search abilities',
								'gratis-ai-agent'
							) }
							value={ search }
							onChange={ handleSearchChange }
							placeholder={ __(
								'Search by name or description…',
								'gratis-ai-agent'
							) }
						/>
					</FlexBlock>
					<FlexItem>
						<SelectControl
							label={ __( 'Category', 'gratis-ai-agent' ) }
							value={ categoryFilter }
							options={ categories }
							onChange={ setCategoryFilter }
						/>
					</FlexItem>
					<FlexItem>
						<SelectControl
							label={ __( 'Status', 'gratis-ai-agent' ) }
							value={ statusFilter }
							options={ statusOptions }
							onChange={ setStatusFilter }
						/>
					</FlexItem>
				</Flex>
				<p className="gratis-abilities-count">
					{ filtered.length === abilities.length
						? sprintf(
								/* translators: %d: total number of abilities */
								__(
									'%d abilities registered',
									'gratis-ai-agent'
								),
								abilities.length
						  )
						: sprintf(
								/* translators: 1: filtered count, 2: total count */
								__(
									'Showing %1$d of %2$d abilities',
									'gratis-ai-agent'
								),
								filtered.length,
								abilities.length
						  ) }
				</p>
			</div>

			{ filtered.length === 0 ? (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'No abilities match your filters.',
						'gratis-ai-agent'
					) }
				</Notice>
			) : (
				<div className="gratis-abilities-grid">
					{ filtered.map( ( ability ) => (
						<AbilityCard key={ ability.name } ability={ ability } />
					) ) }
				</div>
			) }
		</div>
	);
}
