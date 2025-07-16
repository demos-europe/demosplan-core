/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const ESLintWebpackPlugin = require('eslint-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const VueLoader = require('vue-loader')
const webpack = require('webpack') // Is webpack is webpack

const { config } = require('../config/config')

const { resolveDir } = require('../webpack/util')

const webpackDevOnlyPlugins = []
const webpackProdOnlyPlugins = []

const webpackDefaultPlugins = [
  new ESLintWebpackPlugin({
    baseConfig: require('../../../eslintrc'),
    cache: true,
    emitError: false,
    extensions: ['js', 'vue'],
    failOnError: false,
    files: ['demosplan'],
    quiet: true,
    useEslintrc: false
  }),
  // Global project variable consumed by application code
  new webpack.DefinePlugin({
    PROJECT: JSON.stringify(config.project)
  }),
  // Regarding the weird path declaration: we're at webpack.output.path here
  new MiniCssExtractPlugin({
    filename: '../../css/style.[contenthash:6].css',
    chunkFilename: '../../css/[id].css',
    ignoreOrder: false // Enable to remove warnings about conflicting order
  }),
  new CopyWebpackPlugin({
    patterns: [
      // Core videos, images and fonts
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/video'),
        to: `${config.projectRoot}/web/video`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/img'),
        to: `${config.projectRoot}/web/img`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/fonts'),
        to: `${config.projectRoot}/web/fonts`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/files'),
        to: `${config.projectRoot}/web/files`
      },
      // Project specific videos, images, fonts and pdfs, may not exist
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/video`,
        to: `${config.projectRoot}/web/video`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/img`,
        to: `${config.projectRoot}/web/img`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/fonts`,
        to: `${config.projectRoot}/web/fonts`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/files`,
        to: `${config.projectRoot}/web/files`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/pdf`,
        to: `${config.projectRoot}/web/pdf`,
        noErrorOnMissing: true
      },
      // Addon files, may not exist
      {
        from: 'addons/vendor/demos-europe/**/public/files/*',
        to: `${config.projectRoot}/web/files/[name][ext]`,
        force: true, // overwrite existing files
        noErrorOnMissing: true
      },
      {
        from: resolveDir('node_modules/@demos-europe/demosplan-ui/dist'),
        to: `${config.projectRoot}/web/js/bundles`
      }
    ]
  }),
  new VueLoader.VueLoaderPlugin(),

  // Provide Vue instance to all modules (is configured in InitVue.js before initialization).
  new webpack.ProvidePlugin({
    Vue: 'vue'
  })
]

module.exports = {
  webpackDefaultPlugins,
  webpackDevOnlyPlugins,
  webpackProdOnlyPlugins
}
