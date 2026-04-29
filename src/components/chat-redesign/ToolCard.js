/**
 * Tool call card — collapsible, status chip + name + summary,
 * expanded body shows arguments and result with optional revert strip.
 *
 * Adapts the design spec to the store's `{ type: 'call', ... }` +
 * `{ type: 'result', ... }` pair shape.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, check, chevronDown, undo, caution } from '@wordpress/icons';

/**
 *
 * @param {*} name
 */
function formatName( name ) {
	let display = name || '';
	if ( display.startsWith( 'wpab__' ) ) {
		display = display.substring( 6 );
	}
	return display.replace( /__/g, '/' );
}

/**
 *
 * @param {*} value
 */
function formatValue( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	if ( typeof value === 'string' ) {
		return value;
	}
	try {
		return JSON.stringify( value, null, 2 );
	} catch {
		return String( value );
	}
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.status
 */
function StatusChip( { status } ) {
	if ( status === 'running' ) {
		return (
			<span className="gaa-cr-tool-status is-running">
				<span className="gaa-cr-tool-spin" />
			</span>
		);
	}
	if ( status === 'error' ) {
		return (
			<span className="gaa-cr-tool-status is-error">
				<span
					style={ { fontWeight: 700, fontSize: 11, lineHeight: 1 } }
				>
					!
				</span>
			</span>
		);
	}
	if ( status === 'warn' ) {
		return (
			<span className="gaa-cr-tool-status is-warn">
				<span
					style={ { fontWeight: 700, fontSize: 11, lineHeight: 1 } }
				>
					!
				</span>
			</span>
		);
	}
	return (
		<span className="gaa-cr-tool-status is-ok">
			<Icon icon={ check } size={ 12 } />
		</span>
	);
}

/**
 *
 * @param {*} call
 * @param {*} response
 */
function deriveStatus( call, response ) {
	if ( ! response ) {
		return 'running';
	}
	const r = response.response;
	if ( r && typeof r === 'object' ) {
		if ( r.success === false || r.error ) {
			return 'error';
		}
		if ( r.warning ) {
			return 'warn';
		}
	}
	return 'ok';
}

/**
 *
 * @param {*} call
 * @param {*} response
 */
function deriveSummary( call, response ) {
	if ( ! response ) {
		return '';
	}
	const r = response.response;
	if ( r && typeof r === 'object' ) {
		if ( r.summary ) {
			return r.summary;
		}
		if ( r.title ) {
			return r.title;
		}
		if ( r.id && r.status ) {
			return `ID ${ r.id } · ${ r.status }`;
		}
	}
	if ( typeof r === 'string' && r.length < 120 ) {
		return r;
	}
	return '';
}

/**
 *
 * @param {*} call
 */
function isMutating( call ) {
	// Heuristic: any tool whose name hints at creating/updating/deleting
	// is considered mutating. The store doesn't carry this flag natively.
	const n = ( call.name || '' ).toLowerCase();
	return /create|update|delete|install|activate|deactivate|switch|set|move|publish|draft|revert|bulk|import/.test(
		n
	);
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.call
 * @param {*}      root0.response
 * @param {*}      root0.defaultOpen
 * @param {*}      root0.onRevert
 */
export default function ToolCard( {
	call,
	response,
	defaultOpen = false,
	onRevert,
} ) {
	const status = deriveStatus( call, response );
	const [ open, setOpen ] = useState( defaultOpen );

	const name = formatName( call.name );
	const summary = deriveSummary( call, response );
	const args = formatValue( call.args );
	const result = response ? formatValue( response.response ) : '';
	const mutating = isMutating( call );

	return (
		<div
			className={ `gaa-cr-tool-card${ open ? ' is-open' : '' }${
				status === 'running' ? ' is-running' : ''
			}${ status === 'error' ? ' is-error' : '' }` }
		>
			<button
				type="button"
				className="gaa-cr-tool-card-head"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
			>
				<StatusChip status={ status } />
				<div className="gaa-cr-tool-card-name">
					<code>{ name }</code>
					{ summary && (
						<span className="gaa-cr-tool-card-summary">
							{ summary }
						</span>
					) }
				</div>
				<div
					className={ `gaa-cr-tool-card-meta${
						status === 'running' ? ' is-running' : ''
					}` }
				>
					{ status === 'running' && (
						<span>{ __( 'Running…', 'sd-ai-agent' ) }</span>
					) }
					<span className="gaa-cr-tool-card-chevron">
						<Icon icon={ chevronDown } size={ 16 } />
					</span>
				</div>
			</button>
			{ open && (
				<div className="gaa-cr-tool-card-body">
					{ args && args !== '{}' && args !== 'null' && (
						<div>
							<div className="gaa-cr-tool-detail-label">
								{ __( 'Arguments', 'sd-ai-agent' ) }
							</div>
							<pre className="gaa-cr-tool-detail-value">
								{ args }
							</pre>
						</div>
					) }
					{ result && (
						<div>
							<div className="gaa-cr-tool-detail-label">
								{ __( 'Result', 'sd-ai-agent' ) }
							</div>
							<pre className="gaa-cr-tool-detail-value">
								{ result }
							</pre>
						</div>
					) }
					{ mutating && response && (
						<div className="gaa-cr-tool-detail-revert">
							<span className="gaa-cr-tool-detail-revert-note">
								<Icon icon={ caution } size={ 14 } />
								{ __(
									'This action modified your site.',
									'sd-ai-agent'
								) }
							</span>
							{ onRevert && (
								<button
									type="button"
									className="gaa-cr-btn-sm"
									onClick={ onRevert }
								>
									<Icon icon={ undo } size={ 12 } />
									{ __( 'Revert this', 'sd-ai-agent' ) }
								</button>
							) }
						</div>
					) }
				</div>
			) }
		</div>
	);
}
