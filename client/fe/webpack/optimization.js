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

const { config } = require('../config/config')
const path = require('path')

const chunkSplitting = {
  cacheGroups: {
    core: {
      name: 'core',
      chunks: 'all',
      minChunks: 40,
      enforce: true,
      test: /[\\/]node_modules[\\/]|[\\/]demosplan[\\/]DemosPlanCoreBundle[\\/]Resources[\\/]client[\\/]js[\\/](InitVue)\.js/,
      priority: 2
    },
    common: {
      name: 'common',
      chunks: 'all',
      enforce: true,
      test: /[\\/]client[\\/]js[\\/]generated[\\/](translations|routes)\.json/,
      priority: 2
    },
    bs: {
      name: 'bs',
      chunks: 'all',
      enforce: true,
      test: /([\\/]demosplan[\\/]DemosPlanCoreBundle[\\/]Resources[\\/]client[\\/]js[\\/]lib)|([\\/]client[\\/]js[\\/]lib)/,
      priority: 2
    },
    ol: {
      name: 'ol',
      chunks: 'all',
      enforce: true,
      test (module) {
        return module.resource &&
          (
            (module.resource.endsWith('.js') && module.resource.includes(`${path.sep}node_modules${path.sep}ol${path.sep}`)) ||
            module.resource.includes(`${path.sep}node_modules${path.sep}@masterportal${path.sep}`) ||
            module.resource.includes(`${path.sep}node_modules${path.sep}ol-mapbox-style${path.sep}`) ||
            module.resource.includes(`${path.sep}node_modules${path.sep}olcs${path.sep}`) ||
            module.resource.includes(`${path.sep}node_modules${path.sep}xmlbuilder${path.sep}`) ||
            module.resource.includes(`${path.sep}node_modules${path.sep}sax${path.sep}`)
          )
      },
      priority: -5
    },
    d3: {
      name: 'd3',
      chunks: 'all',
      enforce: true,
      test: /[\\/]node_modules[\\/]d3.*[\\/]/,
      priority: -5
    },
    leaflet: {
      name: 'leaflet',
      chunks: 'all',
      enforce: true,
      test: /[\\/]node_modules[\\/](leaflet|vue2-leaflet|leaflet.markercluster)[\\/]/,
      priority: -5
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
