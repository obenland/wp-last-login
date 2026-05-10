#!/usr/bin/env node

/**
 * Cross-platform npm-script entry for `wp-env run <container> --env-cwd=<plugin>`.
 *
 * Computes the plugin's mount path inside the container from the host
 * directory's basename, so the same scripts work in CI, regular checkouts,
 * and worktrees with non-canonical directory names — without relying on
 * POSIX command substitution that fails on Windows cmd.exe.
 *
 * Usage:
 *     node bin/wp-env-run.js <container> <command...>
 */

'use strict';

const { spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );

const [ container, ...command ] = process.argv.slice( 2 );

if ( ! container || command.length === 0 ) {
	process.stderr.write(
		'Usage: node bin/wp-env-run.js <container> <command...>\n'
	);
	process.exit( 2 );
}

const dir = path.basename( process.cwd() );
const envCwd = `/var/www/html/wp-content/plugins/${ dir }`;

const result = spawnSync(
	'npx',
	[ 'wp-env', 'run', container, `--env-cwd=${ envCwd }`, ...command ],
	{ stdio: 'inherit', shell: process.platform === 'win32' }
);

if ( result.error ) {
	process.stderr.write( `${ result.error.message }\n` );
	process.exit( 1 );
}

process.exit( result.status ?? 1 );
