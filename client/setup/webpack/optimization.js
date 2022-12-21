/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const TerserPlugin = require('terser-webpack-plugin')
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin')

const { config } = require('../config')

const chunkSplitting = {
  cacheGroups: {
    core: {
      name: 'core',
      chunks: 'all',
      enforce: true,
      test (module) {
        const nodeModulesCoreTest = /[\\/]node_modules[\\/](@demos-europe|vue[\\/]|vuex|v-tooltip|portal-vue|axios|popper|dayjs|dompurify|lodash|@efrane|@sentry|core-js|qs|tooltip|deep-object-diff|js-base64)/
        const baseModulesCoreTest = /[\\/]demosplan[\\/]DemosPlanCoreBundle[\\/]Resources[\\/]client[\\/]js[\\/](InitVue\.js|VueConfigCore\.js)/
        return module.resource && (nodeModulesCoreTest.test(module.resource) || baseModulesCoreTest.test(module.resource))
      },
      priority: 2,
      reuseExistingChunk: true
    },
    common: {
      name: 'common',
      chunks: 'all',
      enforce: true,
      test: /[\\/]client[\\/]js[\\/]generated[\\/](translations\.json|routes\.json)/,
      priority: 2,
      reuseExistingChunk: true
    },
    bs: {
      name: 'bs',
      chunks: 'all',
      enforce: true,
      test: /([\\/]demosplan[\\/]DemosPlanCoreBundle[\\/]Resources[\\/]client[\\/]js[\\/]lib)|([\\/]client[\\/]js[\\/]lib)/,
      priority: 2,
      reuseExistingChunk: true
    },
    tiptap: {
      name: 'tiptap',
      chunks: 'all',
      enforce: true,
      test: /[\\/]node_modules[\\/](tiptap|prosemirror)/,
      priority: -5,
      reuseExistingChunk: true
    },
    ol: {
      name: 'ol',
      chunks: 'all',
      enforce: true,
      test (module) {
        const path = require('path')
        return module.resource &&
          module.resource.endsWith('.js') &&
          module.resource.includes(`${path.sep}node_modules${path.sep}ol${path.sep}`)
      },
      priority: -5,
      reuseExistingChunk: true
    },
    d3: {
      name: 'd3',
      chunks: 'all',
      enforce: true,
      test: /[\\/]node_modules[\\/]d3.*[\\/]/,
      priority: -5,
      reuseExistingChunk: true
    },
    leaflet: {
      name: 'leaflet',
      chunks: 'all',
      enforce: true,
      test: /[\\/]node_modules[\\/](leaflet|vue2-leaflet|leaflet.markercluster)[\\/]/,
      priority: -5,
      reuseExistingChunk: true
    }
  }
}

function optimization () {
  let optimization = {
    splitChunks: chunkSplitting,
    runtimeChunk: 'single',
    minimize: false
  }

  if (config.isProduction === true) {
    optimization = Object.assign(optimization, {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          test: /\.js($|\?)/i,
          parallel: true,
          terserOptions: {
            ecma: 2016,
            compress: {
              drop_console: true,
              drop_debugger: true,
              global_defs: {
                PROJECT: config.project
              }
            },
            format: {
              comments: false,
              indent_level: 2
            }
          },
          extractComments: false
        }),
        new CssMinimizerPlugin()
      ]
    }, optimization)
  }

  return optimization
}

module.exports = { optimization }
