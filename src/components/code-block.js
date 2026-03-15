/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter';
import { oneDark } from 'react-syntax-highlighter/dist/esm/styles/prism';

export default function CodeBlock( { language, children } ) {
	const [ copied, setCopied ] = useState( false );
	const code = String( children ).replace( /\n$/, '' );

	const handleCopy = useCallback( () => {
		navigator.clipboard.writeText( code ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	}, [ code ] );

	return (
		<div className="ai-agent-code-block">
			<div className="ai-agent-code-header">
				{ language && (
					<span className="ai-agent-code-language">{ language }</span>
				) }
				<button
					className="ai-agent-code-copy"
					onClick={ handleCopy }
					type="button"
				>
					{ copied
						? __( 'Copied!', 'ai-agent' )
						: __( 'Copy', 'ai-agent' ) }
				</button>
			</div>
			<SyntaxHighlighter
				style={ oneDark }
				language={ language || 'text' }
				PreTag="div"
				customStyle={ {
					margin: 0,
					borderRadius: '0 0 4px 4px',
					fontSize: '0.85em',
				} }
			>
				{ code }
			</SyntaxHighlighter>
		</div>
	);
}
