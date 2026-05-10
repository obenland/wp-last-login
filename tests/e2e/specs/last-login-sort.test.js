/**
 * External dependencies
 */
const { execSync } = require( 'node:child_process' );

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Run a wp-cli command inside the wp-env tests container.
 *
 * @param {string} command wp-cli arguments (without the leading `wp`).
 * @return {string} Command stdout, trimmed.
 */
function wpCli( command ) {
	return execSync( `npx wp-env run tests-cli wp ${ command }`, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'inherit' ],
	} ).trim();
}

test.describe( 'Users › sort by Last Login', () => {
	const usernames = [
		'wpll_e2e_recent',
		'wpll_e2e_older',
		'wpll_e2e_seeded_zero',
		'wpll_e2e_no_meta',
	];

	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllUsers();

		for ( const username of usernames ) {
			await requestUtils.createUser( {
				username,
				email: `${ username }@example.test`,
				password: 'password',
				roles: [ 'subscriber' ],
			} );
		}

		/*
		 * Spread the users across the four states the column has to handle:
		 * a recent timestamp, an older timestamp, a 0 sentinel (the value
		 * `wpll_user_register` seeds), and no meta row at all (the case the
		 * sort regression on issue #4 — once the activation backfill is
		 * removed, every site has users in this state).
		 */
		wpCli( `user meta update wpll_e2e_recent wp-last-login 1700000000` );
		wpCli( `user meta update wpll_e2e_older wp-last-login 1600000000` );
		wpCli( `user meta update wpll_e2e_seeded_zero wp-last-login 0` );
		wpCli( `user meta delete wpll_e2e_no_meta wp-last-login` );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllUsers();
	} );

	test( 'descending sort lists every user, including ones with no meta', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/users.php?orderby=wp-last-login&order=desc'
		);

		const rows = page.locator( '#the-list tr' );
		const usernameCells = rows.locator( 'td.username strong a' );
		const visible = await usernameCells.allInnerTexts();

		for ( const username of usernames ) {
			expect( visible ).toContain( username );
		}
	} );

	test( 'ascending sort lists every user, including ones with no meta', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/users.php?orderby=wp-last-login&order=asc'
		);

		const rows = page.locator( '#the-list tr' );
		const usernameCells = rows.locator( 'td.username strong a' );
		const visible = await usernameCells.allInnerTexts();

		for ( const username of usernames ) {
			expect( visible ).toContain( username );
		}
	} );
} );
