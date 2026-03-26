/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';

/**
 * Custom hook for push-to-talk speech recognition via the browser Web Speech API.
 *
 * Returns an object with:
 *   - isListening {boolean}  — true while the mic is active.
 *   - isSupported {boolean}  — false when the browser lacks SpeechRecognition.
 *   - transcript  {string}   — the latest interim/final transcript text.
 *   - error       {string|null} — last error message, or null.
 *   - startListening  {Function} — begin recording.
 *   - stopListening   {Function} — stop recording.
 *   - toggleListening {Function} — toggle start/stop.
 *   - resetTranscript {Function} — clear the transcript.
 *
 * @param {Object}   [options]                     - Configuration options.
 * @param {string}   [options.lang='']             - BCP-47 language tag (e.g. 'en-US').
 *                                                 Empty string uses the browser default.
 * @param {boolean}  [options.continuous=false]    - Keep listening after a pause.
 * @param {boolean}  [options.interimResults=true] - Emit interim (in-progress) results.
 * @param {Function} [options.onResult]            - Called with the transcript string on
 *                                                 each result event.
 * @param {Function} [options.onEnd]               - Called when recognition ends.
 * @return {Object} Speech recognition state and controls.
 */
export default function useSpeechRecognition( {
	lang = '',
	continuous = false,
	interimResults = true,
	onResult,
	onEnd,
} = {} ) {
	const SpeechRecognition =
		window.SpeechRecognition || window.webkitSpeechRecognition;

	const isSupported = Boolean( SpeechRecognition );

	const [ isListening, setIsListening ] = useState( false );
	const [ transcript, setTranscript ] = useState( '' );
	const [ error, setError ] = useState( null );

	const recognitionRef = useRef( null );
	// Track whether the stop was intentional (user action) vs automatic (end of speech).
	const intentionalStopRef = useRef( false );

	// Cleanup on unmount.
	useEffect( () => {
		return () => {
			if ( recognitionRef.current ) {
				intentionalStopRef.current = true;
				recognitionRef.current.abort();
			}
		};
	}, [] );

	const startListening = useCallback( () => {
		if ( ! isSupported || isListening ) {
			return;
		}

		setError( null );
		setTranscript( '' );
		intentionalStopRef.current = false;

		const recognition = new SpeechRecognition();
		recognition.lang = lang;
		recognition.continuous = continuous;
		recognition.interimResults = interimResults;

		recognition.onstart = () => {
			setIsListening( true );
		};

		recognition.onresult = ( event ) => {
			let fullTranscript = '';
			for ( let i = 0; i < event.results.length; i++ ) {
				fullTranscript += event.results[ i ][ 0 ].transcript;
			}
			setTranscript( fullTranscript );
			if ( onResult ) {
				onResult( fullTranscript );
			}
		};

		recognition.onerror = ( event ) => {
			// 'aborted' fires when we call abort() intentionally — not a real error.
			if ( event.error !== 'aborted' ) {
				setError( event.error );
			}
			setIsListening( false );
		};

		recognition.onend = () => {
			setIsListening( false );
			if ( onEnd ) {
				onEnd();
			}
		};

		recognitionRef.current = recognition;
		recognition.start();
	}, [
		SpeechRecognition,
		isSupported,
		isListening,
		lang,
		continuous,
		interimResults,
		onResult,
		onEnd,
	] );

	const stopListening = useCallback( () => {
		if ( recognitionRef.current ) {
			intentionalStopRef.current = true;
			recognitionRef.current.stop();
		}
		setIsListening( false );
	}, [] );

	const toggleListening = useCallback( () => {
		if ( isListening ) {
			stopListening();
		} else {
			startListening();
		}
	}, [ isListening, startListening, stopListening ] );

	const resetTranscript = useCallback( () => {
		setTranscript( '' );
	}, [] );

	return {
		isListening,
		isSupported,
		transcript,
		error,
		startListening,
		stopListening,
		toggleListening,
		resetTranscript,
	};
}
