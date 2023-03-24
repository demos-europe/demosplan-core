/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { prefixClass } from '@demos-europe/demosplan-ui'

/**
 *  Tabs - add behavior to tabbed elements.
 *
 *  @deprecated use <tabs> component
 */
export default function Tabs () {
  const tabs = document.querySelector(prefixClass('.js__tabs'))
  const activeTabClass = prefixClass('is-active-tab')

  // Helper for Tabs - takes any number of objects as arguments, removes 'is-active-tab' class from any of them
  const makeInactive = function () {
    Array.prototype.slice.call(arguments).forEach((el) => {
      el.querySelector(`.${activeTabClass}`).classList.remove(activeTabClass)
    })
  }

  // Execute only if tabs component found
  if (tabs) {
    console.warn('Found usage of deprecated Tabs component, use <tabs> instead.')

    const tabContent = document.querySelector(`[data-tabs-content="${tabs.getAttribute('data-tabs')}"]`)
    const realHash = location.hash.replace('#_', '#')
    const hashTarget = tabs.querySelector(`[href="${realHash}"]`)

    // On page load: activate tab depending on hash
    if (location.hash.indexOf('#', 0) !== -1 && hashTarget) {
      makeInactive(tabs, tabContent)
      document.querySelector(realHash).classList.add(activeTabClass)
      document.querySelector(realHash).removeAttribute('hidden')
      hashTarget.parentNode.classList.add(activeTabClass)
    }

    Array.from(tabs.getElementsByTagName('a')).forEach(tabLink => {
      tabLink.addEventListener('click', (event) => {
        if (tabLink.getAttribute('data-tabs') !== 'undefined') {
          event.preventDefault()

          makeInactive(tabs, tabContent)

          const target = tabLink.getAttribute('href')
          document.querySelector(target).classList.add(activeTabClass)
          tabLink.parentNode.classList.add(activeTabClass)

          /*
           * Implement hash support
           * http://lea.verou.me/2011/05/change-url-hash-without-page-jump/
           */
          history.pushState(null, null, target)
        }
      })
    })
  }
}
