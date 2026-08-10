/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const path = require('path');

module.exports = {
  modules: [
    utils.srcScriptsPath(), 
    path.resolve(__dirname, '../../../node_modules'), // Bắt buộc nhận node_modules của child theme cho các file ở parent
    'node_modules'
  ],
  extensions: ['.js', '.jsx', '.json', '.css', '.scss'],
  alias: {
    // Riêng file config.json BẮT BUỘC dùng của Child Theme để bạn setup URL dev local (BrowserSync) và đè các biến màu sắc
    '@config': utils.themeRootPath('config.json'),
    '@scripts': path.resolve(__dirname, '../../../../lacadev-client/resources/scripts'),
    '@styles': path.resolve(__dirname, '../../../../lacadev-client/resources/styles'),
    '@images': path.resolve(__dirname, '../../../../lacadev-client/resources/images'),
    '@fonts': path.resolve(__dirname, '../../../../lacadev-client/resources/fonts'),
    '@child-fonts': path.resolve(__dirname, '../../fonts'),
    '@vendor': path.resolve(__dirname, '../../../../lacadev-client/resources/vendor'),
    // Phục vụ cho output của child theme
    '@dist': utils.distPath(),
    '@child': utils.srcPath(),
    '@parent': require('path').resolve(__dirname, '../../../../lacadev-client/resources'),
    '~': utils.themeRootPath('node_modules'),
    'isotope': 'isotope-layout',
  },
};
