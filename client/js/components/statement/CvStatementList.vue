<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-list">
    <div class="cv-container">
      <!-- Statements Table -->
      <cv-data-table
        :columns="filteredColumns"
        :batch-cancel-label="Translator.trans('abort')"
        :data="statements"
        id="cv-statement-table"
        :rows-selected="selectedRows"
        sortable
        @sort="onSort"
      >
        <template v-slot:actions>
          <cv-search
            light
            :placeholder="Translator.trans('searching')"
            :value="searchValue"
            @input="applySearch"
          />
          <!-- Custom Column Selector Button -->
          <div class="cv-column-selector">
            <div
              class="cv-column-selector-trigger"
              @click="toggleColumnSelector"
              @keydown.enter="toggleColumnSelector"
              @keydown.space="toggleColumnSelector"
              ref="columnTrigger">
              <cv-button
                id="colSort"
                kind="ghost">
                {{ localTranslations.adjustColumns }} <ChevronDown16 />
              </cv-button>
            </div>
          </div>
          <cv-button
            kind="tertiary"
            class="cv-export-btn">
            {{ Translator.trans('export') }} <Export16 />
          </cv-button>
          <cv-button
            kind="primary"
            class="cv-add-btn"
            @click="createNewStatement">
            {{ localTranslations.addNewStatement }} <DocumentAdd16 />
          </cv-button>
        </template>

        <!-- Batch Actions -->
        <template v-slot:batch-actions>
          <cv-button
            kind="primary"
            size="default">
            {{ localTranslations.checkSegmentation }}
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            {{ localTranslations.acceptSegmentation }}
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            {{ Translator.trans('edit') }}
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            {{ Translator.trans('delete') }}
          </cv-button>
        </template>

        <!-- Custom Data Slot: Using v-slot:data instead of default table rows
             because Carbon's native checkbox selection has known bugs with
             dynamic data and custom row expansion. Manual checkbox handling
             provides more reliable selection state management. -->
        <template v-slot:data>
          <template
            v-for="(statement, index) in statements"
            :key="index">
            <cv-data-table-row
              :value="String(statement.id)"
              :id="`row-${statement.id}`">
              <cv-data-table-cell v-if="visibleColumns.includes('id')">
                {{ statement.id }}
              </cv-data-table-cell>
              <cv-data-table-cell v-if="visibleColumns.includes('status')">
                <cv-tag
                  :label="statement.status"
                  :kind="statusTypes[statement.status] || 'gray'"
                  :class="statusClasses[statement.status] || ''" />
              </cv-data-table-cell>
              <cv-data-table-cell v-if="visibleColumns.includes('author')">
                <div class="cv-author-cell">
                  <div class="cv-author-name">
                    {{ statement.author }}
                  </div>
                  <div class="cv-author-date">
                    {{ statement.authorDate }}
                  </div>
                </div>
              </cv-data-table-cell>
              <cv-data-table-cell v-if="visibleColumns.includes('institution')">
                {{ statement.institution }}
              </cv-data-table-cell>
              <cv-data-table-cell v-if="visibleColumns.includes('sections')">
                {{ statement.sections }}
              </cv-data-table-cell>
              <cv-data-table-cell v-if="visibleColumns.includes('confidence')">
                <cv-tag
                  :label="`${statement.confidence}%`"
                  :kind="confidenceTypes[statement.confidenceType]"
                  :class="confidenceClasses[statement.confidenceType] || ''" />
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('expand')">
                <cv-button
                  kind="ghost"
                  size="sm"
                  @click="toggleRowExpansion(statement.id)"
                  :aria-label="Translator.trans('dropdown.open')">
                  <ChevronDown16 :style="{ transform: expandedRows.includes(statement.id) ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s' }" />
                </cv-button>
              </cv-data-table-cell>
            </cv-data-table-row>

            <!-- Expanded Content Row -->
            <cv-data-table-row
              v-if="expandedRows.includes(statement.id)"
              class="cv-expanded-row">
              <cv-data-table-cell :colspan="visibleColumns.length">
                <div v-html="statement.text" />
              </cv-data-table-cell>
            </cv-data-table-row>
          </template>
        </template>
      </cv-data-table>

      <cv-pagination
        v-if="pagination.totalPages > 1"
        :page="pagination.currentPage"
        :page-sizes="computedPageSizes"
        :number-of-items="pagination.total"
        :page-sizes-label="localTranslations.elementsPerPage"
        @change="onPaginationChange"
        class="cv-pagination">
        <template v-slot:range-text="{ scope }">
          {{ scope.start }}-{{ scope.end }} von {{ scope.items }} Elementen
        </template>
        <template v-slot:of-n-pages="{ scope }">
          von {{ scope.pages }} Seiten
        </template>
      </cv-pagination>
    </div>
  </div>

  <!-- Column Selector Dropdown (rendered outside table) -->
  <div
    v-if="isColumnSelectorOpen"
    class="cv-column-selector-dropdown-overlay"
    ref="columnDropdown">
    <div class="cv-column-selector-dropdown">
      <div class="cv-column-selector-header">
        <span>{{ localTranslations.selectColumns }}</span>
      </div>
      <div class="cv-column-selector-options">
        <cv-button
          v-for="column in selectableColumns"
          :key="column.key"
          kind="tertiary"
          class="cv-column-option-button"
          @click="toggleColumn(column.key)">
          <input
            type="checkbox"
            :checked="visibleColumns.includes(column.key)"
            class="cv-column-checkbox"
            :aria-label="localTranslations.selectColumn"
            readonly
          >
          {{ column.label }}
        </cv-button>
      </div>
    </div>
  </div>
</template>

<script>
import {
  CvButton,
  CvDataTable,
  CvDataTableCell,
  CvDataTableRow,
  CvPagination,
  CvSearch,
  CvTag
} from '@carbon/vue'
import { mapActions, mapState } from 'vuex'
import ChevronDown16 from '@carbon/icons-vue/es/chevron--down/16'
import DocumentAdd16 from '@carbon/icons-vue/es/document--add/16'
import Export16 from '@carbon/icons-vue/es/export/16'

export default {
  name: 'CvStatementList',

  components: {
    CvButton,
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell,
    CvPagination,
    CvSearch,
    CvTag,
    DocumentAdd16,
    Export16,
    ChevronDown16
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    localStorageKey: {
      type: String,
      default: 'statementList'
    },

    procedureId: {
      required: true,
      type: String
    },

    useLocalStorage: {
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      apiStatusLabels: {
        new: Translator.trans('new'),
        processing: Translator.trans('fragment.status.editing'),
        completed: Translator.trans('terminated')
      },
      checkboxListeners: [], // Track individual checkbox listeners for cleanup
      clientSortDirection: null,
      clientSortField: null, // For client-side sorting
      columns: [
        { key: 'id', label: 'ID', sortable: true },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'institution', label: 'Institution', sortable: true },
        { key: 'sections', label: 'Abschnitte', headingStyle: { 'pointer-events': 'none' } },
        { key: 'confidence', label: 'Konfidenz', sortable: true },
        { key: 'expand', label: '', headingStyle: { 'pointer-events': 'none' } }
      ],
      // Confidence mapping objects
      confidenceClasses: {
        medium: 'cv-confidence-medium'
      },
      confidenceTypes: {
        low: 'red', // <= 33%
        medium: 'warm-gray', // 34-66%
        high: 'green' // >= 67%
      },
      expandedRows: [],
      headerCheckboxHandler: null, // Store handler for cleanup
      isColumnSelectorOpen: false,
      lastPaginationEventTime: 0, // Debouncing for pagination events
      /*
       * Local translations: Temporary storage for strings that don't have
       * translation keys yet in messages+intl-icu.de.yml
       */
      localTranslations: {
        acceptSegmentation: 'Aufteilung so akzeptieren',
        addNewStatement: 'Neue Stellungnahme hinzufügen',
        adjustColumns: 'Spalten anpassen',
        checkSegmentation: 'Aufteilung überprüfen',
        elementsPerPage: 'Elemente pro Seite:',
        selectColumn: 'Spalte auswählen',
        selectColumns: 'Spalten auswählen'
      },
      pagination: {
        currentPage: 1,
        perPage: 10,
        total: 0,
        totalPages: 0
      },
      searchValue: '',
      selectableColumns: [
        { key: 'id', label: 'ID' },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'institution', label: 'Institution' },
        { key: 'sections', label: 'Abschnitte' },
        { key: 'confidence', label: 'Konfidenz' }
      ],
      selectedRows: [],
      sortBy: '',
      sortDirection: '',
      statusClasses: {
        'In Bearbeitung': 'status-editing',
        Abgeschlossen: 'status-completed'
      },
      statusTypes: {
        Neu: 'blue',
        'In Bearbeitung': 'gray',
        Abgeschlossen: 'gray'
      },
      visibleColumns: []
    }
  },

  computed: {
    ...mapState('Statement', {
      statementsObject: 'items'
    }),

    statements () {
      const rawData = Object.values(this.statementsObject) || []
      const processedData = rawData.map(stmt => {
        const segmentsCount = stmt.relationships?.segments?.data?.length || 0
        // Dummy confidence value - replace with API data when available
        const confidence = 75
        let confidenceType
        if (confidence <= 33) {
          confidenceType = 'low'
        } else if (confidence <= 66) {
          confidenceType = 'medium'
        } else {
          confidenceType = 'high'
        }

        return {
          id: stmt.attributes?.externId || stmt.id,
          status: this.apiStatusLabels[stmt.attributes.status] || stmt.attributes.status,
          statusDate: stmt.attributes.submitDate,
          author: stmt.attributes.authorName,
          authorDate: this.formatDate(stmt.attributes.authoredDate),
          institution: stmt.attributes?.initialOrganisationName || '-',
          text: stmt.attributes?.text || stmt.text, // For expanded row
          sections: segmentsCount > 0 ? segmentsCount : '-',
          confidence,
          confidenceType
        }
      })

      // Client-side sorting
      if (this.clientSortField === 'id') {
        // ID sorting
        processedData.sort((a, b) => {
          const numA = this.extractNumericId(a.id)
          const numB = this.extractNumericId(b.id)

          return this.clientSortDirection === 'ascending' ? numA - numB : numB - numA
        })
      } else if (this.clientSortField === 'status') {
        /*
         * Status sorting: Carbon table doesn't support semantic status sorting,
         * so we handle it client-side with custom order
         */
        const statusOrder = { Neu: 1, 'In Bearbeitung': 2, Abgeschlossen: 3 }
        processedData.sort((a, b) => {
          const orderA = statusOrder[a.status] || 999
          const orderB = statusOrder[b.status] || 999

          return this.clientSortDirection === 'ascending' ? orderA - orderB : orderB - orderA
        })
      } else if (this.clientSortField === 'institution') {
        // Institution sorting
        processedData.sort((a, b) => {
          const instA = (a.institution || '').toLowerCase()
          const instB = (b.institution || '').toLowerCase()

          if (this.clientSortDirection === 'ascending') {
            return instA.localeCompare(instB)
          } else {
            return instB.localeCompare(instA)
          }
        })
      } else if (this.clientSortField === 'confidence') {
        // Confidence sorting
        processedData.sort((a, b) => {
          const confA = a.confidence || 0
          const confB = b.confidence || 0

          return this.clientSortDirection === 'ascending' ? confA - confB : confB - confA
        })
      }

      return processedData
    },

    computedPageSizes () {
      return [10, 25, 50, 100].map(size => ({
        value: size,
        selected: size === this.pagination.perPage
      }))
    },

    filteredColumns () {
      return this.columns.filter(column => this.visibleColumns.includes(column.key))
    }
  },

  watch: {
    statements: {
      handler (newStatements) {
        if (newStatements.length > 0) {
          // Re-setup checkbox listeners when statements are loaded
          this.$nextTick(() => {
            this.setupCheckboxListeners()
          })
        }
      },
      deep: true
    }
  },

  methods: {
    ...mapActions('Statement', {
      fetchStatements: 'list'
    }),

    handleFetchStatements (options = {}) {
      const defaultOptions = {
        page: { number: 1, size: this.pagination.perPage },
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        include: ['segments', 'assignee', 'sourceAttachment', 'sourceAttachment.file'].join(),
        fields: {
          Statement: [
            'authoredDate',
            'authorName',
            'externId',
            'isSubmittedByCitizen',
            'initialOrganisationName',
            'internId',
            'status',
            'submitDate',
            'submitName',
            'text',
            'textIsTruncated',
            'segments'
          ].join(),
          SourceStatementAttachment: ['file'].join()
        },
        sort: this.getSortString()
      }

      const mergedOptions = { ...defaultOptions, ...options }
      return this.fetchStatements(mergedOptions)
    },
    applySearch (term, page = 1) {
      // Check if the search term has changed
      const searchChanged = term !== this.searchValue
      this.searchValue = term

      // Always go to page 1 when search term changes, otherwise use specified page
      const targetPage = searchChanged ? 1 : page

      this.handleFetchStatements({
        page: { number: targetPage, size: this.pagination.perPage },
        search: {
          value: this.searchValue
        }
      }).then(response => {
        if (response?.meta?.pagination) {
          // Use API totalPages if perPage matches, otherwise recalculate
          const shouldUseApiPages = response.meta.pagination.per_page === this.pagination.perPage
          const finalTotalPages = shouldUseApiPages
            ? response.meta.pagination.total_pages
            : Math.ceil(response.meta.pagination.total / this.pagination.perPage)

          // Always keep local perPage value - API might send back old value
          this.pagination = {
            ...this.pagination,
            currentPage: response.meta.pagination.current_page,
            total: response.meta.pagination.total,
            totalPages: finalTotalPages
          }
        }
      })
    },

    createNewStatement () {
      const hasSimplifiedCreate = hasPermission('feature_simplified_new_statement_create')
      const route = hasSimplifiedCreate ? 'DemosPlan_procedure_import' : 'DemosPlan_statement_new_submitted'

      window.location.href = Routing.generate(route, { procedureId: this.procedureId })
    },

    formatDate (dateString) {
      if (!dateString) {

        return ''
      }
      const date = new Date(dateString)

      return date.toLocaleDateString('de-DE')
    },
    onPaginationChange (event) {
      const now = Date.now()

      if (event.page && event.page !== this.pagination.currentPage) {
        const maxPage = this.pagination.totalPages
        const targetPage = Math.max(1, Math.min(event.page, maxPage))

        this.applySearch(this.searchValue, targetPage)
      } else if (event.length && event.length !== this.pagination.perPage) {
        // Debounce for size changes
        if (now - this.lastPaginationEventTime < 100) {

          return
        }

        this.lastPaginationEventTime = now

        if ([10, 25, 50, 100].includes(event.length)) {
          this.pagination.perPage = event.length
          this.pagination.currentPage = 1

          this.applySearch(this.searchValue, 1)
        }
      }
    },

    onSort (sortBy) {
      // Toggle sort direction if same column, otherwise set to ascending
      if (this.sortBy === sortBy.index) {
        this.sortDirection = this.sortDirection === 'ascending' ? 'descending' : 'ascending'
      } else {
        this.sortBy = sortBy.index
        this.sortDirection = 'ascending'
      }

      this.applySearch(this.searchValue, 1)
    },

    extractNumericId (id) {
      const match = String(id).match(/\d+/)

      return match ? parseInt(match[0]) : 0
    },

    getSortString () {
      /**
       * Converts Carbon table column sorting to API sort parameters.
       *
       * sortBy data flow:
       * - Initialized as empty string in data()
       * - Set to column index (number) by Carbon table's @sort event
       * - Used here to determine API vs client-side sorting
       *
       * Mixed sorting approach needed because:
       * - API supports limited sorting (only submitName field)
       * - Custom sorts (ID extraction, status priority, institution names)
       *   must be handled client-side
       * - Always return API sort string for pagination consistency
       */
      if (this.sortBy !== '' && this.sortDirection) {
        const direction = this.sortDirection === 'ascending' ? '' : '-'

        /*
         * Column index to API field mapping
         * null = client-side sorting required
         */
        const sortFieldMap = {
          0: null, // ID - extract numeric part client-side
          1: null, // Status - custom priority order client-side
          2: 'submitName', // Author - API supports this field
          3: null, // Institution - name comparison client-side
          4: null, // Sections - not sortable (display only)
          5: null // Confidence - dummy data, client-side for now
        }

        // Handle client-side sorting columns (indices 0,1,3,5)
        if (this.sortBy === 0) {
          this.sortStatementsClientSide('id')

          return '-submitDate,id' // Fallback to keep pagination stable
        }
        if (this.sortBy === 1) {
          this.sortStatementsClientSide('status')

          return '-submitDate,id'
        }
        if (this.sortBy === 3) {
          this.sortStatementsClientSide('institution')

          return '-submitDate,id'
        }
        if (this.sortBy === 5) {
          this.sortStatementsClientSide('confidence')

          return '-submitDate,id'
        }

        // Handle API-supported sorting (index 2 = submitName)
        const field = sortFieldMap[this.sortBy]

        return field ? `${direction}${field}` : '-submitDate,id'
      }
      // Default sort: newest statements first, then by ID for consistency

      return '-submitDate,id'
    },

    toggleRowExpansion (rowId) {
      const index = this.expandedRows.indexOf(rowId)
      if (index > -1) {
        // Row is expanded - collapse
        this.expandedRows.splice(index, 1)
      } else {
        // Row is collapsed - expand
        this.expandedRows.push(rowId)
      }
    },

    sortStatementsClientSide (sortField) {
      // Set client-side sorting parameters
      this.clientSortField = sortField
      this.clientSortDirection = this.sortDirection
      // Computed property statements() will be automatically recalculated
    },

    toggleColumnSelector () {
      this.isColumnSelectorOpen = !this.isColumnSelectorOpen
      if (this.isColumnSelectorOpen) {
        this.$nextTick(() => {
          this.positionDropdown()
          document.addEventListener('click', this.handleOutsideClick)
        })
      } else {
        document.removeEventListener('click', this.handleOutsideClick)
      }
    },

    positionDropdown () {
      if (this.$refs.columnTrigger && this.$refs.columnDropdown) {
        const triggerRect = this.$refs.columnTrigger.getBoundingClientRect()
        const dropdown = this.$refs.columnDropdown.querySelector('.cv-column-selector-dropdown')

        if (dropdown) {
          dropdown.style.top = `${triggerRect.bottom + 4}px`
          dropdown.style.right = `${window.innerWidth - triggerRect.right}px`
        }
      }
    },

    handleOutsideClick (event) {
      if (this.$refs.columnTrigger && !this.$refs.columnTrigger.contains(event.target) &&
          this.$refs.columnDropdown && !this.$refs.columnDropdown.contains(event.target)) {
        this.isColumnSelectorOpen = false
        document.removeEventListener('click', this.handleOutsideClick)
      }
    },

    toggleColumn (columnKey) {
      if (this.visibleColumns.includes(columnKey)) {
        this.visibleColumns = this.visibleColumns.filter(key => key !== columnKey)
      } else {
        this.visibleColumns.push(columnKey)
      }
      this.saveColumnSelection()
    },

    saveColumnSelection () {
      if (this.useLocalStorage) {
        localStorage.setItem(this.localStorageKey, JSON.stringify(this.visibleColumns))
      }
    },

    loadColumnSelection () {
      if (this.useLocalStorage) {
        const stored = localStorage.getItem(this.localStorageKey)

        return stored ? JSON.parse(stored) : ['id', 'status', 'author', 'institution', 'sections', 'confidence', 'expand']
      }

      return ['id', 'status', 'author', 'institution', 'sections', 'confidence', 'expand']
    },

    setupCheckboxListeners () {
      /*
       * Manual checkbox handling: Carbon's native selection has bugs with dynamic data
       * and row expansion. We manually bind to checkbox events for reliable state management.
       *
       * Clean up existing listeners first
       */
      this.removeCheckboxListeners()

      this.$nextTick(() => {
        // Header checkbox for "Select All"
        const headerCheckbox = document.querySelector('#cv-statement-table .bx--table-head .bx--table-column-checkbox input')

        if (headerCheckbox) {
          // Remove existing listeners to prevent conflicts
          headerCheckbox.removeEventListener('change', this.headerCheckboxHandler)

          this.headerCheckboxHandler = (event) => {
            if (event.target.checked) {
              // Check all rows
              this.selectedRows = this.statements.map(s => String(s.id))
            } else {
              // Uncheck all rows
              this.selectedRows = []
            }
            this.updateBatchActionsVisibility()
            this.updateRowCheckboxes()
          }

          headerCheckbox.addEventListener('change', this.headerCheckboxHandler)
        }

        // Individual row checkboxes
        const checkboxes = document.querySelectorAll('#cv-statement-table .bx--table-column-checkbox input[type="checkbox"]')

        checkboxes.forEach((checkbox) => {
          // Skip header checkbox (already handled above)
          if (checkbox.closest('.bx--table-head')) return

          const handler = (event) => {
            const rowValue = event.target.closest('tr')?.getAttribute('data-value') || event.target.value

            if (event.target.checked) {
              if (!this.selectedRows.includes(rowValue)) {
                this.selectedRows.push(rowValue)
              }
            } else {
              const index = this.selectedRows.indexOf(rowValue)
              if (index > -1) {
                this.selectedRows.splice(index, 1)
              }
            }

            this.updateBatchActionsVisibility()
            this.updateHeaderCheckboxState()
          }

          checkbox.addEventListener('change', handler)

          // Track listener for cleanup
          this.checkboxListeners.push({ element: checkbox, handler })
        })
      })
    },

    removeCheckboxListeners () {
      // Remove all individual checkbox listeners
      this.checkboxListeners.forEach(({ element, handler }) => {
        element.removeEventListener('change', handler)
      })
      this.checkboxListeners = []

      // Remove header checkbox listener
      if (this.headerCheckboxHandler) {
        const headerCheckbox = document.querySelector('#cv-statement-table .bx--table-head .bx--table-column-checkbox input')
        if (headerCheckbox) {
          headerCheckbox.removeEventListener('change', this.headerCheckboxHandler)
        }
        this.headerCheckboxHandler = null
      }
    },
    updateBatchActionsVisibility () {
      const batchActions = document.querySelector('.bx--batch-actions')
      if (batchActions) {
        if (this.selectedRows.length > 0) {
          batchActions.setAttribute('aria-hidden', 'false')
          batchActions.classList.add('bx--batch-actions--active')

          // Update Carbon's internal counter
          const itemsSelectedSpan = batchActions.querySelector('[data-items-selected]')
          if (itemsSelectedSpan) {
            itemsSelectedSpan.textContent = `${this.selectedRows.length} items selected`
          }
        } else {
          batchActions.setAttribute('aria-hidden', 'true')
          batchActions.classList.remove('bx--batch-actions--active')
        }
      }
    },

    updateHeaderCheckboxState () {
      const headerCheckbox = document.querySelector('#cv-statement-table .bx--table-head .bx--table-column-checkbox input')
      if (headerCheckbox) {
        const totalRows = this.statements.length
        const selectedCount = this.selectedRows.length

        if (selectedCount === 0) {
          headerCheckbox.checked = false
          headerCheckbox.indeterminate = false
        } else if (selectedCount === totalRows) {
          headerCheckbox.checked = true
          headerCheckbox.indeterminate = false
        } else {
          headerCheckbox.checked = false
          headerCheckbox.indeterminate = true
        }
      }
    },

    updateRowCheckboxes () {
      const checkboxes = document.querySelectorAll('#cv-statement-table .bx--data-table tbody .bx--table-column-checkbox input')
      checkboxes.forEach((checkbox) => {
        const rowValue = checkbox.closest('tr')?.getAttribute('data-value')
        if (rowValue) {
          checkbox.checked = this.selectedRows.includes(rowValue)
        }
      })
    },

    replacePaginationText () {
      const pageTexts = document.querySelectorAll('.bx--pagination__right .bx--pagination__text')
      pageTexts.forEach(element => {
        if (element.textContent.includes('Page')) {
          element.textContent = element.textContent.replace('Page', 'Seite')
        }
      })
    }
  },

  mounted () {
    // Initialize visibleColumns from localStorage
    this.visibleColumns = this.loadColumnSelection()

    this.handleFetchStatements().then(response => {
      if (response?.meta?.pagination) {
        this.pagination = {
          currentPage: response.meta.pagination.current_page,
          perPage: response.meta.pagination.per_page,
          total: response.meta.pagination.total,
          totalPages: response.meta.pagination.total_pages
        }
      }

      // Replace pagination text and disable buttons after DOM updates
      this.$nextTick(() => {
        this.replacePaginationText()
      })
    })
  },

  updated () {
    this.$nextTick(() => {
      this.replacePaginationText()
    })
  },

  beforeUnmount () {
    this.removeCheckboxListeners()
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>
