/**
 * Notification Manager
 *
 * Manages browser notifications for pending tool confirmations and
 * document title flashing when the page is hidden. Singleton pattern
 * so all callers share the same active-notification registry and the
 * title flash loop.
 *
 * Usage:
 *   import { requestPermission, notifyConfirmationNeeded, clearNotification } from '../utils/notification-manager';
 *
 *   // On first tool confirmation (or from settings):
 *   requestPermission();
 *
 *   // When awaiting_confirmation and document.hidden:
 *   notifyConfirmationNeeded( jobId, toolName );
 *
 *   // When confirmation resolved:
 *   clearNotification( jobId );
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/** @type {Map<string, Notification>} Active notifications keyed by jobId. */
const activeNotifications = new Map();

/** @type {number|null} setInterval ID for the title flash loop. */
let titleFlashInterval = null;

/** @type {string} Original document title before flashing started. */
let originalTitle = '';

/** @type {string[]} Job IDs currently awaiting confirmation (drives flash loop). */
const pendingJobIds = [];

/**
 * Start flashing the document title with an approval-needed prefix.
 * No-op when there are no pending jobs or the page is already visible.
 *
 * The flash alternates between "⚠ Approval needed" and the original
 * title every 1500 ms, and is cleared automatically when the page
 * becomes visible again.
 */
function startTitleFlash() {
	if ( titleFlashInterval !== null ) {
		// Already flashing.
		return;
	}

	originalTitle = document.title;
	// Build the flash title once so we don't call __() on every interval tick.
	const flashTitle =
		'\u26A0 ' +
		__( 'Approval needed', 'sd-ai-agent' ) +
		' \u2014 ' +
		originalTitle;
	let toggle = false;

	titleFlashInterval = setInterval( () => {
		if ( ! document.hidden ) {
			stopTitleFlash();
			return;
		}
		document.title = toggle ? originalTitle : flashTitle;
		toggle = ! toggle;
	}, 1500 );
}

/**
 * Stop flashing the document title and restore the original value.
 */
function stopTitleFlash() {
	if ( titleFlashInterval !== null ) {
		clearInterval( titleFlashInterval );
		titleFlashInterval = null;
	}
	if ( originalTitle ) {
		document.title = originalTitle;
		originalTitle = '';
	}
}

/**
 * Request browser notification permission from the user.
 *
 * Should be called in response to a user gesture (e.g. first tool
 * confirmation click or from the settings page) so that browsers
 * that require user activation can honour the prompt.
 *
 * @return {Promise<string>} Resolved permission state ('granted'|'denied'|'default').
 */
export async function requestPermission() {
	if ( ! ( 'Notification' in window ) ) {
		return 'denied';
	}

	if ( Notification.permission !== 'default' ) {
		return Notification.permission;
	}

	return Notification.requestPermission();
}

/**
 * Fire a browser notification when a tool confirmation is needed and
 * the page is not visible.  Starts the document title flash loop too.
 *
 * Skips silently when:
 *  - The Notifications API is not available.
 *  - Permission is 'denied'.
 *  - A notification for this jobId is already active.
 *  - `document.hidden` is false (user is already looking at the page).
 *
 * @param {string} jobId    Job identifier awaiting confirmation.
 * @param {string} toolName Name of the tool awaiting approval (for the notification body).
 */
export function notifyConfirmationNeeded( jobId, toolName ) {
	if ( ! pendingJobIds.includes( jobId ) ) {
		pendingJobIds.push( jobId );
	}

	if ( document.hidden ) {
		startTitleFlash();
	}

	if ( ! ( 'Notification' in window ) ) {
		return;
	}

	if ( Notification.permission !== 'granted' ) {
		return;
	}

	if ( activeNotifications.has( jobId ) ) {
		// Already notified for this job.
		return;
	}

	const body = toolName
		? `${ __( 'Tool approval needed:', 'sd-ai-agent' ) } ${ toolName }`
		: __( 'A tool is awaiting your approval.', 'sd-ai-agent' );

	const notification = new Notification(
		__( 'AI Agent — Approval Required', 'sd-ai-agent' ),
		{
			body,
			requireInteraction: true,
			tag: `job-confirm-${ jobId }`,
			icon: window.sdAiAgentData?.pluginUrl
				? `${ window.sdAiAgentData.pluginUrl }assets/icon-128.png`
				: undefined,
		}
	);

	notification.onclick = () => {
		window.focus();
		notification.close();
	};

	activeNotifications.set( jobId, notification );
}

/**
 * Clear the browser notification and stop the title flash for a job.
 *
 * Call this when the confirmation has been resolved (confirmed or rejected)
 * or when the job transitions away from `awaiting_confirmation`.
 *
 * @param {string} jobId Job identifier whose notification should be cleared.
 */
export function clearNotification( jobId ) {
	const notification = activeNotifications.get( jobId );
	if ( notification ) {
		notification.close();
		activeNotifications.delete( jobId );
	}

	const idx = pendingJobIds.indexOf( jobId );
	if ( idx !== -1 ) {
		pendingJobIds.splice( idx, 1 );
	}

	// If no more pending jobs, stop flashing.
	if ( pendingJobIds.length === 0 ) {
		stopTitleFlash();
	}
}

/**
 * Clear all active notifications and stop the title flash.
 * Useful on page unload or plugin reset.
 */
export function clearAllNotifications() {
	activeNotifications.forEach( ( notification ) => notification.close() );
	activeNotifications.clear();
	pendingJobIds.length = 0;
	stopTitleFlash();
}

/**
 * Return whether the Notifications API is available and permission is granted.
 *
 * @return {boolean} True when notifications can be fired.
 */
export function canNotify() {
	return 'Notification' in window && Notification.permission === 'granted';
}

/**
 * Return the current Notification permission state, or 'unsupported'
 * when the API is unavailable.
 *
 * @return {string} 'granted' | 'denied' | 'default' | 'unsupported'
 */
export function getPermissionState() {
	if ( ! ( 'Notification' in window ) ) {
		return 'unsupported';
	}
	return Notification.permission;
}
