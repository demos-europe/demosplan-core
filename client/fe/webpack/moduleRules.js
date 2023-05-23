/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const config = require('../config/config').config
const resolveDir = require('./util').resolveDir

/**
 * List of modules which need to be transpiled with Babel
 *
 * @type {String[]}
 */
let transpiledModules = [
  // Core modules first
  resolveDir('demosplan'),
  resolveDir('projects')
]

// Npm-managed modules that require transpilation
transpiledModules = transpiledModules.concat(
  [
    '@efrane/vuex-json-api',
    '@mapbox', // Ol sub-dependency
    'vue-resize'
  ].map(nodeModule => resolveDir('node_modules/' + nodeModule))
)

const postcssPresetEnv = require('postcss-preset-env')

const postCssPlugins = [
  require('postcss-prefix-selector')({
    prefix: config.cssPrefix,
    exclude: [
      ...config.cssPrefixExcludes.defaultExcludePatterns,
      ...config.cssPrefixExcludes.externalClassPrefixes
    ],
    transform (prefix, selector) {
      selector = selector.split(' ').map((selector) => {
        if (selector[0] !== '.') {
          // We only want to prefix classes
          return selector
        }

        // Patch OpenLayers & Tooltips, which got excluded by a too aggressive regex
        if (selector.match(/\.([\w-_]-ol-[\w-_]+)/) || selector.match(/\.(o-tooltip[\w-_]*)/)) {
          return prefix + selector.substring(1)
        }

        return prefix + selector.substring(1)
      }).join(' ')

      return selector
    },
    ignoreFiles: [/.+style\.scss/]
  }),
  require('postcss-flexbugs-fixes'),
  /*
   * The focus-visible pseudo class is disabled, as demosPlan does not polyfill :focus-visible. It can either not be
   * ignored because it conflicts with the way that :focus-visible is used within the `keyboard-focus` scss mixin.
   */
  postcssPresetEnv({
    features: {
      'focus-visible-pseudo-class': false
    }
  }),
  // The autoprefixer must run after postcss-prefix-selector
  require('autoprefixer')
]

if (config.cssPurge.enabled) {
  const purgeCss = [
    '@fullhuman/postcss-purgecss',
    {
      content: config.cssPurge.paths,
      defaultExtractor (content) {
        const contentWithoutStyleBlocks = content.replace(/<style[^]+?<\/style>/gi, '')
        return contentWithoutStyleBlocks.match(/[A-Za-z0-9-_/:]*[A-Za-z0-9-_/]+/g) || []
      },
      safelist: config.cssPurge.safelist
    }
  ]
  postCssPlugins.splice(-2, 0, purgeCss)
}

/**
 * Module Rules for Webpack
 *
 * @type {({include: [], test: RegExp, loader: string, options: {formatter: (function(*=): *|string), cache: boolean, configFile: string, quiet: boolean, failOnError: boolean, useEslintrc: boolean}, enforce: string}|{test: RegExp, loader: string}|{include: String[], test: RegExp, use: {loader: string}, exclude: [String]}|{test: RegExp, use: [string], enforce: string}|{test: RegExp, use: [{loader: string}, {loader: *, options: {reloadAll: boolean, publicPath: string, hmr: boolean}}, {loader: string, options: {url: boolean}}, {loader: string, options: {sassOptions: {includePaths: [string, *]}}}]})[]}
 */
const moduleRules =
  [
    {
      test: /\.css$/,
      use: [
        MiniCssExtractPlugin.loader,
        'vue-loader'
      ]
    },
    {
      test: /\.vue$/,
      loader: 'vue-loader'
    },
    {
      test: /\.js$/,
      include: transpiledModules,
      exclude: [
        resolveDir('demosplan/DemosPlanCoreBundle/Resources/client/js/legacy')
      ],
      use: {
        loader: 'babel-loader'
      }
    },
    {
      test: /\.js$/,
      use: ['source-map-loader'],
      enforce: 'pre',
      exclude: (path) => {
        return /[\\/]node_modules[\\/]/.test(path) && !/[\\/]node_modules[\\/](@sentry|popper|portal-vue|tooltip|fscreen)/.test(path)
      }
    },
    {
      test: /\.s?css$/,
      use: [
        {
          loader: 'vue-style-loader'
        },
        {
          loader: MiniCssExtractPlugin.loader,
          // Output path is declared in the plugin due to weird webpack path declarations
          options: {
            /*
             * You can specify a publicPath here
             * by default it uses publicPath in webpackOptions.output
             */
            esModule: false,
            publicPath: '../../css/'
          }
        },
        {
          loader: 'css-loader',
          options: {
            url: false
          }
        },
        {
          loader: 'postcss-loader',
          options: {
            postcssOptions: {
              plugins: postCssPlugins
            }
          }
        },
        {
          loader: 'sass-loader',
          options: {
            implementation: require('sass'),
            additionalData: `$url-path-prefix: '${config.urlPathPrefix}';`,
            sassOptions: {
              includePaths: [
                config.projectRoot + 'web/',
                config.publicPath
              ]
            }
          }
        }
      ]
    },
    {
      test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
      type: 'asset/resource',
      generator: {
        filename: '[name].[ext]',
        outputPath: 'fonts/'
      }
    },
    {
      test: /\.(png|jp(e)?g|gif|svg)$/,
      type: 'asset/resource',
      generator: {
        filename: '[name].[ext]',
        outputPath: 'img/'
      }
    }
  ]

module.exports = { moduleRules }
