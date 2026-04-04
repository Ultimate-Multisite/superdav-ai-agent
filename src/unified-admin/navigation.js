/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Navigation Component
 *
 * Sidebar navigation for the unified admin.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.currentRoute Current route.
 * @param {Function} props.onNavigate   Navigation callback.
 * @return {JSX.Element} Navigation element.
 */
export default function Navigation( { currentRoute, onNavigate } ) {
	const menuItems = window.gratisAiAgentData?.menuItems || [];
	// Normalize empty/null route to 'chat' so the Chat item is highlighted on initial load.
	const baseRoute = ( currentRoute || '' ).split( '/' )[ 0 ] || 'chat';

	return (
		<nav
			className="gratis-ai-admin-nav"
			aria-label={ __( 'AI Agent Navigation', 'gratis-ai-agent' ) }
		>
			<div className="gratis-ai-agent-nav-header">
				<span className="gratis-ai-agent-nav-logo">
					<span className="dashicons dashicons-robot"></span>
				</span>
				<h1>{ __( 'AI Agent', 'gratis-ai-agent' ) }</h1>
			</div>

			<ul className="gratis-ai-agent-nav-menu" role="menubar">
				{ menuItems.map( ( item ) => (
					<li
						key={ item.slug }
						className={ `gratis-ai-agent-nav-item${
							baseRoute === item.slug ? ' is-active' : ''
						}` }
						role="none"
					>
						<Button
							className="gratis-ai-agent-nav-link"
							onClick={ () => onNavigate( item.slug ) }
							aria-current={
								baseRoute === item.slug ? 'page' : undefined
							}
							role="menuitem"
						>
							<span
								className={ `dashicons ${ item.icon }` }
							></span>
							<span className="gratis-ai-agent-nav-label">
								{ item.label }
							</span>
						</Button>
					</li>
				) ) }
			</ul>
		</nav>
	);
}
