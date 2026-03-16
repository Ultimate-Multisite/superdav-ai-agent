/**
 * Text-to-speech hook using the Web Speech API (SpeechSynthesis).
 *
 * Provides a simple interface to speak text aloud, cancel speech,
 * and query browser support. Voice, rate, and pitch are configurable.
 *
 * @module use-text-to-speech
 */

/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';

/**
 * Whether the browser supports the Web Speech API SpeechSynthesis interface.
 *
 * @type {boolean}
 */
export const isTTSSupported =
	typeof window !== 'undefined' && 'speechSynthesis' in window;

/**
 * Return the list of available SpeechSynthesis voices.
 * Voices load asynchronously in some browsers (Chrome), so this hook
 * subscribes to the `voiceschanged` event and re-reads the list.
 *
 * @return {Object[]} Available voices (may be empty until loaded).
 */
export function useAvailableVoices() {
	const [ voices, setVoices ] = useState( () =>
		isTTSSupported ? window.speechSynthesis.getVoices() : []
	);

	useEffect( () => {
		if ( ! isTTSSupported ) {
			return;
		}

		const update = () => setVoices( window.speechSynthesis.getVoices() );

		// Chrome fires voiceschanged; Firefox populates synchronously.
		window.speechSynthesis.addEventListener( 'voiceschanged', update );
		update();

		return () => {
			window.speechSynthesis.removeEventListener(
				'voiceschanged',
				update
			);
		};
	}, [] );

	return voices;
}

/**
 * Strip markdown syntax from text before speaking it.
 * Removes code fences, inline code, headers, bold/italic markers, links,
 * and leading list/blockquote characters so the spoken output is clean prose.
 *
 * @param {string} text Raw markdown text.
 * @return {string} Plain text suitable for speech synthesis.
 */
function stripMarkdown( text ) {
	return (
		text
			// Fenced code blocks — replace with a brief spoken label.
			.replace( /```[\s\S]*?```/g, ' code block. ' )
			// Inline code.
			.replace( /`[^`]+`/g, ( m ) => m.slice( 1, -1 ) )
			// ATX headings.
			.replace( /^#{1,6}\s+/gm, '' )
			// Bold / italic.
			.replace( /\*{1,3}([^*]+)\*{1,3}/g, '$1' )
			.replace( /_{1,3}([^_]+)_{1,3}/g, '$1' )
			// Links — keep the label.
			.replace( /\[([^\]]+)\]\([^)]+\)/g, '$1' )
			// Images.
			.replace( /!\[[^\]]*\]\([^)]+\)/g, '' )
			// Blockquote markers.
			.replace( /^>\s*/gm, '' )
			// List markers.
			.replace( /^[\s]*[-*+]\s+/gm, '' )
			.replace( /^[\s]*\d+\.\s+/gm, '' )
			// Horizontal rules.
			.replace( /^[-*_]{3,}\s*$/gm, '' )
			// Collapse multiple blank lines.
			.replace( /\n{3,}/g, '\n\n' )
			.trim()
	);
}

/**
 * Hook that provides text-to-speech functionality via SpeechSynthesis.
 *
 * @param {Object} options               - Configuration options.
 * @param {string} [options.voiceURI=''] - URI of the voice to use (empty = browser default).
 * @param {number} [options.rate=1]      - Speech rate (0.1–10, default 1).
 * @param {number} [options.pitch=1]     - Speech pitch (0–2, default 1).
 * @return {{
 *   isSpeaking: boolean,
 *   speak: (text: string) => void,
 *   cancel: () => void,
 *   isSupported: boolean,
 * }} TTS controls.
 */
export default function useTextToSpeech( {
	voiceURI = '',
	rate = 1,
	pitch = 1,
} = {} ) {
	const [ isSpeaking, setIsSpeaking ] = useState( false );
	const utteranceRef = useRef( null );

	// Cancel any in-progress speech when the component unmounts.
	useEffect( () => {
		return () => {
			if ( isTTSSupported ) {
				window.speechSynthesis.cancel();
			}
		};
	}, [] );

	const speak = useCallback(
		( text ) => {
			if ( ! isTTSSupported || ! text ) {
				return;
			}

			// Cancel any current speech before starting new.
			window.speechSynthesis.cancel();

			const plain = stripMarkdown( text );
			if ( ! plain ) {
				return;
			}

			const utterance = new SpeechSynthesisUtterance( plain );
			utterance.rate = rate;
			utterance.pitch = pitch;

			// Resolve voice by URI if specified.
			if ( voiceURI ) {
				const voices = window.speechSynthesis.getVoices();
				const match = voices.find( ( v ) => v.voiceURI === voiceURI );
				if ( match ) {
					utterance.voice = match;
				}
			}

			utterance.onstart = () => setIsSpeaking( true );
			utterance.onend = () => setIsSpeaking( false );
			utterance.onerror = () => setIsSpeaking( false );

			utteranceRef.current = utterance;
			window.speechSynthesis.speak( utterance );
		},
		[ voiceURI, rate, pitch ]
	);

	const cancel = useCallback( () => {
		if ( isTTSSupported ) {
			window.speechSynthesis.cancel();
			setIsSpeaking( false );
		}
	}, [] );

	return {
		isSpeaking,
		speak,
		cancel,
		isSupported: isTTSSupported,
	};
}
