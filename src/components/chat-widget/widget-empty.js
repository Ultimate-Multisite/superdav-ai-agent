/**
 * Compact empty state — agent-aware greeting + suggestion cards that
 * seed common first-turn prompts. Dispatches sendMessage on pick.
 *
 * When a selected agent provides a suggestions array, that choice is respected,
 * including an explicitly empty array. The greeting text comes from the agent's
 * greeting field, falling back to branding or the generic default.
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import STORE_NAME from '../../store';
import { getBranding } from '../../utils/branding';

/**
 * Default suggestions used when no agent-specific suggestions array is available.
 */
const DEFAULT_SUGGESTIONS = [
	{
		title: __( 'Site health check', 'superdav-ai-agent' ),
		description: __(
			'Run a full report and summarize issues',
			'superdav-ai-agent'
		),
		prompt: __(
			'Run a site health check and summarize the issues you find.',
			'superdav-ai-agent'
		),
	},
	{
		title: __( 'Draft a blog post', 'superdav-ai-agent' ),
		description: __(
			"Pick a topic and I'll set it up",
			'superdav-ai-agent'
		),
		prompt: __(
			'Help me draft a new blog post - suggest a topic, then create a draft.',
			'superdav-ai-agent'
		),
	},
	{
		title: __( 'Review installed plugins', 'superdav-ai-agent' ),
		description: __( 'Find unused or outdated ones', 'superdav-ai-agent' ),
		prompt: __(
			'Review my installed plugins. Flag any that are unused or outdated.',
			'superdav-ai-agent'
		),
	},
	{
		title: __( 'List recent signups', 'superdav-ai-agent' ),
		description: __( 'Last 7 days, grouped by role', 'superdav-ai-agent' ),
		prompt: __(
			'List users who signed up in the last 7 days, grouped by role.',
			'superdav-ai-agent'
		),
	},
];

/**
 *
 */
export default function WidgetEmpty() {
	const { sendMessage } = useDispatch( STORE_NAME );
	const branding = getBranding();

	const selectedAgent = useSelect( ( select ) => {
		return select( STORE_NAME ).getSelectedAgent();
	}, [] );

	// Agent-aware greeting.
	const greeting =
		selectedAgent?.greeting ||
		branding.greeting ||
		__( 'What can I help you with?', 'superdav-ai-agent' );

	// Agent-aware suggestions.
	const agentSuggestions = selectedAgent?.suggestions;
	const suggestions = Array.isArray( agentSuggestions )
		? agentSuggestions
		: DEFAULT_SUGGESTIONS;

	// Agent name for the footer.
	const agentName =
		selectedAgent?.name ||
		branding.agentName ||
		__( 'SD AI Agent', 'superdav-ai-agent' );

	return (
		<div className="sdaa-w-empty">
			<h2 className="sdaa-w-empty-greeting">{ greeting }</h2>
			<p className="sdaa-w-empty-sub">
				{ selectedAgent?.description ||
					branding.tagline ||
					__(
						'I can manage content, products, users, SEO and more - across every plugin on your site.',
						'superdav-ai-agent'
					) }
			</p>
			{ suggestions.length > 0 && (
				<>
					<div className="sdaa-w-empty-label">
						{ __( 'Suggested', 'superdav-ai-agent' ) }
					</div>
					<div className="sdaa-w-suggestion-list">
						{ suggestions.map( ( s, i ) => (
							<button
								key={ i }
								type="button"
								className="sdaa-w-suggestion-card"
								onClick={ () => sendMessage( s.prompt, [] ) }
								aria-label={ s.title }
							>
								<span className="sdaa-w-suggestion-card-body">
									<span className="sdaa-w-suggestion-card-title">
										{ s.title }
									</span>
									<span className="sdaa-w-suggestion-card-sub">
										{ s.description }
									</span>
								</span>
							</button>
						) ) }
					</div>
				</>
			) }
			<p className="sdaa-w-empty-foot">{ agentName }</p>
		</div>
	);
}
