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

	/*
	 * The PHPUnit suite shares tests-mysql with this environment and
	 * does not persist active_plugins to the DB, so any prior `npm run
	 * test-php` leaves the plugin inactive. Activate explicitly so the
	 * column actually renders and `wpll_user_register` actually fires
	 * when we create users below.
	 */
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'wp-last-login' );
	} );

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
		 * `wpll_user_register` seeds), and no meta row at all — the case
		 * behind issue #4 once the activation backfill is removed.
		 */
		wpCli( 'user meta update wpll_e2e_recent wp-last-login 1700000000' );
		wpCli( 'user meta update wpll_e2e_older wp-last-login 1600000000' );
		wpCli( 'user meta update wpll_e2e_seeded_zero wp-last-login 0' );
		wpCli( 'user meta delete wpll_e2e_no_meta wp-last-login' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllUsers();
	} );

	test( 'renders the Last Login column header', async ( { page } ) => {
		await page.goto( '/wp-admin/users.php' );

		// WP renders both thead and tfoot column headers; scope to thead.
		await expect(
			page.locator( 'thead#the-list, table.wp-list-table thead' ).first()
				.getByRole( 'columnheader', { name: /Last Login/ } )
		).toBeVisible();
	} );

	test( 'descending sort orders timestamped users before never-logged-in users', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/users.php?orderby=wp-last-login&order=desc'
		);

		const visible = await page
			.locator( '#the-list tr td.username strong a' )
			.allInnerTexts();

		for ( const username of usernames ) {
			expect( visible ).toContain( username );
		}

		const recent       = visible.indexOf( 'wpll_e2e_recent' );
		const older        = visible.indexOf( 'wpll_e2e_older' );
		const seeded       = visible.indexOf( 'wpll_e2e_seeded_zero' );
		const noMeta       = visible.indexOf( 'wpll_e2e_no_meta' );
		const lastLoggedIn = Math.max( recent, older );

		// Recent timestamp comes before older, both come before never-logged-in.
		expect( recent ).toBeLessThan( older );
		expect( lastLoggedIn ).toBeLessThan( seeded );
		expect( lastLoggedIn ).toBeLessThan( noMeta );
	} );

	test( 'ascending sort orders never-logged-in users before timestamped users', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/users.php?orderby=wp-last-login&order=asc'
		);

		const visible = await page
			.locator( '#the-list tr td.username strong a' )
			.allInnerTexts();

		for ( const username of usernames ) {
			expect( visible ).toContain( username );
		}

		const recent        = visible.indexOf( 'wpll_e2e_recent' );
		const older         = visible.indexOf( 'wpll_e2e_older' );
		const seeded        = visible.indexOf( 'wpll_e2e_seeded_zero' );
		const noMeta        = visible.indexOf( 'wpll_e2e_no_meta' );
		const firstLoggedIn = Math.min( recent, older );

		// Never-logged-in users come before timestamped, older before recent.
		expect( seeded ).toBeLessThan( firstLoggedIn );
		expect( noMeta ).toBeLessThan( firstLoggedIn );
		expect( older ).toBeLessThan( recent );
	} );
} );
