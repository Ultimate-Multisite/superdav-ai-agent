/**
 * Add-credit popup shown when managed SD AI credit runs out mid-chat.
 *
 * The flow is deliberately short: one click opens the service-hosted checkout
 * in a new tab, returning to this tab re-checks the balance automatically, and
 * one more click resumes the request that ran out of credit. Payment details
 * are never entered or stored in WordPress.
 */

import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const ACCOUNT_PATH = '/sd-ai-agent/v1/superdav-account';

/**
 * Format a USD micros amount for display.
 *
 * @param {number|string|null|undefined} micros Amount in millionths of a USD.
 * @return {string} Localized amount, or an empty string when unknown.
 */
function formatBalance( micros ) {
	const amount = Number( micros );
	if (
		micros === null ||
		micros === undefined ||
		! Number.isFinite( amount )
	) {
		return '';
	}

	return new Intl.NumberFormat( undefined, {
		style: 'currency',
		currency: 'USD',
	} ).format( amount / 1_000_000 );
}

/**
 * Read the total wallet balance from a safe account status response.
 *
 * @param {Object|null} account Account status.
 * @return {number|null} Balance in USD micros, or null when unknown.
 */
function getBalance( account ) {
	const total = Number( account?.wallet?.total_usd_micros );
	return Number.isFinite( total ) ? total : null;
}

/**
 * Whether the current user may manage SD AI billing for this site.
 *
 * Billing REST routes require manage_options; WordPress only localizes a
 * settings URL for users with that capability.
 *
 * @return {boolean} True when billing actions are available.
 */
function canManageBilling() {
	const data =
		typeof window !== 'undefined' ? window.sdAiAgentData || {} : {};
	// wp_localize_script() stringifies scalars: true → "1", false → "".
	if ( Object.prototype.hasOwnProperty.call( data, 'canManageBilling' ) ) {
		return !! data.canManageBilling && data.canManageBilling !== '0';
	}

	return !! data.settingsPageUrl;
}

/**
 * Resolve the account settings deep link used as a manual fallback.
 *
 * @return {string} Account settings URL, or an empty string.
 */
function getAccountPageUrl() {
	const data =
		typeof window !== 'undefined' ? window.sdAiAgentData || {} : {};
	return data.accountPageUrl || data.settingsPageUrl || '';
}

/**
 * Add-credit popup.
 *
 * @param {Object}        props               Component props.
 * @param {Function}      props.onClose       Close the popup.
 * @param {Function}      props.onCreditAdded Called once new credit is detected.
 * @param {Function|null} props.onContinue    Resume the request, when possible.
 * @return {JSX.Element} Popup.
 */
export default function CreditUpgradeModal( {
	onClose,
	onCreditAdded,
	onContinue,
} ) {
	const isBillingAdmin = canManageBilling();
	const accountPageUrl = getAccountPageUrl();

	// offer → checkout → success. Coupon redemption can jump offer → success.
	const [ step, setStep ] = useState( 'offer' );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ balance, setBalance ] = useState( null );
	const [ showCoupon, setShowCoupon ] = useState( false );
	const [ couponCode, setCouponCode ] = useState( '' );
	const baselineRef = useRef( null );
	const checkingRef = useRef( false );

	const markCreditAdded = useCallback(
		( account ) => {
			setBalance( getBalance( account ) );
			setNotice( null );
			setStep( 'success' );
			onCreditAdded?.();
		},
		[ onCreditAdded ]
	);

	// Capture the starting balance so a later check can tell whether credit
	// was actually added (a small remaining balance can still be insufficient).
	useEffect( () => {
		if ( ! isBillingAdmin ) {
			return;
		}

		apiFetch( { path: ACCOUNT_PATH, method: 'POST' } )
			.then( ( account ) => {
				const current = getBalance( account );
				baselineRef.current = current;
				setBalance( current );
			} )
			.catch( () => {
				// The popup still works; the later check falls back to > 0.
			} );
	}, [ isBillingAdmin ] );

	const checkBalance = useCallback(
		async ( { silent = false } = {} ) => {
			if ( checkingRef.current ) {
				return;
			}
			checkingRef.current = true;
			if ( ! silent ) {
				setBusy( 'check' );
				setNotice( null );
			}

			try {
				const account = await apiFetch( {
					path: ACCOUNT_PATH,
					method: 'POST',
				} );
				const current = getBalance( account );
				const baseline = baselineRef.current ?? 0;
				if ( current !== null && current > baseline ) {
					markCreditAdded( account );
					return;
				}

				setBalance( current );
				if ( ! silent ) {
					setNotice( {
						status: 'info',
						message: __(
							'No new credit yet. Payments can take a moment to arrive. Finish checkout, then check again.',
							'superdav-ai-agent'
						),
					} );
				}
			} catch {
				if ( ! silent ) {
					setNotice( {
						status: 'warning',
						message: __(
							'Your balance could not be checked. Try again in a moment.',
							'superdav-ai-agent'
						),
					} );
				}
			} finally {
				checkingRef.current = false;
				if ( ! silent ) {
					setBusy( '' );
				}
			}
		},
		[ markCreditAdded ]
	);

	// Returning from the checkout tab re-checks the balance automatically.
	useEffect( () => {
		if ( step !== 'checkout' ) {
			return undefined;
		}

		const onFocus = () => checkBalance( { silent: true } );
		window.addEventListener( 'focus', onFocus );

		return () => window.removeEventListener( 'focus', onFocus );
	}, [ step, checkBalance ] );

	const openCheckout = useCallback( async () => {
		if ( busy ) {
			return;
		}

		// Open the tab synchronously inside the click so popup blockers allow it,
		// then point it at the one-time checkout URL from the service.
		const checkoutWindow = window.open( 'about:blank', '_blank' );
		if ( checkoutWindow ) {
			checkoutWindow.opener = null;
		}

		setBusy( 'checkout' );
		setNotice( null );

		try {
			const result = await apiFetch( {
				path: `${ ACCOUNT_PATH }/action`,
				method: 'POST',
				data: { action: 'purchase_credits' },
			} );
			if ( ! result?.url ) {
				throw new Error( 'invalid_account_action' );
			}

			if ( checkoutWindow ) {
				checkoutWindow.location.assign( result.url );
			} else {
				window.open( result.url, '_blank', 'noopener' );
			}
			setStep( 'checkout' );
		} catch {
			if ( checkoutWindow ) {
				checkoutWindow.close();
			}
			setNotice( {
				status: 'error',
				message: __(
					'Checkout could not be opened. Try again, or add credit from your SD AI account settings.',
					'superdav-ai-agent'
				),
				showAccountLink: true,
			} );
		} finally {
			setBusy( '' );
		}
	}, [ busy ] );

	const redeemCoupon = useCallback(
		async ( event ) => {
			event.preventDefault();
			const code = couponCode.trim();
			if ( ! code || busy ) {
				return;
			}

			setBusy( 'coupon' );
			setNotice( null );

			try {
				const account = await apiFetch( {
					path: `${ ACCOUNT_PATH }/redeem-coupon`,
					method: 'POST',
					data: { coupon_code: code },
				} );
				setCouponCode( '' );
				markCreditAdded( account );
			} catch ( err ) {
				const messages = {
					sd_ai_agent_coupon_invalid: __(
						'The coupon is invalid.',
						'superdav-ai-agent'
					),
					sd_ai_agent_coupon_expired: __(
						'The coupon has expired.',
						'superdav-ai-agent'
					),
					sd_ai_agent_coupon_revoked: __(
						'The coupon is no longer available.',
						'superdav-ai-agent'
					),
					sd_ai_agent_coupon_not_eligible: __(
						'The coupon is not eligible for this site.',
						'superdav-ai-agent'
					),
				};
				setNotice( {
					status: 'error',
					message:
						messages[ err?.code ] ||
						__(
							'Coupon redemption is temporarily unavailable.',
							'superdav-ai-agent'
						),
				} );
			} finally {
				setBusy( '' );
			}
		},
		[ busy, couponCode, markCreditAdded ]
	);

	const balanceText = formatBalance( balance );
	let title = __( 'You’ve used your SD AI credit', 'superdav-ai-agent' );
	if ( ! isBillingAdmin ) {
		title = __( 'SD AI credit has run out', 'superdav-ai-agent' );
	} else if ( step === 'checkout' ) {
		title = __( 'Finish checkout in the new tab', 'superdav-ai-agent' );
	} else if ( step === 'success' ) {
		title = __( 'Credit added', 'superdav-ai-agent' );
	}

	const noticeElement = notice && (
		<Notice status={ notice.status } isDismissible={ false }>
			{ notice.message }
			{ notice.showAccountLink && accountPageUrl && (
				<>
					{ ' ' }
					<a
						href={ accountPageUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open account settings', 'superdav-ai-agent' ) }
					</a>
				</>
			) }
		</Notice>
	);

	let body;
	if ( ! isBillingAdmin ) {
		body = (
			<>
				<p>
					{ __(
						'Ask a site administrator to add credit to this site’s SD AI account. Your conversation is saved.',
						'superdav-ai-agent'
					) }
				</p>
				<div className="sd-ai-agent-credit-modal__actions">
					<Button variant="primary" onClick={ onClose }>
						{ __( 'OK', 'superdav-ai-agent' ) }
					</Button>
				</div>
			</>
		);
	} else if ( step === 'success' ) {
		body = (
			<>
				<p>
					{ balanceText
						? sprintf(
								/* translators: %s: available credit balance. */
								__(
									'You’re all set. Available balance: %s.',
									'superdav-ai-agent'
								),
								balanceText
						  )
						: __( 'You’re all set.', 'superdav-ai-agent' ) }
				</p>
				<div className="sd-ai-agent-credit-modal__actions">
					{ onContinue ? (
						<>
							<Button variant="tertiary" onClick={ onClose }>
								{ __( 'Close', 'superdav-ai-agent' ) }
							</Button>
							<Button variant="primary" onClick={ onContinue }>
								{ __(
									'Continue my request',
									'superdav-ai-agent'
								) }
							</Button>
						</>
					) : (
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Back to chat', 'superdav-ai-agent' ) }
						</Button>
					) }
				</div>
			</>
		);
	} else if ( step === 'checkout' ) {
		body = (
			<>
				<p>
					{ __(
						'Complete your purchase in the SD AI checkout tab, then come back here. We’ll detect your new balance automatically.',
						'superdav-ai-agent'
					) }
				</p>
				{ noticeElement }
				<div className="sd-ai-agent-credit-modal__actions">
					<Button
						variant="tertiary"
						onClick={ openCheckout }
						disabled={ !! busy }
					>
						{ __( 'Reopen checkout', 'superdav-ai-agent' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ () => checkBalance() }
						isBusy={ busy === 'check' }
						disabled={ !! busy }
					>
						{ __( 'I’ve added credit', 'superdav-ai-agent' ) }
					</Button>
				</div>
			</>
		);
	} else {
		body = (
			<>
				<p>
					{ __(
						'Add credit to keep working. Your conversation is saved and picks up where it left off.',
						'superdav-ai-agent'
					) }
				</p>
				{ balanceText && (
					<p className="sd-ai-agent-credit-modal__balance">
						{ sprintf(
							/* translators: %s: available credit balance. */
							__( 'Available balance: %s', 'superdav-ai-agent' ),
							balanceText
						) }
					</p>
				) }
				{ noticeElement }
				<div className="sd-ai-agent-credit-modal__actions">
					<Button
						variant="tertiary"
						onClick={ onClose }
						disabled={ !! busy }
					>
						{ __( 'Not now', 'superdav-ai-agent' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ openCheckout }
						isBusy={ busy === 'checkout' }
						disabled={ !! busy }
					>
						{ __( 'Add credit', 'superdav-ai-agent' ) }
					</Button>
				</div>
				<p className="sd-ai-agent-credit-modal__hint">
					{ __(
						'Opens secure SD AI checkout in a new tab. Payment details are never stored in WordPress.',
						'superdav-ai-agent'
					) }
				</p>
				{ showCoupon ? (
					<form
						className="sd-ai-agent-credit-modal__coupon"
						onSubmit={ redeemCoupon }
					>
						<TextControl
							label={ __( 'Coupon code', 'superdav-ai-agent' ) }
							value={ couponCode }
							onChange={ setCouponCode }
							autoComplete="off"
							disabled={ !! busy }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<Button
							variant="secondary"
							type="submit"
							isBusy={ busy === 'coupon' }
							disabled={ !! busy || ! couponCode.trim() }
						>
							{ __( 'Apply', 'superdav-ai-agent' ) }
						</Button>
					</form>
				) : (
					<Button
						variant="link"
						className="sd-ai-agent-credit-modal__coupon-toggle"
						onClick={ () => setShowCoupon( true ) }
					>
						{ __( 'Have a coupon code?', 'superdav-ai-agent' ) }
					</Button>
				) }
			</>
		);
	}

	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="sd-ai-agent-credit-modal"
			size="small"
		>
			{ body }
		</Modal>
	);
}
