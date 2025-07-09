/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * The "v-scroller" directive sets max-height on an element so that it does not exceed the viewport,
 * then it applies a utility class that makes the element children scrollable.
 *
 * Usage in Vue Components:
 *
 * In <template>:
 * <div v-scroller>
 *    Content that sometimes will be scrollable, sometimes not, depending on viewport height
 * </div>
 *
 * In <script>:
 * import Scroller from '@DpJs/directives/scroller'
 * name + components definition...
 * directives: {
 *   scroller: Scroller
 * }
 */

import { throttle } from '@demos-europe/demosplan-ui'

const updateElement = function (element) {
  const innerHeight = window.innerHeight || document.documentElement.clientHeight
  const styles = window.getComputedStyle(element)
  const cssPropertiesToParse = ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']
  const additionalSpacing = cssPropertiesToParse.reduce((acc, item) => {
    return acc + parseFloat(styles[item])
  }, 0)

  // Set maxHeight of element
  element.style.maxHeight = innerHeight - element.getBoundingClientRect().top - additionalSpacing + 'px'

  // Toggle classes that allow to scroll if the content overflows its container after applying maxHeight.
  if (element.scrollHeight > element.clientHeight) {
    element.classList.add('overflow-y-scroll')
  } else {
    element.classList.remove('overflow-y-scroll')
  }
}

const Scroller = {
  mounted: function (element) {
    setTimeout(updateElement.bind(null, element), 60)
    window.addEventListener('resize', throttle(updateElement.bind(null, element), 60))

    /*
     * This MutationObserver because when v-scroll directive (max-height) is set to container,
     * ResizeObserver does not trigger anymore even if its content changes.
     */
    const contextMutationObserver = new MutationObserver(updateElement.bind(null, element))
    contextMutationObserver.observe(element, {
      childList: true,
      subtree: true
    })
  },
  unmounted: function () {
    window.removeEventListener('resize', updateElement)
  }
}

export default Scroller
