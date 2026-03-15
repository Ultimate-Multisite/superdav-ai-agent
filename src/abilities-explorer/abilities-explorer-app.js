/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	SearchControl,
	SelectControl,
	Spinner,
	Badge,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Annotation badge component.
 *
 * @param {Object}  props
 * @param {string}  props.label  Badge label.
 * @param {boolean} props.active Whether the annotation is active.
 * @param {string}  props.intent Badge intent when active.
 */
function AnnotationBadge( { label, active, intent } ) {
	if ( ! active ) {
		return null;
	}
	return (
		<Badge intent={ intent } className="ai-agent-abilities-explorer__badge">
			{ label }
		</Badge>
	);
}

/**
 * Single ability row.
 *
 * @param {Object} props
 * @param {Object} props.ability Ability data object.
 */
function AbilityRow( { ability } ) {
	const [ expanded, setExpanded ] = useState( false );
	const { annotations = {}, output_schema: outputSchema } = ability;

	return (
		<div className="ai-agent-abilities-explorer__row">
			<div
				className="ai-agent-abilities-explorer__row-header"
				role="button"
				tabIndex={ 0 }
				onClick={ () => setExpanded( ( prev ) => ! prev ) }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						setExpanded( ( prev ) => ! prev );
					}
				} }
				aria-expanded={ expanded }
			>
				<div className="ai-agent-abilities-explorer__row-title">
					<code className="ai-agent-abilities-explorer__name">
						{ ability.name }
					</code>
					{ ability.label && ability.label !== ability.name && (
						<span className="ai-agent-abilities-explorer__label">
							{ ability.label }
						</span>
					) }
				</div>
				<div className="ai-agent-abilities-explorer__row-meta">
					<span className="ai-agent-abilities-explorer__category">
						{ ability.category ||
							__( 'uncategorised', 'ai-agent' ) }
					</span>
					<AnnotationBadge
						label={ __( 'readonly', 'ai-agent' ) }
						active={ annotations.readonly }
						intent="info"
					/>
					<AnnotationBadge
						label={ __( 'idempotent', 'ai-agent' ) }
						active={ annotations.idempotent }
						intent="success"
					/>
					<AnnotationBadge
						label={ __( 'destructive', 'ai-agent' ) }
						active={ annotations.destructive }
						intent="warning"
					/>
					{ ability.show_in_rest && (
						<Badge
							intent="default"
							className="ai-agent-abilities-explorer__badge"
						>
							{ __( 'REST', 'ai-agent' ) }
						</Badge>
					) }
					<span
						className="ai-agent-abilities-explorer__toggle"
						aria-hidden="true"
					>
						{ expanded ? '▲' : '▼' }
					</span>
				</div>
			</div>

			{ ability.description && (
				<p className="ai-agent-abilities-explorer__description">
					{ ability.description }
				</p>
			) }

			{ expanded && (
				<div className="ai-agent-abilities-explorer__details">
					<div className="ai-agent-abilities-explorer__detail-section">
						<h4>{ __( 'Annotations', 'ai-agent' ) }</h4>
						<table className="ai-agent-abilities-explorer__annotations-table">
							<tbody>
								<tr>
									<th>{ __( 'readonly', 'ai-agent' ) }</th>
									<td>
										{ annotations.readonly
											? __( 'Yes', 'ai-agent' )
											: __( 'No', 'ai-agent' ) }
									</td>
								</tr>
								<tr>
									<th>{ __( 'idempotent', 'ai-agent' ) }</th>
									<td>
										{ annotations.idempotent
											? __( 'Yes', 'ai-agent' )
											: __( 'No', 'ai-agent' ) }
									</td>
								</tr>
								<tr>
									<th>{ __( 'destructive', 'ai-agent' ) }</th>
									<td>
										{ annotations.destructive
											? __( 'Yes', 'ai-agent' )
											: __( 'No', 'ai-agent' ) }
									</td>
								</tr>
								<tr>
									<th>
										{ __( 'show_in_rest', 'ai-agent' ) }
									</th>
									<td>
										{ ability.show_in_rest
											? __( 'Yes', 'ai-agent' )
											: __( 'No', 'ai-agent' ) }
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					{ outputSchema && (
						<div className="ai-agent-abilities-explorer__detail-section">
							<h4>{ __( 'Output Schema', 'ai-agent' ) }</h4>
							<pre className="ai-agent-abilities-explorer__schema">
								{ JSON.stringify( outputSchema, null, 2 ) }
							</pre>
						</div>
					) }
				</div>
			) }
		</div>
	);
}

/**
 * Main Abilities Explorer application.
 */
export default function AbilitiesExplorerApp() {
	const [ abilities, setAbilities ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );

	const fetchAbilities = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( {
				path: '/ai-agent/v1/abilities-explorer',
			} );
			setAbilities( Array.isArray( data ) ? data : [] );
		} catch ( err ) {
			setError(
				err?.message || __( 'Failed to load abilities.', 'ai-agent' )
			);
		}
		setLoading( false );
	}, [] );

	useEffect( () => {
		fetchAbilities();
	}, [ fetchAbilities ] );

	const categories = [
		{ label: __( 'All categories', 'ai-agent' ), value: '' },
		...[
			...new Set(
				abilities.map( ( a ) => a.category ).filter( Boolean )
			),
		]
			.sort()
			.map( ( cat ) => ( { label: cat, value: cat } ) ),
	];

	const filtered = abilities.filter( ( ability ) => {
		const matchesSearch =
			! search ||
			ability.name.toLowerCase().includes( search.toLowerCase() ) ||
			( ability.label || '' )
				.toLowerCase()
				.includes( search.toLowerCase() ) ||
			( ability.description || '' )
				.toLowerCase()
				.includes( search.toLowerCase() );

		const matchesCategory =
			! categoryFilter || ability.category === categoryFilter;

		return matchesSearch && matchesCategory;
	} );

	if ( loading ) {
		return (
			<div className="ai-agent-abilities-explorer__loading">
				<Spinner />
				<span>{ __( 'Loading abilities…', 'ai-agent' ) }</span>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="ai-agent-abilities-explorer__error">
				<p>{ error }</p>
			</div>
		);
	}

	return (
		<div className="ai-agent-abilities-explorer">
			<div className="ai-agent-abilities-explorer__toolbar">
				<SearchControl
					label={ __( 'Search abilities', 'ai-agent' ) }
					value={ search }
					onChange={ setSearch }
					className="ai-agent-abilities-explorer__search"
				/>
				<SelectControl
					label={ __( 'Category', 'ai-agent' ) }
					value={ categoryFilter }
					options={ categories }
					onChange={ setCategoryFilter }
					className="ai-agent-abilities-explorer__category-filter"
				/>
				<span className="ai-agent-abilities-explorer__count">
					{ filtered.length === abilities.length
						? /* translators: %d: number of abilities */
						  sprintf(
								/* translators: %d: number of abilities */
								__( '%d abilities', 'ai-agent' ),
								abilities.length
						  )
						: /* translators: 1: filtered count, 2: total count */
						  sprintf(
								/* translators: 1: filtered count, 2: total count */
								__( '%1$d of %2$d abilities', 'ai-agent' ),
								filtered.length,
								abilities.length
						  ) }
				</span>
			</div>

			{ filtered.length === 0 ? (
				<p className="ai-agent-abilities-explorer__empty">
					{ __( 'No abilities match your search.', 'ai-agent' ) }
				</p>
			) : (
				<div className="ai-agent-abilities-explorer__list">
					{ filtered.map( ( ability ) => (
						<AbilityRow key={ ability.name } ability={ ability } />
					) ) }
				</div>
			) }
		</div>
	);
}
