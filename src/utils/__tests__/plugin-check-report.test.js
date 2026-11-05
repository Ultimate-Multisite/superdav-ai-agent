const { analyzeReport } = require( '../../../scripts/plugin-check-report' );

describe( 'Plugin Check release report gate', () => {
	const finding = ( type ) => ( { type, code: `scanner.${ type }` } );

	it( 'fails ERROR findings even when a scanner wrapper reports success', () => {
		const text = `FILE: includes/example.php\n${ JSON.stringify( [
			finding( 'ERROR' ),
			finding( 'WARNING' ),
		] ) }\nSuccess: command completed\n`;
		expect( analyzeReport( text ) ).toMatchObject( {
			passed: false,
			errors: 1,
			warnings: 1,
		} );
	} );

	it( 'aggregates per-file multiline JSON without hiding warnings', () => {
		const text = `FILE: first.php\n${ JSON.stringify(
			[ finding( 'WARNING' ) ],
			null,
			2
		) }\nFILE: second.php\n${ JSON.stringify( [ finding( 'WARNING' ) ] ) }`;
		expect( analyzeReport( text ) ).toMatchObject( {
			passed: true,
			errors: 0,
			warnings: 2,
		} );
		expect( analyzeReport( text, { failOnWarnings: true } ).passed ).toBe(
			false
		);
	} );

	it.each( [ '[]', 'Success: Checks complete. No errors found.' ] )(
		'accepts explicit clean evidence: %s',
		( text ) => {
			expect( analyzeReport( text ).passed ).toBe( true );
		}
	);

	it.each( [
		'',
		'Success: command completed',
		'[{"type":"ERROR"',
		'[{"type":"INFO","code":"unknown"}]',
		'[{}]',
	] )( 'rejects missing, truncated, or unknown evidence: %s', ( text ) => {
		expect( () => analyzeReport( text ) ).toThrow();
	} );
} );
