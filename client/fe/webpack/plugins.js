/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const ESLintWebpackPlugin = require('eslint-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')
const { CleanWebpackPlugin } = require('clean-webpack-plugin')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const VueLoader = require('vue-loader')
const webpack = require('webpack') // Is webpack is webpack

const { config } = require('../config/config')

const { resolveDir } = require('../webpack/util')

const webpackDevOnlyPlugins = []
const webpackProdOnlyPlugins = []

const webpackDefaultPlugins = [
  new CleanWebpackPlugin(),
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
  // Globale projectvariable für JS
  new webpack.DefinePlugin({
    PROJECT: JSON.stringify(config.project)
  }),
  /*
   * New AssetsPlugin({
   *   prettyPrint: true,
   *   entrypoints: true
   * }),
   * regarding the weird path declaration: we're at webpack.output.path here
   */
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
      {
        from: resolveDir('node_modules/@demos-europe/demosplan-ui/dist'),
        to: `${config.projectRoot}/web/js/bundles`
      }
    ]
  }),
  new VueLoader.VueLoaderPlugin(),

  // Provide configured Vue and Bus instances to all modules
  new webpack.ProvidePlugin({
    Vue: 'vue'
  })
]

module.exports = {
  webpackDefaultPlugins,
  webpackDevOnlyPlugins,
  webpackProdOnlyPlugins
}
