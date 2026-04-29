/**
 * WordPress dependencies
 */
import { useState, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Extracts plain text from a React node (handles strings and nested elements).
 *
 * @param {*} node - React node to extract text from.
 * @return {string} Plain text content.
 */
function nodeToText( node ) {
	if ( node === null || node === undefined ) {
		return '';
	}
	if ( typeof node === 'string' || typeof node === 'number' ) {
		return String( node );
	}
	if ( Array.isArray( node ) ) {
		return node.map( nodeToText ).join( '' );
	}
	if ( node.props && node.props.children ) {
		return nodeToText( node.props.children );
	}
	return '';
}

/**
 * Extracts header labels from a <thead> React element.
 *
 * @param {*} thead - React element representing <thead>.
 * @return {string[]} Array of header label strings.
 */
function extractHeaders( thead ) {
	if ( ! thead ) {
		return [];
	}
	const rows = [].concat( thead.props?.children ?? [] );
	const firstRow = rows[ 0 ];
	if ( ! firstRow ) {
		return [];
	}
	const cells = [].concat( firstRow.props?.children ?? [] );
	return cells.map( ( cell ) => nodeToText( cell?.props?.children ) );
}

/**
 * Extracts row data from a <tbody> React element.
 * Returns an array of arrays (rows × cells), each cell as { text, node }.
 *
 * @param {*} tbody - React element representing <tbody>.
 * @return {Array<Array<{text: string, node: *}>>} Parsed row data.
 */
function extractRows( tbody ) {
	if ( ! tbody ) {
		return [];
	}
	const rows = [].concat( tbody.props?.children ?? [] );
	return rows.map( ( row ) => {
		const cells = [].concat( row?.props?.children ?? [] );
		return cells.map( ( cell ) => ( {
			text: nodeToText( cell?.props?.children ),
			node: cell?.props?.children,
		} ) );
	} );
}

/**
 * Renders a sortable, filterable data table from GFM markdown table children.
 *
 * Accepts the same children that ReactMarkdown passes to a custom `table`
 * renderer: a `<thead>` and a `<tbody>` as React elements.
 *
 * @param {Object} props          - Component props.
 * @param {*}      props.children - React children (thead + tbody elements).
 * @return {JSX.Element} Interactive data table.
 */
export default function DataTable( { children } ) {
	const [ sortCol, setSortCol ] = useState( null );
	const [ sortDir, setSortDir ] = useState( 'asc' );
	const [ filter, setFilter ] = useState( '' );

	// Separate thead and tbody from children.
	const childArray = [].concat( children ?? [] );
	const thead = childArray.find(
		( c ) => c?.type === 'thead' || c?.props?.originalType === 'thead'
	);
	const tbody = childArray.find(
		( c ) => c?.type === 'tbody' || c?.props?.originalType === 'tbody'
	);

	const headers = useMemo( () => extractHeaders( thead ), [ thead ] );
	const rawRows = useMemo( () => extractRows( tbody ), [ tbody ] );

	// Filter rows by the search term across all cells.
	const filteredRows = useMemo( () => {
		if ( ! filter.trim() ) {
			return rawRows;
		}
		const term = filter.toLowerCase();
		return rawRows.filter( ( row ) =>
			row.some( ( cell ) => cell.text.toLowerCase().includes( term ) )
		);
	}, [ rawRows, filter ] );

	// Sort filtered rows by the selected column.
	const sortedRows = useMemo( () => {
		if ( sortCol === null ) {
			return filteredRows;
		}
		return [ ...filteredRows ].sort( ( a, b ) => {
			const aVal = a[ sortCol ]?.text ?? '';
			const bVal = b[ sortCol ]?.text ?? '';
			// Numeric sort when both values parse as numbers.
			const aNum = parseFloat( aVal );
			const bNum = parseFloat( bVal );
			if ( ! isNaN( aNum ) && ! isNaN( bNum ) ) {
				return sortDir === 'asc' ? aNum - bNum : bNum - aNum;
			}
			return sortDir === 'asc'
				? aVal.localeCompare( bVal )
				: bVal.localeCompare( aVal );
		} );
	}, [ filteredRows, sortCol, sortDir ] );

	const handleSort = useCallback(
		( colIndex ) => {
			if ( sortCol === colIndex ) {
				setSortDir( ( d ) => ( d === 'asc' ? 'desc' : 'asc' ) );
			} else {
				setSortCol( colIndex );
				setSortDir( 'asc' );
			}
		},
		[ sortCol ]
	);

	const handleFilterChange = useCallback( ( e ) => {
		setFilter( e.target.value );
	}, [] );

	const handleFilterClear = useCallback( () => {
		setFilter( '' );
	}, [] );

	const sortIndicator = ( colIndex ) => {
		if ( sortCol !== colIndex ) {
			return (
				<span className="sd-ai-agent-table-sort-icon sd-ai-agent-table-sort-none">
					⇅
				</span>
			);
		}
		return (
			<span className="sd-ai-agent-table-sort-icon sd-ai-agent-table-sort-active">
				{ sortDir === 'asc' ? '↑' : '↓' }
			</span>
		);
	};

	return (
		<div className="sd-ai-agent-data-table-wrap">
			{ /* Filter bar — only shown when the table has more than 10 rows */ }
			{ rawRows.length > 10 && (
				<div className="sd-ai-agent-data-table-toolbar">
					<div className="sd-ai-agent-data-table-filter">
						<input
							type="search"
							className="sd-ai-agent-data-table-filter-input"
							placeholder={ __( 'Filter…', 'sd-ai-agent' ) }
							value={ filter }
							onChange={ handleFilterChange }
							aria-label={ __(
								'Filter table rows',
								'sd-ai-agent'
							) }
						/>
						{ filter && (
							<button
								type="button"
								className="sd-ai-agent-data-table-filter-clear"
								onClick={ handleFilterClear }
								aria-label={ __(
									'Clear filter',
									'sd-ai-agent'
								) }
							>
								✕
							</button>
						) }
					</div>
					{ filter && (
						<span className="sd-ai-agent-data-table-count">
							{ filteredRows.length } / { rawRows.length }
						</span>
					) }
				</div>
			) }

			<div className="sd-ai-agent-data-table-scroll">
				<table className="sd-ai-agent-data-table">
					{ headers.length > 0 && (
						<thead>
							<tr>
								{ headers.map( ( header, i ) => (
									<th
										key={ i }
										className={
											sortCol === i
												? 'sd-ai-agent-data-table-th is-sorted'
												: 'sd-ai-agent-data-table-th'
										}
										onClick={ () => handleSort( i ) }
										aria-sort={ ( () => {
											if ( sortCol !== i ) {
												return 'none';
											}
											return sortDir === 'asc'
												? 'ascending'
												: 'descending';
										} )() }
										role="columnheader"
										tabIndex={ 0 }
										onKeyDown={ ( e ) => {
											if (
												e.key === 'Enter' ||
												e.key === ' '
											) {
												e.preventDefault();
												handleSort( i );
											}
										} }
									>
										<span className="sd-ai-agent-data-table-th-label">
											{ header }
										</span>
										{ sortIndicator( i ) }
									</th>
								) ) }
							</tr>
						</thead>
					) }
					<tbody>
						{ sortedRows.length > 0 ? (
							sortedRows.map( ( row, rowIdx ) => (
								<tr key={ rowIdx }>
									{ row.map( ( cell, cellIdx ) => (
										<td
											key={ cellIdx }
											className="sd-ai-agent-data-table-td"
										>
											{ cell.node }
										</td>
									) ) }
								</tr>
							) )
						) : (
							<tr>
								<td
									colSpan={ headers.length || 1 }
									className="sd-ai-agent-data-table-empty"
								>
									{ __( 'No matching rows.', 'sd-ai-agent' ) }
								</td>
							</tr>
						) }
					</tbody>
				</table>
			</div>
		</div>
	);
}
