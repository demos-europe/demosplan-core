/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * DPConfirm - Display a confirmation dialog
 */
export default function Confirm () {
  window.dpconfirm = function (message, isUrlEncoded) {
    if (typeof isUrlEncoded !== 'undefined' && isUrlEncoded) {
      message = decodeURI(message)
    }
    return confirm(message)
  }
}
