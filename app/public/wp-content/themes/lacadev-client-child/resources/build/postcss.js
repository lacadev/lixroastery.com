/**
 * The internal dependencies.
 */

/**
 * Setup PostCSS plugins.
 * Tailwind CSS v3: dùng require('tailwindcss') — đọc tailwind.config.js ở root.
 */
const plugins = [
  require('tailwindcss'),
  require('autoprefixer'),
  require('cssnano')({ preset: 'default' })
];

/**
 * Prepare the configuration.
 */
const config = {
  plugins,
};

module.exports = config;
