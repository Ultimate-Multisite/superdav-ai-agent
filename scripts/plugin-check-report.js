/** Fail closed on Plugin Check findings, independently of its process status. */
const fs = require( 'node:fs' );

/**
 * Summarize machine-readable scanner evidence without trusting its exit code.
 *
 * @param {string}  text                   Raw report, optionally including wrapper headers.
 * @param {Object}  options                Gate policy.
 * @param {boolean} options.failOnWarnings Whether warnings also fail the gate.
 * @return {Object} Finding counts and gate result.
 */
function analyzeReport( text, { failOnWarnings = false } = {} ) {
	const lines = text.split( /\r?\n/ );
	const findings = [];
	let foundJson = false;
	for ( let index = 0; index < lines.length; index++ ) {
		if ( ! /^\s*\[\s*(?:\{|\]|$)/.test( lines[ index ] ) ) {
			continue;
		}
		let parsed;
		let block = '';
		for ( ; index < lines.length; index++ ) {
			block += lines[ index ] + '\n';
			try {
				parsed = JSON.parse( block );
				break;
			} catch {
				// Multi-line JSON is valid; a truncated/malformed block is not.
			}
		}
		if ( ! Array.isArray( parsed ) ) {
			throw new Error( 'Malformed Plugin Check JSON report' );
		}
		foundJson = true;
		for ( const finding of parsed ) {
			if (
				! finding ||
				! [ 'ERROR', 'WARNING' ].includes( finding.type ) ||
				typeof finding.code !== 'string' ||
				! finding.code
			) {
				throw new Error( 'Unrecognized Plugin Check finding' );
			}
			findings.push( finding );
		}
	}
	if (
		! foundJson &&
		! /^Success:.*\bNo (?:errors|issues) found\b/im.test( text )
	) {
		throw new Error( 'No verifiable Plugin Check results found' );
	}
	const errors = findings.filter( ( item ) => item.type === 'ERROR' ).length;
	const warnings = findings.filter(
		( item ) => item.type === 'WARNING'
	).length;
	const codes = Object.create( null );
	for ( const finding of findings ) {
		codes[ finding.code ] = ( codes[ finding.code ] || 0 ) + 1;
	}
	return {
		passed: errors === 0 && ( ! failOnWarnings || warnings === 0 ),
		errors,
		warnings,
		codes,
	};
}

if ( require.main === module ) {
	try {
		const args = process.argv.slice( 2 );
		const paths = args.filter( ( arg ) => arg !== '--fail-on-warnings' );
		if ( paths.length !== 1 || paths[ 0 ].startsWith( '--' ) ) {
			throw new Error(
				'Usage: node scripts/plugin-check-report.js REPORT [--fail-on-warnings]'
			);
		}
		const summary = analyzeReport( fs.readFileSync( paths[ 0 ], 'utf8' ), {
			failOnWarnings: args.includes( '--fail-on-warnings' ),
		} );
		process.stdout.write( JSON.stringify( summary ) + '\n' );
		process.exitCode = summary.passed ? 0 : 1;
	} catch ( error ) {
		process.stderr.write( error.message + '\n' );
		process.exitCode = 2;
	}
}

module.exports = { analyzeReport };
