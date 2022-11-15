<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    This is a Wrapper for DpDataTable.
    It comes with a sticky header where a search Bar and a pager including items-per-page are placed
    Additional Actions can be placed in the sticky footer

    !!!
    As in DpDataTable, be aware of the data structure for header-fields and table-items.
    the Keys in the table-Items have to match the field values of the header-fields
    !!!
  -->
  <usage discription="minimal version">
    <dp-data-table-extended
      :header-fields="[{field, label},{field, label}]"
      :table-items="[{fieldName}, {fieldName2}]" />
  </usage>
  <usage discription="all options">
    <dp-data-table-extended
      :header-fields="[{field, label},{field, label}]"
      is-selectable
      is-sortable
      :init-items-per-page="50"
      :items-per-page-options="[10, 50, 100, 200]"
      :table-items="[{fieldName}, {fieldName2}]"
      @items-selected="emitsSelectedItemIds"
      track-by="id">
      <template v-slot:footer>
        <!-- Stuff for the footer -->
      </template>
    </dp-data-table-extended>
  </usage>
</documentation>

<template>
  <div class="u-mt-0_5">
    <dp-sticky-element>
      <div class="layout u-pv-0_5">
        <div class="layout__item u-10-of-12">
          <!-- Search field -->
          <label
            class="display--inline"
            for="search">
            {{ Translator.trans('search') }}
          </label>
          <input
            type="text"
            id="search"
            name="search"
            class="o-form__control-input"
            v-model="searchString"
            @input="updateFields(null)">

          <!-- pager -->
          <sliding-pagination
            v-if="totalPages > 1"
            class="display--inline-block u-ml-0_5 u-mt-0_125"
            :current="currentPage"
            :total="totalPages"
            @page-change="handlePageChange" />
          <dp-select-page-item-count
            class="display--inline-block u-mt-0_125 u-ml-0_5"
            @changed-count="setPageItemCount"
            :page-count-options="itemsPerPageOptions"
            :current-item-count="itemsPerPage"
            :translations="{ pagerElementsPerPage: Translator.trans('pager.per.page') }" />
        </div>
      </div>
    </dp-sticky-element>

    <dp-data-table
      :has-flyout="hasFlyout"
      :header-fields="headerFields"
      :is-expandable="isExpandable"
      :is-loading="isLoading"
      :is-resizable="isResizable"
      :is-selectable="isSelectable"
      :is-truncatable="isTruncatable"
      @items-selected="emitSelectedItems"
      :items="onPageItems"
      :search-string="searchString"
      :should-be-selected-items="currentlySelectedItems"
      :track-by="trackBy">
      <template
        v-for="el in sortableFilteredFields"
        v-slot:[`header-${el.field}`]="element">
        <slot
          :name="`header-${element.field}`"
          v-bind="element">
          <div
            :key="element.field"
            class="o-hellip--nowrap position--relative u-pr-0_75">
            <button
              :aria-label="Translator.trans('table.cols.sort') + ': ' + element.label"
              :title="Translator.trans('table.cols.sort') + ': ' + element.label"
              class="btn--blank u-top-0 u-right-0 position--absolute"
              @click="setOrder(element.field)"
              type="button">
              <i
                class="fa"
                :class="(element.field === sortOrder.key) ? (sortOrder.direction < 0 ? 'fa-sort-up color--highlight' : 'fa-sort-down color--highlight') : 'fa-sort color--grey'" />
            </button>
            {{ element.label }}
          </div>
        </slot>
      </template>
      <template
        v-for="(el, i) in filteredFields"
        v-slot:[filteredFields[i].field]="element">
        <!-- table cells (TDs) -->
        <slot
          :name="filteredFields[i].field"
          v-bind="element" />
      </template>
      <template v-slot:expandedContent="element">
        <!-- expanded content area -->
        <slot
          name="expandedContent"
          v-bind="element" />
      </template>
      <template v-slot:flyout="element">
        <!-- flyout content area -->
        <slot
          name="flyout"
          v-bind="element" />
      </template>
    </dp-data-table>

    <dp-sticky-element direction="bottom">
      <slot name="footer" />
    </dp-sticky-element>
  </div>
</template>

<script>
import dataTableSearch from './DataTableSearch'
import DomPurify from 'dompurify'
import DpDataTable from './DpDataTable'
import DpSelectPageItemCount from './DpSelectPageItemCount'
import DpStickyElement from '../shared/DpStickyElement'
import { hasOwnProp } from 'demosplan-utils'
import SlidingPagination from 'vue-sliding-pagination'
import { tableSelectAllItems } from 'demosplan-utils/mixins'

export default {
  name: 'DpDataTableExtended',

  components: {
    DpDataTable,
    DpSelectPageItemCount,
    DpStickyElement,
    SlidingPagination
  },

  mixins: [tableSelectAllItems],

  props: {
    defaultSortOrder: {
      type: [Object, null],
      required: false,
      default: null,
      validator: data => {
        if (typeof data === 'object' && data !== null) {
          return hasOwnProp(data, 'key') && hasOwnProp(data, 'direction')
        }
        return data === null
      }
    },

    hasFlyout: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * {Array<Object>} { field, label } object fields with Label
     */
    headerFields: {
      type: Array,
      required: true,
      default: () => []
    },

    initItemsPerPage: {
      type: Number,
      required: false,
      default: 50
    },

    isExpandable: {
      type: Boolean,
      required: false,
      default: false
    },

    isLoading: {
      type: Boolean,
      required: false,
      default: false
    },

    isResizable: {
      type: Boolean,
      required: false,
      default: false
    },

    isSelectable: {
      type: Boolean,
      required: false,
      default: false
    },

    isSortable: {
      type: Boolean,
      required: false,
      default: false
    },

    isTruncatable: {
      type: Boolean,
      required: false,
      default: false
    },

    itemsPerPageOptions: {
      type: Array,
      required: false,
      default: () => [10, 50, 100, 200]
    },

    /**
     * {Array{Object} {fieldName1, fieldName2, ...} The field names have to match the field values from the headerFields.
     * has to be a computed in the parent (can't be in data)
     */
    tableItems: {
      type: Array,
      required: false,
      default: () => []
    },

    trackBy: {
      type: String,
      required: false,
      default: 'id'
    }
  },

  data () {
    return {
      currentPage: 1,
      filteredItems: [],
      filters: this.headerFields.reduce((obj, item) => {
        obj[item.field] = true
        return obj
      }, {}),
      isHighlighted: '',
      itemsPerPage: this.initItemsPerPage,
      searchString: '',
      sortOrder: (this.defaultSortOrder !== null) ? this.defaultSortOrder : (this.headerFields.length > 0) ? { key: this.headerFields[0].field, direction: -1 } : null
    }
  },

  computed: {
    filteredFields () {
      const filteredFields = this.headerFields.filter(el => this.filters[el.field])
      this.$emit('updated:filteredItems', filteredFields)
      return filteredFields
    },

    onPageItems () {
      let last = this.currentPage * this.itemsPerPage
      const first = last - this.itemsPerPage
      last = last <= this.filteredItems.length ? last : this.filteredItems.length
      const result = this.filteredItems.slice(first, last)
      this.$emit('updated:onPageItems', result)
      return result
    },

    /*
     * This is to prevent having both a v-if and a v-for on the template. If isSortable is set,
     * there should be some sorting arrows in the header, which get passed in the slot.
     */
    sortableFilteredFields () {
      return this.isSortable ? this.filteredFields : []
    },

    totalPages () {
      return this.filteredItems.length > 0 ? Math.ceil(this.filteredItems.length / this.itemsPerPage) : 1
    }
  },

  watch: {
    tableItems () {
      this.updateFields()
    }
  },

  methods: {
    emitSelectedItems (selectedItems) {
      this.$emit('items-selected', selectedItems)
    },

    handlePageChange (page) {
      this.currentPage = page
      this.updateFields()
    },

    highlightText (value) {
      let displayText = DomPurify.sanitize(value.replace('<br>', '__br__')).replace('__br__', '<br>')
      if (this.searchString.length) {
        const regex = new RegExp(this.searchString, 'ig')
        displayText = value.replace(regex, '<span style="background-color: yellow;">$&</span>')
      }
      return displayText
    },

    setOrder (field) {
      // If clicked on one button multiple times, toggle the order direction
      if (field === this.sortOrder.key) {
        this.sortOrder.direction = this.sortOrder.direction * -1
      } else {
        // Otherwise reset the direction and set the field
        this.sortOrder = { key: field, direction: 1 }
      }
      this.updateFields()
      this.$emit('updated:sortOrder', this.sortOrder)
    },

    setPageItemCount (count) {
      this.itemsPerPage = count
      this.currentPage = this.currentPage > this.totalPages ? this.totalPages : this.currentPage
      this.updateFields()
    },

    updateFields (items = null) {
      let sortedList = items || this.tableItems

      // Sort by selected col
      if (this.sortOrder) {
        sortedList.sort((a, b) => {
          /**
           * In JS is `null > 'string' === null < 'string'`
           * so we have to do such weird stuff
           */
          if (a[this.sortOrder.key] === null || typeof a[this.sortOrder.key] === 'undefined') {
            return this.sortOrder.direction * 1
          }
          if (b[this.sortOrder.key] === null || typeof b[this.sortOrder.key] === 'undefined') {
            return this.sortOrder.direction * -1
          }
          return a[this.sortOrder.key].localeCompare(b[this.sortOrder.key], 'de', { sensitivity: 'base' }) * this.sortOrder.direction
        })
      }

      // Filter by search string
      if (this.searchString.length > 0) {
        sortedList = dataTableSearch(this.searchString, sortedList, this.headerFields.map(el => el.field))
      }
      this.filteredItems = sortedList
    }
  },

  mounted () {
    this.updateFields()
  }
}
</script>
