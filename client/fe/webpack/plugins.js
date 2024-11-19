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
        to: `${config.publicPath}/video`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/img'),
        to: `${config.publicPath}/img`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/fonts'),
        to: `${config.publicPath}/fonts`
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/files'),
        to: `${config.publicPath}/files`
      },
      // Project specific videos, images, fonts and pdfs, may not exist
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/video`,
        to: `${config.publicPath}/video`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/img`,
        to: `${config.publicPath}/img`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/fonts`,
        to: `${config.publicPath}/fonts`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/files`,
        to: `${config.publicPath}/files`,
        noErrorOnMissing: true
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/pdf`,
        to: `${config.publicPath}/pdf`,
        noErrorOnMissing: true
      },
      {
        from: resolveDir('node_modules/@demos-europe/demosplan-ui/dist'),
        to: `${config.publicPath}/js/bundles`
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
