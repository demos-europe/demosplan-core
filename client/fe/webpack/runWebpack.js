/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin
const chalk = require('chalk')
const CliProgress = require('cli-progress')
const fs = require('fs')
const ProgressPlugin = require('webpack/lib/ProgressPlugin')
const webpack = require('webpack')
const { log, resolveDir } = require('./util')

/**
 * Callback function used by webpack to provide user feedback during the run
 *
 * @param options
 * @return {Function}
 */
function buildUserFeedbackFunction (options) {
  return (err, stats) => {
    showErrorMessage(err, stats)
    showWebpackStatisticsMessage(options, stats)
  }
}

/**
 * Configure the bundle analysis
 *
 * @param options
 * @param project
 * @param webpackConfig
 */
function configureBundleAnalysis (options, project, webpackConfig) {
  let analysisType = 'html'
  if (options.json && options.json.length > 0) {
    analysisType = 'json'
  }

  let reportFilename = resolveDir(`./client/stats/bundle_analysis_${project}_${process.env.NODE_ENV}.html`)

  // Save analysis to json if requested
  if (analysisType === 'json') {
    reportFilename = options.json
  }

  log(`Bundle analysis will be stored at ${chalk.bold(reportFilename)}`)
  const bundleAnalyzer = new BundleAnalyzerPlugin({
    analyzerMode: (analysisType === 'html') ? 'static' : 'json',
    defaultSizes: 'gzip',
    openAnalyzer: true,
    reportFilename,
    logLevel: 'silent'
  })

  webpackConfig[0].plugins.unshift(bundleAnalyzer)
}

function configureProgressBar (options, webpackRunner) {
  if (!options.parent.silent) {
    const progressBar = new CliProgress.SingleBar({
      format: `{message}[${chalk.green('{bar}')}] ${chalk.bold('{percentage}%')}`,
      hideCursor: true,
      barCompleteChar: '\u2588',
      barIncompleteChar: '\u2591'
    })

    createProgressPlugin(options, progressBar, webpackRunner)

    // Register an exit handler to not mess up the terminal after watch
    const termSignals = ['SIGINT', 'SIGTERM']
    termSignals.forEach(
      signal => {
        process.on(signal, () => {
          progressBar.stop()
          process.exit(0)
        })
      }
    )
  }
}

function createProgressPlugin (options, progressBar, webpackRunner) {
  new ProgressPlugin((percentage, msg) => {
    if (progressBar.startTime === null) {
      progressBar.start(1, 0, {
        message: ''
      })
    }

    progressBar.update(percentage, {
      // Minor hack to remove the build step number from the webpack message
      message: (msg !== '[0] ') ? msg.substr(4) + ' ' : ''
    })

    if (percentage === 1.0 && options.mode !== 'watch') {
      progressBar.stop()

      // Yay, we done
      log(chalk.green('\\o/'))
    }
  }).apply(webpackRunner)
}

/**
 * Command execution function builder for the webpack build
 *
 * This builds a function which will be called by fe when a webpack build
 * is to be run. It needs the desired build mode ('build' or 'watch') as
 * input.
 *
 * @param mode
 * @return {Function}
 */
function runWebpack (mode) {
  return (project, options) => {
    // Setup environment for webpack
    process.env.NODE_ENV = (options.prod) ? 'production' : 'development'
    process.env.project = project
    process.env.silent = (options.parent.silent) ? 'true' : 'false'
    options.mode = mode

    log(chalk.green('Loading webpack config'))

    /*
     * Load the webpack config with error handling as there may occur errors during
     * the backend-integration commands which must be caught and
     * abort the build process
     */
    const webpackConfig = webpackConfiguration(options)

    // Bundle analysis
    if (options.analyze) {
      configureBundleAnalysis(options, project, webpackConfig)
    }

    // Create the webpack runner object
    const webpackRunner = webpack(webpackConfig)

    // Patch in the progress bar if needed
    configureProgressBar(options, webpackRunner)

    // Create the callback function for user feedback
    const userFeedbackCallback = buildUserFeedbackFunction(options)

    showWebpackRunMessage(userFeedbackCallback, mode, project, webpackConfig, webpackRunner)
  }
}

function showErrorMessage (err, stats) {
  if (err || stats.hasErrors()) {
    log(chalk.red('Build encountered errors'))

    // Handle webpacks error objects. why are there two? i bet they don't even know
    if (err) {
      log(chalk.red(err.stack || err))

      if (err.details) {
        log(err.details)
      }

      return
    }

    const info = stats.toJson()

    if (stats.hasErrors()) {
      for (const error of info.errors) {
        log(chalk.red(error.message))
      }
    }

    if (stats.hasWarnings()) {
      for (const warning of info.warnings) {
        log(chalk.yellow(warning.message))
      }
    }
  }
}

function showWebpackRunMessage (userFeedbackCallback, mode, project, webpackConfig, webpackRunner) {
  if (mode === 'build') {
    log(chalk.green(`Begin ${chalk.bold('building')} frontend assets for ${project} in ${chalk.bold(webpackConfig[0].mode)} mode`))
    webpackRunner.run(userFeedbackCallback)
  }

  if (mode === 'watch') {
    log(chalk.green(`Begin ${chalk.bold('watching')} frontend assets for ${project} in ${chalk.bold(webpackConfig[0].mode)} mode`))
    webpackRunner.watch({
      aggregateTimeout: 1000
    }, userFeedbackCallback)
  }
}

function showWebpackStatisticsMessage (options, stats) {
  if (options.stats) {
    const webpackStatisticsOptions = {
      chunks: false
    }

    if (options.json && options.json.length > 0) {
      log(chalk.yellow(`Writing webpack build statistics to ${options.json}`))
      const json = stats.toJson(webpackStatisticsOptions)
      fs.writeFileSync(options.json, JSON.stringify(json))
    } else {
      log(stats.toString({
        colors: true,
        ...webpackStatisticsOptions
      }))
    }
  }
}

function webpackConfiguration (options) {
  let webpackConfig = null
  try {
    webpackConfig = require(resolveDir('config.webpack'))
  } catch (e) {
    console.error(e.message)
    return 1
  }

  // Drop unneeded configurations for current build config
  webpackConfig = webpackConfig.filter(config => !(options.onlyStyles && config.name !== 'styles'))

  // Set build mode for all webpack configs in the webpack-multi setup
  webpackConfig.forEach(config => {
    config.mode = 'development'
    if (options.prod) {
      config.mode = 'production'
    }
  })

  return webpackConfig
}

module.exports = runWebpack
