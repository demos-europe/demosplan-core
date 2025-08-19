<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-list">
    <div class="cv-container">
    <div class="cv-header-row">
      <h4 class="cv-main-title">Stellungnahmen zum aktuellen Verfahren</h4>
      <span class="cv-switch-label">Darstellung</span>

      <!-- Content Switcher -->
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
    </div>

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

      <template #actions>
        <cv-search
          light
          placeholder="Suchen"
          :value="searchValue"
          @input="applySearch"
        />
        <!-- Custom Column Selector Button -->
        <div class="cv-column-selector">
          <div class="cv-column-selector-trigger" @click="toggleColumnSelector" ref="columnTrigger">
            <cv-button id="colSort" kind="ghost">
              Spalten anpassen <ChevronDown16 />
            </cv-button>
          </div>
        </div>
        <cv-button kind="tertiary" class="cv-export-btn">
          Exportieren <Export16 />
        </cv-button>
        <cv-button kind="primary" class="cv-add-btn" @click="openImportModal">
          Neue Stellungnahme hinzufügen <DocumentAdd16 />
        </cv-button>
      </template>

      <!-- Batch Actions -->
      <template #batch-actions>
        <cv-button kind="primary" size="default">
          Aufteilung überprüfen
        </cv-button>
        <cv-button kind="primary" size="default">
          Aufteilung so akzeptieren
        </cv-button>
        <cv-button kind="primary" size="default">
          Bearbeiten
        </cv-button>
        <cv-button kind="primary" size="default">
          Löschen
        </cv-button>
      </template>

      <!-- Custom Data Slot mit direkter Checkbox-Überwachung -->
      <template #data>
        <template v-for="(statement, index) in statements" :key="index">
          <cv-data-table-row
            :value="String(statement.id)"
            :id="`row-${statement.id}`">
            <cv-data-table-cell v-if="visibleColumns.includes('id')">{{ statement.id }}</cv-data-table-cell>
            <cv-data-table-cell v-if="visibleColumns.includes('status')">
              <cv-tag
                :label="statement.status"
                :kind="getStatusType(statement.status)"
                :class="getStatusClass(statement.status)" />
            </cv-data-table-cell>
            <cv-data-table-cell v-if="visibleColumns.includes('author')">
              <div class="cv-author-cell">
                <div class="cv-author-name">{{ statement.author }}</div>
                <div class="cv-author-date">{{ statement.authorDate }}</div>
              </div>
            </cv-data-table-cell>
            <cv-data-table-cell v-if="visibleColumns.includes('institution')">{{ statement.institution }}</cv-data-table-cell>
            <cv-data-table-cell v-if="visibleColumns.includes('sections')">{{ statement.sections }}</cv-data-table-cell>
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
          <cv-data-table-row v-if="expandedRows.includes(statement.id)" class="cv-expanded-row">
            <cv-data-table-cell :colspan="visibleColumns.length">
              <div v-html="statement.text"></div>
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
      <template #range-text="{ scope }">
        {{ scope.start }}-{{ scope.end }} von {{ scope.items }} Elementen
      </template>
      <template #of-n-pages="{ scope }">
        von {{ scope.pages }} Seiten
      </template>
    </cv-pagination>

    </div>
  </div>


  <!-- Sections Content -->
    <div v-if="activeTab === 'sections'">
      <p>Aufteilung in Abschnitte Content - Coming Soon</p>
    </div>

  <!-- Column Selector Dropdown (rendered outside table) -->
  <div v-if="isColumnSelectorOpen" class="cv-column-selector-dropdown-overlay" ref="columnDropdown">
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
            readonly
          />
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
  CvDataTableRow,
  CvDataTableCell,
  CvDataTableHeader,
  CvDataTableHeaderCell,
  CvPagination,
  CvSearch,
  CvTag,
  CvContentSwitcher,
  CvContentSwitcherButton
} from '@carbon/vue'
import DocumentAdd16 from '@carbon/icons-vue/es/document--add/16'
import Export16 from '@carbon/icons-vue/es/export/16'
import Filter16 from '@carbon/icons-vue/es/filter/16'
import Search16 from '@carbon/icons-vue/es/search/16'
import ChevronDown16 from '@carbon/icons-vue/es/chevron--down/16'
import Settings16 from '@carbon/icons-vue/es/settings/16'
import { mapState, mapActions } from 'vuex'

export default {
  name: 'CvStatementList',

  components: {
    CvButton,
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell,
    CvDataTableHeader,
    CvDataTableHeaderCell,
    CvPagination,
    CvSearch,
    CvTag,
    CvContentSwitcher,
    CvContentSwitcherButton,
    DocumentAdd16,
    Export16,
    Filter16,
    Search16,
    ChevronDown16,
    Settings16
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

  data() {
    return {
      activeTab: 'statements',
      headerCheckboxHandler: null, // Store handler for cleanup
      columns: [
        { key: 'id', label: 'ID', sortable: true },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'institution', label: 'Institution' },
        { key: 'sections', label: 'Abschnitte', headingStyle: { 'pointer-events': 'none' } },
        { key: 'confidence', label: 'Konfidenz'},
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
      filterActive: false,
      sortBy: '',
      sortDirection: '',
      expandedRows: [], // Track welche Rows expandiert sind
      lastPaginationEventTime: 0, // Debouncing für Pagination Events
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

    statements() {
      const rawData = Object.values(this.statementsObject) || []
      return rawData.map(stmt => {
        const segmentsCount = stmt.relationships?.segments?.data?.length || 0
        return {
          id: stmt.attributes?.externId || stmt.id,
          status: this.mapApiStatusToDisplay(stmt.attributes.status),
          statusDate: stmt.attributes.submitDate,
          author: stmt.attributes.authorName,
          authorDate: this.formatDate(stmt.attributes.authoredDate),
          institution: stmt.attributes?.initialOrganisationName || '-',
          text: stmt.attributes?.text || stmt.text, // Für expanded row
          sections: segmentsCount > 0 ? segmentsCount : '-',
          confidence: Math.floor(Math.random() * 100) + 1 // Dummy: 1-100%
        }
      })
    },

    computedPageSizes() {
      return [10, 25, 50, 100].map(size => ({
        value: size,
        selected: size === this.pagination.perPage
      }))
    },

    filteredColumns() {
      return this.columns.filter(column => this.visibleColumns.includes(column.key))
    }
  },

  methods: {
    // Vuex Actions importieren
    ...mapActions('Statement', {
      fetchStatements: 'list'
    }),

    applySearch(term, page = 1) {
      // Prüfen ob sich der Suchbegriff geändert hat
      const searchChanged = term !== this.searchValue
      this.searchValue = term

      // Bei geändertem Suchbegriff IMMER zu Seite 1, sonst verwende angegebene Seite
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
        sort: this.getSortString(), // Dynamic sort basierend auf UI-Auswahl
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

          // Verwende API totalPages wenn perPage übereinstimmt, sonst berechne neu
          const shouldUseApiPages = response.meta.pagination.per_page === this.pagination.perPage
          const finalTotalPages = shouldUseApiPages
            ? response.meta.pagination.total_pages
            : Math.ceil(response.meta.pagination.total / this.pagination.perPage)

          // Behalte immer den lokalen perPage-Wert bei - die API könnte den alten Wert zurücksenden
          this.pagination = {
            ...this.pagination,
            currentPage: response.meta.pagination.current_page,
            total: response.meta.pagination.total,
            totalPages: finalTotalPages
          }
        }
      })
    },

    createNewStatement() {
      const hasSimplifiedCreate = hasPermission('feature_simplified_new_statement_create')
      const route = hasSimplifiedCreate ? 'DemosPlan_procedure_import' : 'DemosPlan_statement_new_submitted'

      window.location.href = Routing.generate(route, { procedureId: this.procedureId })
    },

    formatDate(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleDateString('de-DE')  // DD.MM.YYYY Format
    },

    getConfidenceType(confidence) {
      if (confidence <= 33) return 'red'        // Niedrig = Carbon Rot 🔴
      if (confidence <= 66) return 'warm-gray'  // Medium = Warm-Gray + Custom Orange 🟠
      return 'green'                           // Hoch = Carbon Grün 🟢
    },

    getConfidenceClass(confidence) {
      if (confidence <= 33) return ''
      if (confidence <= 66) return 'cv-confidence-medium'
      return ''
    },

    getStatusType(status) {
      const statusMap = {
        'Neu': 'blue',               // Carbon Standard
        'In Bearbeitung': 'gray',    // CSS Override to 'orange'
        'Abgeschlossen': 'gray'      // CSS Override zu 'green'
      }
      return statusMap[status] || 'gray'
    },

    getStatusClass(status) {
      const classMap = {
        'In Bearbeitung': 'status-editing',
        'Abgeschlossen': 'status-completed'
      }
      return classMap[status] || '';
    },

    // Status von API-Format zu Display-Format
    mapApiStatusToDisplay(apiStatus) {
      const statusMap = {
        'new': 'Neu',
        'processing': 'In Bearbeitung',
        'completed': 'Abgeschlossen'
      }
      return statusMap[apiStatus] || apiStatus
    },

    onPaginationChange(event) {
      const now = Date.now()

      // Priorisiere Seitenwechsel über Größenänderungen
      if (event.page && event.page !== this.pagination.currentPage) {
        // Page Navigation - ignoriere gleichzeitige length Events

        const maxPage = this.pagination.totalPages
        const targetPage = Math.max(1, Math.min(event.page, maxPage))

        this.applySearch(this.searchValue, targetPage)

      } else if (event.length && event.length !== this.pagination.perPage) {
        // Page size change - nur wenn kein page Event gleichzeitig

        // Debounce für size changes
        if (now - this.lastPaginationEventTime < 100) {
          return
        }

        this.lastPaginationEventTime = now

        // Akzeptiere alle validen Dropdown-Werte
        if ([10, 25, 50, 100].includes(event.length)) {
          this.pagination.perPage = event.length
          this.pagination.currentPage = 1

          this.applySearch(this.searchValue, 1)
        }
      }
    },

    resetSearch() {
      this.searchValue = ''
      this.fetchStatements({
        page: {
          number: 1,
          size: 100
        },
        search: {
          value: ''
        },
        include: ['segments'].join()
      })
    },

    onSort(sortBy) {
      // Toggle sort direction if same column, otherwise set to ascending
      if (this.sortBy === sortBy.index) {
        this.sortDirection = this.sortDirection === 'ascending' ? 'descending' : 'ascending'
      } else {
        this.sortBy = sortBy.index
        this.sortDirection = 'ascending'
      }

      // Suche mit neuer Sortierung anwenden
      this.applySearch(this.searchValue, 1)
    },

    extractNumericId(id) {
      // Extrahiert Zahlen aus IDs wie "M1", "M7", "M123" -> 1, 7, 123
      const match = String(id).match(/\d+/)
      return match ? parseInt(match[0]) : 0
    },

    getSortString() {
      if (this.sortBy !== '' && this.sortDirection) {
        const direction = this.sortDirection === 'ascending' ? '' : '-'

        // Mapping von Column Index zu API Field
        const sortFieldMap = {
          0: 'internId',           // ID column
          1: 'status',             // Status column
          2: 'submitName',         // Author column
          3: 'text',               // Text column
          4: null                  // Sections column - API unterstützt kein segments sort
        }

        const field = sortFieldMap[this.sortBy]
        return field ? `${direction}${field}` : '-submitDate,id'
      }
      // Default sort wenn keine Sortierung aktiv
      return '-submitDate,id'
    },

    onTabSwitch(selectedButton) {
      if (selectedButton.includes('statements')) {
        this.activeTab = 'statements'
      } else if (selectedButton.includes('sections')) {
        this.activeTab = 'sections'
      }
    },

    toggleRowExpansion(rowId) {
      const index = this.expandedRows.indexOf(rowId)
      if (index > -1) {
        // Row ist expandiert - kollabieren
        this.expandedRows.splice(index, 1)
      } else {
        // Row ist kollabiert - expandieren
        this.expandedRows.push(rowId)
      }
    },

    toggleFilter() {
      this.filterActive = !this.filterActive
      // Filter logic hier - z.B. nur "Neu" Status
      this.applySearch(this.searchValue, 1)
    },

    toggleColumnSelector() {
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

    positionDropdown() {
      if (this.$refs.columnTrigger && this.$refs.columnDropdown) {
        const triggerRect = this.$refs.columnTrigger.getBoundingClientRect()
        const dropdown = this.$refs.columnDropdown.querySelector('.cv-column-selector-dropdown')

        if (dropdown) {
          dropdown.style.top = `${triggerRect.bottom + 4}px`
          dropdown.style.right = `${window.innerWidth - triggerRect.right}px`
        }
      }
    },

    handleOutsideClick(event) {
      if (this.$refs.columnTrigger && !this.$refs.columnTrigger.contains(event.target) &&
          this.$refs.columnDropdown && !this.$refs.columnDropdown.contains(event.target)) {
        this.isColumnSelectorOpen = false
        document.removeEventListener('click', this.handleOutsideClick)
      }
    },

    toggleColumn(columnKey) {
      if (this.visibleColumns.includes(columnKey)) {
        this.visibleColumns = this.visibleColumns.filter(key => key !== columnKey)
      } else {
        this.visibleColumns.push(columnKey)
      }
      this.saveColumnSelection()
    },

    saveColumnSelection() {
      if (this.useLocalStorage) {
        localStorage.setItem(this.localStorageKey, JSON.stringify(this.visibleColumns))
      }
    },

    loadColumnSelection() {
      if (this.useLocalStorage) {
        const stored = localStorage.getItem(this.localStorageKey)
        return stored ? JSON.parse(stored) : ['id', 'status', 'author', 'institution', 'sections', 'confidence', 'expand']
      }
      return ['id', 'status', 'author', 'institution', 'sections', 'confidence', 'expand']
    },


    setupCheckboxListeners() {
      console.log('setupCheckboxListeners called')
      // Wait for DOM to be fully rendered with statements
      this.$nextTick(() => {
        console.log('DOM is ready, looking for checkboxes')
        // Header checkbox für "Select All" - Debug multiple selectors
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
            console.log('Header checkbox clicked, checked:', event.target.checked)
            console.log('Current statements count:', this.statements.length)

            if (event.target.checked) {
              // Alle Rows auswählen
              this.selectedRows = this.statements.map(s => String(s.id))
              console.log('Selected all rows:', this.selectedRows)
            } else {
              // Alle Rows abwählen
              this.selectedRows = []
              console.log('Deselected all rows')
            }
            this.updateBatchActionsVisibility()
            this.updateRowCheckboxes()
          }

          headerCheckbox.addEventListener('change', this.headerCheckboxHandler)
        } else {
          console.log('Header checkbox not found with any selector')
          // Debug: Show all checkboxes found
          const allCheckboxes = document.querySelectorAll('#cv-statement-table input[type="checkbox"]')
          console.log('All checkboxes found:', allCheckboxes.length)
          allCheckboxes.forEach((cb, index) => {
            console.log(`Checkbox ${index}:`, cb.closest('tr')?.getAttribute('class') || 'no-tr', cb.closest('th')?.getAttribute('class') || 'no-th')
          })
        }

        // Individual row checkboxes
        const checkboxes = document.querySelectorAll('#cv-statement-table .bx--table-column-checkbox input[type="checkbox"]')

        checkboxes.forEach((checkbox, index) => {
          // Skip header checkbox (already handled above)
          if (checkbox.closest('.bx--table-head')) return

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

    updateBatchActionsVisibility() {
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

    updateHeaderCheckboxState() {
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

    updateRowCheckboxes() {
      const checkboxes = document.querySelectorAll('#cv-statement-table .bx--data-table tbody .bx--table-column-checkbox input')
      checkboxes.forEach((checkbox) => {
        const rowValue = checkbox.closest('tr')?.getAttribute('data-value')
        if (rowValue) {
          checkbox.checked = this.selectedRows.includes(rowValue)
        }
      })
    },

    replacePaginationText() {
      // Replace "Page" with "Seite" in pagination
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

  watch: {
    statements: {
      handler(newStatements) {
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

  mounted() {
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

  updated() {
    this.$nextTick(() => {
      this.replacePaginationText()
    })
  },

  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>


