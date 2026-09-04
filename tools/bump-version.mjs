#!/usr/bin/env node
/**
 * Bump the plugin version everywhere it is recorded.
 *
 *   npm run bump 1.5.1
 *   npm run bump 1.5.1 -- --dry-run
 *
 * Edits files only — no staging, no commit, no tag. Re-running with the same version is a no-op,
 * so it is safe to run twice.
 *
 * The version lives in five places and they have to agree: the plugin header WordPress reads,
 * Basebelles::$version (which cache-busts the enqueued CSS and JS), package.json, and the two
 * copies npm keeps in package-lock.json. The npm pair is delegated to `npm version` rather than
 * hand-edited, because the lockfile is npm's to format.
 *
 * Deliberately narrow: only basebelles.php is touched for PHP. features/class-comment-probation.php
 * and helpers/class-impostercide.php carry their own unrelated "Version: 4.0" headers, and a
 * broader search-and-replace would quietly rewrite those too.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const PLUGIN_DIR = path.resolve( import.meta.dirname, '..' );
const PLUGIN_FILE = path.join( PLUGIN_DIR, 'basebelles.php' );
const PACKAGE_FILE = path.join( PLUGIN_DIR, 'package.json' );

// X.Y.Z, optionally with a prerelease suffix such as 1.6.0-beta.1.
const VERSION_PATTERN = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/;

/** The plugin header line WordPress parses. */
const HEADER_RE = /^(\s*\*\s*Version:\s*)(\S+)(\s*)$/m;

/** The assignment in Basebelles::__construct(). */
const ASSIGNMENT_RE = /(self::\$version\s*=\s*')([^']+)(';)/;

function die( message ) {
	console.error( `\n  ✗ ${ message }\n` );
	process.exit( 1 );
}

/** Compare two dotted versions numerically, ignoring any prerelease suffix. */
function compare( a, b ) {
	const parts = ( version ) => version.split( '-' )[ 0 ].split( '.' ).map( Number );
	const [ left, right ] = [ parts( a ), parts( b ) ];

	for ( let i = 0; i < 3; i++ ) {
		if ( left[ i ] !== right[ i ] ) {
			return left[ i ] < right[ i ] ? -1 : 1;
		}
	}

	return 0;
}

const args = process.argv.slice( 2 );
const dryRun = args.includes( '--dry-run' );
const force = args.includes( '--force' );
const target = args.find( ( arg ) => ! arg.startsWith( '-' ) );

if ( ! target ) {
	console.error( `
  Usage: npm run bump <version>

    npm run bump 1.5.1
    npm run bump 1.6.0-beta.1
    npm run bump 1.5.1 -- --dry-run    show what would change
    npm run bump 1.5.0 -- --force      allow a lower version than the current one
` );
	process.exit( 1 );
}

if ( ! VERSION_PATTERN.test( target ) ) {
	die( `"${ target }" is not a version. Expected X.Y.Z, optionally with a suffix like 1.6.0-beta.1.` );
}

const pluginSource = fs.readFileSync( PLUGIN_FILE, 'utf8' );
const headerMatch = pluginSource.match( HEADER_RE );
const assignmentMatch = pluginSource.match( ASSIGNMENT_RE );

if ( ! headerMatch ) {
	die( 'Could not find the "Version:" header in basebelles.php. Has the plugin header changed?' );
}

if ( ! assignmentMatch ) {
	die( 'Could not find the self::$version assignment in basebelles.php. Has the constructor changed?' );
}

const current = headerMatch[ 2 ];

if ( current !== assignmentMatch[ 2 ] ) {
	console.warn(
		`  ! basebelles.php disagrees with itself: header says ${ current }, self::$version says ${ assignmentMatch[ 2 ] }.\n    Both will be set to ${ target }.`
	);
}

const phpNeedsWork = current !== target || assignmentMatch[ 2 ] !== target;

if ( phpNeedsWork && compare( target, current ) < 0 && ! force ) {
	die( `${ target } is lower than the current ${ current }. Pass --force if that is deliberate.` );
}

const updatedSource = pluginSource
	.replace( HEADER_RE, `$1${ target }$3` )
	.replace( ASSIGNMENT_RE, `$1${ target }$3` );

const packageJson = JSON.parse( fs.readFileSync( PACKAGE_FILE, 'utf8' ) );
const packageCurrent = packageJson.version;

// Nothing behind anywhere: say so once and stop, rather than reporting a bump that did nothing.
if ( ! phpNeedsWork && packageCurrent === target && ! dryRun ) {
	console.log( `\n  Already at ${ target } — nothing to do.\n` );
	process.exit( 0 );
}

if ( dryRun ) {
	console.log( `
  Dry run — nothing written.

    basebelles.php   Version:          ${ headerMatch[ 2 ] }  ->  ${ target }
    basebelles.php   self::$version    ${ assignmentMatch[ 2 ] }  ->  ${ target }
    package.json     version           ${ packageCurrent }  ->  ${ target }
    package-lock.json                  ${ packageCurrent }  ->  ${ target }   (via npm version)
` );
	process.exit( 0 );
}

fs.writeFileSync( PLUGIN_FILE, updatedSource );

// npm owns the lockfile's shape, so let it write both copies. --allow-same-version keeps this
// idempotent; --no-git-tag-version keeps the script out of git, which is the whole contract here.
if ( packageCurrent !== target ) {
	try {
		execFileSync(
			'npm',
			[ 'version', target, '--no-git-tag-version', '--allow-same-version' ],
			{ cwd: PLUGIN_DIR, stdio: 'pipe' }
		);
	} catch ( error ) {
		// The PHP file is already written, so say so rather than leaving it looking untouched.
		die(
			`basebelles.php was updated to ${ target }, but "npm version" failed:\n    ${ String( error.stderr || error.message ).trim() }\n    Set the version in package.json and package-lock.json by hand.`
		);
	}
}

console.log( `
  ✓ Bumped ${ current } -> ${ target }

    basebelles.php     Version: header and self::$version
    package.json       version
    package-lock.json  version (both copies)

  Nothing staged or committed. Review with:  git diff
` );
