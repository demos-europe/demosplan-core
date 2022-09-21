/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Returns a nicely formatted string with a readable file size.
 * @param bytes
 * @param decimals
 * @return {string}
 */
export default function formatBytes (bytes, decimals) {
  if (bytes === 0) {
    return '0 Bytes'
  }
  const k = 1024
  const dm = decimals <= 0 ? 0 : decimals || 2
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}
