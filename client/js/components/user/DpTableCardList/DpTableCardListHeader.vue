<template>
  <dp-sticky-element>
    <!-- search and buttons -->
    <dp-search-field
      v-if="searchable"
      class="layout__item u-1-of-2 u-mb-0_5"
      :placeholder="searchPlaceholder"
      @reset="$emit('reset-search')"
      @search="val => $emit('search', val)" /><!--
 --><slot name="header-buttons" />
    <!-- header with checkbox and labels-->
    <div class="layout__item border--bottom">
      <dp-checkbox
        v-if="selectable"
        id="selectAll"
        class="inline-block w-[20px] u-pv-0_25"
        @change="val => $emit('select-all', val)" /><!--
    --><div
        class="layout__item weight--bold u-pv-0_5"
        :class="[item.classes ? item.classes : '', item.width ? item.width : '']"
        v-for="(item, idx) in items"
        :key="idx">
        {{ item.label }}
      </div>
    </div>
  </dp-sticky-element>
</template>

<script>
import { DpCheckbox, DpSearchField, DpStickyElement } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpTableCardListHeader',

  components: {
    DpCheckbox,
    DpSearchField,
    DpStickyElement
  },

  props: {
    /**
     * Items to display in the list header
     * { label: 'columnLabel', width: 'u-x-of-x', classes: 'your css classes' }
     */
    items: {
      type: Array,
      required: true
    },

    /**
     * Add a search field above the list
     */
    searchable: {
      type: Boolean,
      default: false
    },

    searchPlaceholder: {
      type: String,
      default: ''
    },

    /**
     * Adds a checkbox in the first column
     */
    selectable: {
      type: Boolean,
      default: false
    }
  },

  emits: [
    'search',
    'select-all',
    'reset-search'
  ]
}
</script>
