<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- everything within this container will be displayed in the fullscreen mode -->
  <div
    class="c-mastertoeb bg-color--white"
    :class="{'is-fullscreen': isFullscreen}">
    <!-- Sticky Header -->
    <dp-sticky-element
      v-if="isMounted"
      ref="header"
      :class="{'u-1-of-1': isFullscreen}"
      :observe-context="false">
      <div
        class="text-right"
        :class="{'u-pb-0_5': isFullscreen, 'u-pv-0_5': !isFullscreen}">
        <button
          class="btn--blank color-main u-mt-0_125 u-mr-0_75"
          aria-expanded="false"
          aria-haspopup="true"
          :aria-label="Translator.trans('editor.fullscreen')"
          aria-role="navigation"
          type="button"
          @click="() => fullscreen()">
          <i
            class="fa fa-arrows-alt"
            aria-hidden="true" />
          {{ fullscreenText }}
        </button>
        <a
          v-if="hasPermission('area_manage_mastertoeblist') || hasPermission('area_use_mastertoeblist')"
          class="btn--blank u-mt-0_125 u-mr-0_75"
          :href="Routing.generate('DemosPlan_user_mastertoeblist_export')">
          {{ Translator.trans('export') }}
        </a>
        <!-- responsible for adding new master toeb organisations, should only be displayed if a user may edit organisations -->
        <dp-new-master-toeb
          v-if="isEditable"
          class="inline"
          :bool-to-string-fields="boolToStringFields"
          :fields="filteredFields"
          @orga-added="insertOrga" /><!--
     --><dp-invite-master-toeb
          v-if="isEditable === false"
          class="inline"
          :procedure-id="procedureId"
          :selected-toeb-ids="selectedItems" />
      </div>
      <div class="flex u-pb-0_5">
        <!-- Search field -->
        <input
          v-model="searchString"
          class="o-form__control-input u-mr-0_5"
          :placeholder="Translator.trans('search')"
          type="text"
          @input="searchItems">
        <dp-filter-master-toeb
          v-if="isEditable === false"
          class="inline"
          :fields="fields"
          :items="currentItems"
          @items-filtered="setFilteredItems" />
        <!-- dropdown to select cols to be shown/hidden -->
        <div
          class="c-actionmenu o-page__switcher-menu"
          data-actionmenu>
          <button
            class="btn--blank color-main c-actionmenu__trigger"
            aria-expanded="false"
            aria-haspopup="true"
            :aria-label="Translator.trans('table.cols.hide')"
            aria-role="navigation"
            type="button">
            {{ Translator.trans('table.cols.hide') }}
            <i class="fa fa-caret-down u-ml-0_25" />
          </button>
          <div
            class="c-actionmenu__menu overflow-hidden"
            role="menu">
            <div class="max-h-13 overflow-y-auto">
              <label class="lbl--text u-pl-0_5 u-mb-0_25 w-10 border--bottom">
                <input
                  checked
                  type="checkbox"
                  @change="toggleAllCols">
                {{ Translator.trans('aria.select.all') }}
              </label>
              <label
                v-for="(filterField, idx) in filteredFields"
                :key="`${filterField}-${idx}`"
                class="lbl--text u-pl-0_5 u-mb-0_25 w-10">
                <input
                  v-model="filters[filterField.field]"
                  type="checkbox"
                  @change="updateFields()">
                {{ filterField.value }}
              </label>
            </div>
          </div>
        </div>
      </div>
    </dp-sticky-element>

    <!-- Table -->
    <dp-data-table
      class="w-full font-size-5 overflow-x-hidden relative u-pb"
      ref="dataTable"
      has-sticky-header
      :header-fields="headerFields"
      :is-selectable="isEditable === false"
      :items="onPageItems"
      :search-string="searchString"
      track-by="oId"
      v-scroller
      @items-selected="setSelectedItems">
      <template
        v-for="headerField in headerFields"
        v-slot:[headerField.field]="rowData">
        <dp-update-mastertoeb
          v-if="headerField.field !== 'deletion'"
          :key="headerField.field"
          :is-editing="editModeElementId === rowData.ident && editModeElementField === headerField.field"
          :value="transformValue(rowData[headerField.field], headerField.field)" />
        <dp-delete-master-toeb
          v-if="headerField.field === 'deletion' && isEditable"
          :key="headerField.field"
          :orga-id="rowData.ident"
          @orga-deleted="removeOrga" />
      </template>
      <template
        v-for="headerField in headerFields"
        v-slot:[`header-${headerField.field}`]>
        <div
          v-if="headerField.field !== 'deletion'"
          :key="headerField.field"
          class="whitespace-nowrap u-pr-0_5 relative">
          <button
            class="btn--blank u-top-0 u-right-0 absolute"
            type="button"
            @click="setOrder(headerField.field)">
            <i
              class="fa"
              :class="(headerField.field === sortOrder.key) ? (sortOrder.direction === 1 ? 'fa-sort-up color-highlight' : 'fa-sort-down color-highlight') : 'fa-sort color--grey'" />
          </button>
          {{ headerField.value }}
        </div>
        <i
          v-if="headerField.field === 'deletion'"
          :key="headerField + 'header'"
          class="fa fa-trash"
          aria-hidden="true" />
      </template>
    </dp-data-table>

    <!-- Sticky Footer below table - the u-1-of-1 is just a hack to not have to refresh sticky when going into fullscreen -->
    <dp-sticky-element
      v-if="isMounted"
      ref="footer"
      class="c-mastertoeb__footer"
      direction="bottom">
      <!-- The scrollBar element serves as a "custom" horizontal scrollbar by forcing its child to be the same width as the dataTable -->
      <div
        ref="scrollBar"
        class="overflow-x-scroll overflow-y-hidden">
        <div />
      </div>

      <!-- Pager & "Items per page" control -->
      <div class="u-mv-0_5 text-right">
        <sliding-pagination
          v-if="totalPages > 1"
          class="inline-block u-mr-0_25 u-ml-0_5 u-mt-0_125"
          :current="currentPage"
          :total="totalPages"
          @page-change="handlePageChange" />
        <dp-select-page-item-count
          class="inline"
          :current-item-count="itemsPerPage"
          :label-text="Translator.trans('pager.per.page')"
          :page-count-options="itemsPerPageOptions"
          @changed-count="setPageItemCount" />
      </div>
    </dp-sticky-element>
  </div>
</template>

<script>
import {
  bindFullScreenChange,
  dataTableSearch,
  dpApi,
  DpDataTable,
  DpSelectPageItemCount,
  DpStickyElement,
  isActiveFullScreen,
  makeFormPost,
  toggleFullscreen,
  unbindFullScreenChange
} from '@demos-europe/demosplan-ui'
import DpDeleteMasterToeb from './DpMasterToebList/DpDeleteMasterToeb'
import DpFilterMasterToeb from './DpMasterToebList/DpFilterMasterToeb'
import DpInviteMasterToeb from './DpMasterToebList/DpInviteMasterToeb'
import DpNewMasterToeb from './DpMasterToebList/DpNewMasterToeb'
import DpUpdateMastertoeb from './DpMasterToebList/DpUpdateMastertoeb'
import Scroller from '@DpJs/directives/scroller'
import SlidingPagination from 'vue-sliding-pagination'

const setupCellUpdate = (originalValue, id, field, isBoolToString) => (e) => {
  let newValue = e.target.value

  if (newValue !== originalValue) {
    if (isBoolToString) {
      newValue = newValue !== ''
    }

    if (newValue === '-') {
      newValue = ''
    }

    const payload = {
      oId: id,
      field,
      value: newValue
    }

    return makeFormPost(payload, Routing.generate('DemosPlan_user_mastertoeblist_update_ajax'))
  }
}

export default {
  name: 'DpMasterToebList',

  directives: {
    scroller: Scroller
  },

  components: {
    DpDataTable,
    DpDeleteMasterToeb,
    DpFilterMasterToeb,
    DpInviteMasterToeb,
    DpNewMasterToeb,
    DpSelectPageItemCount,
    DpStickyElement,
    DpUpdateMastertoeb,
    SlidingPagination
  },

  props: {
    fields: {
      required: false,
      type: Array,
      default: () => []
    },

    isEditable: {
      type: Boolean,
      required: false,
      default: false
    },

    items: {
      required: false,
      type: Array,
      default: () => []
    },

    userId: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      boolToStringFields: ['documentRoughAgreement', 'documentAgreement', 'documentNotice', 'documentAssessment'],
      currentPage: 1,
      deletedItems: {},
      editModeElementField: '',
      editModeElementId: '',
      filteredItems: {},
      filters: this.fields.reduce((obj, item) => {
        obj[item.field] = true
        return obj
      }, {}),
      isFullscreen: false,
      isHighlighted: '',
      isMounted: false,
      itemsPerPageOptions: [10, 50, 100, 200],
      itemsPerPage: 50,
      rowItems: this.possiblyInsertDeletion(this.items),
      searchedItems: {},
      searchString: '',
      selectedItems: [],
      sortOrder: { key: 'orgaName', direction: 1 },
      updatedItems: this.possiblyInsertDeletion(this.items)
    }
  },

  computed: {
    currentItems () {
      // Remove deleted items which are still in this.items because the page wasn't reloaded yet
      const updatedItems = this.updatedItems.filter(item => typeof this.deletedItems[item.ident] === 'undefined')
      return updatedItems
    },

    filteredFields () {
      return this.fields.filter(el => el.field !== 'oId' && el.field !== 'ident')
    },

    fullscreenText () {
      return this.isFullscreen ? Translator.trans('editor.fullscreen.close') : Translator.trans('editor.fullscreen')
    },

    headerFields () {
      const deletionField = this.isEditable ? { field: 'deletion', value: 'delete' } : null
      let filteredFields = this.filteredFields.filter(el => this.filters[el.field])
      filteredFields = deletionField ? [deletionField, ...filteredFields] : filteredFields

      return filteredFields
    },

    onPageItems () {
      let last = this.currentPage * this.itemsPerPage
      const first = last - this.itemsPerPage
      last = last <= this.rowItems.length ? last : this.rowItems.length
      return this.rowItems.slice(first, last)
    },

    totalPages () {
      return this.rowItems.length > 0 ? Math.ceil(this.rowItems.length / this.itemsPerPage) : 1
    }
  },

  methods: {
    /**
     * This method adds ids and fields to each cell as a data attribute. This is needed for later use in the event listener.
     * The row index is set as a safety check to see later if there haven't been any shifts between cells and their
     * corresponding items in this.onPageItems.
     * @param table
     * @param currentItems
     */
    addCellIdsAndFields (table, currentItems) {
      const rows = Array.prototype.slice.call(table.getElementsByTagName('tr'))
      rows.forEach((row, idx) => {
        row.setAttribute('data-index', idx)
        this.addCellAttributes(Array.prototype.slice.call(row.children), currentItems[idx])
      })
    },

    addCellAttributes (row, item) {
      row.forEach((cell, idx) => {
        cell.setAttribute('data-ident', item.ident)
        cell.setAttribute('data-field', this.headerFields[idx].field)
      })
    },

    checkForUnreadChanges () {
      // Check if there are unseen changes in the mastertöb list
      dpApi.get(Routing.generate('DemosPlan_user_mastertoeblist_has_new_reportentry_ajax', { userId: this.userId }))
        .then(data => {
          if (data.code === 100 && data.success === true && data.hasNewReportEntry === true) {
            // @todo find a more solid way to detect the target.
            document.querySelector('.fa-bell').classList.add('color-status-failed-fill')
          }
        })
    },

    fullscreen () {
      toggleFullscreen(this.$el)
    },

    generateItemMap (items) {
      return items.reduce((acc, item) => {
        acc[item.ident] = 'in'
        return acc
      }, {})
    },

    handlePageChange (page) {
      this.currentPage = page
      this.updateFields()
    },

    insertOrga (orga) {
      // Include new orgas by default although they might not be within the searched or filtered items
      this.searchedItems[orga.ident] = 'in'
      this.filteredItems[orga.ident] = 'in'
      this.updatedItems = [...this.updatedItems, orga]
      this.updateFields()
    },

    possiblyInsertDeletion (items) {
      if (this.isEditable) {
        return items.map(item => ({ ...{ deletion: 'delete' }, ...item }))
      }

      return items
    },

    removeOrga (ident) {
      // Mark item as deleted
      this.deletedItems = { ...this.deletedItems, ...{ [ident]: true } }
      this.updateFields()
    },

    searchItems () {
      if (this.searchString.length > 0) {
        const searchResultItems = dataTableSearch(this.searchString, this.currentItems, this.fields.map(el => el.field))
        this.searchedItems = this.generateItemMap(searchResultItems)
      } else {
        this.searchedItems = this.generateItemMap(this.currentItems)
      }
      this.currentPage = 1
      this.updateFields()
    },

    setFilteredItems (filteredItems) {
      this.filteredItems = this.generateItemMap(filteredItems)
      this.currentPage = 1
      this.updateFields()
    },

    setIsFullscreen () {
      this.isFullscreen = isActiveFullScreen()
    },

    setOrder (field) {
      // If clicked on one button multiple times, toggle the order direction
      if (field === this.sortOrder.key) {
        this.sortOrder.direction = this.sortOrder.direction * -1
      } else {
        // Otherwise reset the direction and set the field
        this.sortOrder = { key: field, direction: -1 }
      }
      this.updateFields()
    },

    setPageItemCount (count) {
      this.itemsPerPage = count
      this.currentPage = this.currentPage > this.totalPages ? this.totalPages : this.currentPage
      this.updateFields()
    },

    setSelectedItems (items) {
      this.selectedItems = items
    },

    sortItems (items) {
      return items.sort((a, b) => {
        if (a[this.sortOrder.key] === null || typeof a[this.sortOrder.key] === 'undefined') {
          return this.sortOrder.direction
        }

        if (b[this.sortOrder.key] === null || typeof b[this.sortOrder.key] === 'undefined') {
          return this.sortOrder.direction * -1
        }

        return String(a[this.sortOrder.key])
          .localeCompare(String(b[this.sortOrder.key]), 'de', { sensitivity: 'base' }) * this.sortOrder.direction
      })
    },

    toggleAllCols (ev) {
      Object.keys(this.filters).forEach(key => { this.filters[key] = ev.target.checked })
    },

    /**
     * This method maps null and boolean values to their predefined display counterparts. Values in boolToStringFields need
     * to be represented as 'x' (true)/ '' (false). Null values should be displayed as '-'
     *
     * @param value {string|boolean|null}
     * @param field {string}
     * @returns {string}
     */
    transformValue (value, field) {
      if (this.boolToStringFields.includes(field)) {
        return value ? 'x' : ''
      }

      if (value === null || typeof value === 'undefined' || value === false) {
        return '-'
      }

      return value
    },

    /**
     * This receives a click event and should trigger the edit mode for the cell that was clicked.
     * It sets this.editModeElementId to the clicked element's id (data-ident) and this.editModeElementField to the
     * corresponding field (data-field). This is a little bit weird and non-standard for Vue but it was done to
     * improve performance in IE11.
     *
     * An event listener to handle saving of the changes is attached as well.
     * @param e
     */
    triggerEditMode (e) {
      const el = e.target
      const id = el.getAttribute('data-ident')
      const field = el.getAttribute('data-field')
      const rowIdx = parseInt(el.parentNode.getAttribute('data-index'))
      const isSafeToEdit = this.onPageItems[rowIdx] && (this.onPageItems[rowIdx].ident === id)

      if (this.editModeElementId.length === 0 && field !== 'deletion' && isSafeToEdit) {
        const value = el.textContent

        this.editModeElementId = id
        this.editModeElementField = field
        const isBoolToString = this.boolToStringFields.includes(field)
        this.$nextTick(() => {
          el.children[0].focus()
        })
        const updateCell = setupCellUpdate(value, id, field, isBoolToString)
        const runCellUpdate = (e) => {
          if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'textarea') {
            const newValue = e.target.value
            updateCell(e)
              .then(() => {
                this.$emit('orga-updated', id, { [field]: newValue }, 'confirm')
                el.classList.add('animation--bg-highlight-grey--light-1')
                setTimeout(() => {
                  el.classList.remove('animation--bg-highlight-grey--light-1')
                }, 3000)
              })
              .catch(() => {
                this.$emit('orga-updated', id, { [field]: value }, 'error')
              })
            el.removeEventListener('focusout', runCellUpdate)
            this.editModeElementId = ''
            this.editModeElementField = ''
          }
        }
        el.addEventListener('focusout', runCellUpdate)
      } else if (this.editModeElementId === 0) {
        dplan.notify.error(Translator.trans('error.api.generic'))
      }
    },

    updateItems (items, ident, updatedField) {
      return items.map(item => {
        if (item.ident === ident) {
          return { ...item, ...updatedField }
        }
        return item
      })
    },

    updateOrga (ident, updatedField, status) {
      this.updatedItems = this.updateItems(this.updatedItems, ident, updatedField)
      this.rowItems = this.updateItems(this.rowItems, ident, updatedField)

      if (status === 'confirm') {
        const fieldName = this.fields.filter(el => el.field === Object.keys(updatedField)[0])[0].value
        dplan.notify.notify(status, Translator.trans('confirm.field.changes.saved', { fieldName }))
      } else {
        dplan.notify.notify(status, Translator.trans('error.api.generic'))
      }
    },

    updateFields () {
      const sortedItems = this.sortItems(this.currentItems)

      this.rowItems = sortedItems.filter(item => this.searchedItems[item.ident] === 'in' && this.filteredItems[item.ident] === 'in')

      this.$nextTick(() => {
        if (this.isEditable) this.addCellIdsAndFields(this.dataTableElement.getElementsByTagName('tbody')[0], this.onPageItems)
      })
    },

    /**
     * Adjust the width of the inner element of the footer scrollbar to the width of the Table.
     */
    updateScrollbarWidth () {
      this.scrollbar.firstChild.setAttribute('style', 'width:' + window.getComputedStyle(this.dataTableElement).width + ';height:1px;')
    }
  },

  mounted () {
    // SearchedItems and filteredItems hold the current state of results from filtering and searching
    this.searchedItems = this.filteredItems = this.generateItemMap(this.items)

    // Store references for later usage when calculating widths
    this.dataTableContainerElement = this.$refs.dataTable.$el
    this.dataTableElement = this.$refs.dataTable.$refs.tableEl
    const tableBody = this.dataTableElement.getElementsByTagName('tbody')[0]
    this.checkForUnreadChanges()

    /*
     * To improve performance in IE11 an event listener is attached to the table body instead of attaching event listeners
     * to each individual cell.
     */
    if (this.isEditable) {
      this.addCellIdsAndFields(tableBody, this.onPageItems)
      tableBody.addEventListener('click', this.triggerEditMode)
    }
    this.$on('orga-updated', this.updateOrga)

    // The code below forces reflow therefore it should be executed once all other code from mounted has run (perf gains)
    this.isMounted = true
    this.$nextTick(() => {
      this.scrollbar = this.$refs.scrollBar

      // Bind behaviour and position of the footer scrollbar to the scroll position of the dataTableContainerElement.
      this.scrollbar.addEventListener('scroll', () => {
        this.dataTableContainerElement.scrollLeft = this.scrollbar.scrollLeft
      })

      // Observe changes to dataTable to update scrollbar accordingly
      this.dataTableObserver = new ResizeObserver(this.updateScrollbarWidth.bind(this))
      this.dataTableObserver.observe(this.dataTableElement)

      // To unbind handler on beforeDestroy, it exists as a named function.
      bindFullScreenChange(this.setIsFullscreen.bind(this))

      // Set scrollbars + dataTable container height.
      this.updateScrollbarWidth()
    })
  },

  beforeDestroy () {
    // Remove event listener, just to not let them pile up
    unbindFullScreenChange(this.setIsFullscreen)
  }
}
</script>
