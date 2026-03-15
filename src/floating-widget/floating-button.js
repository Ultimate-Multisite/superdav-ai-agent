/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, comment } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Floating action button (FAB) that opens the chat panel.
 *
 * Rendered in the bottom-right corner when the floating panel is closed.
 *
 * @return {JSX.Element} The floating action button element.
 */
export default function FloatingButton() {
	const { setFloatingOpen } = useDispatch( STORE_NAME );

	return (
		<Button
			className="gratis-ai-agent-fab"
			onClick={ () => setFloatingOpen( true ) }
			label={ __( 'Open Gratis AI Agent', 'gratis-ai-agent' ) }
		>
			<Icon icon={ comment } size={ 24 } />
		</Button>
	);
}
