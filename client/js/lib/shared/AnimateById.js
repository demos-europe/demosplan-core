/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Applies an animation of the background color of an element to draw attention to it.
 * The element is selected via the id found in the url fragment  (which has to match an element id).
 */
import { getAnimationEventName } from '@demos-europe/demosplan-ui'

const Animate = () => {
  if (window.location.hash) {
    const element = document.getElementById(window.location.hash.slice(1))
    if (element) {
      element.classList.add('run-animate')
      const animationendEvent = getAnimationEventName()
      element.addEventListener(animationendEvent, function callback (event) {
        event.currentTarget.classList.remove('run-animate')
        event.currentTarget.removeEventListener(event.type, callback)
      })
    }
  }
}

export default function AnimateById (delay = 300) {
  setTimeout(function () {
    Animate()
  }, delay)
}
