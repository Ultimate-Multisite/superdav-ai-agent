/**
 * Custom icon glyphs used by the chat redesign.
 *
 * Everything else comes from @wordpress/icons. These are only the ones that
 * the package does not ship (sparkles avatar, microphone, paperclip, stop).
 */

/**
 * Unified AI mark — "AI" wordmark with 3-sparkle constellation.
 *
 * @param {Object}  root0
 * @param {boolean} root0.thinking Apply twinkling sparkle animation.
 * @param {number}  root0.size     Icon width/height in pixels (default 14).
 */
export function AiIcon( { thinking = false, size = 14 } ) {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width={ size }
			height={ size }
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
			className={ `gaa-ai-icon${ thinking ? ' thinking' : '' }` }
		>
			<text
				x="1.5"
				y="22.5"
				fontFamily="-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif"
				fontSize="23"
				fontWeight="800"
				letterSpacing="-1.2"
			>
				A
			</text>
			<text
				x="15.5"
				y="22.5"
				fontFamily="-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif"
				fontSize="18"
				fontWeight="800"
			>
				I
			</text>
			<path
				className="sp sp-lg"
				d="M18 3.5l1 3 3 1-3 1-1 3-1-3-3-1 3-1z"
			/>
			<path
				className="sp sp-md"
				d="M1.2 1l.55 1.6 1.6.55-1.6.55-.55 1.6-.55-1.6-1.6-.55 1.6-.55z"
			/>
			<path
				className="sp sp-sm"
				d="M22.5 14l.45 1.4 1.4.45-1.4.45-.45 1.4-.45-1.4-1.4-.45 1.4-.45z"
			/>
		</svg>
	);
}

/**
 *
 */
export function Sparkles() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="14"
			height="14"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M12 2l1.7 5.3L19 9l-5.3 1.7L12 16l-1.7-5.3L5 9l5.3-1.7L12 2zm6 10l.9 2.6L21 15l-2.1.4L18 18l-.9-2.6L15 15l2.1-.4L18 12zM6 13l.7 2L9 15.7 7 16.3 6 19l-.7-2.7L3 15.7 5 15l1-2z" />
		</svg>
	);
}

/**
 *
 */
export function Microphone() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.93V20H9v2h6v-2h-2v-2.07A7 7 0 0 0 19 11h-2z" />
		</svg>
	);
}

/**
 *
 */
export function Paperclip() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
		</svg>
	);
}

/**
 *
 */
export function Template() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<rect x="3" y="3" width="18" height="18" rx="2" />
			<path d="M3 9h18M9 21V9" />
		</svg>
	);
}

/**
 *
 */
export function Stop() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="12"
			height="12"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<rect x="6" y="6" width="12" height="12" rx="1.5" />
		</svg>
	);
}

/**
 *
 */
export function Speaker() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z" />
		</svg>
	);
}

/**
 *
 */
export function SpeakerMuted() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" />
		</svg>
	);
}
