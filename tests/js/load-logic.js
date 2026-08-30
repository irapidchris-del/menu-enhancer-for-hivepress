/**
 * Loads assets/js/preview-logic.js the way a browser would, so the tests drive
 * the SHIPPED file rather than a copy of it.
 *
 * The file is plain browser JavaScript: it publishes one global and knows
 * nothing about Node. Rather than adding a module wrapper to shipped code just
 * so a test can require it - which is the test shape leaking into the product -
 * this evaluates the real file in a vm context whose only global is a stand-in
 * `window`, and hands back what it published. If the file ever stopped being
 * loadable in a browser, this shim would stop working too, which is the point.
 *
 * Node built-ins only. There is no package.json here and there must not be one.
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const SOURCE = path.join( __dirname, '..', '..', 'assets', 'js', 'preview-logic.js' );

const sandbox = { window: {} };

vm.createContext( sandbox );
vm.runInContext( fs.readFileSync( SOURCE, 'utf8' ), sandbox, { filename: SOURCE } );

if ( ! sandbox.window.amehpPreviewLogic ) {
	throw new Error( 'preview-logic.js did not publish window.amehpPreviewLogic' );
}

module.exports = {
	logic: sandbox.window.amehpPreviewLogic,
	source: SOURCE,
};
