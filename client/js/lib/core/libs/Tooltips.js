/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { prefixClass } from '@demos-europe/demosplan-ui'

/*
 *
 *JS-Solution to display Tooltips.
 *
 *Conditions: the caller-Element has the class "o-tooltip" and
 *has an aria-describedby-Attribute with the ID of the content-element
 *OR the caller has a child-Element with the class "o-tooltip__content"
 *
 *@todo find a way to use tooltip.js with custom markup and prevent the copying of markup like it is right now
 *
 *@link https://yaits.demos-deutschland.de/w/demosplan/frontend-documentation/components/#tooltips Wiki: Tooltips
 *
 *https://github.com/FezVrasta/popper.js/blob/master/docs/_includes/tooltip-documentation.md
 *
 */
import Tooltip from 'tooltip.js'

let elements = []

function register (elem, tooltipContentContainer) {
  //  Configuration for the tooltip library. Has to be in sync with v-tooltip config.
  const config = {
    title: tooltipContentContainer.innerHTML,
    html: true,
    boundariesElement: 'scrollParent',
    delay: {
      show: 300,
      hide: 100
    },
    template: '<div class="tooltip" role="tooltip"><div class="tooltip__arrow"></div><div class="tooltip__inner"></div></div>'
  }

  return new Tooltip(elem, config)
}

export default function Tooltips () {
  /*
   * The dispose() method makes it possible to destroy tooltip instances on a page. This is used from the Wizard
   * where tooltips are only rendered when not inside wizard mode.
   */
  const disposeAllTooltips = function () {
    for (const element in elements) {
      elements[element].dispose()
    }
    elements = []
  }

  const initializeTooltips = () => {
    const tooltips = document.querySelectorAll(prefixClass('.o-tooltip'))

    for (let i = 0; i < tooltips.length; i++) {
      const elem = tooltips[i]

      // Get the content of the tooltip
      let tooltipContentContainer = document.getElementById(elem.getAttribute('aria-describedby'))
      if (tooltipContentContainer === null) {
        tooltipContentContainer = elem.getElementsByClassName(prefixClass('o-tooltip__content'))[0]
      }

      // Do not generate Tooltips without content
      if (tooltipContentContainer === null || typeof tooltipContentContainer === 'undefined') {
        continue
      }

      // Generate the Tooltip
      elements.push(register(elem, tooltipContentContainer))
    }
  }

  disposeAllTooltips()

  initializeTooltips()

  return {
    init: initializeTooltips,
    dispose: disposeAllTooltips,
    elements: elements
  }
}
