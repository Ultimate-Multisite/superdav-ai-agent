/**
 * Internal dependencies
 */
import SettingsApp from '../../settings-page/settings-app';

/**
 * Settings Route Component
 *
 * Thin wrapper around SettingsApp — the outer General/Providers/Advanced tab
 * set was redundant with the inner tab bar and has been removed. Provider API
 * keys are now configured from the network-level Connectors page (WP Multisite
 * WaaS 7+), so there is no in-app Providers tab either.
 *
 * @return {JSX.Element} Settings route element.
 */
export default function SettingsRoute() {
	return (
		<div className="sd-ai-agent-route sd-ai-agent-route-settings">
			<SettingsApp />
		</div>
	);
}
