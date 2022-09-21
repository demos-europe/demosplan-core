<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    class="c-publicindex__handle o-link--default text--left position--absolute"
    :class="{ 'box-shadow-2': !isOpen }"
    type="button"
    @click="$emit('input', !isOpen)">
    <i
      aria-hidden="true"
      class="c-publicindex__handle-icon fa"
      :class="{ 'fa-chevron-left': isOpen, 'fa-chevron-right': !isOpen }" />
  </button>
</template>

<script>
export default {
  name: 'DpHandle',

  props: {
    isOpen: {
      type: Boolean,
      required: true
    }
  }
}
</script>
