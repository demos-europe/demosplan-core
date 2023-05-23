/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const glob = require('glob')

function bundleEntryPoints (clientBundleGlob) {
  const entries = {}

  glob.sync(clientBundleGlob).forEach(filename => {
    const parts = filename.split('/')

    const bundle = parts[parts.length - 2]
    const name = parts[parts.length - 1].replace('.js', '')

    entries[bundle + '-' + name] = filename
  })

  return entries
}

module.exports = { bundleEntryPoints }
