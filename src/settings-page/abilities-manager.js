/**
 * Abilities Manager
 *
 * Settings > Abilities tab: search filter, category grouping with collapsible
 * sections, and per-ability permission selects (Auto / Confirm / Disabled).
 */

/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { SearchControl, SelectControl, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Permission options shared across all ability selects.
 */
const PERMISSION_OPTIONS = [
	{
		label: __( 'Auto (always allow)', 'superdav-ai-agent' ),
		value: 'auto',
	},
	{
		label: __( 'Confirm (ask before use)', 'superdav-ai-agent' ),
		value: 'confirm',
	},
	{
		label: __( 'Disabled', 'superdav-ai-agent' ),
		value: 'disabled',
	},
];

/**
 * Single collapsible category section.
 *
 * @param {Object}   props
 * @param {string}   props.category        Category label.
 * @param {Array}    props.abilities       Abilities in this category.
 * @param {Object}   props.toolPermissions Current tool_permissions map.
 * @param {Function} props.onPermChange    Called with (abilityName, newValue).
 * @param {boolean}  props.defaultOpen     Whether the section starts expanded.
 * @param {boolean}  props.isFiltering     Whether a search/category filter is active.
 */
function AbilityCategorySection( {
	category,
	abilities,
	toolPermissions,
	onPermChange,
	defaultOpen,
	isFiltering,
} ) {
	const [ open, setOpen ] = useState( defaultOpen );

	// Sync open state when the parent changes defaultOpen (e.g. collapse/expand all).
	useEffect( () => {
		setOpen( defaultOpen );
	}, [ defaultOpen ] );

	// Force-open the section whenever filtering becomes active so that a
	// section manually collapsed while allOpen===true is not left hidden
	// when the user starts a search or category filter.
	useEffect( () => {
		if ( isFiltering ) {
			setOpen( true );
		}
	}, [ isFiltering ] );

	// Count non-default (non-auto) permissions in this category.
	const nonDefaultCount = abilities.filter( ( a ) => {
		const perm = toolPermissions[ a.name ];
		return perm && perm !== 'auto';
	} ).length;

	return (
		<div className="sdaa-abilities-category">
			<button
				type="button"
				className="sdaa-abilities-category-header"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
			>
				<span className="sdaa-abilities-category-chevron">
					{ open ? '▾' : '▸' }
				</span>
				<span className="sdaa-abilities-category-name">
					{ category }
				</span>
				<span className="sdaa-abilities-category-count">
					{ abilities.length }
				</span>
				{ nonDefaultCount > 0 && (
					<span className="sdaa-abilities-category-badge">
						{ nonDefaultCount }{ ' ' }
						{ __( 'customised', 'superdav-ai-agent' ) }
					</span>
				) }
			</button>

			{ open && (
				<div className="sdaa-abilities-category-body">
					{ abilities.map( ( ability ) => {
						const currentPerm =
							toolPermissions[ ability.name ] || 'auto';
						return (
							<div
								key={ ability.name }
								className="sdaa-ability-row"
							>
								<SelectControl
									label={ ability.label || ability.name }
									help={ ability.description || '' }
									value={ currentPerm }
									options={ PERMISSION_OPTIONS }
									onChange={ ( v ) =>
										onPermChange( ability.name, v )
									}
									__nextHasNoMarginBottom
								/>
							</div>
						);
					} ) }
				</div>
			) }
		</div>
	);
}

/**
 * Abilities Manager component.
 *
 * @param {Object}   props
 * @param {Array}    props.abilities       All registered abilities from the API.
 * @param {Object}   props.toolPermissions Current tool_permissions map from settings.
 * @param {Function} props.onPermChange    Called with (abilityName, newValue).
 */
export default function AbilitiesManager( {
	abilities,
	toolPermissions,
	onPermChange,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );
	const [ allOpen, setAllOpen ] = useState( true );
	// Track which categories have been manually toggled.
	const [ openOverrides, setOpenOverrides ] = useState( {} );

	const handleSearchChange = useCallback( ( value ) => {
		setSearch( value );
	}, [] );

	// Derive unique sorted categories for the filter dropdown.
	const categoryOptions = useMemo( () => {
		const cats = [
			...new Set(
				abilities.map(
					( a ) => a.category || __( 'General', 'superdav-ai-agent' )
				)
			),
		].sort();
		return [
			{ label: __( 'All Categories', 'superdav-ai-agent' ), value: '' },
			...cats.map( ( c ) => ( { label: c, value: c } ) ),
		];
	}, [ abilities ] );

	// Filter abilities by search + category.
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

			const abilityCategory =
				ability.category || __( 'General', 'superdav-ai-agent' );
			const matchesCategory =
				! categoryFilter || abilityCategory === categoryFilter;

			return matchesSearch && matchesCategory;
		} );
	}, [ abilities, search, categoryFilter ] );

	// Group filtered abilities by category, preserving sort order.
	const grouped = useMemo( () => {
		const map = {};
		filtered.forEach( ( ability ) => {
			const cat =
				ability.category || __( 'General', 'superdav-ai-agent' );
			if ( ! map[ cat ] ) {
				map[ cat ] = [];
			}
			map[ cat ].push( ability );
		} );
		// Return sorted array of [category, abilities[]] pairs.
		return Object.entries( map ).sort( ( a, b ) =>
			a[ 0 ].localeCompare( b[ 0 ] )
		);
	}, [ filtered ] );

	const handleExpandAll = useCallback( () => {
		setAllOpen( true );
		setOpenOverrides( {} );
	}, [] );

	const handleCollapseAll = useCallback( () => {
		setAllOpen( false );
		setOpenOverrides( {} );
	}, [] );

	if ( abilities.length === 0 ) {
		return <p>{ __( 'No abilities registered.', 'superdav-ai-agent' ) }</p>;
	}

	const isFiltering = search || categoryFilter;

	return (
		<div className="sdaa-abilities-manager">
			{ /* Toolbar: search + category filter + expand/collapse */ }
			<div className="sdaa-abilities-toolbar">
				<div className="sdaa-abilities-search">
					<SearchControl
						label={ __( 'Search abilities', 'superdav-ai-agent' ) }
						value={ search }
						onChange={ handleSearchChange }
						placeholder={ __(
							'Search by name or description…',
							'superdav-ai-agent'
						) }
					/>
				</div>
				<div className="sdaa-abilities-filters">
					<SelectControl
						label={ __( 'Category', 'superdav-ai-agent' ) }
						value={ categoryFilter }
						options={ categoryOptions }
						onChange={ setCategoryFilter }
						__nextHasNoMarginBottom
					/>
					<div className="sdaa-abilities-expand-buttons">
						<Button
							variant="tertiary"
							size="small"
							onClick={ handleExpandAll }
						>
							{ __( 'Expand all', 'superdav-ai-agent' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							onClick={ handleCollapseAll }
						>
							{ __( 'Collapse all', 'superdav-ai-agent' ) }
						</Button>
					</div>
				</div>
			</div>

			{ /* Result count */ }
			<p className="sdaa-abilities-count description">
				{ filtered.length === abilities.length
					? sprintf(
							/* translators: %d: total number of abilities */
							__( '%d abilities', 'superdav-ai-agent' ),
							abilities.length
					  )
					: sprintf(
							/* translators: 1: filtered count, 2: total count */
							__(
								'Showing %1$d of %2$d abilities',
								'superdav-ai-agent'
							),
							filtered.length,
							abilities.length
					  ) }
			</p>

			{ /* No results */ }
			{ filtered.length === 0 && (
				<p className="description">
					{ __(
						'No abilities match your search.',
						'superdav-ai-agent'
					) }
				</p>
			) }

			{ /* Category sections */ }
			<div className="sdaa-abilities-sections">
				{ grouped.map( ( [ category, categoryAbilities ] ) => (
					<AbilityCategorySection
						key={ category }
						category={ category }
						abilities={ categoryAbilities }
						toolPermissions={ toolPermissions }
						onPermChange={ onPermChange }
						isFiltering={ Boolean( isFiltering ) }
						defaultOpen={
							isFiltering ||
							( openOverrides[ category ] !== undefined
								? openOverrides[ category ]
								: allOpen )
						}
					/>
				) ) }
			</div>
		</div>
	);
}
