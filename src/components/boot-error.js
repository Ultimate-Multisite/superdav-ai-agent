/**
 * Boot error component — shown when initial API calls fail with
 * auth/permission errors (403/401). Prevents an infinite request loop
 * by displaying a friendly message with a manual retry button.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Friendly error screen shown when the plugin cannot reach its REST API.
 *
 * @return {JSX.Element} The boot error element.
 */
export default function BootError() {
	const bootError = useSelect(
		( select ) => select( STORE_NAME ).getBootError(),
		[]
	);
	const { retryBoot } = useDispatch( STORE_NAME );

	if ( ! bootError ) {
		return null;
	}

	const is403 = bootError.status === 403;

	return (
		<div className="sd-ai-agent-boot-error">
			<div className="sd-ai-agent-boot-error__card">
				<h2>
					{ is403
						? __( 'Access Denied', 'sd-ai-agent' )
						: __( 'Connection Error', 'sd-ai-agent' ) }
				</h2>
				<p>
					{ is403
						? __(
								'The AI Agent could not authenticate with the REST API. This usually happens when your session has expired.',
								'sd-ai-agent'
						  )
						: __(
								'The AI Agent could not connect to the REST API. Please check that the plugin is active and try again.',
								'sd-ai-agent'
						  ) }
				</p>
				<div className="sd-ai-agent-boot-error__actions">
					<Button
						variant="primary"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload Page', 'sd-ai-agent' ) }
					</Button>
					<Button variant="secondary" onClick={ retryBoot }>
						{ __( 'Try Again', 'sd-ai-agent' ) }
					</Button>
				</div>
				<p className="sd-ai-agent-boot-error__hint">
					{ __(
						'Reloading the page refreshes your session. If the error persists, try logging out and back in.',
						'sd-ai-agent'
					) }
				</p>
			</div>
		</div>
	);
}
