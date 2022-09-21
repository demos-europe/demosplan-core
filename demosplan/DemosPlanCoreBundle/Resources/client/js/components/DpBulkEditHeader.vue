<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    role="menu"
    class="layout__item u-12-of-12 u-p-0_5 bg-color--system-info-light-1 line-height--1_6">
    <span class="u-mr">
      {{ selectedItemsText }}
    </span>
    <slot :selected-items-count="selectedItemsCount">
      <!-- place edit actions here -->
    </slot>
    <button
      type="button"
      class="btn btn--secondary float--right"
      @click="$emit('reset-selection')">
      {{ Translator.trans('unselect') }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'DpBulkEditHeader',

  props: {
    selectedItemsCount: {
      type: Number,
      required: false,
      default: 0
    },

    selectionText: {
      type: String,
      required: false,
      default: ''
    }
  },

  computed: {
    selectedItemsText () {
      return this.selectedItemsCount + ' ' + this.selectionText
    }
  }
}
</script>
