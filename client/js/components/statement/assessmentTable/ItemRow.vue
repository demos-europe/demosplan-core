<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="layout--flush u-ph-0_5"
    :class="{'border--bottom': borderBottom, 'border--right': borderRight}">
    <div
      class="layout__item u-pl-0 c-at-item__row-icon color--grey"
      :title="Translator.trans(title)">
      <i
        class="fa"
        :class="icon"
        aria-hidden="true"
        v-if="icon" />
    </div><!--

 --><div class="layout--flush layout__item c-at-item__row">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  name: 'DpItemRow',

  props: {
    icon: {
      required: false,
      type: String,
      default: ''
    },

    title: {
      required: false,
      type: String,
      default: ''
    },

    borderBottom: {
      required: false,
      type: Boolean,
      default: true
    },

    borderRight: {
      required: false,
      type: Boolean,
      default: false
    }
  }
}
</script>
