/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const CopyWebpackPlugin = require('copy-webpack-plugin')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const VueLoader = require('vue-loader')
const webpack = require('webpack') // Is webpack is webpack

const { config } = require('../config/config')

const { resolveDir } = require('../webpack/util')

const webpackDevOnlyPlugins = []
const webpackProdOnlyPlugins = []

const webpackDefaultPlugins = [
  // Global project variable consumed by application code
  new webpack.DefinePlugin({
    PROJECT: JSON.stringify(config.project),
  }),
  // Regarding the weird path declaration: we're at webpack.output.path here
  new MiniCssExtractPlugin({
    filename: '../../css/style.[contenthash:6].css',
    chunkFilename: '../../css/[id].css',
    ignoreOrder: false, // Enable to remove warnings about conflicting order
  }),
  new CopyWebpackPlugin({
    patterns: [
      // Core videos, images and fonts
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/video'),
        to: `${config.publicPath}/video`,
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/img'),
        to: `${config.publicPath}/img`,
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/fonts'),
        to: `${config.publicPath}/fonts`,
      },
      {
        from: resolveDir('demosplan/DemosPlanCoreBundle/Resources/public/files'),
        to: `${config.publicPath}/files`,
      },
      // Project specific videos, images, fonts and pdfs, may not exist
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/video`,
        to: `${config.publicPath}/video`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/img`,
        to: `${config.publicPath}/img`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/fonts`,
        to: `${config.publicPath}/fonts`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/files`,
        to: `${config.publicPath}/files`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/pdf`,
        to: `${config.publicPath}/pdf`,
        noErrorOnMissing: true,
      },
      // Per-project root-level static files (favicon, manifest, robots), may not exist
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/favicon.ico`,
        to: `${config.publicPath}/favicon.ico`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/favicon-16x16.png`,
        to: `${config.publicPath}/favicon-16x16.png`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/manifest.json`,
        to: `${config.publicPath}/manifest.json`,
        noErrorOnMissing: true,
      },
      {
        from: `${config.projectRoot}/app/Resources/DemosPlanCoreBundle/public/robots.txt`,
        to: `${config.publicPath}/robots.txt`,
        noErrorOnMissing: true,
      },
      // Addon files, may not exist
      {
        from: 'addons/vendor/demos-europe/**/public/files/*',
        to: `${config.publicPath}/files/[name][ext]`,
        force: true, // Overwrite existing files
        noErrorOnMissing: true,
      },
      {
        from: resolveDir('node_modules/@demos-europe/demosplan-ui/dist'),
        to: `${config.publicPath}/js/bundles`,
      },
    ],
  }),
  new VueLoader.VueLoaderPlugin(),

  // Provide Vue instance to all modules (is configured in InitVue.js before initialization).
  new webpack.ProvidePlugin({
    Vue: 'vue',
  }),
]

module.exports = {
  webpackDefaultPlugins,
  webpackDevOnlyPlugins,
  webpackProdOnlyPlugins,
}
