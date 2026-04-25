/**
 * Floating chat widget top-level — renders either the launcher (FAB)
 * when closed, or the redesigned widget panel when open. State for
 * open/minimized comes from the shared store so every surface
 * (keyboard shortcut, close button, legacy code paths) stays in sync.
 */

import { useSelect } from '@wordpress/data';

import STORE_NAME from '../../store';
import WidgetLauncher from './widget-launcher';
import WidgetPanel from './widget-panel';
// chat-redesign base styles provide the primitives (.gaa-cr-tool-card,
// .gaa-cr-changes-drawer, .gaa-cr-icon-btn, .gaa-cr-btn-sm) used by the
// shared components we import from chat-redesign. All rules are scoped
// under .gaa-cr* so they don't leak into the wp-admin surface.
import '../chat-redesign/chat-redesign.css';
import './widget.css';

/**
 *
 */
export default function ChatWidget() {
	const isOpen = useSelect(
		( sel ) => sel( STORE_NAME ).isFloatingOpen(),
		[]
	);
	return isOpen ? <WidgetPanel /> : <WidgetLauncher />;
}
