/**
 * WordPress dependencies
 */
import { useMemo, lazy, Suspense } from '@wordpress/element';

/**
 * External dependencies
 */
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

/**
 * Internal dependencies
 */
import DataTable from './data-table';

/**
 * CodeBlock and ChartBlock are lazy-loaded so their heavy dependencies
 * (CodeMirror ~800 KB, Chart.js ~220 KB) are bundled into separate async
 * chunks that are only downloaded the first time the AI returns a fenced
 * code block. webpackPrefetch causes the browser to fetch the chunks in
 * the background during idle time so the download is usually already
 * complete by the time a code block first appears.
 */
const CodeBlock = lazy( () =>
	import(
		/* webpackChunkName: "code-block", webpackPrefetch: true */
		'./code-block'
	)
);
const ChartBlock = lazy( () =>
	import(
		/* webpackChunkName: "chart-block", webpackPrefetch: true */
		'./chart-block'
	)
);

/**
 * Plain fallback rendered while the syntax-highlighter chunk downloads.
 * Shows the raw code text immediately so the user can read it before
 * the interactive editor hydrates (~50 ms on a cached chunk).
 *
 * @param {Object} props          - Fallback props.
 * @param {string} [props.lang]   - Language label for the header line.
 * @param {*}      props.children - Code text.
 * @return {JSX.Element} Preformatted code element.
 */
function CodeFallback( { lang, children } ) {
	return (
		<div className="gratis-ai-agent-code-block">
			{ lang && (
				<div className="gratis-ai-agent-code-header">
					<span className="gratis-ai-agent-code-language">
						{ lang }
					</span>
				</div>
			) }
			<pre className="gratis-ai-agent-code-cm gratis-ai-agent-code-plain">
				<code>{ children }</code>
			</pre>
		</div>
	);
}

/** Languages that should render as interactive Chart.js charts. */
const CHART_LANGUAGES = new Set( [ 'chart', 'chartjs', 'chart.js' ] );

/**
 * Custom renderers for ReactMarkdown.
 */
const components = {
	code( { inline, className, children, ...props } ) {
		const match = /language-(\w+)/.exec( className || '' );
		if ( ! inline && match ) {
			const lang = match[ 1 ];
			if ( CHART_LANGUAGES.has( lang.toLowerCase() ) ) {
				return (
					<Suspense
						fallback={
							<CodeFallback lang={ lang }>
								{ children }
							</CodeFallback>
						}
					>
						<ChartBlock>{ children }</ChartBlock>
					</Suspense>
				);
			}
			return (
				<Suspense
					fallback={
						<CodeFallback lang={ lang }>{ children }</CodeFallback>
					}
				>
					<CodeBlock language={ lang }>{ children }</CodeBlock>
				</Suspense>
			);
		}
		if ( ! inline && String( children ).includes( '\n' ) ) {
			return (
				<Suspense
					fallback={ <CodeFallback>{ children }</CodeFallback> }
				>
					<CodeBlock>{ children }</CodeBlock>
				</Suspense>
			);
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
	/**
	 * Replace plain <table> with the interactive DataTable component.
	 * DataTable handles its own scroll wrapper, so no extra div needed.
	 *
	 * @param {Object} props          - Renderer props from ReactMarkdown.
	 * @param {*}      props.children - Table children (thead + tbody).
	 * @return {JSX.Element} Interactive DataTable.
	 */
	table( { children } ) {
		return <DataTable>{ children }</DataTable>;
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
