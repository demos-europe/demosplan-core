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

  glob.sync(config.bundlesPath + '*').forEach(dir => {
    const jsDir = 'client/js/bundles/' + dir

    if (fs.existsSync(jsDir)) {
      aliases['@' + dir.split('/').pop()] = jsDir
    }
  })

  return aliases
}

module.exports = { resolveAliases }
