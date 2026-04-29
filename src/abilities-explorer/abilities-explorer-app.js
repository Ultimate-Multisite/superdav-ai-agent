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

/* global sdAiAgentAbilities */

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

	const isClientAbility = category === 'sd-ai-agent-js';

	return (
		<div className="sd-ai-agent-ability-row">
			<div className="sd-ai-agent-ability-row-header">
				<div className="sd-ai-agent-ability-title">
					{ label || name }
				</div>
				<div className="sd-ai-agent-ability-name">{ name }</div>
				<div className="sd-ai-agent-ability-badges">
					{ isClientAbility && (
						<Badge intent="info">
							{ __( 'client', 'sd-ai-agent' ) }
						</Badge>
					) }
					{ ! isClientAbility && isConfigured && (
						<Badge intent="success">
							{ __( 'Configured', 'sd-ai-agent' ) }
						</Badge>
					) }
					{ ! isClientAbility && ! isConfigured && (
						<Badge intent="warning">
							{ __( 'Needs Setup', 'sd-ai-agent' ) }
						</Badge>
					) }
					<AnnotationBadge
						label={ __( 'destructive', 'sd-ai-agent' ) }
						active={ annotations.destructive }
						intent="error"
					/>
					<AnnotationBadge
						label={ __( 'readonly', 'sd-ai-agent' ) }
						active={ annotations.readonly }
						intent="info"
					/>
					<AnnotationBadge
						label={ __( 'idempotent', 'sd-ai-agent' ) }
						active={ annotations.idempotent }
						intent="success"
					/>
					{ showInRest && (
						<Badge intent="default">
							{ __( 'REST', 'sd-ai-agent' ) }
						</Badge>
					) }
				</div>
			</div>
			<div className="sd-ai-agent-ability-row-body">
				<p className="sd-ai-agent-ability-category">{ category }</p>
				<p className="sd-ai-agent-ability-description">
					{ description ||
						__( 'No description available.', 'sd-ai-agent' ) }
				</p>
				<div className="sd-ai-agent-ability-meta">
					<span className="sd-ai-agent-ability-params">
						{ paramCount === 1
							? __( '1 parameter', 'sd-ai-agent' )
							: sprintf(
									/* translators: %d: number of parameters */
									__( '%d parameters', 'sd-ai-agent' ),
									paramCount
							  ) }
					</span>
					{ requiredParams && requiredParams.length > 0 && (
						<span className="sd-ai-agent-ability-required">
							{ __( 'Required:', 'sd-ai-agent' ) }{ ' ' }
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
							className="sd-ai-agent-ability-notice"
						>
							{ __( 'Requires:', 'sd-ai-agent' ) }{ ' ' }
							{ requiredApiKeys.join( ', ' ) }{ ' ' }
							{ sdAiAgentAbilities?.settingsUrl && (
								<a href={ sdAiAgentAbilities.settingsUrl }>
									{ __(
										'Configure in Settings',
										'sd-ai-agent'
									) }
								</a>
							) }
						</Notice>
					) }
				{ outputSchema && Object.keys( outputSchema ).length > 0 && (
					<div className="sd-ai-agent-ability-schema-toggle">
						<Button
							variant="link"
							onClick={ () => setExpanded( ( v ) => ! v ) }
							aria-expanded={ expanded }
						>
							{ expanded
								? __( 'Hide output schema', 'sd-ai-agent' )
								: __( 'Show output schema', 'sd-ai-agent' ) }
						</Button>
						{ expanded && (
							<pre className="sd-ai-agent-ability-schema">
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
		<div className="sd-ai-agent-abilities-category">
			<button
				type="button"
				className="sd-ai-agent-abilities-category-header"
				onClick={ onToggle }
				aria-expanded={ open }
			>
				<span className="sd-ai-agent-abilities-category-name">
					{ category }
				</span>
				<span className="sd-ai-agent-abilities-category-count">
					{ abilities.length }
				</span>
			</button>
			{ open && (
				<div className="sd-ai-agent-abilities-category-body">
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
 *   .sd-ai-agent-abilities-manager       — outer wrapper
 *   .sd-ai-agent-abilities-search        — SearchControl wrapper
 *   .sd-ai-agent-abilities-filters       — category SelectControl wrapper
 *   .sd-ai-agent-abilities-count         — count paragraph
 *   .sd-ai-agent-abilities-category      — per-category section
 *   .sd-ai-agent-abilities-category-header — clickable header button
 *   .sd-ai-agent-abilities-category-body   — collapsible body
 *   .sd-ai-agent-abilities-category-count  — count badge in header
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
		apiFetch( { path: '/sd-ai-agent/v1/abilities/explorer' } )
			.then( ( data ) => {
				setAbilities( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ||
						__( 'Failed to load abilities.', 'sd-ai-agent' )
				);
				setLoading( false );
			} );
	}, [] );

	// Derive unique categories for the filter dropdown.
	const categoryOptions = useMemo(
		() => [
			{ label: __( 'All Categories', 'sd-ai-agent' ), value: '' },
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

	// Render the outer wrapper immediately so E2E tests can detect the
	// abilities route has mounted. Loading and error states appear inside
	// the wrapper so the .sd-ai-agent-abilities-manager selector is
	// always present once the route renders, regardless of fetch status.
	if ( loading ) {
		return (
			<div className="sd-ai-agent-abilities-manager">
				<div className="sd-ai-agent-abilities-loading">
					<Spinner />
					<span>{ __( 'Loading abilities…', 'sd-ai-agent' ) }</span>
				</div>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="sd-ai-agent-abilities-manager">
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			</div>
		);
	}

	return (
		<div className="sd-ai-agent-abilities-manager">
			{ /* Toolbar: search, category filter, expand/collapse controls */ }
			<div className="sd-ai-agent-abilities-toolbar">
				<div className="sd-ai-agent-abilities-controls">
					<div className="sd-ai-agent-abilities-search">
						<SearchControl
							label={ __( 'Search abilities', 'sd-ai-agent' ) }
							value={ search }
							onChange={ handleSearchChange }
							placeholder={ __(
								'Search by name or description…',
								'sd-ai-agent'
							) }
						/>
					</div>
					<div className="sd-ai-agent-abilities-filters">
						<SelectControl
							label={ __( 'Category', 'sd-ai-agent' ) }
							value={ categoryFilter }
							options={ categoryOptions }
							onChange={ setCategoryFilter }
						/>
					</div>
					<div className="sd-ai-agent-abilities-bulk-actions">
						<Button
							variant="tertiary"
							onClick={ handleCollapseAll }
						>
							{ __( 'Collapse all', 'sd-ai-agent' ) }
						</Button>
						<Button variant="tertiary" onClick={ handleExpandAll }>
							{ __( 'Expand all', 'sd-ai-agent' ) }
						</Button>
					</div>
				</div>
				<p className="sd-ai-agent-abilities-count">
					{ filtered.length === abilities.length
						? sprintf(
								/* translators: %d: total number of abilities */
								__( '%d abilities registered', 'sd-ai-agent' ),
								abilities.length
						  )
						: sprintf(
								/* translators: 1: filtered count, 2: total count */
								__(
									'Showing %1$d of %2$d abilities',
									'sd-ai-agent'
								),
								filtered.length,
								abilities.length
						  ) }
				</p>
			</div>

			{ /* Category sections */ }
			{ filtered.length === 0 ? (
				<p className="sd-ai-agent-abilities-no-results">
					{ abilities.length === 0
						? __( 'No abilities are registered.', 'sd-ai-agent' )
						: __(
								'No abilities match your current filters.',
								'sd-ai-agent'
						  ) }
				</p>
			) : (
				<div className="sd-ai-agent-abilities-list">
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
