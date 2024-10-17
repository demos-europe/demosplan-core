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
    vue: config.absoluteRoot + 'node_modules/@vue/compat/dist/vue.esm-bundler',
    './olcs/olcsMap.js':  config.absoluteRoot + 'node_modules/@masterportal/masterportalapi/src/maps/olcs/olcsMap.js',
    './olcs': config.absoluteRoot + 'node_modules/olcs/lib/olcs',
    'olcs/lib': config.absoluteRoot + 'node_modules/olcs/lib',
    'olcs/core': config.absoluteRoot + 'node_modules/olcs/lib/olcs/core',
    'olcs/print': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print',
    './olcs/print': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print',
    './print/computeRectangle': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print/computeRectangle.js',
    './print/rawCesiumMask': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print/rawCesiumMask.js',
    './print/takeCesiumScreenshot': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print/takeCesiumScreenshot.js',
    './print/drawCesiumMask': config.absoluteRoot + 'node_modules/olcs/lib/olcs/print/drawCesiumMask.js',

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
