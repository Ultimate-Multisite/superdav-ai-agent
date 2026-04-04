/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * ErrorBoundary catches JavaScript errors anywhere in its child component tree,
 * logs those errors, and displays a user-friendly fallback UI instead of
 * crashing the entire application.
 *
 * Usage:
 *   <ErrorBoundary>
 *     <MyComponent />
 *   </ErrorBoundary>
 *
 * Or with a custom fallback label:
 *   <ErrorBoundary label={ __( 'Message List', 'gratis-ai-agent' ) }>
 *     <MessageList />
 *   </ErrorBoundary>
 */
export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false, error: null };
		this.handleReset = this.handleReset.bind( this );
	}

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	componentDidCatch( error, info ) {
		// Log to console so developers can see the full stack trace.
		// eslint-disable-next-line no-console
		console.error( '[AI Agent] Component error:', error, info );
	}

	handleReset() {
		this.setState( { hasError: false, error: null } );
	}

	render() {
		if ( this.state.hasError ) {
			const { label } = this.props;
			const areaLabel = label || __( 'This section', 'gratis-ai-agent' );

			return (
				<div className="gratis-ai-agent-error-boundary" role="alert">
					<p className="gratis-ai-agent-error-boundary-message">
						{ /* translators: %s: name of the UI area that failed */ }
						{ areaLabel }{ ' ' }
						{ __(
							'encountered an unexpected error.',
							'gratis-ai-agent'
						) }
					</p>
					<Button
						variant="secondary"
						onClick={ this.handleReset }
						className="gratis-ai-agent-error-boundary-retry"
					>
						{ __( 'Try again', 'gratis-ai-agent' ) }
					</Button>
				</div>
			);
		}

		return this.props.children;
	}
}
