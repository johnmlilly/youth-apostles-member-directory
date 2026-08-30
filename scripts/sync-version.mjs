import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// WordPress reads the version from the plugin header, and the enqueue code
// cache-busts from YAMD_VERSION, so both have to track package.json or a
// release ships stale assets to browsers that already cached the old build.
const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const pluginFile = join( root, 'youth-apostles-member-directory.php' );

const { version } = JSON.parse( readFileSync( join( root, 'package.json' ), 'utf8' ) );

if ( ! /^\d+\.\d+\.\d+/.test( version ) ) {
	throw new Error( `package.json version is not usable: ${ version }` );
}

const replacements = [
	{
		label: 'plugin header',
		pattern: /^(\s*\*\s*Version:\s*).*$/m,
		replacement: `$1${ version }`,
	},
	{
		label: 'YAMD_VERSION',
		pattern: /(define\(\s*'YAMD_VERSION',\s*')[^']*(')/,
		replacement: `$1${ version }$2`,
	},
];

let source = readFileSync( pluginFile, 'utf8' );

for ( const { label, pattern, replacement } of replacements ) {
	if ( ! pattern.test( source ) ) {
		// Failing loudly beats silently shipping a version that only moved in one file.
		throw new Error( `Could not find the ${ label } version in ${ pluginFile }` );
	}
	source = source.replace( pattern, replacement );
}

writeFileSync( pluginFile, source );
console.log( `Synced plugin version to ${ version }` );
