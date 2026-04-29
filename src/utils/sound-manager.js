/**
 * Sound Manager
 *
 * Synthesises notification sounds using the Web Audio API.
 * No external audio files are required — all tones are generated in-browser.
 *
 * Three sounds are provided:
 *   playDing()     — short ascending tones, played on a successful AI response.
 *   playDong()     — descending tones, played when the agent returns an error.
 *   playThinking() — subtle tick, played each time a tool action completes.
 *
 * Each function reads its corresponding localStorage flag before playing and
 * silently returns when the flag is absent or set to 'false'.
 *
 * The shared AudioContext is created lazily on first use so that the module
 * can be imported freely without triggering autoplay-policy warnings.
 */

/** Whether the Web Audio API is available in this browser. */
export const isSoundSupported =
	typeof window !== 'undefined' &&
	( typeof AudioContext !== 'undefined' ||
		typeof window.webkitAudioContext !== 'undefined' );

/** @type {AudioContext|null} Shared AudioContext instance (created lazily). */
let audioCtx = null;

/**
 * Return (or lazily create) the shared AudioContext.
 *
 * Resumes the context if it was suspended by the browser's autoplay policy.
 * The browser requires a user gesture before audio can play; we rely on the
 * settings toggle being that gesture.
 *
 * @return {AudioContext|null} Shared context, or null when unsupported.
 */
function getAudioContext() {
	if ( ! isSoundSupported ) {
		return null;
	}
	if ( ! audioCtx ) {
		const Ctx =
			typeof AudioContext !== 'undefined'
				? AudioContext
				: window.webkitAudioContext;
		audioCtx = new Ctx();
	}
	// Resume if suspended (browser autoplay policy).
	if ( audioCtx.state === 'suspended' ) {
		audioCtx.resume().catch( () => {} );
	}
	return audioCtx;
}

/**
 * Play a single synthesised oscillator tone with an amplitude envelope.
 *
 * The tone fades in instantly (10 ms attack) and fades out over the final
 * fraction of its duration defined by `rampDown`.
 *
 * @param {Object} options
 * @param {number} options.frequency  - Oscillator frequency in Hz.
 * @param {number} options.duration   - Total tone duration in seconds.
 * @param {number} options.gain       - Peak amplitude (0–1).
 * @param {string} [options.type]     - OscillatorType ('sine'|'square'|'triangle'|'sawtooth'). Default 'sine'.
 * @param {number} [options.rampDown] - Fraction of `duration` at which gain ramp-down begins. Default 0.7.
 */
function playTone( {
	frequency,
	duration,
	gain,
	type = 'sine',
	rampDown = 0.7,
} ) {
	const ctx = getAudioContext();
	if ( ! ctx ) {
		return;
	}
	const now = ctx.currentTime;

	const oscillator = ctx.createOscillator();
	const gainNode = ctx.createGain();

	oscillator.connect( gainNode );
	gainNode.connect( ctx.destination );

	oscillator.type = type;
	oscillator.frequency.setValueAtTime( frequency, now );

	// Attack: instant ramp from 0 to peak.
	gainNode.gain.setValueAtTime( 0, now );
	gainNode.gain.linearRampToValueAtTime( gain, now + 0.01 );
	// Sustain: hold until rampDown point.
	gainNode.gain.setValueAtTime( gain, now + duration * rampDown );
	// Release: fade to silence.
	gainNode.gain.linearRampToValueAtTime( 0, now + duration );

	oscillator.start( now );
	oscillator.stop( now + duration );
}

/**
 * Play the success "ding" sound.
 *
 * Two ascending sine tones played in quick succession — pleasant and
 * non-intrusive.  Skipped when `sdAiAgentSoundSuccess` is not 'true'.
 */
export function playDing() {
	if ( localStorage.getItem( 'sdAiAgentSoundSuccess' ) !== 'true' ) {
		return;
	}
	playTone( { frequency: 880, duration: 0.15, gain: 0.28, type: 'sine' } );
	setTimeout( () => {
		playTone( {
			frequency: 1100,
			duration: 0.2,
			gain: 0.22,
			type: 'sine',
		} );
	}, 120 );
}

/**
 * Play the error "dong" sound.
 *
 * Two descending sine tones — clearly distinct from the ding, without being
 * alarming.  Skipped when `sdAiAgentSoundError` is not 'true'.
 */
export function playDong() {
	if ( localStorage.getItem( 'sdAiAgentSoundError' ) !== 'true' ) {
		return;
	}
	playTone( { frequency: 440, duration: 0.25, gain: 0.3, type: 'sine' } );
	setTimeout( () => {
		playTone( {
			frequency: 330,
			duration: 0.3,
			gain: 0.25,
			type: 'sine',
		} );
	}, 200 );
}

/**
 * Play the thinking / tool-action tick sound.
 *
 * A single soft triangle-wave tick — subtle enough to acknowledge activity
 * without distracting from work.  Skipped when `sdAiAgentSoundThinking`
 * is not 'true'.
 */
export function playThinking() {
	if ( localStorage.getItem( 'sdAiAgentSoundThinking' ) !== 'true' ) {
		return;
	}
	playTone( {
		frequency: 660,
		duration: 0.08,
		gain: 0.16,
		type: 'triangle',
		rampDown: 0.4,
	} );
}
