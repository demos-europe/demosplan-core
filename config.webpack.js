/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const merge = require('webpack-merge').default
const CopyWebpackPlugin = require('copy-webpack-plugin')
const { DefinePlugin, NormalModuleReplacementPlugin } = require('webpack')
const { WebpackManifestPlugin } = require('webpack-manifest-plugin')

const { config } = require('./client/fe/config/config') // All our configuration

const resolveDir = require('./client/fe/webpack/util').resolveDir
const moduleRules = require('./client/fe/webpack/moduleRules').moduleRules
const bundleEntryPoints = require('./client/fe/webpack/bundleEntryPoints').bundleEntryPoints
const optimization = require('./client/fe/webpack/optimization').optimization

// Do not use destructuring as that may break the tooling on Windows
const { webpackDefaultPlugins, webpackDevOnlyPlugins, webpackProdOnlyPlugins } = require('./client/fe/webpack/plugins')

const baseConfig = {
  mode: config.mode,
  context: config.absoluteRoot,
  output: {
    filename: './[name].[contenthash:6].js',
    chunkFilename: './[name].[contenthash:6].js',
    clean: true
  },
  devtool: (config.isProduction) ? false : 'eval',
  plugins: (() => {
    let plugins = webpackDefaultPlugins
    switch (config.mode) {
      case 'development':
        plugins = plugins.concat(webpackDevOnlyPlugins)
        break

      case 'testing':
        break

      case 'production':
        plugins = plugins.concat(webpackProdOnlyPlugins)
        break

      default:
        throw new Error('No config mode defined')
    }

    return plugins
  })(),
  cache: true,
  performance: {
    hints: false
  },
  module: {
    rules: moduleRules
  },
  resolve: {
    fallback: {
      timers: require.resolve('timers-browserify'),
      /*
       * Prevent webpack from injecting useless setImmediate polyfill because Vue
       * source contains it (although it only uses it if it's native).
       */
      setImmediate: false,
      /*
       * Prevent webpack from injecting mocks to Node native modules
       * that do not make sense for the client
       */
      child_process: false,
      dgram: false,
      fs: false,
      net: false,
      tls: false,
      buffer: false,
      stream: false,
      path: false
    }
  }
}

const bundlesConfig = merge(baseConfig, {
  name: 'main',
  entry: () => {
    return {
      ...bundleEntryPoints(config.clientBundleGlob),
      style: config.stylesEntryPoint,
      'style-public': config.publicStylesEntryPoint,
      preflight: resolveDir('./client/css/preflight.css'),
      'demosplan-ui-style': resolveDir('./client/css/tailwind.css') // In the End we will get the styling from demosplan-ui
    }
  },
  output: {
    path: config.projectRoot + '/web/js/bundles',
    publicPath: config.urlPathPrefix + '/js/bundles/'
  },
  devtool: (config.isProduction) ? false : 'eval',
  resolve: {
    fullySpecified: false,
    extensions: ['...', '.js', '.vue', '.json', '.ts', '.tsx'],
    alias: {
      '@DpJs': config.absoluteRoot + 'client/js',
      vue: config.absoluteRoot + 'node_modules/@vue/compat/dist/vue.esm-bundler',
      // To Fix masterportal issues, we have to resolve some imports within olcs manually
      './olcs/olcsMap.js': config.absoluteRoot + 'node_modules/@masterportal/masterportalapi/src/maps/olcs/olcsMap.js',
      './olcs': config.absoluteRoot + 'node_modules/olcs/lib/olcs',
      'olcs/lib': config.absoluteRoot + 'node_modules/olcs/lib'
    }
  },
  optimization: optimization(),
  plugins: [
    new DefinePlugin({
      URL_PATH_PREFIX: JSON.stringify(config.urlPathPrefix), // Path prefix for dynamically generated urls
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false, // Vue CLI is in maintenance mode, and probably won't merge my PR to fix this in their tooling  https://github.com/vuejs/vue-cli/pull/7443
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: false
    }),
    new WebpackManifestPlugin({
      fileName: '../../dplan.manifest.json'
    }),
    // To Fix masterportal issues, we have to resolve some imports within olcs manually
    new NormalModuleReplacementPlugin(
      /\.\.\/core$/,
      (resource) => {
        if (resource.context.includes('olcs')) {
          resource.request = config.absoluteRoot + 'node_modules/olcs/lib/olcs/core.js'
        }
      }
    )
  ]
})

const stylesConfig = merge(baseConfig, {
  name: 'styles',
  entry: () => {
    return {
      style: config.stylesEntryPoint,
      'style-public': config.publicStylesEntryPoint,
      'demosplan-ui-style': './client/css/tailwind.css'
    }
  },
  output: {
    path: config.projectRoot + '/web/js/bundles',
    publicPath: config.urlPathPrefix + '/js/bundles/',
    clean: {
      /*
       * As the styles are emitted into the same output directory as
       * JS assets, we want tp prevent the "clean" option from erasing
       * everything. Instead, only style.[hash].js and style-public.[hash].js
       * should be replaced.
       * See https://webpack.js.org/configuration/output/#outputclean
       */
      keep (asset) {
        return !/style\.|style-public\./.test(asset)
      }
    }
  },
  devtool: (config.isProduction) ? false : 'eval',
  optimization: optimization(),
  plugins: [
    new DefinePlugin({
      URL_PATH_PREFIX: JSON.stringify(config.urlPathPrefix) // Path prefix for dynamically generated urls
    }),
    new WebpackManifestPlugin({
      fileName: '../../styles.manifest.json'
    })
  ]
})

const legacyBundlesConfig = {
  mode: 'production',
  name: 'legacy-bundles',
  entry: resolveDir('client/js/legacy/legacy.js'),
  output: {
    path: config.projectRoot + '/web/js/legacy',
    publicPath: config.urlPathPrefix + '/js/legacy/'
  },
  cache: true,
  performance: {
    hints: false
  },
  plugins: [
    new WebpackManifestPlugin({
      fileName: '../../legacy.manifest.json'
    }),
    new CopyWebpackPlugin({
      patterns: [
        {
          from: resolveDir('client/js/legacy/'),
          to: `${config.projectRoot}/web/js/legacy`
        }
      ]
    })
  ]
}

module.exports = [
  bundlesConfig,
  legacyBundlesConfig,
  stylesConfig
]
