/**
 * WordPress dependencies
 */
import { useMemo } from '@wordpress/element';

/**
 * External dependencies
 */
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

/**
 * Internal dependencies
 */
import CodeBlock from './code-block';

/**
 * Custom renderers for ReactMarkdown.
 */
const components = {
	code( { inline, className, children, ...props } ) {
		const match = /language-(\w+)/.exec( className || '' );
		if ( ! inline && match ) {
			return <CodeBlock language={ match[ 1 ] }>{ children }</CodeBlock>;
		}
		if ( ! inline && String( children ).includes( '\n' ) ) {
			return <CodeBlock>{ children }</CodeBlock>;
		}
		return (
			<code className={ className } { ...props }>
				{ children }
			</code>
		);
	},
	a( { href, children, ...props } ) {
		return (
			<a
				href={ href }
				target="_blank"
				rel="noopener noreferrer"
				{ ...props }
			>
				{ children }
			</a>
		);
	},
	table( { children, ...props } ) {
		return (
			<div className="gratis-ai-agent-table-wrap">
				<table { ...props }>{ children }</table>
			</div>
		);
	},
	// Prevent wrapping image in paragraph.
	img( { src, alt, ...props } ) {
		return (
			<img src={ src } alt={ alt || '' } loading="lazy" { ...props } />
		);
	},
};

const remarkPlugins = [ remarkGfm ];

/**
 * Renders markdown content using ReactMarkdown with GFM support.
 *
 * Custom renderers:
 * - `code`: delegates to CodeBlock for fenced code blocks.
 * - `a`: opens links in a new tab with rel="noopener noreferrer".
 * - `table`: wraps in a scrollable div.
 * - `img`: adds lazy loading.
 *
 * @param {Object} props         - Component props.
 * @param {string} props.content - Markdown string to render.
 * @return {JSX.Element} The rendered markdown element.
 */
export default function MarkdownMessage( { content } ) {
	const memoizedContent = useMemo( () => content, [ content ] );

	return (
		<ReactMarkdown
			remarkPlugins={ remarkPlugins }
			components={ components }
		>
			{ memoizedContent }
		</ReactMarkdown>
	);
}
