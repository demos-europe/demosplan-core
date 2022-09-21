/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const glob = require('glob')

function bundleEntryPoints (bundleEntryPointsGlob) {
  const entries = {}

  glob.sync(bundleEntryPointsGlob).forEach(filename => {
    const bundle = filename.replace(/^.*DemosPlan/, '').replace(/Bundle.*$/, '').toLowerCase()
    const name = filename.replace(/^.*bundles\//, '').replace('.js', '')

    entries[bundle + '-' + name] = filename
  })

  return entries
}

module.exports = { bundleEntryPoints }
