/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/* This mixin can be used to select a single item or all items on a page */

export default {
  data () {
    return {
      allSelected: false,
      /**
       * DpSelectAll adds the itemId and the corresponding {Boolean} selectedStatus of all items to this Object
       * For huge numbers of items, this is more performant than using an array of itemIds
       */
      itemSelections: {}
    }
  },

  methods: {
    /**
     * Select all items on a page
     * @param {Boolean} status  Should all items be selected or deselected
     * @param {Object}  items   An object holding all items being selected and deselected
     */
    dpToggleAll (status, items) {
      this.allSelected = status
      this.itemSelections = this.setItemSelections(items, this.allSelected)
    },

    /**
     * Select a single item on a page
     * @param id  The id of the item to be selected
     */
    dpToggleOne (id) {
      if (this.itemSelections[id] && this.allSelected) this.allSelected = false
      const isInSelection = !!this.itemSelections[id]
      this.itemSelections = { ...this.itemSelections, [id]: !isInSelection }
    },
    /**
     * Set selectedStatus of items
     * @param {Object} items
     * @param {Boolean} status
     */
    setItemSelections (items, status) {
      return Object.keys(items).reduce((acc, key) => {
        return {
          ...acc,
          ...{ [key]: status }
        }
      }, {})
    }
  }
}
