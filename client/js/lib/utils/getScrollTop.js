/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Returns the scroll offset aka. the distance between the upper document position and the upper viewport position.
 * @return {number}
 * @private
 */
export default function getScrollTop () {
  return Math.abs(parseInt(window.scrollY || window.scrollTop || document.getElementsByTagName('html')[0].scrollTop, 10))
}
