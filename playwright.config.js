/**
 * External dependencies
 */
const path = require( 'path' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: path.resolve( __dirname, 'tests/e2e/specs' ),
};
