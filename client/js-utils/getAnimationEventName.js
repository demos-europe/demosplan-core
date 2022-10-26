/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Returns the browser specific prefixed name for `animationend` event.
 *
 * @return {(string|boolean)} Name of animationend event or false, if none of the tested names is supported
 * @see {@link where it comes from} https://jonsuh.com/blog/detect-the-end-of-css-animations-and-transitions-with-javascript/
 */
export default function getAnimationEventName () {
  const testElement = document.createElement('fakeelement')
  const animations = {
    animation: 'animationend',
    OAnimation: 'oAnimationEnd',
    MozAnimation: 'animationend',
    WebkitAnimation: 'webkitAnimationEnd'
  }
  let t

  for (t in animations) {
    if (testElement.style[t] !== undefined) {
      return animations[t]
    }
  }

  return false
}
