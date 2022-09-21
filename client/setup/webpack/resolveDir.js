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
 * Resolve a dir relative to the repository root
 *
 * @param dir
 * @return {String}
 */
function resolveDir (dir) {
  return path.join(__dirname, '../../../', dir)
}

module.exports = { resolveDir }
