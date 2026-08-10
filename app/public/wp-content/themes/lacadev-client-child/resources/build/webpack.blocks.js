const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const fs = require('fs');

// === PARENT THEME RESOURCES PATH ===
const parentResourcesDir = path.resolve(__dirname, '../../../lacadev-client/resources');

/**
 * Patch sass-loader rules trong default config để thêm @parent alias
 * Dùng legacy Dart Sass importer (sass-loader v12 + @wordpress/scripts v27)
 */
function patchSassLoader(config) {
  if (!config.module || !config.module.rules) return config;

  const patchedRules = config.module.rules.map(rule => {
    if (!rule.use || !Array.isArray(rule.use)) return rule;

    const patchedUse = rule.use.map(loader => {
      const loaderPath = typeof loader === 'string' ? loader : loader?.loader;
      if (!loaderPath || !loaderPath.includes('sass-loader')) return loader;

      const existingOptions = (typeof loader === 'object' ? loader.options : {}) || {};

      return {
        ...(typeof loader === 'object' ? loader : { loader }),
        options: {
          ...existingOptions,
          sassOptions: {
            ...(existingOptions.sassOptions || {}),
            includePaths: [
              parentResourcesDir,
              path.join(parentResourcesDir, 'styles'),
            ],
            // Legacy importer: resolve @parent/* → parent theme resources/*
            importer: function(url, prev) {
              if (url.startsWith('@parent/')) {
                const resolvedPath = url.replace('@parent/', parentResourcesDir + '/');
                // Thử tìm file SCSS (có hoặc không có underscore prefix)
                const dir = path.dirname(resolvedPath);
                const base = path.basename(resolvedPath);
                const candidates = [
                  resolvedPath + '.scss',
                  resolvedPath + '.css',
                  path.join(dir, '_' + base + '.scss'),
                  resolvedPath,
                ];
                for (const candidate of candidates) {
                  if (fs.existsSync(candidate)) {
                    return { file: candidate };
                  }
                }
                return { file: resolvedPath };
              }
              return null;
            },
          },
        },
      };
    });

    return { ...rule, use: patchedUse };
  });

  return { ...config, module: { ...config.module, rules: patchedRules } };
}

// === Scan Child-specific blocks ===
// Hỗ trợ 2 cấp: block nằm phẳng ngay dưới root (chưa phân loại site) HOẶC
// nằm trong 1 thư mục "bucket" theo site (vd phucdainam-blocks-site/) — 1
// thư mục có block.json ngay trong nó = 1 block, không có = 1 bucket, quét
// thêm 1 cấp con để tìm block bên trong (không hỗ trợ lồng sâu hơn).
function scanBlockRelativePaths(rootDir) {
  if (!fs.existsSync(rootDir)) return [];

  const relativePaths = [];

  fs.readdirSync(rootDir).forEach(entry => {
    const entryPath = path.join(rootDir, entry);
    if (!fs.statSync(entryPath).isDirectory()) return;

    if (fs.existsSync(path.join(entryPath, 'block.json'))) {
      relativePaths.push(entry);
      return;
    }

    fs.readdirSync(entryPath).forEach(subEntry => {
      const subEntryPath = path.join(entryPath, subEntry);
      if (fs.statSync(subEntryPath).isDirectory() && fs.existsSync(path.join(subEntryPath, 'block.json'))) {
        relativePaths.push(path.join(entry, subEntry));
      }
    });
  });

  return relativePaths;
}

const childBlocksDir = path.resolve(__dirname, '../../block-gutenberg');

const childBlockConfigs = scanBlockRelativePaths(childBlocksDir).map(relPath => {
  return patchSassLoader({
    ...defaultConfig,
    entry: {
      index: path.join(childBlocksDir, relPath, 'index.js')
    },
    output: {
      ...defaultConfig.output,
      path: path.join(childBlocksDir, relPath, 'build'),
      filename: '[name].js'
    }
  });
});

// === Global Gutenberg bundle: Parent theme → dist/gutenberg/ của Child ===
const parentBlocksDir = path.resolve(__dirname, '../../../lacadev-client/block-gutenberg');
const gutenbergLegacyConfig = patchSassLoader({
  ...defaultConfig,
  entry: {
    index: path.join(parentBlocksDir, 'index.js'),
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(__dirname, '../../dist/gutenberg'),
    filename: '[name].js',
  },
  resolve: {
    ...defaultConfig.resolve,
    modules: [
      ...(defaultConfig.resolve?.modules || ['node_modules']),
      path.resolve(__dirname, '../../node_modules')
    ]
  }
});

module.exports = [...childBlockConfigs, gutenbergLegacyConfig];
