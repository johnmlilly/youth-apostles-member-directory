/**
 * ---------------------------------------------------------------------
 * WHAT THIS SCRIPT DOES
 *
 * The plugin's version number lives in three places that must agree:
 *
 *   1. package.json                       -> what npm knows about
 *   2. the plugin header docblock         -> what WordPress shows in
 *                                            the Plugins admin screen
 *   3. the YAMD_VERSION constant          -> what wp_enqueue_script uses
 *                                            to cache-bust build/index.js
 *
 * (3) is the one that bites. Browsers cache the built JS/CSS against that
 * version string, so if it does not change, returning visitors keep running
 * the old build even after a successful upload.
 *
 * This script makes package.json the single source of truth and rewrites
 * (2) and (3) from it.
 *
 * HOW IT RUNS
 *
 * You never call it directly. package.json declares a script named
 * "version", which is an npm lifecycle hook: `npm version patch` bumps
 * package.json, then runs that hook BEFORE creating its git commit and tag.
 * Anything the hook stages with `git add` gets swept into that same commit,
 * which is why the hook ends with a `git add` of the plugin file.
 *
 *   npm version patch
 *     -> package.json 0.4.0 becomes 0.4.1
 *     -> this script rewrites both PHP spots to 0.4.1
 *     -> git add stages the plugin file
 *     -> npm commits all of it and tags v0.4.1
 * ---------------------------------------------------------------------
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// import.meta.url is this file's own location. Node ES modules have no
// __dirname, so this is the standard way to resolve paths relative to the
// script rather than to whatever directory npm was invoked from.
const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const pluginFile = join( root, 'youth-apostles-member-directory.php' );

const { version } = JSON.parse( readFileSync( join( root, 'package.json' ), 'utf8' ) );

if ( ! /^\d+\.\d+\.\d+/.test( version ) ) {
	throw new Error( `package.json version is not usable: ${ version }` );
}

const replacements = [
	{
		label: 'plugin header',
		// The header line WordPress parses, e.g. " * Version: 0.4.0".
		pattern: /^(\s*\*\s*Version:\s*).*$/m,
		replacement: `$1${ version }`,
	},
	{
		label: 'YAMD_VERSION',
		// Captures the text on either side of the quoted version so the
		// replacement can put the new number back between them.
		pattern: /(define\(\s*'YAMD_VERSION',\s*')[^']*(')/,
		replacement: `$1${ version }$2`,
	},
];

let source = readFileSync( pluginFile, 'utf8' );

for ( const { label, pattern, replacement } of replacements ) {
	if ( ! pattern.test( source ) ) {
		// Throwing aborts `npm version` before it commits. Silently skipping
		// would be worse: the release would ship with the version moved in
		// some files but not others, which is the exact bug this prevents.
		throw new Error( `Could not find the ${ label } version in ${ pluginFile }` );
	}
	source = source.replace( pattern, replacement );
}

writeFileSync( pluginFile, source );
console.log( `Synced plugin version to ${ version }` );
