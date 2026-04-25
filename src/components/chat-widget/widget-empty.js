/**
 * Compact empty state — greeting + short sub + suggestion cards that
 * seed common first-turn prompts. Dispatches sendMessage on pick.
 */

import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, chartBar, post, plugins, people } from '@wordpress/icons';

import STORE_NAME from '../../store';
import { getBranding } from '../../utils/branding';

/**
 *
 */
export default function WidgetEmpty() {
	const { sendMessage } = useDispatch( STORE_NAME );
	const branding = getBranding();
	const agentName = branding.agentName || __( 'AI Agent', 'gratis-ai-agent' );

	const suggestions = [
		{
			icon: chartBar,
			title: __( 'Site health check', 'gratis-ai-agent' ),
			sub: __(
				'Run a full report and summarise issues',
				'gratis-ai-agent'
			),
			prompt: __(
				'Run a site health check and summarise the issues you find.',
				'gratis-ai-agent'
			),
		},
		{
			icon: post,
			title: __( 'Draft a blog post', 'gratis-ai-agent' ),
			sub: __( "Pick a topic and I'll set it up", 'gratis-ai-agent' ),
			prompt: __(
				'Help me draft a new blog post — suggest a topic, then create a draft.',
				'gratis-ai-agent'
			),
		},
		{
			icon: plugins,
			title: __( 'Review installed plugins', 'gratis-ai-agent' ),
			sub: __( 'Find unused or outdated ones', 'gratis-ai-agent' ),
			prompt: __(
				'Review my installed plugins. Flag any that are unused or outdated.',
				'gratis-ai-agent'
			),
		},
		{
			icon: people,
			title: __( 'List recent signups', 'gratis-ai-agent' ),
			sub: __( 'Last 7 days, grouped by role', 'gratis-ai-agent' ),
			prompt: __(
				'List users who signed up in the last 7 days, grouped by role.',
				'gratis-ai-agent'
			),
		},
	];

	return (
		<div className="gaa-w-empty">
			<h2 className="gaa-w-empty-greeting">
				{ __( 'What can I help you with?', 'gratis-ai-agent' ) }
			</h2>
			<p className="gaa-w-empty-sub">
				{ branding.tagline
					? branding.tagline
					: __(
							'I can manage content, products, users, SEO and more — across every plugin on your site.',
							'gratis-ai-agent'
					  ) }
			</p>
			<div className="gaa-w-empty-label">
				{ __( 'Suggested', 'gratis-ai-agent' ) }
			</div>
			<div className="gaa-w-suggestion-list">
				{ suggestions.map( ( s, i ) => (
					<button
						key={ i }
						type="button"
						className="gaa-w-suggestion-card"
						onClick={ () => sendMessage( s.prompt, [] ) }
						aria-label={ s.title }
					>
						<span className="gaa-w-suggestion-card-icon">
							<Icon icon={ s.icon } size={ 16 } />
						</span>
						<span className="gaa-w-suggestion-card-body">
							<span className="gaa-w-suggestion-card-title">
								{ s.title }
							</span>
							<span className="gaa-w-suggestion-card-sub">
								{ s.sub }
							</span>
						</span>
					</button>
				) ) }
			</div>
			<p className="gaa-w-empty-foot">{ agentName }</p>
		</div>
	);
}
