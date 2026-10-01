/**
 * White-label branding helpers (t075).
 *
 * Reads the `window.sdAiAgentBranding` object injected by FloatingWidget.php
 * via wp_localize_script and provides typed accessors with safe fallbacks.
 *
 * The object is only present when the floating widget is enqueued (admin and
 * optionally frontend). On the settings page it is absent — components there
 * use the Redux store directly.
 */

/**
 * @typedef {Object} BrandingConfig
 * @property {string} agentName       Display name for the agent (may be empty).
 * @property {string} primaryColor    CSS colour string for FAB/title-bar background.
 * @property {string} textColor       CSS colour string for FAB/title-bar text.
 * @property {string} logoUrl         URL of the logo/avatar image (may be empty).
 * @property {string} greeting        Normalized custom greeting message.
 * @property {string} greetingMessage Custom greeting message (may be empty).
 */

/**
 * Return the branding config injected by PHP, or an empty object if absent.
 *
 * @return {BrandingConfig} The branding configuration object.
 */
export function getBranding() {
	const branding = ( typeof window !== 'undefined' &&
		window.sdAiAgentBranding ) || {
		agentName: '',
		primaryColor: '',
		textColor: '',
		logoUrl: '',
		greetingMessage: '',
	};

	return {
		...branding,
		// Older frontend code read `greeting`; keep it normalized so every
		// widget surface consumes the same localized `greetingMessage` value.
		greeting: branding.greeting || branding.greetingMessage || '',
	};
}

/**
 * Build an inline style object for the FAB button and title bar.
 *
 * Returns an empty object when no custom colours are configured so that the
 * CSS `var(--wp-admin-theme-color)` fallback in the stylesheet takes effect.
 *
 * @return {Object} CSS style properties object.
 */
export function getBrandingStyle() {
	const { primaryColor, textColor } = getBranding();
	return {
		...getBrandingCssVariables(),
		...( primaryColor ? { background: primaryColor } : {} ),
		...( textColor ? { color: textColor } : {} ),
	};
}

/**
 * Build CSS custom properties for widget surfaces that should inherit branding.
 *
 * @return {Object} CSS custom property style object.
 */
export function getBrandingCssVariables() {
	const { primaryColor, textColor } = getBranding();
	const style = {};
	if ( primaryColor ) {
		style[ '--sdaa-w-primary' ] = primaryColor;
		style[ '--sdaa-w-primary-dark' ] = primaryColor;
		style[ '--sdaa-primary' ] = primaryColor;
		style[ '--sdaa-primary-dark' ] = primaryColor;
	}
	if ( textColor ) {
		style[ '--sdaa-w-on-primary' ] = textColor;
		style[ '--sdaa-on-primary' ] = textColor;
	}
	return style;
}
