/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Format a cost value as a human-readable USD string.
 *
 * @param {number} cost Cost in USD.
 * @return {string} Formatted string, e.g. "$2.34" or "$0.0012".
 */
function formatCost( cost ) {
	const num = parseFloat( cost ) || 0;
	if ( num < 0.01 ) {
		return '$' + num.toFixed( 4 );
	}
	return '$' + num.toFixed( 2 );
}

/**
 * Resolve a tooltip string for the given warning level.
 *
 * @param {string} warningLevel One of 'ok', 'warning', 'exceeded'.
 * @return {string} Tooltip text.
 */
function getTooltip( warningLevel ) {
	if ( warningLevel === 'exceeded' ) {
		return __(
			'Budget exceeded — new requests are blocked.',
			'gratis-ai-agent'
		);
	}
	if ( warningLevel === 'warning' ) {
		return __( 'Approaching budget limit.', 'gratis-ai-agent' );
	}
	return __( 'Budget usage', 'gratis-ai-agent' );
}

/**
 * Budget indicator component.
 *
 * Displays current daily spend vs cap in the chat header.
 * Shows a warning banner when approaching the threshold and a red
 * indicator when the budget is exceeded.
 *
 * @return {JSX.Element|null} The budget indicator element, or null when no cap is set.
 */
export default function BudgetIndicator() {
	const [ status, setStatus ] = useState( null );

	useEffect( () => {
		let cancelled = false;

		const fetchStatus = () => {
			apiFetch( { path: '/gratis-ai-agent/v1/budget' } )
				.then( ( data ) => {
					if ( ! cancelled ) {
						setStatus( data );
					}
				} )
				.catch( () => {} );
		};

		fetchStatus();

		// Refresh every 5 minutes to stay in sync with the server cache.
		const interval = setInterval( fetchStatus, 5 * 60 * 1000 );

		return () => {
			cancelled = true;
			clearInterval( interval );
		};
	}, [] );

	if ( ! status ) {
		return null;
	}

	// Destructure with camelCase aliases to satisfy the camelcase lint rule.
	const {
		daily_spend: dailySpend,
		monthly_spend: monthlySpend,
		daily_cap: dailyCap,
		monthly_cap: monthlyCap,
		warning_level: warningLevel,
	} = status;

	// No caps configured — nothing to show.
	if (
		( ! dailyCap || dailyCap <= 0 ) &&
		( ! monthlyCap || monthlyCap <= 0 )
	) {
		return null;
	}

	// Prefer daily cap display; fall back to monthly.
	const hasDailyCap = dailyCap > 0;
	const spend = hasDailyCap ? dailySpend : monthlySpend;
	const cap = hasDailyCap ? dailyCap : monthlyCap;
	const label = hasDailyCap
		? __( 'today', 'gratis-ai-agent' )
		: __( 'this month', 'gratis-ai-agent' );

	const classMap = {
		ok: 'ai-agent-budget-indicator--ok',
		warning: 'ai-agent-budget-indicator--warning',
		exceeded: 'ai-agent-budget-indicator--exceeded',
	};

	const levelClass = classMap[ warningLevel ] || classMap.ok;

	return (
		<span
			className={ `ai-agent-budget-indicator ${ levelClass }` }
			title={ getTooltip( warningLevel ) }
		>
			{ formatCost( spend ) } / { formatCost( cap ) } { label }
		</span>
	);
}
