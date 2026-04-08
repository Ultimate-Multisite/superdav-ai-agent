/**
 * Abilities Explorer App
 *
 * Lists all registered WordPress abilities with name, description,
 * configuration status, required API keys, annotations, and output schema.
 * Abilities are grouped by category with collapsible sections.
 */

/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import {
	SearchControl,
	SelectControl,
	Spinner,
	Notice,
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
 * Single ability row component.
 *
 * @param {Object} props
 * @param {Object} props.ability Ability data object from the REST API.
 */
function AbilityRow( { ability } ) {
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

	const isClientAbility = category === 'gratis-ai-agent-js';

	return (
		<div className="gratis-ai-agent-ability-row">
			<div className="gratis-ai-agent-ability-row-header">
				<div className="gratis-ai-agent-ability-title">
					{ label || name }
				</div>
				<div className="gratis-ai-agent-ability-name">{ name }</div>
				<div className="gratis-ai-agent-ability-badges">
					{ isClientAbility && (
						<Badge intent="info">
							{ __( 'client', 'gratis-ai-agent' ) }
						</Badge>
					) }
					{ ! isClientAbility && isConfigured && (
						<Badge intent="success">
							{ __( 'Configured', 'gratis-ai-agent' ) }
						</Badge>
					) }
					{ ! isClientAbility && ! isConfigured && (
						<Badge intent="warning">
							{ __( 'Needs Setup', 'gratis-ai-agent' ) }
						</Badge>
					) }
					<AnnotationBadge
						label={ __( 'destructive', 'gratis-ai-agent' ) }
						active={ annotations.destructive }
						intent="error"
					/>
					<AnnotationBadge
						label={ __( 'readonly', 'gratis-ai-agent' ) }
						active={ annotations.readonly }
						intent="info"
					/>
					<AnnotationBadge
						label={ __( 'idempotent', 'gratis-ai-agent' ) }
						active={ annotations.idempotent }
						intent="success"
					/>
					{ showInRest && (
						<Badge intent="default">
							{ __( 'REST', 'gratis-ai-agent' ) }
						</Badge>
					) }
				</div>
			</div>
			<div className="gratis-ai-agent-ability-row-body">
				<p className="gratis-ai-agent-ability-category">{ category }</p>
				<p className="gratis-ai-agent-ability-description">
					{ description ||
						__( 'No description available.', 'gratis-ai-agent' ) }
				</p>
				<div className="gratis-ai-agent-ability-meta">
					<span className="gratis-ai-agent-ability-params">
						{ paramCount === 1
							? __( '1 parameter', 'gratis-ai-agent' )
							: sprintf(
									/* translators: %d: number of parameters */
									__( '%d parameters', 'gratis-ai-agent' ),
									paramCount
							  ) }
					</span>
					{ requiredParams && requiredParams.length > 0 && (
						<span className="gratis-ai-agent-ability-required">
							{ __( 'Required:', 'gratis-ai-agent' ) }{ ' ' }
							<code>{ requiredParams.join( ', ' ) }</code>
						</span>
					) }
				</div>
				{ ! isConfigured &&
					requiredApiKeys &&
					requiredApiKeys.length > 0 && (
						<Notice
							status="warning"
							isDismissible={ false }
							className="gratis-ai-agent-ability-notice"
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
					<div className="gratis-ai-agent-ability-schema-toggle">
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
							<pre className="gratis-ai-agent-ability-schema">
								{ JSON.stringify( outputSchema, null, 2 ) }
							</pre>
						) }
					</div>
				) }
			</div>
		</div>
	);
}

/**
 * Collapsible category section component.
 *
 * Renders a header with the category name and ability count badge,
 * and a collapsible body containing the ability rows.
 *
 * @param {Object}   props
 * @param {string}   props.category  Category name.
 * @param {Array}    props.abilities Abilities in this category.
 * @param {boolean}  props.open      Whether the section is expanded.
 * @param {Function} props.onToggle  Callback to toggle open/closed state.
 */
function CategorySection( { category, abilities, open, onToggle } ) {
	return (
		<div className="gratis-ai-agent-abilities-category">
			<button
				type="button"
				className="gratis-ai-agent-abilities-category-header"
				onClick={ onToggle }
				aria-expanded={ open }
			>
				<span className="gratis-ai-agent-abilities-category-name">
					{ category }
				</span>
				<span className="gratis-ai-agent-abilities-category-count">
					{ abilities.length }
				</span>
			</button>
			{ open && (
				<div className="gratis-ai-agent-abilities-category-body">
					{ abilities.map( ( ability ) => (
						<AbilityRow key={ ability.name } ability={ ability } />
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * Main Abilities Explorer application component.
 *
 * Renders abilities grouped by category with:
 *   - A SearchControl that filters by name/description.
 *   - A SelectControl that filters by category.
 *   - Collapsible category sections with Expand all / Collapse all buttons.
 *   - A result count paragraph that updates as filters change.
 *
 * CSS classes used by E2E tests:
 *   .gratis-ai-agent-abilities-manager       — outer wrapper
 *   .gratis-ai-agent-abilities-search        — SearchControl wrapper
 *   .gratis-ai-agent-abilities-filters       — category SelectControl wrapper
 *   .gratis-ai-agent-abilities-count         — count paragraph
 *   .gratis-ai-agent-abilities-category      — per-category section
 *   .gratis-ai-agent-abilities-category-header — clickable header button
 *   .gratis-ai-agent-abilities-category-body   — collapsible body
 *   .gratis-ai-agent-abilities-category-count  — count badge in header
 */
export default function AbilitiesExplorerApp() {
	const [ abilities, setAbilities ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );
	// Map of category name → open state. True = expanded.
	const [ openCategories, setOpenCategories ] = useState( {} );

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
	const categoryOptions = useMemo(
		() => [
			{ label: __( 'All Categories', 'gratis-ai-agent' ), value: '' },
			...[ ...new Set( abilities.map( ( a ) => a.category ) ) ]
				.sort()
				.map( ( cat ) => ( { label: cat, value: cat } ) ),
		],
		[ abilities ]
	);

	// Filtered abilities based on search and category filter.
	const filtered = useMemo( () => {
		const searchLower = search.toLowerCase();
		return abilities.filter( ( ability ) => {
			const matchesSearch =
				! search ||
				( ability.label || '' ).toLowerCase().includes( searchLower ) ||
				ability.name.toLowerCase().includes( searchLower ) ||
				( ability.description || '' )
					.toLowerCase()
					.includes( searchLower );

			const matchesCategory =
				! categoryFilter || ability.category === categoryFilter;

			return matchesSearch && matchesCategory;
		} );
	}, [ abilities, search, categoryFilter ] );

	// Group filtered abilities by category.
	const groupedByCategory = useMemo( () => {
		const groups = {};
		for ( const ability of filtered ) {
			if ( ! groups[ ability.category ] ) {
				groups[ ability.category ] = [];
			}
			groups[ ability.category ].push( ability );
		}
		return groups;
	}, [ filtered ] );

	const sortedCategories = useMemo(
		() => Object.keys( groupedByCategory ).sort(),
		[ groupedByCategory ]
	);

	// When filtering is active, auto-expand all categories.
	const isFiltering = search !== '' || categoryFilter !== '';

	// Initialise open state when abilities load or categories change.
	useEffect( () => {
		if ( abilities.length === 0 ) {
			return;
		}
		setOpenCategories( ( prev ) => {
			const next = { ...prev };
			for ( const cat of sortedCategories ) {
				if ( ! ( cat in next ) ) {
					// Default to open.
					next[ cat ] = true;
				}
			}
			return next;
		} );
	}, [ abilities, sortedCategories ] );

	// Auto-expand all categories when a filter is active.
	useEffect( () => {
		if ( isFiltering ) {
			setOpenCategories( ( prev ) => {
				const next = { ...prev };
				for ( const cat of sortedCategories ) {
					next[ cat ] = true;
				}
				return next;
			} );
		}
	}, [ isFiltering, sortedCategories ] );

	const handleSearchChange = useCallback( ( value ) => {
		setSearch( value );
	}, [] );

	const handleCollapseAll = useCallback( () => {
		setOpenCategories( ( prev ) => {
			const next = { ...prev };
			for ( const cat of Object.keys( next ) ) {
				next[ cat ] = false;
			}
			return next;
		} );
	}, [] );

	const handleExpandAll = useCallback( () => {
		setOpenCategories( ( prev ) => {
			const next = { ...prev };
			for ( const cat of Object.keys( next ) ) {
				next[ cat ] = true;
			}
			return next;
		} );
	}, [] );

	const handleToggleCategory = useCallback( ( category ) => {
		setOpenCategories( ( prev ) => ( {
			...prev,
			[ category ]: ! prev[ category ],
		} ) );
	}, [] );

	if ( loading ) {
		return (
			<div className="gratis-ai-agent-abilities-loading">
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
		<div className="gratis-ai-agent-abilities-manager">
			{ /* Toolbar: search, category filter, expand/collapse controls */ }
			<div className="gratis-ai-agent-abilities-toolbar">
				<div className="gratis-ai-agent-abilities-controls">
					<div className="gratis-ai-agent-abilities-search">
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
					</div>
					<div className="gratis-ai-agent-abilities-filters">
						<SelectControl
							label={ __( 'Category', 'gratis-ai-agent' ) }
							value={ categoryFilter }
							options={ categoryOptions }
							onChange={ setCategoryFilter }
						/>
					</div>
					<div className="gratis-ai-agent-abilities-bulk-actions">
						<Button
							variant="tertiary"
							onClick={ handleCollapseAll }
						>
							{ __( 'Collapse all', 'gratis-ai-agent' ) }
						</Button>
						<Button variant="tertiary" onClick={ handleExpandAll }>
							{ __( 'Expand all', 'gratis-ai-agent' ) }
						</Button>
					</div>
				</div>
				<p className="gratis-ai-agent-abilities-count">
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

			{ /* Category sections */ }
			{ filtered.length === 0 ? (
				<p className="gratis-ai-agent-abilities-no-results">
					{ abilities.length === 0
						? __(
								'No abilities are registered.',
								'gratis-ai-agent'
						  )
						: __(
								'No abilities match your current filters.',
								'gratis-ai-agent'
						  ) }
				</p>
			) : (
				<div className="gratis-ai-agent-abilities-list">
					{ sortedCategories.map( ( category ) => (
						<CategorySection
							key={ category }
							category={ category }
							abilities={ groupedByCategory[ category ] }
							open={ !! openCategories[ category ] }
							onToggle={ () => handleToggleCategory( category ) }
						/>
					) ) }
				</div>
			) }
		</div>
	);
}
