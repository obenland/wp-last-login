/**
 * External dependencies
 */
const path = require( 'path' );

/*
 * The @wordpress/scripts base config defaults the e2e base URL to port 8889
 * (the legacy tests environment). We run e2e against the development env that
 * `npm run wp-env start` boots on 8888, so target that unless overridden.
 */
process.env.WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: path.resolve( __dirname, 'tests/e2e/specs' ),
};
