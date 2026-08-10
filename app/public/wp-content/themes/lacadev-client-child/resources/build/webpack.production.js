/**
 * External dependencies.
 */
const { ProvidePlugin, WatchIgnorePlugin } = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const ImageMinimizerPlugin = require('image-minimizer-webpack-plugin');

/**
 * Internal dependencies.
 */
const utils = require('./lib/utils');
const configLoader = require('./config-loader');
const postcss = require('./postcss');

/**
 * Setup the environment.
 */
const { env: envName } = utils.detectEnv();

/**
 * Setup Babel loader.
 */
const babelLoader = {
    loader: 'babel-loader',
    options: {
        cacheDirectory: false,
        comments: false,
        presets: [
            '@babel/preset-env'
        ],
    },
};

/**
 * Setup MiniCssExtractPlugin for CSS.
 */
const miniCss = new MiniCssExtractPlugin({
    filename: 'styles/[name].css',
});

/**
 * Setup Webpack plugins.
 */
const plugins = [
    new WatchIgnorePlugin({
        paths: [/node_modules/, /dist/],
    }),
    new ProvidePlugin({
        // $: 'jquery',
        // jQuery: 'jquery'
    }),
    miniCss,
    new WebpackManifestPlugin(),
    new BundleAnalyzerPlugin({
        analyzerMode: 'static',
        reportFilename: 'bundle-report.html',
        openAnalyzer: false
    }),
    new CopyWebpackPlugin({
        patterns: [
            {
                from: require('path').resolve(__dirname, '../../../lacadev-client/resources/scripts/sw.js'),
                to: utils.distPath('sw.js'),
            },
            {
                from: require('path').resolve(__dirname, '../../../lacadev-client/resources/scripts/lib/instantpage.js'),
                to: utils.distPath('instantpage.js'),
            },
            {
                from: require('path').resolve(__dirname, '../../../lacadev-client/resources/scripts/lib/smooth-scroll.min.js'),
                to: utils.distPath('smooth-scroll.min.js'),
            },
            {
                from: require('path').resolve(__dirname, '../../../lacadev-client/resources/scripts/lib/lazysizes.min.js'),
                to: utils.distPath('lazysizes.min.js'),
            },
        ],
    }),
];

module.exports = {
    optimization: {
        minimize: true, // Enable minification for production
        minimizer: [
            new TerserPlugin({
                parallel: true,
                extractComments: false,
                exclude: /\.min\.js$/, // Don't minify already minified files
                terserOptions: {
                    compress: {
                        drop_console: true,
                        drop_debugger: true,
                        pure_funcs: [],
                    },
                    mangle: {
                        // Rename variables but keep global functions safe
                        reserved: ['globalFunctions', 'themeData', 'ajaxurl_params', 'adminI18n', 'Swal', 'grecaptcha', 'LacaDashboard', 'lacaDashboard'],
                        // CRITICAL: Don't mangle property names (breaks alert.title, data.success, etc)
                        properties: false,
                    },
                    format: {
                        comments: false,
                    },
                }
            }),
            new CssMinimizerPlugin({
                minimizerOptions: {
                    preset: [
                        'default',
                        {
                            discardComments: { removeAll: true },
                        },
                    ],
                },
            }),
            new ImageMinimizerPlugin({
                minimizer: {
                    implementation: ImageMinimizerPlugin.sharpMinify,
                    options: {
                        encodeOptions: {
                            jpeg: { quality: 85, progressive: true },
                            png: { quality: 85, compressionLevel: 8 },
                            gif: {},
                            avif: { quality: 80 },
                        },
                    },
                },
                generator: [
                    // Tự động tạo thêm phiên bản WebP cho mọi ảnh JPEG/PNG
                    {
                        type: 'asset',
                        preset: 'webp',
                        implementation: ImageMinimizerPlugin.sharpGenerate,
                        options: {
                            encodeOptions: {
                                webp: { quality: 85 },
                            },
                        },
                    },
                ],
            })
        ],
        // Tắt splitChunks: WP không tự enqueue vendor chunks,
        // nên tất cả dependencies phải bundle vào entry file tương ứng.
        splitChunks: false,
    },
    entry: require('./webpack/entry'),
    output: {
        ...require('./webpack/output'),
        clean: true,
    },
    resolve: require('./webpack/resolve'),
    externals: require('./webpack/externals'),
    module: {
        rules: [
            // Hỗ trợ import glob cho các file JS/CSS/SCSS.
            {
                enforce: 'pre',
                test: /\.(js|jsx|css|scss|sass)$/i,
                use: 'import-glob'
            },
            // Xử lý file config.json.
            {
                test: utils.themeRootPath('config.json'),
                use: configLoader
            },
            // Xử lý JS qua Babel.
            {
                test: utils.tests.scripts,
                exclude: /node_modules/,
                use: babelLoader
            },
            // Xử lý SCSS/CSS qua MiniCssExtractPlugin.
            {
                test: utils.tests.styles,
                use: [
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            publicPath: '../', // Fix: CSS ở styles/, assets ở fonts/ hoặc images/ → cần ../
                        },
                    },
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: false,
                            importLoaders: 2
                        }
                    },
                    {
                        loader: 'postcss-loader',
                        options: {
                            sourceMap: false,
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: false,
                            api: 'modern-compiler',
                            sassOptions: {
                                includePaths: [
                                    require('path').resolve(__dirname, '../../../lacadev-client/resources'),
                                    require('path').resolve(__dirname, '../../../lacadev-client/resources/styles'),
                                    require('path').resolve(__dirname, '../../../node_modules')
                                ],
                                importers: [{
                                    findFileUrl(url) {
                                        if (url.startsWith('@parent/')) {
                                            const parentRes = require('path').resolve(__dirname, '../../../lacadev-client/resources');
                                            const resolved = require('path').join(parentRes, url.slice('@parent/'.length));
                                            return new URL(`file://${resolved}`);
                                        }
                                        return null;
                                    }
                                }],
                            }
                        }
                    }
                ]
            },
            // Xử lý hình ảnh với asset modules.
            {
                test: utils.tests.images,
                type: 'asset/resource',
                generator: {
                    filename: (pathData) => {
                        const hash = utils.filehash(pathData.filename).substr(0, 10);
                        return `images/[name].${hash}[ext]`;
                    }
                }
            },
            // Xử lý font với asset modules.
            {
                test: utils.tests.fonts,
                type: 'asset/resource',
                generator: {
                    filename: (pathData) => {
                        const hash = utils.filehash(pathData.filename).substr(0, 10);
                        return `fonts/[name].${hash}[ext]`;
                    }
                }
            }
        ]
    },
    plugins,
    mode: 'production',
    cache: false,
    bail: false,
    watch: false,
    devtool: false,
    performance: {
        maxEntrypointSize: 512000,
        maxAssetSize: 512000,
    },
};
