<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-list">
    <div class="cv-container">
<!--      <div class="cv-header-row">
        <h4 class="cv-main-title">
          Stellungnahmen zum aktuellen Verfahren
        </h4>
        <span class="cv-switch-label">Darstellung</span>

        &lt;!&ndash; Content Switcher &ndash;&gt;
        <cv-content-switcher @selected="onTabSwitch">
          <cv-content-switcher-button
            content-selector=".statements-content"
            :selected="activeTab === 'statements'">
            Stellungnahmen
          </cv-content-switcher-button>
          <cv-content-switcher-button
            content-selector=".sections-content"
            :selected="activeTab === 'sections'">
            Abschnitte
          </cv-content-switcher-button>
        </cv-content-switcher>
      </div>-->

      <!-- Tab Content -->
      <cv-data-table
        v-if="activeTab === 'statements'"
        :columns="filteredColumns"
        batch-cancel-label="Abbrechen"
        :data="statements"
        id="cv-statement-table"
        :rows-selected="selectedRows"
        sortable
        @sort="onSort"
      >
        <template v-slot:actions>
          <cv-search
            light
            placeholder="Suchen"
            :value="searchValue"
            @input="applySearch"
          />
          <!-- Custom Column Selector Button -->
          <div class="cv-column-selector">
            <div
              class="cv-column-selector-trigger"
              @click="toggleColumnSelector"
              ref="columnTrigger">
              <cv-button
                id="colSort"
                kind="ghost">
                Spalten anpassen <ChevronDown16 />
              </cv-button>
            </div>
          </div>
          <cv-button
            kind="tertiary"
            class="cv-export-btn">
            Exportieren <Export16 />
          </cv-button>
          <cv-button
            kind="primary"
            class="cv-add-btn">
            Neue Stellungnahme hinzufügen <DocumentAdd16 />
          </cv-button>
        </template>

        <!-- Batch Actions -->
        <template v-slot:batch-actions>
          <cv-button
            kind="primary"
            size="default">
            Aufteilung überprüfen
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            Aufteilung so akzeptieren
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            Bearbeiten
          </cv-button>
          <cv-button
            kind="primary"
            size="default">
            Löschen
          </cv-button>
        </template>

        <!-- Custom Data Slot mit direkter Checkbox-Überwachung -->
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
                  :kind="getStatusType(statement.status)"
                  :class="getStatusClass(statement.status)" />
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
                  :kind="getConfidenceType(statement.confidence)"
                  :class="getConfidenceClass(statement.confidence)" />
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('expand')">
                <cv-button
                  kind="ghost"
                  size="sm"
                  @click="toggleRowExpansion(statement.id)"
                  :aria-label="`Expand row ${statement.id}`">
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
        page-sizes-label="Elemente pro Seite:"
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

  <!-- Sections Content -->
  <cv-segment-list
    v-if="activeTab === 'sections'"
    :current-user-id="currentUserId"
    :procedure-id="procedureId" />

  <!-- Column Selector Dropdown (rendered outside table) -->
  <div
    v-if="isColumnSelectorOpen"
    class="cv-column-selector-dropdown-overlay"
    ref="columnDropdown">
    <div class="cv-column-selector-dropdown">
      <div class="cv-column-selector-header">
        <span>Spalten auswählen</span>
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
            :aria-label="`Toggle ${column.label} column`"
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
  CvContentSwitcher,
  CvContentSwitcherButton,
  CvDataTable,
  CvDataTableCell,
  CvDataTableRow,
  CvPagination,
  CvSearch,
  CvTag
} from '@carbon/vue'
import { mapActions, mapState } from 'vuex'
import ChevronDown16 from '@carbon/icons-vue/es/chevron--down/16'
import CvSegmentList from './segments/CvSegmentList'
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
    CvSegmentList,
    CvTag,
    CvContentSwitcher,
    CvContentSwitcherButton,
    DocumentAdd16,
    Export16,
    ChevronDown16
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    procedureId: {
      required: true,
      type: String
    },

    localStorageKey: {
      type: String,
      default: 'statementList'
    },

    useLocalStorage: {
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      activeTab: 'statements',
      headerCheckboxHandler: null, // Store handler for cleanup
      clientSortField: null, // For client-side sorting
      clientSortDirection: null,
      columns: [
        { key: 'id', label: 'ID', sortable: true },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'institution', label: 'Institution', sortable: true },
        { key: 'sections', label: 'Abschnitte', headingStyle: { 'pointer-events': 'none' } },
        { key: 'confidence', label: 'Konfidenz', sortable: true },
        { key: 'expand', label: '', headingStyle: { 'pointer-events': 'none' } }
      ],
      pagination: {
        currentPage: 1,
        perPage: 10,
        total: 0,
        totalPages: 0
      },
      selectedRows: [],
      searchValue: '',
      sortBy: '',
      sortDirection: '',
      expandedRows: [], // Track which rows are expanded
      lastPaginationEventTime: 0, // Debouncing for pagination events
      isColumnSelectorOpen: false,
      visibleColumns: [],
      selectableColumns: [
        { key: 'id', label: 'ID' },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'institution', label: 'Institution' },
        { key: 'sections', label: 'Abschnitte' },
        { key: 'confidence', label: 'Konfidenz' }
      ]
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
        return {
          id: stmt.attributes?.externId || stmt.id,
          status: this.mapApiStatusToDisplay(stmt.attributes.status),
          statusDate: stmt.attributes.submitDate,
          author: stmt.attributes.authorName,
          authorDate: this.formatDate(stmt.attributes.authoredDate),
          institution: stmt.attributes?.initialOrganisationName || '-',
          text: stmt.attributes?.text || stmt.text, // For expanded row
          sections: segmentsCount > 0 ? segmentsCount : '-',
          confidence: Math.floor(Math.random() * 100) + 1 // Dummy: 1-100%
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
        // Status sorting
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
    // Vuex Actions importieren
    ...mapActions('Statement', {
      fetchStatements: 'list'
    }),

    applySearch (term, page = 1) {
      // Check if the search term has changed
      const searchChanged = term !== this.searchValue
      this.searchValue = term

      // Always go to page 1 when search term changes, otherwise use specified page
      const targetPage = searchChanged ? 1 : page

      this.fetchStatements({
        page: { number: targetPage, size: this.pagination.perPage },
        search: {
          value: this.searchValue
        },
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        sort: this.getSortString(), // Dynamic sort based on UI selection
        include: ['segments', 'assignee', 'sourceAttachment', 'sourceAttachment.file'].join(),
        fields: {
          Statement: [
            'authoredDate', 'authorName', 'externId', 'isSubmittedByCitizen',
            'initialOrganisationName', 'internId', 'status', 'submitDate',
            'submitName', 'text', 'textIsTruncated', 'segments'
          ].join(),
          SourceStatementAttachment: ['file'].join()
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

    formatDate (dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleDateString('de-DE')
    },

    getConfidenceType (confidence) {
      if (confidence <= 33) return 'red'
      if (confidence <= 66) return 'warm-gray' // Medium = Warm-Gray + Custom Orange 🟠
      return 'green'
    },

    getConfidenceClass (confidence) {
      if (confidence <= 33) return ''
      if (confidence <= 66) return 'cv-confidence-medium'
      return ''
    },

    getStatusType (status) {
      const statusMap = {
        Neu: 'blue', // Carbon Standard
        'In Bearbeitung': 'gray', // CSS Override to 'orange'
        Abgeschlossen: 'gray' // CSS Override to 'green'
      }
      return statusMap[status] || 'gray'
    },

    getStatusClass (status) {
      const classMap = {
        'In Bearbeitung': 'status-editing',
        Abgeschlossen: 'status-completed'
      }
      return classMap[status] || ''
    },

    // Status Mapping
    mapApiStatusToDisplay (apiStatus) {
      const statusMap = {
        new: 'Neu',
        processing: 'In Bearbeitung',
        completed: 'Abgeschlossen'
      }
      return statusMap[apiStatus] || apiStatus
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
      if (this.sortBy !== '' && this.sortDirection) {
        const direction = this.sortDirection === 'ascending' ? '' : '-'

        // Mapping from Column Index to API Field
        const sortFieldMap = {
          0: null,
          1: null,
          2: 'submitName',
          3: null,
          4: null,
          5: null
        }

        // Client-side sortieren
        if (this.sortBy === 0) {
          this.sortStatementsClientSide('id')
          return '-submitDate,id' // Fallback API sort
        }
        if (this.sortBy === 1) {
          this.sortStatementsClientSide('status')
          return '-submitDate,id' // Fallback API sort
        }
        if (this.sortBy === 3) {
          this.sortStatementsClientSide('institution')
          return '-submitDate,id' // Fallback API sort
        }
        if (this.sortBy === 5) {
          this.sortStatementsClientSide('confidence')
          return '-submitDate,id' // Fallback API sort
        }

        const field = sortFieldMap[this.sortBy]
        return field ? `${direction}${field}` : '-submitDate,id'
      }
      // Default sort when no sorting is active
      return '-submitDate,id'
    },

    onTabSwitch (selectedButton) {
      if (selectedButton.includes('statements')) {
        this.activeTab = 'statements'
      } else if (selectedButton.includes('sections')) {
        this.activeTab = 'sections'
      }
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
      console.log('setupCheckboxListeners called')
      // Wait for DOM to be fully rendered with statements
      this.$nextTick(() => {
        console.log('DOM is ready, looking for checkboxes')
        // Header checkbox for "Select All" - Debug multiple selectors
        const headerSelectors = [
          '#cv-statement-table .bx--table-head .bx--table-column-checkbox input',
          '#cv-statement-table thead .bx--table-column-checkbox input',
          '#cv-statement-table .bx--data-table-header .bx--table-column-checkbox input',
          '#cv-statement-table th .bx--table-column-checkbox input'
        ]

        let headerCheckbox = null
        for (const selector of headerSelectors) {
          headerCheckbox = document.querySelector(selector)
          if (headerCheckbox) {
            console.log('Found header checkbox with selector:', selector)
            break
          }
        }

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

        // Individual row checkboxes - exclude header checkbox
        const checkboxes = document.querySelectorAll('#cv-statement-table tbody .bx--table-column-checkbox input[type="checkbox"]')

        checkboxes.forEach((checkbox) => {

          checkbox.addEventListener('change', (event) => {
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
          })
        })
      })
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

      // Disable forward button on last page
      const paginationEl = document.querySelector('.cv-statement-list .bx--pagination')
      if (paginationEl && this.pagination.currentPage >= this.pagination.totalPages) {
        paginationEl.setAttribute('data-last-page', 'true')

        // Block clicks on forward button
        const forwardBtn = paginationEl.querySelector('.bx--pagination__button--forward')
        if (forwardBtn) {
          forwardBtn.addEventListener('click', (e) => {
            e.preventDefault()
            e.stopPropagation()
          }, { once: false })
        }
      } else if (paginationEl) {
        paginationEl.removeAttribute('data-last-page')
      }
    }
  },

  mounted () {
    // Initialize visibleColumns from localStorage
    this.visibleColumns = this.loadColumnSelection()

    this.fetchStatements({
      page: { number: 1, size: this.pagination.perPage },
      filter: {
        procedureId: {
          condition: {
            path: 'procedure.id',
            value: this.procedureId
          }
        }
      },
      include: ['segments'].join(),
      fields: {
        Statement: [
          'authoredDate', 'authorName', 'externId', 'isSubmittedByCitizen',
          'initialOrganisationName', 'internId', 'status', 'submitDate',
          'submitName', 'text', 'textIsTruncated', 'segments'
        ].join()
      }
    }).then(response => {
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
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>
