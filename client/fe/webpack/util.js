/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const path = require('path')

/**
 * Helper function wrapping console printing with silent mode
 *
 * @param msg
 */
function log (msg) {
  if (process.env.silent === 'false') {
    console.log(msg)
  }
}

/**
 * Resolve a dir relative to the repository root
 *
 * @param dir
 * @return {String}
 */
function resolveDir (dir) {
  return path.join(__dirname, '../../../', dir)
}

module.exports = { log, resolveDir }
