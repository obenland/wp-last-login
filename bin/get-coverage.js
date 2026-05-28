#!/usr/bin/env node

/**
 * Cross-platform npm-script entry for `npm run get-coverage`.
 *
 * Reads coverage.xml from the test environment's `cli` container and writes
 * it to the host workspace. Avoids the POSIX `> coverage.xml` redirect so the
 * script works on Windows cmd.exe.
 */

'use strict';

const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const dir = path.basename( process.cwd() );
const envCwd = `/var/www/html/wp-content/plugins/${ dir }`;

const result = spawnSync(
	'npx',
	[
		'wp-env',
		'--config',
		'.wp-env.tests.json',
		'run',
		'cli',
		`--env-cwd=${ envCwd }`,
		'cat',
		'coverage.xml',
	],
	{ encoding: 'utf8', shell: process.platform === 'win32' }
);

if ( result.error ) {
	process.stderr.write( `${ result.error.message }\n` );
	process.exit( 1 );
}

if ( result.status !== 0 ) {
	process.stderr.write( result.stderr || '' );
	process.exit( result.status ?? 1 );
}

fs.writeFileSync( 'coverage.xml', result.stdout );
