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
const config = require('../config/config').config

function resolveAliases () {
  const aliases = {
    '@DpJs': config.absoluteRoot + 'client/js',
    vue: 'vue/dist/vue.esm.js',
    'prosemirror-model': config.absoluteRoot + 'node_modules/prosemirror-model',
    'prosemirror-state': config.absoluteRoot + 'node_modules/prosemirror-state',
    'prosemirror-view': config.absoluteRoot + 'node_modules/prosemirror-view',
    'prosemirror-history': config.absoluteRoot + 'node_modules/prosemirror-history',
    'prosemirror-tables': config.absoluteRoot + 'node_modules/prosemirror-tables',
    'prosemirror-utils': config.absoluteRoot + 'node_modules/prosemirror-utils'
  }

  glob.sync(config.oldBundlesPath + 'Demos*Bundle').forEach(dir => {
    const jsDir = dir + '/Resources/client/js'

    if (fs.existsSync(jsDir)) {
      aliases['@' + dir.split('/').pop()] = jsDir
    }
  })

  return aliases
}

module.exports = { resolveAliases }
