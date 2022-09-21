<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
export default {
  name: 'DpTransitionExpand',

  functional: true,

  render (createElement, context) {
    const data = {
      props: {
        name: 'expand'
      },

      on: {
        afterEnter (element) {
          element.style.height = 'auto'
        },

        enter (element) {
          element.style.width = getComputedStyle(element).width
          element.style.position = 'absolute'
          element.style.visibility = 'hidden'
          element.style.height = 'auto'

          const height = getComputedStyle(element).height

          element.style.width = null
          element.style.position = null
          element.style.visibility = null
          element.style.height = 0

          /*
           * Force repaint to make sure the
           * animation is triggered correctly.
           */
          /* eslint-disable no-unused-expressions */
          getComputedStyle(element).height

          /*
           * Trigger the animation.
           * We use `requestAnimationFrame` because we need
           * to make sure the browser has finished
           * painting after setting the `height`
           * to `0` in the line above.
           */
          requestAnimationFrame(() => {
            element.style.height = height
          })
        },

        leave (element) {
          element.style.height = getComputedStyle(element).height

          getComputedStyle(element).height

          requestAnimationFrame(() => {
            element.style.height = 0
          })
        }
      }
    }

    return createElement('transition', data, context.children)
  }
}
</script>

<!--
  This is currently not supported but will accelerate the animation
  once the <style> part of Vue single file templates is also included in the build.
-->
<style scoped>
  * {
    will-change: height;
    transform: translateZ(0);
    backface-visibility: hidden;
    perspective: 1000px;
  }
</style>
