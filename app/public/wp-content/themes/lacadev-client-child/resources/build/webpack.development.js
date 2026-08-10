/**
 * The external dependencies.
 */
const {WatchIgnorePlugin} = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const ManifestPlugin = require('webpack-manifest-plugin').WebpackManifestPlugin;
const CopyWebpackPlugin = require('copy-webpack-plugin');

/**
 * The internal dependencies.
 */
const utils = require('./lib/utils');
const configLoader = require('./config-loader');
const postcss = require('./postcss');
const browsersync = require('./browsersync');

/**
 * Setup the env.
 */
const {env: envName} = utils.detectEnv();

/**
 * Setup babel loader.
 */
const babelLoader = {
    loader : 'babel-loader',
    options: {
        cacheDirectory: true,
        comments      : false,
        presets       : [
            '@babel/preset-env'
        ],
    },
};

/**
 * Setup MiniCssExtractPlugin.
 */
const miniCss = new MiniCssExtractPlugin({
    filename: 'styles/[name].css',
});

/**
 * Setup webpack plugins.
 */
const plugins = [
    // Note: jQuery is provided by WordPress as external, don't use ProvidePlugin
    miniCss,
    browsersync,
    new ManifestPlugin(),
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

/**
 * Export the configuration.
 */
module.exports = {
    /**
     * The input.
     */
    entry: require('./webpack/entry'),

    /**
     * The output.
     */
    output: {
        ...require('./webpack/output'),
        clean: true,
    },

    /**
     * Resolve utilities.
     */
    resolve: require('./webpack/resolve'),

    /**
     * Resolve the dependencies that are available in the global scope.
     */
    externals: require('./webpack/externals'),

    /**
     * Setup the transformations.
     */
    module: {
        rules: [
            /**
             * Add support for blogs in import statements.
             */
            {
                enforce: 'pre',
                test   : /\.(js|jsx|css|scss|sass)$/,
                use    : 'import-glob',
            },

            /**
             * Handle the theme config.json.
             */
            {
                test: utils.themeRootPath('config.json'),
                use : configLoader,
            },

            /**
             * Handle scripts.
             */
            {
                test   : utils.tests.scripts,
                exclude: /node_modules/,
                use    : babelLoader,
            },

            /**
             * Handle styles.
             */
            {
                test: utils.tests.styles,
                use: [
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            publicPath: '../',  // Fix: CSS ở styles/, fonts ở fonts/ → cần ../fonts/
                        },
                    },
                    {
                        loader: 'css-loader',
                        options: {
                            sourceMap: true,
                        },
                    },
                    {
                        loader: 'postcss-loader',
                        options: {
                            sourceMap: true,
                        },
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: true,
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
                        },
                    },
                ],
            },

            /**
             * Handle images.
             */
            {
                test: utils.tests.images,
                type: 'asset/resource',
                generator: {
                    filename: (pathData) => {
                        const hash = utils.filehash(pathData.filename).substr(0, 10);
                        return `images/[name].${hash}[ext]`;
                    },
                },
            },

            /**
             * Handle fonts.
             */
            {
                test: utils.tests.fonts,
                type: 'asset/resource',
                generator: {
                    filename: (pathData) => {
                        const hash = utils.filehash(pathData.filename).substr(0, 10);
                        return `fonts/[name].${hash}[ext]`;
                    },
                },
            },
        ],
    },

    /**
     * Setup the transformations.
     */
    plugins,

    /**
     * Setup the development tools.
     */
    mode   : envName,
    cache  : true,
    bail   : false,
    watch  : true,
    devtool: 'source-map',
};