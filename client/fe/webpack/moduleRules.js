/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const config = require('../config/config').config
const resolveDir = require('./util').resolveDir
const { purgeCSSPlugin } = require('@fullhuman/postcss-purgecss')

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

const postcssPrefixSelector = require('postcss-prefix-selector')({
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
})
const tailwindCss = require('tailwindcss')
const postcssFlexbugsFixes = require('postcss-flexbugs-fixes')
/*
 * The focus-visible pseudo class is disabled, as demosPlan does not polyfill :focus-visible. It can either not be
 * ignored because it conflicts with the way that :focus-visible is used within the `keyboard-focus` scss mixin.
 */
const postcssPresetEnv = require('postcss-preset-env')({
  features: {
    'focus-visible-pseudo-class': false
  }
})
const postcssPurgeCss = purgeCSSPlugin({
  ...config.purgeCss,
  defaultExtractor (content) {
    const contentWithoutStyleBlocks = content.replace(/<style[^]+?<\/style>/gi, '')
    return contentWithoutStyleBlocks.match(/[A-Za-z0-9-_/:]*[A-Za-z0-9-_/.[\]%]+/g) || []
  }
})
const autoprefixer = require('autoprefixer') // The autoprefixer must run after postcss-prefix-selector

const postCssPlugins = [
  postcssPrefixSelector,
  tailwindCss,
  postcssFlexbugsFixes,
  postcssPresetEnv,
  postcssPurgeCss,
  autoprefixer
]

const postCssPluginsWithoutPurgeCss = [
  postcssPrefixSelector,
  tailwindCss,
  postcssFlexbugsFixes,
  postcssPresetEnv,
  autoprefixer
]

/**
 * Module Rules for Webpack
 *
 * @type {({include: [], test: RegExp, loader: string, options: {formatter: (function(*=): *|string), cache: boolean, configFile: string, quiet: boolean, failOnError: boolean, useEslintrc: boolean}, enforce: string}|{test: RegExp, loader: string}|{include: String[], test: RegExp, use: {loader: string}, exclude: [String]}|{test: RegExp, use: [string], enforce: string}|{test: RegExp, use: [{loader: string}, {loader: *, options: {reloadAll: boolean, publicPath: string, hmr: boolean}}, {loader: string, options: {url: boolean}}, {loader: string, options: {sassOptions: {includePaths: [string, *]}}}]})[]}
 */
const moduleRules =
  [
    {
      test: /\.css$/,
      use: [MiniCssExtractPlugin.loader],
      exclude: [/client\/css\/(tailwind|preflight)\.css/] // Compiling and Purging happens in Tailwind config.
    },
    {
      test: /\.s?css$/,
      use: [
        MiniCssExtractPlugin.loader,
        {
          loader: 'css-loader',
          options: {
            /*
             * "importLoaders: 1" gets postcss-loader to also process css imports.
             * @see https://webpack.js.org/loaders/css-loader/#importloaders
             * However when omitting the .css extension from the @imported css files,
             * sass-loader will treat the import like a scss file, inlining it
             * instead of leaving the css @import unprocessed as a native import.
             * @see https://github.com/webpack-contrib/sass-loader/issues/101#issuecomment-128684387
             */
            importLoaders: config.isProduction === true ? 1 : 0,
            sourceMap: false,
            url: false
          }
        },
        {
          loader: 'postcss-loader',
          options: {
            postcssOptions: (loaderContext) => {
              // Do not pass 3rd party css through postCss in dev mode to gain some speed
              const skipPostCss = /node_modules/.test(loaderContext.resourcePath) && config.isProduction === false
              // Do not purge styles that are already purged by tailwindcss postcss plugin
              const tailwindProcessed = /client\/css\/(preflight|tailwind)\.css/.test(loaderContext.resourcePath)
              const postCssPluginsApplied = tailwindProcessed ? postCssPluginsWithoutPurgeCss : postCssPlugins

              return {
                plugins: skipPostCss ? [] : postCssPluginsApplied
              }
            },
            sourceMap: false
          }
        },
        {
          loader: 'sass-loader',
          options: {
            implementation: require('sass-embedded'),
            sassOptions: {
              additionalData: `$url-path-prefix: '${config.urlPathPrefix}';`,
              loadPaths: [
                config.projectRoot + 'web/',
                config.publicPath
              ]
            }
          }
        }
      ]
    },
    {
      test: /\.vue$/,
      loader: 'vue-loader',
      options: {
        compilerOptions: {
          compatConfig: {
            MODE: 2
          }
        }
      }
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
        return /[\\/]node_modules[\\/]/.test(path) && !/[\\/]node_modules[\\/](@sentry|popper|tooltip|fscreen)/.test(path)
      }
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
