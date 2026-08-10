/**
 * PostCSS config — được pick up tự động bởi @wordpress/scripts (webpack.blocks.js).
 *
 * Ghi chú: webpack.production.js / webpack.development.js dùng
 * resources/build/postcss.js riêng (không đọc file này).
 */
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
};
