/** Opt-in, bounded, disposable-site checks against installed release ZIPs. */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { randomBytes } = require( 'node:crypto' );
const { spawnSync } = require( 'node:child_process' );

/**
 * Validate operator intent before starting WordPress or spending provider credit.
 *
 * @param {string[]} args CLI arguments.
 * @return {Object} Validated settings.
 */
function parseOptions( args ) {
	const options = {
		scenarios: 'single,multi',
		wpEnv: 'tests-cli',
		user: 'admin',
		maxSeconds: 600,
		phaseSeconds: 180,
		maxRequests: 4,
	};
	const values = {
		'site-url': 'siteUrl',
		provider: 'provider',
		model: 'model',
		scenarios: 'scenarios',
		'wp-env': 'wpEnv',
		'wp-path': 'wpPath',
		user: 'user',
		report: 'report',
		'max-seconds': 'maxSeconds',
		'phase-seconds': 'phaseSeconds',
		'max-requests': 'maxRequests',
	};
	for ( const arg of args.filter( ( item ) => item !== '--' ) ) {
		if ( arg === '--live' || arg === '--disposable-site' ) {
			options[ arg === '--live' ? 'live' : 'disposableSite' ] = true;
			continue;
		}
		const match = arg.match( /^--([a-z-]+)=(.+)$/ );
		if ( ! match || ! Object.hasOwn( values, match[ 1 ] ) ) {
			throw new Error(
				'Unknown argument; use --help (never pass credentials)'
			);
		}
		options[ values[ match[ 1 ] ] ] = match[ 2 ];
	}
	if ( ! options.live || ! options.disposableSite ) {
		throw new Error( 'Both --live and --disposable-site are required' );
	}
	for ( const key of [ 'provider', 'model', 'user' ] ) {
		if (
			! /^[a-zA-Z0-9][a-zA-Z0-9_.:/-]{0,190}$/.test(
				options[ key ] || ''
			)
		) {
			throw new Error( `A valid --${ key } is required` );
		}
	}
	const url = new URL( options.siteUrl );
	if (
		! [ 'http:', 'https:' ].includes( url.protocol ) ||
		url.username ||
		url.password ||
		url.search ||
		url.hash
	) {
		throw new Error(
			'--site-url must be an exact site URL without credentials'
		);
	}
	if ( ! [ 'cli', 'tests-cli' ].includes( options.wpEnv ) ) {
		throw new Error( '--wp-env must be cli or tests-cli' );
	}
	for ( const key of [ 'maxSeconds', 'phaseSeconds', 'maxRequests' ] ) {
		options[ key ] = Number( options[ key ] );
		const max = key === 'maxRequests' ? 20 : 3600;
		if (
			! Number.isInteger( options[ key ] ) ||
			options[ key ] < 1 ||
			options[ key ] > max
		) {
			throw new Error( `Invalid bounded ${ key }` );
		}
	}
	options.scenarios = options.scenarios.split( ',' );
	if (
		new Set( options.scenarios ).size !== options.scenarios.length ||
		! options.scenarios.every( ( item ) =>
			[ 'single', 'multi', 'settings', 'rest', 'agent' ].includes( item )
		)
	) {
		throw new Error(
			'Scenarios must be unique single,multi,settings,rest,agent values'
		);
	}
	return options;
}

/**
 * Build argv, never shell text; wp-env bounds PHP inside the container as well.
 *
 * @param {Object} options    Validated settings.
 * @param {string} code       PHP entry point.
 * @param {number} seconds    Phase timeout.
 * @param {string} skipPlugin Generated plugin to omit during recovery.
 * @return {string[]} Executable and argv.
 */
function commandArgs( options, code, seconds, skipPlugin = '' ) {
	const command = options.wpPath
		? [ 'wp', `--path=${ options.wpPath }` ]
		: [
				'corepack',
				'pnpm',
				'exec',
				'wp-env',
				'run',
				options.wpEnv,
				'--',
				'timeout',
				'-k',
				'5',
				String( seconds ),
				'wp',
		  ];
	command.push(
		'--no-color',
		`--url=${ options.siteUrl }`,
		`--user=${ options.user }`
	);
	if ( skipPlugin ) {
		command.push( `--skip-plugins=${ skipPlugin }` );
	}
	return [ ...command, 'eval', code ];
}

/**
 * Run each operation in a fresh WordPress process and always attempt cleanup.
 *
 * @param {Object} options Validated settings.
 * @return {Object} Credential-free machine-readable evidence.
 */
function runSmoke( options ) {
	const started = Date.now();
	const deadline = started + options.maxSeconds * 1000;
	const source = fs
		.readFileSync(
			path.join( __dirname, '../bin/plugin-creation-smoke.php' ),
			'utf8'
		)
		.replace( /^<\?php\s*/, '' );
	const encoded = Buffer.from( source ).toString( 'base64' );
	const results = [];
	for ( const scenario of options.scenarios ) {
		const slug = `sd-release-smoke-${ randomBytes( 6 ).toString(
			'hex'
		) }-${ scenario }`;
		const result = { scenario, slug, passed: false, phases: [] };
		let owned = false;
		try {
			for ( const phase of [
				'preflight',
				'generate',
				'sandbox',
				'activate',
				'verify',
				'update',
				'verify-updated',
				'reject-invalid',
				'verify-updated',
			] ) {
				const seconds = Math.min(
					options.phaseSeconds,
					Math.floor( ( deadline - Date.now() ) / 1000 )
				);
				if ( seconds < 1 ) {
					throw new Error( 'total_time_budget_exceeded' );
				}
				const evidence = invokePhase( phase, seconds );
				result.phases.push( evidence );
				if ( ! evidence.passed ) {
					throw new Error( evidence.code );
				}
				if ( phase === 'preflight' ) {
					owned = true;
				}
			}
			result.passed = true;
		} catch ( error ) {
			result.code = error.message;
		} finally {
			if ( owned ) {
				const cleanup = invokePhase( 'cleanup', 60 );
				result.phases.push( cleanup );
				result.passed = result.passed && cleanup.passed;
			}
		}
		results.push( result );
		if ( ! result.passed ) {
			break;
		}

		/**
		 * Execute one phase without returning raw provider output or error bodies.
		 *
		 * @param {string} phase   Operation.
		 * @param {number} seconds Bounded operation time.
		 * @return {Object} Validated operation receipt.
		 */
		function invokePhase( phase, seconds ) {
			const receipt = randomBytes( 12 ).toString( 'hex' );
			const payload = Buffer.from(
				JSON.stringify( {
					...options,
					phase,
					scenario,
					slug,
					phaseSeconds: seconds,
				} )
			).toString( 'base64' );
			const code = `$operation = eval(base64_decode('${ encoded }')); $result = $operation(json_decode(base64_decode('${ payload }'), true)); $result['receipt'] = '${ receipt }'; WP_CLI::line('SD_SMOKE_REPORT=' . wp_json_encode($result)); WP_CLI::halt($result['passed'] ? 0 : 1);`;
			const [ command, ...args ] = commandArgs(
				options,
				code,
				seconds,
				phase === 'cleanup' ? slug : ''
			);
			const child = spawnSync( command, args, {
				cwd: path.resolve( __dirname, '..' ),
				encoding: 'utf8',
				timeout: ( seconds + 15 ) * 1000,
				maxBuffer: 4 * 1024 * 1024,
			} );
			try {
				const line = ( child.stdout || '' )
					.split( /\r?\n/ )
					.findLast( ( item ) =>
						item.startsWith( 'SD_SMOKE_REPORT=' )
					);
				const evidence = JSON.parse(
					line.slice( 'SD_SMOKE_REPORT='.length )
				);
				if (
					evidence.receipt !== receipt ||
					evidence.phase !== phase
				) {
					throw new Error( 'invalid_receipt' );
				}
				delete evidence.receipt;
				return {
					...evidence,
					passed:
						evidence.passed === true &&
						child.status === 0 &&
						! child.error,
				};
			} catch {
				return {
					phase,
					passed: false,
					code:
						child.error?.code === 'ETIMEDOUT'
							? 'phase_time_budget_exceeded'
							: 'phase_failed_without_receipt',
				};
			}
		}
	}
	return {
		schemaVersion: 1,
		passed:
			results.length === options.scenarios.length &&
			results.every( ( item ) => item.passed ),
		elapsedSeconds: Math.round( ( Date.now() - started ) / 1000 ),
		provider: options.provider,
		model: options.model,
		results,
	};
}

if ( require.main === module ) {
	try {
		if ( process.argv.includes( '--help' ) ) {
			process.stdout.write(
				'Usage: pnpm run test:plugin-creation -- --live --disposable-site --site-url=URL --provider=ID --model=ID [--scenarios=single,multi,settings,rest,agent] [--wp-env=tests-cli | --wp-path=PATH] [--max-seconds=600] [--phase-seconds=180] [--max-requests=4] [--report=NEW_FILE]\nUses existing configured credentials. Requires installed Core + Advanced ZIPs. Never use a shared/live site. Agent mode uses the existing auto-approving CLI.\n'
			);
		} else {
			const options = parseOptions( process.argv.slice( 2 ) );
			// Reserve a private, new report file before any paid or mutating operation.
			const reportFd = options.report
				? fs.openSync( options.report, 'wx', 0o600 )
				: null;
			try {
				const report = runSmoke( options );
				const json = JSON.stringify( report, null, 2 ) + '\n';
				if ( reportFd !== null ) {
					fs.writeFileSync( reportFd, json );
				}
				process.stdout.write( json );
				process.exitCode = report.passed ? 0 : 1;
			} finally {
				if ( reportFd !== null ) {
					fs.closeSync( reportFd );
				}
			}
		}
	} catch {
		process.stderr.write(
			'Smoke setup failed; check --help, explicit opt-ins, URL, bounded options, and a new report path. No credentials belong in arguments.\n'
		);
		process.exitCode = 2;
	}
}

module.exports = { parseOptions, commandArgs };
