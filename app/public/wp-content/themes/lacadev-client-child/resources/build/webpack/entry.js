/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const path = require('path');

// Trỏ trực tiếp source scripts về parent theme
const parentSrc = path.resolve(__dirname, '../../../../lacadev-client/resources/scripts');
const childSrc  = path.resolve(__dirname, '../../scripts');

module.exports = {
  'theme': path.join(parentSrc, 'theme/index.js'),
  'admin': path.join(parentSrc, 'admin/index.js'),
  'login': path.join(parentSrc, 'login/index.js'),
  'editor': path.join(parentSrc, 'editor/index.js'),
  // Child theme: SCSS override + custom JS (archive-gallery, etc.)
  // Dùng array entry để webpack bundle cả 2 vào dist/child.js + dist/styles/child.css
  'child': [
    utils.srcStylesPath('child.scss'),
    path.join(childSrc, 'theme/index.js'),
  ],
};
