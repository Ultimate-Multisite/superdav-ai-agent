/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ChatRoute from './routes/chat';
import AbilitiesRoute from './routes/abilities';
import ChangesRoute from './routes/changes';
import SettingsRoute from './routes/settings';

/**
 * Router Component
 *
 * Renders the appropriate route based on the current hash path.
 *
 * @param {Object} props       Component props.
 * @param {string} props.route Current route.
 * @return {JSX.Element} Route component.
 */
export default function Router( { route } ) {
	const routeParts = ( route || '' ).split( '/' );
	const mainRoute = routeParts[ 0 ];
	const subRoute = routeParts.slice( 1 ).join( '/' ) || null;

	switch ( mainRoute ) {
		case 'chat':
		case '':
			return <ChatRoute />;

		case 'abilities':
			return <AbilitiesRoute />;

		case 'changes':
			return <ChangesRoute />;

		case 'settings':
			return <SettingsRoute subRoute={ subRoute } />;

		default:
			return (
				<div className="gratis-ai-agent-route-not-found">
					<h2>{ __( 'Page Not Found', 'gratis-ai-agent' ) }</h2>
					<p>
						{ __(
							'The requested page could not be found.',
							'gratis-ai-agent'
						) }
					</p>
				</div>
			);
	}
}
