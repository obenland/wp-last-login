#!/usr/bin/env node

/**
 * Cross-platform npm-script entry for `wp-env run <container> --env-cwd=<plugin>`.
 *
 * Computes the plugin's mount path inside the container from the host
 * directory's basename, so the same scripts work in CI, regular checkouts,
 * and worktrees with non-canonical directory names — without relying on
 * POSIX command substitution that fails on Windows cmd.exe.
 *
 * An optional leading `--config <file>` is forwarded to wp-env as a global
 * flag (it must precede the `run` subcommand), letting test scripts target
 * the isolated test environment defined in `.wp-env.tests.json`.
 *
 * Usage:
 *     node bin/wp-env-run.js [--config <file>] <container> <command...>
 */

'use strict';

const { spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );

const args = process.argv.slice( 2 );

const globalFlags = [];
if ( args[ 0 ] === '--config' ) {
	const configFile = args[ 1 ];
	if ( ! configFile ) {
		process.stderr.write( '--config requires a file argument\n' );
		process.exit( 2 );
	}
	globalFlags.push( '--config', configFile );
	args.splice( 0, 2 );
}

const [ container, ...command ] = args;

if ( ! container || command.length === 0 ) {
	process.stderr.write(
		'Usage: node bin/wp-env-run.js [--config <file>] <container> <command...>\n'
	);
	process.exit( 2 );
}

const dir = path.basename( process.cwd() );
const envCwd = `/var/www/html/wp-content/plugins/${ dir }`;

const result = spawnSync(
	'npx',
	[
		'wp-env',
		...globalFlags,
		'run',
		container,
		`--env-cwd=${ envCwd }`,
		...command,
	],
	{ stdio: 'inherit', shell: process.platform === 'win32' }
);

if ( result.error ) {
	process.stderr.write( `${ result.error.message }\n` );
	process.exit( 1 );
}

process.exit( result.status ?? 1 );
