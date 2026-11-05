const {
	parseOptions,
	commandArgs,
} = require( '../../../scripts/plugin-creation-smoke' );

const required = [
	'--live',
	'--disposable-site',
	'--site-url=http://localhost:8893',
	'--provider=sd-ai-agent-cloud',
	'--model=superdav-chat-standard',
];

describe( 'Live release smoke safety', () => {
	it( 'requires both explicit paid-inference and disposable-site opt-ins', () => {
		expect( () => parseOptions( required.slice( 1 ) ) ).toThrow();
		expect( () =>
			parseOptions(
				required.filter( ( arg ) => arg !== '--disposable-site' )
			)
		).toThrow();
	} );

	it.each( [
		'--api-key=never-accept-credentials',
		'--constructor=invalid',
		'--max-seconds=0',
		'--max-requests=100000',
		'--phase-seconds=NaN',
		'--scenarios=single,single',
		'--scenarios=unknown',
		'--wp-env=production',
		'--site-url=http://name:secret@localhost:8893',
	] )( 'rejects unsafe or unbounded input: %s', ( arg ) => {
		expect( () => parseOptions( [ ...required, arg ] ) ).toThrow();
	} );

	it( 'keeps PHP code as one argv element and bounds remote execution', () => {
		const options = parseOptions( required );
		const code = '$value = "literal; not shell code";';
		const args = commandArgs( options, code, 60, 'sd-release-smoke-own' );
		expect( args ).toEqual(
			expect.arrayContaining( [
				'timeout',
				'-k',
				'60',
				'--skip-plugins=sd-release-smoke-own',
			] )
		);
		expect( args.slice( -2 ) ).toEqual( [ 'eval', code ] );
	} );

	it( 'supports a disposable host-native install without shell interpolation', () => {
		const options = parseOptions( [
			...required,
			'--wp-path=/disposable wordpress',
			'--scenarios=settings,rest,agent',
		] );
		expect( options.scenarios ).toEqual( [ 'settings', 'rest', 'agent' ] );
		expect( commandArgs( options, 'echo 1;', 60 ).slice( 0, 2 ) ).toEqual( [
			'wp',
			'--path=/disposable wordpress',
		] );
	} );
} );
