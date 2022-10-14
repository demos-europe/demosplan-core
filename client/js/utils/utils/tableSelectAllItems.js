/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This mixin is intended to be used along with DpDataTable and can be used to select a single item or all items over multiple pages.
 * To show additional information about selected items, you can use DpBulkEditHeader.
 * example usage:
 * <dp-bulk-edit-header
 *   v-if="selectedItemsCount > 0"
 *   :selected-items-count="selectedItemsCount"
 *   :selection-text="Whatever information text you want to display"
 *   @reset-selection="resetSelection" />
 * <dp-data-table
 *   is-selectable
 *   :items="items"<!-- Items need to be defined as an array in the specific component and are needed for this mixin. -->
 *   :multi-page-all-selected="allSelectedVisually"
 *   :multi-page-selection-items-total="allItemsCount"
 *   :multi-page-selection-items-toggled="toggledItems.length"
 *   :should-be-selected-items="currentlySelectedItems"
 *   track-by="id"
 *   @select-all="handleSelectAll"
 *   @items-toggled="handleToggleItem" />
 */

import uniqueArrayByObjectKey from './uniqueArrayByObjectKey'

export default {
  data () {
    return {
      allItemsCount: null, // Holds the total count of items
      allSelectedVisually: false, // This fakes "select all" for multi-page selection
      toggledItems: [], // All items that either got selected or deselected on multiple pages
      trackDeselected: false // Determines if `toggledItems` holds selected or deselected items
    }
  },

  computed: {
    currentlySelectedItems () {
      const toggledIds = this.toggledItems.map(item => item.id)
      let selected
      if (this.trackDeselected === false) {
        // If selected items are tracked, simply return all toggled items
        selected = this.toggledItems
      } else if (this.trackDeselected === true) {
        /*
         * If deselected items are tracked, subtract deselected items from all items of current page,
         * but only if there are items deselected. Otherwise, the "select all" checkbox has just being clicked.
         * In this case, return all items.
         */
        selected = this.toggledItems.length === 0 ? this.items : this.items.filter(item => !toggledIds.includes(item.id))
      }

      // The dataTable prop expects a format of {<id>: true}.
      return selected.reduce((acc, el) => {
        return {
          ...acc,
          [el.id]: true
        }
      }, {})
    },

    selectedItemsCount () {
      if (this.trackDeselected) {
        return this.allSelectedVisually ? this.allItemsCount : this.allItemsCount - this.toggledItems.length
      } else {
        return this.toggledItems.length
      }
    }
  },

  methods: {
    /**
     * By default, checked items are tracked. After having selected all items via the checkbox in the TableHeader,
     * this logic is inverted and only deselected items are saved.
     */
    handleSelectAll (status) {
      this.trackDeselected = status

      // If the "check all" checkbox has been triggered, the artificial "all selected" state is entered.
      this.allSelectedVisually = status === true
      this.toggledItems = []
    },

    /**
     * Toggled items are tracked here.
     *
     * @param toggledItems  array of objects with structure {id: <uuid>}
     * @param direction     can be `true` if toggledItems got selected and `false` if they got deselected.
     * @return {*}
     */
    handleToggleItem (toggledItems, direction) {
      // For easier calculation, create a flat list of ids.
      const toggledIds = toggledItems.map(item => item.id)
      // If items got selected (or deselected, if in trackDeselected state), add them to tracked elements, if not already present.
      if ((direction === true && !this.trackDeselected) || (direction === false && this.trackDeselected)) {
        this.toggledItems = uniqueArrayByObjectKey(this.toggledItems.concat(toggledItems), 'id')
      }
      // If items got deselected (or selected, if in trackDeselected state), remove them from tracked elements.
      if ((direction === false && !this.trackDeselected) || (direction === true && this.trackDeselected)) {
        this.toggledItems = this.toggledItems.filter(item => {
          const isIncluded = toggledIds.includes(item.id)
          return isIncluded === false
        })
      }
      // If items got deselected, the artificial "all selected" state is lost.
      if (direction === false) {
        this.allSelectedVisually = false
      }
    },

    resetSelection () {
      this.allSelectedVisually = false
      this.trackDeselected = false
      this.toggledItems = []
    }
  }
}
