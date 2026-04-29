/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ChangesApp from '../../changes-page/changes-app';
import '../../changes-page/style.css';

/**
 * Changes Route Component
 *
 * Renders the full ChangesApp (table, filters, diff modal, revert, export)
 * inside the unified admin router. The standalone changes-page/index.js entry
 * point is retained for backwards compatibility but this route is the live path.
 *
 * @return {JSX.Element} Changes route element.
 */
export default function ChangesRoute() {
	return (
		<div className="sd-ai-agent-route sd-ai-agent-route-changes">
			<h2>{ __( 'Changes', 'sd-ai-agent' ) }</h2>
			<ChangesApp />
		</div>
	);
}
