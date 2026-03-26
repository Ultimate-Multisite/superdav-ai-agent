const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-page': path.resolve(
			process.cwd(),
			'src/admin-page',
			'index.js'
		),
		'changes-page': path.resolve(
			process.cwd(),
			'src/changes-page',
			'index.js'
		),
		'floating-widget': path.resolve(
			process.cwd(),
			'src/floating-widget',
			'index.js'
		),
		'settings-page': path.resolve(
			process.cwd(),
			'src/settings-page',
			'index.js'
		),
		'screen-meta': path.resolve(
			process.cwd(),
			'src/screen-meta',
			'index.js'
		),
		'abilities-explorer': path.resolve(
			process.cwd(),
			'src/abilities-explorer',
			'index.js'
		),
		'benchmark-page': path.resolve(
			process.cwd(),
			'src/benchmark-page',
			'index.js'
		),
	},
};
