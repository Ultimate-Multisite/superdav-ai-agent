/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import {
	Chart,
	CategoryScale,
	LinearScale,
	BarElement,
	LineElement,
	PointElement,
	ArcElement,
	RadialLinearScale,
	Title,
	Tooltip,
	Legend,
	Filler,
	BarController,
	LineController,
	PieController,
	DoughnutController,
	RadarController,
	PolarAreaController,
	BubbleController,
	ScatterController,
} from 'chart.js';

/**
 * Internal dependencies
 */
import CodeBlock from './code-block';

// Register all Chart.js components we want to support.
Chart.register(
	CategoryScale,
	LinearScale,
	BarElement,
	LineElement,
	PointElement,
	ArcElement,
	RadialLinearScale,
	Title,
	Tooltip,
	Legend,
	Filler,
	BarController,
	LineController,
	PieController,
	DoughnutController,
	RadarController,
	PolarAreaController,
	BubbleController,
	ScatterController
);

/**
 * Parses a Chart.js JSON config string, tolerating minor formatting issues.
 *
 * @param {string} raw - Raw JSON string from the code block.
 * @return {{ config: Object|null, error: string|null }} Parsed config or error.
 */
function parseChartConfig( raw ) {
	try {
		const config = JSON.parse( raw );
		if ( ! config || typeof config !== 'object' ) {
			return {
				config: null,
				error: __(
					'Chart config must be a JSON object.',
					'gratis-ai-agent'
				),
			};
		}
		if ( ! config.type ) {
			return {
				config: null,
				error: __(
					'Chart config must include a "type" field (e.g. "bar", "line", "pie").',
					'gratis-ai-agent'
				),
			};
		}
		if ( ! config.data ) {
			return {
				config: null,
				error: __(
					'Chart config must include a "data" field.',
					'gratis-ai-agent'
				),
			};
		}
		return { config, error: null };
	} catch ( e ) {
		return { config: null, error: e.message };
	}
}

/**
 * Renders a Chart.js chart from a JSON config string.
 * Falls back to a syntax-highlighted code block on parse errors.
 *
 * Usage in markdown:
 * ```chart
 * { "type": "bar", "data": { ... }, "options": { ... } }
 * ```
 *
 * @param {Object} props          - Component props.
 * @param {string} props.children - Raw JSON string for the Chart.js config.
 * @return {JSX.Element} Rendered chart or error fallback.
 */
/**
 * Inner component that renders the Chart.js canvas.
 * Only mounted when config is valid — handles canvas lifecycle.
 *
 * @param {Object} props        - Component props.
 * @param {Object} props.config - Validated Chart.js config object.
 * @return {JSX.Element} Canvas element or runtime error message.
 */
function ChartCanvas( { config } ) {
	const canvasRef = useRef( null );
	const chartRef = useRef( null );
	const [ runtimeError, setRuntimeError ] = useState( null );

	useEffect( () => {
		const canvas = canvasRef.current;
		if ( ! canvas ) {
			return;
		}

		// Destroy any previous instance before creating a new one.
		if ( chartRef.current ) {
			chartRef.current.destroy();
			chartRef.current = null;
		}

		try {
			chartRef.current = new Chart( canvas, config );
		} catch ( e ) {
			setRuntimeError( e.message );
		}

		return () => {
			if ( chartRef.current ) {
				chartRef.current.destroy();
				chartRef.current = null;
			}
		};
	}, [ config ] );

	if ( runtimeError ) {
		return (
			<p className="gratis-ai-agent-chart-error-msg">
				{ __( 'Chart render error:', 'gratis-ai-agent' ) }
				<code>{ runtimeError }</code>
			</p>
		);
	}

	return <canvas ref={ canvasRef } />;
}

/**
 * Chart block component — renders a Chart.js chart from a JSON code block.
 *
 * @param {Object} props          - Component props.
 * @param {string} props.children - Raw JSON string for the Chart.js config.
 * @return {JSX.Element} Rendered chart or error fallback.
 */
export default function ChartBlock( { children } ) {
	const raw = String( children ).trim();
	const { config, error } = parseChartConfig( raw );

	// Synchronous parse/validation error — render immediately without useEffect.
	if ( error ) {
		return (
			<div className="gratis-ai-agent-chart-error">
				<p className="gratis-ai-agent-chart-error-msg">
					{ __( 'Chart render error:', 'gratis-ai-agent' ) }
					<code>{ error }</code>
				</p>
				<CodeBlock language="json">{ raw }</CodeBlock>
			</div>
		);
	}

	return (
		<div className="gratis-ai-agent-chart-block">
			<ChartCanvas config={ config } />
		</div>
	);
}
