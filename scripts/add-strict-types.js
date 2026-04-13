/**
 * Post-build script: inject `declare(strict_types=1);` into generated .asset.php files.
 *
 * wp-scripts generates .asset.php manifests that open with `<?php return array(...)`.
 * These committed files must comply with the project-wide PHP coding standard that
 * requires `declare(strict_types=1);` on every PHP file. This script rewrites each
 * generated file to insert the declaration immediately after the opening `<?php` tag
 * without altering the rest of the file content.
 *
 * Run automatically via the `postbuild` npm script.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const BUILD_DIR = path.join( __dirname, '..', 'build' );

/**
 * Inject `declare(strict_types=1);` after `<?php` if not already present.
 *
 * @param {string} filePath Absolute path to the .asset.php file.
 */
function addStrictTypes( filePath ) {
	const content = fs.readFileSync( filePath, 'utf8' );

	// Already compliant — skip to avoid double declaration.
	if ( content.includes( 'declare(strict_types=1);' ) ) {
		return;
	}

	if ( ! content.startsWith( '<?php' ) ) {
		console.warn( `Skipping ${ filePath }: does not start with <?php` );
		return;
	}

	// Insert the declaration immediately after `<?php`, preserving the rest.
	const updated = content.replace( /^<\?php/, '<?php declare(strict_types=1);' );
	fs.writeFileSync( filePath, updated, 'utf8' );
	console.log( `Updated: ${ path.relative( process.cwd(), filePath ) }` );
}

// Process all .asset.php files in the build directory.
const assetFiles = fs
	.readdirSync( BUILD_DIR )
	.filter( ( f ) => f.endsWith( '.asset.php' ) )
	.map( ( f ) => path.join( BUILD_DIR, f ) );

if ( assetFiles.length === 0 ) {
	console.warn( 'No .asset.php files found in build/ — skipping.' );
	process.exit( 0 );
}

assetFiles.forEach( addStrictTypes );
console.log( `Processed ${ assetFiles.length } .asset.php file(s).` );
