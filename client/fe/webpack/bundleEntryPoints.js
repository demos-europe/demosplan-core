/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const glob = require('glob')
const fs = require('fs')
const path = require('path')
const log = require('./util').log
const chalk = require('chalk')

/**
 * Collects all entry points for the client bundles.
 *
 * This function scans the file system for JavaScript files matching the provided glob pattern
 * and constructs an object where each key is a bundle name combined with the file name,
 * and the value is the path to the file. This is useful for dynamically generating entry points
 * in a Webpack configuration.
 *
 * Bundles can be overriden in projects by providing a file with the same name in the project directory.
 * To access project-specific components, use the alias `@DpJsProject` in your imports.
 *
 * @param config
 * @returns {{}}
 */
function bundleEntryPoints (config) {
  const entries = {}
  glob.sync(config.clientBundleGlob).forEach(filename => {
    const baseFilename = filename.replace(path.resolve(__dirname, config.relativeRoot), '')
    const projectBundlePath = path.resolve(__dirname, config.relativeRoot, `./projects/${config.project}${baseFilename}`)

    let addedProjectBundles = false
    if (fs.existsSync(projectBundlePath)) {
      if (!addedProjectBundles) {
        log(chalk.bold(`Project "${config.project}" overrides some bundles:\n`))
      }
      addedProjectBundles = true
      log(`\t- ${projectBundlePath}`)

      filename = projectBundlePath
    }

    if (addedProjectBundles) {
      log('\n')
    }

    let parts = filename.split('/')
    const bundle = parts[parts.length - 2]
    const name = parts[parts.length - 1].replace('.js', '')

    entries[bundle + '-' + name] = filename
  })

  // Scan addon bundles (generic, not addon-specific)
  const addonBundleGlob = path.resolve(__dirname, config.relativeRoot) + '/addons/vendor/demos-europe/demosplan-addon-*/client/js/bundles/**/*.js'
  glob.sync(addonBundleGlob).forEach(filename => {
    let parts = filename.split('/')
    const bundle = parts[parts.length - 2]
    const name = parts[parts.length - 1].replace('.js', '')
    const entryKey = bundle + '-' + name

    if (entries[entryKey]) {
      log(chalk.yellow(`\tAddon bundle "${entryKey}" overrides core bundle\n`))
    }

    entries[entryKey] = filename
  })

  return entries
}

module.exports = { bundleEntryPoints }
