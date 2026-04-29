/**
 * Internal dependencies
 */
import AbilitiesExplorerApp from '../../abilities-explorer/abilities-explorer-app';
import '../../abilities-explorer/style.css';

/**
 * Abilities Route Component
 *
 * Renders the Abilities Explorer within the unified admin SPA.
 *
 * @return {JSX.Element} Abilities route element.
 */
export default function AbilitiesRoute() {
	return (
		<div className="sd-ai-agent-route sd-ai-agent-route-abilities">
			<AbilitiesExplorerApp />
		</div>
	);
}
