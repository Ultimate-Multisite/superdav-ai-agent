/**
 * Screen-meta Help tab entry point.
 *
 * Mounts a compact AI Agent chat panel inside the WordPress admin Help tab
 * (the ? icon in the top-right corner). The panel is context-aware: it reads
 * the current screen data injected by PHP (gratisAiAgentScreenMeta.screenContext)
 * and passes it to the AI via the store's setPageContext action.
 */

/**
 * WordPress dependencies
 */
import { createRoot, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
// Register gratis-ai-agent-js/* client-side abilities into core/abilities
// before the chat mounts (t165 — closes the wiring gap in #815).
import '../abilities';
import ChatPanel from '../components/ChatPanel';
import '../components/shared.css';
import './style.css';

/**
 * Build a human-readable context string from the screen context object
 * injected by PHP (gratisAiAgentScreenMeta.screenContext).
 *
 * @param {Object} screenContext Screen context from PHP.
 * @return {string} Formatted context string for the AI.
 */
function buildContextString( screenContext ) {
	if ( ! screenContext ) {
		return '';
	}

	const parts = [];

	if ( screenContext.screen_id ) {
		parts.push( `screen:${ screenContext.screen_id }` );
	}
	if ( screenContext.base ) {
		parts.push( `base:${ screenContext.base }` );
	}
	if ( screenContext.post_type ) {
		parts.push( `post_type:${ screenContext.post_type }` );
	}
	if ( screenContext.taxonomy ) {
		parts.push( `taxonomy:${ screenContext.taxonomy }` );
	}
	if ( screenContext.url ) {
		parts.push( `url:${ screenContext.url }` );
	}

	return parts.join( ' | ' );
}

/**
 * ScreenMetaChat component.
 *
 * Renders a compact chat panel and sets the page context from the
 * screen data provided by PHP on mount.
 */
function ScreenMetaChat() {
	const { setPageContext, fetchProviders, fetchSessions } =
		useDispatch( STORE_NAME );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
	}, [ fetchProviders, fetchSessions ] );

	// Set context from PHP-injected screen data on mount.
	useEffect( () => {
		const screenMeta = window.gratisAiAgentScreenMeta;
		if ( screenMeta && screenMeta.screenContext ) {
			const contextString = buildContextString(
				screenMeta.screenContext
			);
			if ( contextString ) {
				setPageContext( contextString );
			}
		}
	}, [ setPageContext ] );

	return (
		<div className="gratis-ai-agent-screen-meta-wrap">
			<p className="gratis-ai-agent-screen-meta-intro">
				{ __(
					'Ask the AI Agent about this screen or any WordPress task.',
					'gratis-ai-agent'
				) }
			</p>
			<ChatPanel compact />
		</div>
	);
}

/**
 * Mount the screen-meta chat panel into the Help tab container.
 *
 * WordPress renders the Help tab content lazily when the user first
 * opens the tab. We render immediately — React handles invisible
 * containers fine and the tab content is revealed by WP JS.
 *
 * @param {string} mountId The DOM element ID to mount the React app into.
 */
function mountWhenReady( mountId ) {
	const mountPoint = document.getElementById( mountId );
	if ( ! mountPoint ) {
		return;
	}

	// The Help tab panel is hidden by default (display:none on the parent
	// .contextual-help-tabs-wrap). Render immediately — React handles
	// invisible containers fine and the tab content is revealed by WP JS.
	const root = createRoot( mountPoint );
	root.render( <ScreenMetaChat /> );
}

// Wait for DOM ready before mounting.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		const mountId =
			window.gratisAiAgentScreenMeta?.mountId ??
			'gratis-ai-agent-screen-meta-root';
		mountWhenReady( mountId );
	} );
} else {
	const mountId =
		window.gratisAiAgentScreenMeta?.mountId ??
		'gratis-ai-agent-screen-meta-root';
	mountWhenReady( mountId );
}
