<template>
  <div class="display--inline-block">
    <div
      class="display--inline-block"
      ref="referenceRef"
      @mouseleave="hide"
      @mouseover="show"
      @focus="show"
      @blur="hide">
      <slot />
    </div>
    <div
      :class="{ 'display--none': isHidden }"
      ref="floatingRef"
      class="test-tooltip position--absolute">
      {{ props.content }}

      <div
        class="test-triangle position--absolute"
        ref="arrowRef" />
    </div>
  </div>
</template>

<script setup>

import { arrow, autoPlacement, computePosition, flip, offset, shift } from '@floating-ui/dom'
import { defineProps, onMounted, ref } from 'vue'

const referenceRef = ref()
const floatingRef = ref()
const arrowRef = ref()
const isHidden = ref(true)

const props = defineProps({
  content: {
    type: String,
    default: ''
  },
  placement: {
    type: String,
    default: 'auto'
  }
})

function show () {
  isHidden.value = false

  calculatePosition()
}

function hide () {
  isHidden.value = true
}

async function calculatePosition () {
  // Use autoPlacement middleware only if props.placement is 'auto'
  const middleware = [offset(8), shift({ padding: 5 }), arrow({ element: arrowRef.value })]

  /*
   *If (props.placement === 'auto') {
   *middleware.push(autoPlacement())
   *}
   */

  const { x, y, middlewareData, placement } = await computePosition(referenceRef.value, floatingRef.value, {
    placement: props.placement,
    middleware
  })

  floatingRef.value.style.left = `${x}px`
  floatingRef.value.style.top = `${y}px`

  Object.assign(floatingRef.value.style, {
    left: `${x}px`,
    top: `${y}px`
  })

  const { x: arrowX, y: arrowY } = middlewareData.arrow

  const opposedSide = {
    left: 'right',
    right: 'left',
    top: 'bottom',
    bottom: 'top'
  }[placement]

  Object.assign(arrowRef.value.style, {
    left: arrowX ? `${arrowX}px` : '',
    top: arrowY ? `${arrowY}px` : '',
    bottom: '',
    right: '',
    [opposedSide]: '-4px'
  })

  console.log('position: ', placement)
}

</script>
