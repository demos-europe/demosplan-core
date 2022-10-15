/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const glob = require('glob')
const fs = require('fs')
const config = require('../config').config

function resolveAliases () {
  const aliases = {
    '@DpJs': config.absoluteRoot + 'client/js',
    vue: 'vue/dist/vue.esm.js'
  }

  glob.sync(config.oldBundlesPath + 'Demos*Bundle').forEach(dir => {
    const jsDir = dir + '/Resources/client/js'

    if (fs.existsSync(jsDir)) {
      aliases['@' + dir.split('/').pop()] = jsDir
    }
  })

  /**
   * This should not be needed as we're moving away from individual
   * aliases per bundle towards a single @DpJs alias at the root
   * of client/js.
   */
  // glob.sync(config.clientBundlesPath + '/*').forEach(dir => {
  //   if (fs.existsSync(dir)) {
  //     aliases['@' + dir.split('/').pop()] = dir
  //   }
  // })

  return aliases
}

module.exports = { resolveAliases }
