/**
 * Alerts polling for the floating widget.
 *
 * The /alerts endpoint is admin-only, so users without the capability
 * (see `canViewAlerts` in sdAiAgentData) must not trigger any request.
 */

export const ALERTS_POLL_INTERVAL_MS = 5 * 60 * 1000;

/**
 * Start polling alerts when the user is allowed to view them.
 *
 * @param {Function} fetchAlerts   Fetch action.
 * @param {boolean}  canViewAlerts Whether the user may view alerts.
 * @return {Function} Cleanup function.
 */
export function startAlertsPolling( fetchAlerts, canViewAlerts ) {
	if ( ! canViewAlerts ) {
		return () => {};
	}
	fetchAlerts();
	const interval = setInterval( fetchAlerts, ALERTS_POLL_INTERVAL_MS );
	return () => clearInterval( interval );
}
