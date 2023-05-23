/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is a utility function to highlight links in a hash menu.
 */

import { prefixClass } from '@demos-europe/demosplan-ui'

function highlightActiveLinks (linkSelector) {
  const navLinks = document.querySelectorAll(linkSelector)

  window.addEventListener('hashchange', function () {
    for (let i = 0; i < navLinks.length; i++) {
      if (navLinks[i].href.match(/(#.*)/)[1] === window.location.hash) {
        navLinks[i].className = prefixClass('o-link--active')
      } else {
        navLinks[i].className = prefixClass('o-link--default')
      }
    }
  })

  // Trigger initial highlighting onpageload
  const event = new CustomEvent('hashchangeevent')
  window.dispatchEvent(event)
}

export { highlightActiveLinks }
