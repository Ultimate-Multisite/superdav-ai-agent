/**
 * Abilities Explorer App
 *
 * Lists all registered WordPress abilities with name, description,
 * configuration status, required API keys, annotations, and output schema.
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
	Card,
	CardHeader,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
	Button,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/* global gratisAiAgentAbilities */

/**
 * Intent-to-style map for the custom Badge component.
 * Mirrors the colour conventions of the WordPress Badge component
 * without depending on it being present in the installed version.
 */
const BADGE_STYLES = {
	default: {
		background: '#f0f0f0',
		color: '#1e1e1e',
		border: '1px solid #c3c4c7',
	},
	info: {
		background: '#e8f4fd',
		color: '#0a4b78',
		border: '1px solid #72aee6',
	},
	success: {
		background: '#edfaef',
		color: '#1a4a1f',
		border: '1px solid #68de7c',
	},
	warning: {
		background: '#fcf9e8',
		color: '#4a3c00',
		border: '1px solid #f0b849',
	},
	error: {
		background: '#fce8e8',
		color: '#4a0000',
		border: '1px solid #f86368',
	},
};

/**
 * Custom inline Badge component with intent-based styling.
 * Replaces the @wordpress/components Badge which is unavailable
 * in older WordPress versions and causes a runtime crash.
 *
 * @param {Object} props
 * @param {string} [props.intent='default'] One of: default, info, success, warning, error.
 * @param {*}      props.children           Badge label content.
 */
function Badge( { intent = 'default', children } ) {
	const style = {
		display: 'inline-flex',
		alignItems: 'center',
		padding: '2px 8px',
		borderRadius: '2px',
		fontSize: '11px',
		fontWeight: 600,
		lineHeight: '20px',
		whiteSpace: 'nowrap',
		...( BADGE_STYLES[ intent ] || BADGE_STYLES.default ),
	};
	return <span style={ style }>{ children }</span>;
}

/**
 * Renders a badge only when the annotation is active.
 *
 * @param {Object}  props
 * @param {string}  props.label  Badge label text.
 * @param {boolean} props.active Whether to render the badge.
 * @param {string}  props.intent Badge colour intent.
 */
function AnnotationBadge( { label, active, intent } ) {
	if ( ! active ) {
		return null;
	}
	return <Badge intent={ intent }>{ label }</Badge>;
}

/**
 * Single ability card component with expandable output schema.
 *
 * @param {Object} props
 * @param {Object} props.ability Ability data object from the REST API.
 */
function AbilityCard( { ability } ) {
	const [ expanded, setExpanded ] = useState( false );

	const {
		name,
		label,
		description,
		category,
		param_count: paramCount,
		required_params: requiredParams,
		is_configured: isConfigured,
		required_api_keys: requiredApiKeys,
		annotations = {},
		output_schema: outputSchema,
		show_in_rest: showInRest,
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
							<FlexItem>
								<AnnotationBadge
									label={ __(
										'destructive',
										'gratis-ai-agent'
									) }
									active={ annotations.destructive }
									intent="error"
								/>
							</FlexItem>
							<FlexItem>
								<AnnotationBadge
									label={ __(
										'readonly',
										'gratis-ai-agent'
									) }
									active={ annotations.readonly }
									intent="info"
								/>
							</FlexItem>
							<FlexItem>
								<AnnotationBadge
									label={ __(
										'idempotent',
										'gratis-ai-agent'
									) }
									active={ annotations.idempotent }
									intent="success"
								/>
							</FlexItem>
							{ showInRest && (
								<FlexItem>
									<Badge intent="default">
										{ __( 'REST', 'gratis-ai-agent' ) }
									</Badge>
								</FlexItem>
							) }
						</Flex>
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				<p className="gratis-ability-category">{ category }</p>
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
				{ outputSchema && Object.keys( outputSchema ).length > 0 && (
					<div className="gratis-ability-schema-toggle">
						<Button
							variant="link"
							onClick={ () => setExpanded( ( v ) => ! v ) }
							aria-expanded={ expanded }
						>
							{ expanded
								? __( 'Hide output schema', 'gratis-ai-agent' )
								: __(
										'Show output schema',
										'gratis-ai-agent'
								  ) }
						</Button>
						{ expanded && (
							<pre className="gratis-ability-schema">
								{ JSON.stringify( outputSchema, null, 2 ) }
							</pre>
						) }
					</div>
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
