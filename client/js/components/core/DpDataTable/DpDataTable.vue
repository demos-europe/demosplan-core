<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- The most simple way to use DpDataTable is the following -->
  <!-- NOTICE: the elements in the items array must have a key which matches a field in the header-fields array -->
  <!-- The following example defines a table with two equally sized columns named "E-Mail" a,d "Name":
  headerFields: [
    {
      field: 'mail',
      label: 'E-Mail',
      colClass: 'u-1-of-2', // can be omitted, renders a colgroup > col element
      tooltip: 'longer.text.then.label' // can be omitted
     },
     {
      field: 'name',
      label: 'Name'
     },
   ],
   items: [{mail: 'test@domain.dev', name: 'Testname'}]
  -->
  <usage>
    <dp-data-table
      has-flyout="true|false"
      header-fields="[{field, label}]"
      is-selectable="true|false"
      items="[{fieldName}]"
      search-string="String"
      track-by="ID (used for checkbox identification)" />
  </usage>
  <!-- -->
  <!-- -->
  <!-- Advanced setup with scoped slots -->
  <usage>
    <dp-data-table
      search-string="searchString"
      header-fields="headerInput (see above)"
      table-class="additional classes for the table element"
      items="inputItems (see above)"
      track-by="(see above)">
      <!-- header slots -->
      <template
        v-for="element in headerInput /* iterate over the HeaderFields */"
        v-slot:[`header-${element.field}`]="headerData">
        <div :key="element.id">
          {{ headerData.value }}
        </div>
      </template>
      <!-- field slots -->
      <template
        v-for="(element, i) in inputItems"
        v-slot:[element.field]="rowData">
        <!-- For highlighting text in a slot we have to do something like this, where the `highlightText()` method
        has to handle the highlighting from outside -->
        <span
          :key="`${element.field}:${i}`"
          v-cleanhtml="highlightText(rowData[element.field])" />
      </template>
    </dp-data-table>
  </usage>
</documentation>

<script>
import { CleanHtml } from 'demosplan-ui/directives'
import DomPurify from 'dompurify'
import { DpLoading } from 'demosplan-ui/components'
import DpTableHeader from './DpTableHeader'
import DpTableRow from './DpTableRow'
import draggable from 'vuedraggable'

export default {
  name: 'DpDataTable',

  components: {
    DpLoading,
    DpTableHeader,
    DpTableRow,
    draggable
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    // Adds flyout menu
    hasFlyout: {
      type: Boolean,
      required: false,
      default: false
    },

    // The first table row (consisting of column headers) is being fixed to the top of the outer table element.
    hasStickyHeader: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * The header of every column of the table is defined here.
     *
     * Each column is represented by an object with a `field` key whose value should match
     * a key of the objects inside `items`. The `label` key controls the header of the column.
     * The header can also have a tooltip. To define the width the column is initially rendered with
     * when `isResizable` is used, the keys `initialWidth`, `initialMaxWidth` and `initialMinWidth` take a px value.
     */
    headerFields: {
      type: Array,
      required: true
    },

    initSelectedItems: {
      type: Array,
      required: false,
      default: () => []
    },

    isDraggable: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Rows may be expandable to show additional content inside another row.
     * The `#expandedContent` slot can be utilized to style the content area.
     */
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

    /**
     * Make table columns resizable.
     */
    isResizable: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Display a checkbox in front of each row.
     */
    isSelectable: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * If specified, the checkbox will have a "name" attr with this value, and a value of [trackBy].
     */
    isSelectableName: {
      type: String,
      required: false,
      default: null
    },

    /**
     * Make table rows truncatable.
     * Cell content is truncated after 1 line, but expandable to its original size.
     * To make this work, no custom css must be applied to the cells that hold the
     * content to be truncated.
     * At the moment, `isTruncatable` and `isExpandable` use the same icon as trigger visual.
     * See https://yaits.demos-deutschland.de/T11301#413638 for possible advancement.
     */
    isTruncatable: {
      type: Boolean,
      required: false,
      default: false
    },

    items: {
      type: Array,
      required: true
    },

    /**
     * When selection on multiple pages is supported, this variable forces the "Check all" checkbox into a "checked"
     * state, because instead of passing all checked items into here (which we can't in multi-page-selection suzenario),
     * we just pass the info "all items are selected".
     */
    multiPageAllSelected: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Use a Boolean Property of the Item to set the Checkbox to a locked state.
     * This should only be set if `isSelectable` is true.
     */
    lockCheckboxBy: {
      type: String,
      required: false,
      default: null
    },

    /**
     * When selection on multiple pages is supported, this variable holds number of items currently toggled.
     * It is used for calculating indeterminate state of the "check all" checkbox.
     */
    multiPageSelectionItemsToggled: {
      type: Number,
      required: false,
      default: 0
    },

    /**
     * When selection on multiple pages is supported, this variable holds the absolute number of items available.
     * It is used for calculating indeterminate state of the "check all" checkbox.
     */
    multiPageSelectionItemsTotal: {
      type: Number,
      required: false,
      default: 0
    },

    searchString: {
      type: [String, null],
      required: false,
      default: null
    },

    // This allows item selection to be forced from outside. It will override any internal selection state.
    shouldBeSelectedItems: {
      type: Object,
      required: false,
      default: () => ({})
    },

    tableClass: {
      type: String,
      required: false,
      default: 'c-data-table'
    },

    trackBy: {
      type: String,
      required: true
    },

    translations: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      allExpanded: false,
      allWrapped: false,
      defaultTranslations: {
        footerSelectedElement: Translator.trans('entry.selected'),
        footerSelectedElements: Translator.trans('entries.selected'),
        headerExpandHint: Translator.trans('aria.expand.all'),
        headerSelectHint: Translator.trans('aria.select.all'),
        lockedForSelection: Translator.trans('item.lockedForSelection'),
        searchNoResults: (searchTerm) => Translator.trans('search.no.results', { searchterm: searchTerm }),
        tableLoadingData: Translator.trans('loading.data'),
        tableNoElements: Translator.trans('explanation.noentries')
      },
      elementSelections: {},
      expandedElements: {},
      mergedTranslations: {},
      selectedElements: [],
      tableEl: undefined,
      wrappedElements: {}
    }
  },

  computed: {
    allSelected () {
      if (this.multiPageSelectionItemsTotal > 0) {
        return this.multiPageSelectionItemsToggled === this.multiPageSelectionItemsTotal || this.multiPageAllSelected
      } else {
        return this.items.filter(item => this.elementSelections[item[this.trackBy]]).length === this.items.length
      }
    },

    indeterminate () {
      if (this.isSelectable === false) {
        return
      }
      if (this.multiPageSelectionItemsTotal > 0) {
        return this.multiPageSelectionItemsToggled > 0 && !this.allSelected
      } else {
        return this.selectedElements.length > 0 && !this.allSelected
      }
    },

    searchTerm () {
      if (this.searchString === null || this.searchString.length < 1) {
        return new RegExp()
      }
      const searchTerm = this.searchString.replace(/\s*/ig, '\\s*')
      return new RegExp(searchTerm, 'ig')
    }
  },

  watch: {
    shouldBeSelectedItems () {
      this.forceElementSelections(this.shouldBeSelectedItems)
    },

    indeterminate () {
      this.setIndeterminate()
    }
  },

  methods: {
    extractTranslations (keys) {
      return keys.reduce((acc, key) => {
        const tmp = this.mergedTranslations[key] ? { [key]: this.mergedTranslations[key] } : {}
        return { ...acc, ...tmp }
      }, {})
    },

    /**
     * Transforms and filters the list of selected items across all pages.
     *
     * Return only the `trackBy` of the items
     *
     * @returns {string[]}
     */
    filterElementSelections () {
      return Object.entries(this.elementSelections)
        .filter(selectedItem => selectedItem[1]) // True or false
        .map(selectedItem => selectedItem[0]) // TrackBy of the item
    },

    forceElementSelections (itemsStatusObject) {
      this.elementSelections = itemsStatusObject
      this.selectedElements = this.filterElementSelections()
    },

    setIndeterminate () {
      if (this.isSelectable) {
        this.$refs.selectAll.indeterminate = this.indeterminate
      }
    },

    resetSelection () {
      this.toggleSelectAll(false)
    },

    setElementSelections (elements, status) {
      return elements.reduce((acc, el) => {
        return {
          ...acc,
          ...{ [el[this.trackBy]]: status }
        }
      }, this.elementSelections)
    },

    toggleExpand (id) {
      this.expandedElements = { ...this.expandedElements, [id]: !this.expandedElements[id] }
    },

    toggleExpandAll (status = this.allExpanded === false) {
      this.expandedElements = this.items.reduce((acc, item) => {
        return {
          ...acc,
          ...{ [item[this.trackBy]]: status }
        }
      }, {})
      this.allExpanded = status
    },

    toggleSelect (id) {
      this.elementSelections = { ...this.elementSelections, ...{ [id]: !this.elementSelections[id] } }
      this.selectedElements = this.filterElementSelections()

      this.$emit('items-selected', this.selectedElements)
      this.$emit('items-toggled', [{ id }], this.elementSelections[id])
    },

    toggleSelectAll (status = this.allSelected === false) {
      this.elementSelections = this.setElementSelections(this.items, status)
      this.selectedElements = this.filterElementSelections()
      this.$emit('items-selected', this.selectedElements)
      this.$emit('items-toggled', this.items.map(el => { return { id: el[this.trackBy] } }), status)

      // Used by multi-page selection in SegmentsList to determine whether to track selected or deselected items.
      this.$emit('select-all', status)
    },

    toggleWrap (id) {
      this.wrappedElements = { ...this.wrappedElements, [id]: !this.wrappedElements[id] }
    },

    toggleWrapAll (status = this.allWrapped === false) {
      this.wrappedElements = this.items.reduce((acc, item) => {
        return {
          ...acc,
          ...{ [item[this.trackBy]]: status }
        }
      }, {})

      this.allWrapped = status
    }
  },

  created () {
    this.elementSelections = this.setElementSelections(this.initSelectedItems, true)

    // The searchNoResults translation key needs to be a function, therefore make it a function before merging with defaultTranslations
    let tmpTranslations = { ...this.translations }
    const noResults = this.translations.searchNoResults ? { searchNoResults: () => this.translations.searchNoResults } : {}
    tmpTranslations = { ...tmpTranslations, ...noResults }

    this.mergedTranslations = { ...this.defaultTranslations, ...tmpTranslations }
  },

  mounted () {
    this.tableEl = this.$refs.tableEl

    /**
     * Why is this here you may ask?
     * Tables and overflow are difficult to handle.
     * When truncating cell we want a table cell to respect the max-width of our table.
     * This can be achieved through table-layout: fixed;
     * However, we want to have automatic cell sizing dependent on content.
     * Therefore, we have a normal table, get the auto-sized cell width and set the layout to fixed afterwards.
     */
    if (this.isResizable || this.isTruncatable) {
      const firstRow = this.tableEl.firstChild
      const tableHeaders = Array.prototype.slice.call(firstRow.childNodes)
      tableHeaders.forEach(tableHeader => {
        const width = tableHeader.getBoundingClientRect().width
        tableHeader.style.width = width + 'px'
      })

      this.tableEl.style.tableLayout = 'fixed'
      this.tableEl.classList.add('is-fixed')

      // Remove styles set by initialMaxWidth and initialWidth after copying rendered width into th styles
      if (this.isResizable) {
        const tableRows = Array.from(this.tableEl.children[1].children)
        tableRows.forEach(tableRow => {
          Array.from(tableRow.children).forEach(cell => {
            cell.firstChild.style.width = null
            cell.firstChild.style.maxWidth = null
            cell.firstChild.style.minWidth = null
          })
        })
      }
    }

    /**
     * It makes no sense to have both props `isExpandable` and `isTruncatable` activated on an instance
     * of DpDataTable from a UX perspective, since both do roughly the same, but based on a different kind
     * of presentation.
     */
    if (this.isExpandable && this.isTruncatable) {
      console.error('`isExpandable` and `isTruncatable` should not be activated at the same time when using DpDataTable.')
    }

    this.forceElementSelections(this.shouldBeSelectedItems)
    this.setIndeterminate()
  },

  render: function (h) {
    const self = this
    const scopedSlots = this.$scopedSlots
    const fields = self.headerFields.map(hf => hf.field)
    const items = this.items
    const headerTranslations = this.extractTranslations(['headerSelectHint'])

    const rowItems = items.map((item, idx) => {
      return h(DpTableRow, {
        props: {
          checked: self.elementSelections[item[self.trackBy]] || false,
          expanded: self.expandedElements[item[self.trackBy]] || false,
          fields: fields,
          hasFlyout: self.hasFlyout,
          headerFields: self.headerFields,
          index: idx,
          isDraggable: self.isDraggable,
          isExpandable: self.isExpandable,
          isLoading: self.isLoading && self.items.length > 0,
          isLocked: self.lockCheckboxBy ? item[self.lockCheckboxBy] : false,
          isLockedMessage: self.mergedTranslations.lockedForSelection,
          isResizable: self.isResizable,
          isSelectable: self.isSelectable,
          isSelectableName: self.isSelectableName,
          isTruncatable: self.isTruncatable,
          item: item,
          searchTerm: self.searchTerm,
          trackBy: self.trackBy,
          wrapped: self.wrappedElements[item[self.trackBy]] || false
        },
        on: {
          toggleExpand: self.toggleExpand,
          toggleSelect: self.toggleSelect,
          toggleWrap: self.toggleWrap
        },
        scopedSlots: {
          ...scopedSlots
        }
      })
    })

    const tableHeaderData = {
      props: {
        checked: self.allSelected,
        hasFlyout: self.hasFlyout,
        headerFields: self.headerFields,
        indeterminate: self.indeterminate,
        isDraggable: self.isDraggable,
        isExpandable: self.isExpandable,
        isResizable: self.isResizable,
        isSelectable: self.isSelectable,
        isSticky: self.hasStickyHeader,
        isTruncatable: self.isTruncatable,
        translations: headerTranslations
      },
      on: {
        toggleExpandAll: self.toggleExpandAll,
        toggleSelectAll: self.toggleSelectAll,
        toggleWrapAll: self.toggleWrapAll
      },
      scopedSlots: {
        ...scopedSlots
      }
    }

    let noEntriesItem, noResultsItem

    // Generate placeholder items if there are no other items to display
    if (rowItems.length === 0) {
      const noEntriesData = {}

      noEntriesData.attrs = {
        class: 'u-pt',
        colspan: fields.length + (self.isSelectable && 1) || 0
      }

      const loadingEl = h(DpLoading, {
        props: {
          isLoading: true
        },
        attrs: {
          class: 'u-mt',
          colspan: fields.length + (self.isSelectable && 1) || 0
        }
      })

      noEntriesItem = self.isLoading ? h('td', [loadingEl]) : h('td', noEntriesData, self.mergedTranslations.tableNoElements)

      // If there is no searchTerm an empty RegexEp() object with source '(?:)' is returned
      const searchTermSet = self.searchTerm.source !== '(?:)'
      if (searchTermSet) {
        const noResultsData = { ...noEntriesData }
        noResultsData.domProps = {
          // The searchNoResults translation has to be a function -> code in created() ensures that it will be a function
          innerHTML: self.mergedTranslations.searchNoResults(DomPurify.sanitize('"' + this.searchString + '"'))
        }

        noResultsItem = h('td', noResultsData)
      }
    }

    /**
     * If self.headerFields include at least one item with a `colClass` property defined, this is treated as a Css class
     * and rendered into a colgroup to enable equal column sizing of multiple instances of DpDataTable.
     * Colgroup does not work in tandem with `is-resizable` if the class defines a width.
     * Note that a very limited set of Css properties apply to columns (see https://www.w3.org/TR/CSS21/tables.html#columns).
     */
    let colGroup

    if (self.headerFields.filter(field => field.colClass).length > 0) {
      const cols = self.headerFields.map(field => {
        return h('col', { attrs: { class: field.colClass } })
      })

      const emptyCol = h('col')

      /*
       * Prepend a col element for each of these props set to true, as they
       * introduce additional td elements by themselves.
       */
      for (const condition of [self.isDraggable, self.isSelectable]) {
        if (condition) {
          cols.unshift(emptyCol)
        }
      }

      /*
       * Append a col element for each of these props set to true, as they
       * introduce additional td elements by themselves.
       */
      for (const condition of [self.hasFlyout, self.isExpandable, self.isTruncatable]) {
        if (condition) {
          cols.push(emptyCol)
        }
      }

      colGroup = h('colgroup', cols)
      if (this.isResizable) {
        console.warn('"isResizable" will not work with "colClass" property set in headerFields when applying width definitions.')
      }
    }

    let bodyEl = 'tbody'
    let bodyData = {}
    if (self.isDraggable) {
      bodyEl = draggable
      bodyData = {
        props: {
          tag: 'tbody',
          value: items,
          handle: '[data-handle]'
        },
        on: {
          change: (e) => self.$emit('changed-order', e)
        }
      }
    }

    return h('div',
      [
        h('table', { ref: 'tableEl', class: self.tableClass }, [
          colGroup,
          h(DpTableHeader, tableHeaderData),
          h(bodyEl, bodyData, (rowItems.length && rowItems) || [noResultsItem || noEntriesItem])
        ])
      ])
  }
}
</script>
