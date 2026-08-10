/**
 * Resolves paths relative to the lacadev-client PARENT theme.
 *
 * The parent theme sits at ../lacadev-client/ relative to this child theme.
 * Using this helper instead of hardcoded `../../../lacadev-client/` paths
 * makes the child theme portable — move the child and only update PARENT_DIR here.
 *
 * Usage in webpack config:
 *   const parent = require('./lib/parent-path');
 *   parent.resources('styles')  // → .../lacadev-client/resources/styles
 *   parent.scripts('sw.js')     // → .../lacadev-client/resources/scripts/sw.js
 */

const path = require('path');

// Theme root = the directory containing package.json & resources/
const CHILD_ROOT  = path.resolve(__dirname, '../../../');
const PARENT_ROOT = path.resolve(CHILD_ROOT, '../lacadev-client');

module.exports.root       = (...parts) => path.resolve(PARENT_ROOT, ...parts);
module.exports.resources  = (...parts) => path.resolve(PARENT_ROOT, 'resources', ...parts);
module.exports.scripts    = (...parts) => path.resolve(PARENT_ROOT, 'resources', 'scripts', ...parts);
module.exports.styles     = (...parts) => path.resolve(PARENT_ROOT, 'resources', 'styles', ...parts);
module.exports.nodeModules = (...parts) => path.resolve(PARENT_ROOT, 'node_modules', ...parts);

module.exports.PARENT_ROOT = PARENT_ROOT;
module.exports.CHILD_ROOT  = CHILD_ROOT;
