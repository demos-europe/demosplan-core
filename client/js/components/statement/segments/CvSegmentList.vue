<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-segment-list">
    <div class="cv-container">
      <!-- Segments Table -->
      <cv-data-table
        :columns="filteredColumns"
        batch-cancel-label="Abbrechen"
        :data="segments"
        id="cv-segment-table"
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

        <!-- Custom Data Slot -->
        <template v-slot:data>
          <template v-for="(segment, index) in segments" :key="index">
            <cv-data-table-row :value="String(segment.id)">

              <cv-data-table-cell v-show="visibleColumns.includes('externId')">
                {{ segment.externId }}
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('statementStatus')">
                <cv-tag
                  :label="segment.statementStatus"
                  :kind="statusTypes[segment.statementStatus] || 'gray'"
                  :class="statusClasses[segment.statementStatus] || ''" />
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('submitter')">
                <div class="cv-submitter-cell">
                  <div class="cv-submitter-name">
                    {{ segment.submitter }}
                  </div>
                  <div class="cv-submitter-org" v-if="segment.organisation">
                    {{ segment.organisation }}
                  </div>
                </div>
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('processingStep')">
                {{ segment.processingStep }}
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('keywords')">
                <div class="cv-keywords-cell">
                  <cv-tag
                    v-for="keyword in segment.keywords"
                    :key="keyword.id"
                    :label="keyword.title"
                    :kind="getKeywordType(keyword.title)"
                    :class="getKeywordClass(keyword.title)" />
                </div>
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('confidence')">
                <cv-tag
                  :label="`${segment.confidence}%`"
                  :kind="confidenceTypes[segment.confidenceType]"
                  :class="confidenceClasses[segment.confidenceType] || ''" />
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('topic')">
                {{ segment.topic }}
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('textModule')">
                {{ segment.textModule }}
              </cv-data-table-cell>

              <cv-data-table-cell v-show="visibleColumns.includes('expand')">
                <cv-button
                  kind="ghost"
                  size="sm"
                  @click="toggleRowExpansion(segment.id)"
                  :aria-label="`Element ausklappen`">
                  <ChevronDown16 :style="{ transform: expandedRows.includes(segment.id) ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s' }" />
                </cv-button>
              </cv-data-table-cell>

            </cv-data-table-row>

            <!-- Expanded Content Row -->
            <cv-data-table-row
              v-if="expandedRows.includes(segment.id)"
              class="cv-expanded-row">
              <cv-data-table-cell :colspan="visibleColumns.length">
                <div v-html="segment.text" />
              </cv-data-table-cell>
            </cv-data-table-row>
          </template>
        </template>
      </cv-data-table>

      <!-- Pagination -->
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

    <!-- Column Selector Dropdown -->
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
import Export16 from '@carbon/icons-vue/es/export/16'
import ChevronDown16 from '@carbon/icons-vue/lib/chevron--down/16'

export default {
  name: 'CvSegmentList',

  components: {
    ChevronDown16,
    CvButton,
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell,
    CvPagination,
    CvSearch,
    CvTag,
    Export16
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    localStorageKey: {
      type: String,
      default: 'segmentList'
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
        new: 'Neu',
        processing: 'In Bearbeitung',
        completed: 'Abgeschlossen'
      },
      checkboxListeners: [], // Track individual checkbox listeners for cleanup
      clientSortDirection: null,
      clientSortField: null, // For client-side sorting
      // Confidence mapping objects
      confidenceClasses: {
        medium: 'cv-confidence-medium'
      },
      confidenceTypes: {
        low: 'red', // <= 33%
        medium: 'warm-gray', // 34-66%
        high: 'green' // >= 67%
      },
      expandedRows: [], // Track which rows are expanded
      headerCheckboxHandler: null,
      isColumnSelectorOpen: false,
      lastPaginationEventTime: 0, // Debouncing for pagination events
      searchValue: '',
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
      visibleColumns: [
        'externId',
        'statementStatus',
        'submitter',
        'processingStep',
        'keywords',
        'confidence',
        'topic',
        'textModule',
        'expand'
      ],
      columns: [
        { key: 'externId', label: 'ID' },
        { key: 'statementStatus', label: 'Stn.-Status' },
        { key: 'submitter', label: 'Einreicher*in' },
        { key: 'processingStep', label: 'Bearbeitungsschritt' },
        { key: 'keywords', label: 'Schlagworte' },
        { key: 'confidence', label: 'Konfidenz' },
        { key: 'topic', label: 'Thema' },
        { key: 'textModule', label: 'Textbaustein' },
        { key: 'expand', label: '', headingStyle: { 'pointer-events': 'none' } }
      ],
      selectableColumns: [
        { key: 'externId', label: 'ID' },
        { key: 'statementStatus', label: 'Stn.-Status' },
        { key: 'submitter', label: 'Einreicher*in' },
        { key: 'processingStep', label: 'Bearbeitungsschritt' },
        { key: 'keywords', label: 'Schlagworte' },
        { key: 'confidence', label: 'Konfidenz' },
        { key: 'topic', label: 'Thema' },
        { key: 'textModule', label: 'Textbaustein' }
      ],
      pagination: {
        currentPage: 1,
        perPage: 10,
        total: 0,
        totalPages: 0
      }
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segmentsObject: 'items'
    }),

    ...mapState('Statement', {
      statementsObject: 'items'
    }),

    ...mapState('Tag', {
      tagsObject: 'items'
    }),

    ...mapState('Place', {
      placesObject: 'items'
    }),

    ...mapState('Boilerplate', {
      boilerplatesObject: 'items'
    }),

    segments () {
      const segmentValues = Object.values(this.segmentsObject)

      if (segmentValues.length === 0) {
        return []
      }

      const processedData = segmentValues.map((segment, index) => {
        // Get related statement data
        const parentStatement = this.statementsObject[segment.relationships?.parentStatement?.data?.id]

        // Get place name for processing step
        const placeId = segment.relationships?.place?.data?.id
        const place = placeId ? this.placesObject[placeId] : null

        // Get tags for keywords
        const tagIds = segment.relationships?.tags?.data?.map(tag => tag.id) || []
        const tags = tagIds.map(id => this.tagsObject[id]).filter(tag => tag)

        // Get textModule from all tags with boilerplates
        const tagsWithBoilerplates = tags.filter(tag => {
          const boilerplateId = tag?.relationships?.boilerplate?.data?.id
          return boilerplateId && this.boilerplatesObject[boilerplateId]
        })

        // Collect all boilerplate names
        const textModules = tagsWithBoilerplates.map(tag =>
          this.boilerplatesObject[tag.relationships.boilerplate.data.id]?.attributes?.title
        ).filter(title => title)

        const textModule = textModules.length > 0 ? textModules.join(', ') : '-'

        // Dummy confidence value - replace with API data when available
        const confidence = Math.floor(Math.random() * 100) + 1
        let confidenceType
        if (confidence <= 33) {
          confidenceType = 'low'
        } else if (confidence <= 66) {
          confidenceType = 'medium'
        } else {
          confidenceType = 'high'
        }

        return {
          id: segment.id,
          externId: segment.attributes?.externId || '-',
          statementStatus: this.apiStatusLabels[parentStatement?.attributes?.status || 'new'] || parentStatement?.attributes?.status,
          submitter: parentStatement?.attributes?.authorName || parentStatement?.attributes?.submitName || '-',
          organisation: parentStatement?.attributes?.initialOrganisationName || '',
          processingStep: place?.attributes?.name || '-',
          keywords: tags.map(tag => ({
            id: tag.id,
            title: tag.attributes?.title || ''
          })),
          confidence,
          confidenceType,
          topic: 'Umweltschutz', // Dummy data
          textModule,
          text: segment.attributes?.text || '' // For expanded row
        }
      })

      // Client-side sorting
      if (this.clientSortField === 'externId') {
        // ID sorting
        processedData.sort((a, b) => {
          const numA = this.extractNumericId(a.externId)
          const numB = this.extractNumericId(b.externId)

          return this.clientSortDirection === 'ascending' ? numA - numB : numB - numA
        })
      } else if (this.clientSortField === 'statementStatus') {
        /*
         * Status sorting: Carbon table doesn't support semantic status sorting,
         * so we handle it client-side with custom order
         */
        const statusOrder = { Neu: 1, 'In Bearbeitung': 2, Abgeschlossen: 3 }
        processedData.sort((a, b) => {
          const orderA = statusOrder[a.statementStatus] || 999
          const orderB = statusOrder[b.statementStatus] || 999

          return this.clientSortDirection === 'ascending' ? orderA - orderB : orderB - orderA
        })
      } else if (this.clientSortField === 'submitter') {
        // Submitter sorting
        processedData.sort((a, b) => {
          const subA = (a.submitter || '').toLowerCase()
          const subB = (b.submitter || '').toLowerCase()

          if (this.clientSortDirection === 'ascending') {
            return subA.localeCompare(subB)
          } else {
            return subB.localeCompare(subA)
          }
        })
      } else if (this.clientSortField === 'processingStep') {
        // ProcessingStep sorting
        processedData.sort((a, b) => {
          const stepA = (a.processingStep || '').toLowerCase()
          const stepB = (b.processingStep || '').toLowerCase()

          if (this.clientSortDirection === 'ascending') {
            return stepA.localeCompare(stepB)
          } else {
            return stepB.localeCompare(stepA)
          }
        })
      } else if (this.clientSortField === 'confidence') {
        // Confidence sorting
        processedData.sort((a, b) => {
          const confA = a.confidence || 0
          const confB = b.confidence || 0

          return this.clientSortDirection === 'ascending' ? confA - confB : confB - confA
        })
      } else if (this.clientSortField === 'topic') {
        // Topic sorting
        processedData.sort((a, b) => {
          const topicA = (a.topic || '').toLowerCase()
          const topicB = (b.topic || '').toLowerCase()

          if (this.clientSortDirection === 'ascending') {
            return topicA.localeCompare(topicB)
          } else {
            return topicB.localeCompare(topicA)
          }
        })
      } else if (this.clientSortField === 'textModule') {
        // TextModule sorting
        processedData.sort((a, b) => {
          const moduleA = (a.textModule || '').toLowerCase()
          const moduleB = (b.textModule || '').toLowerCase()

          if (this.clientSortDirection === 'ascending') {
            return moduleA.localeCompare(moduleB)
          } else {
            return moduleB.localeCompare(moduleA)
          }
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
    segments: {
      handler (newSegments) {
        if (newSegments.length > 0) {
          // Re-setup checkbox listeners when segments are loaded
          this.$nextTick(() => {
            this.setupCheckboxListeners()
          })
        }
      },
      deep: true
    }
  },

  methods: {
    ...mapActions('StatementSegment', {
      fetchSegments: 'list'
    }),

    ...mapActions('Place', {
      fetchPlaces: 'list'
    }),

    extractNumericId (id) {
      const match = String(id).match(/\d+/)

      return match ? parseInt(match[0]) : 0
    },

    getKeywordType (keyword) {
      // Use warm-gray for keywords like in CvStatementList confidence tags
      return 'warm-gray'
    },

    getKeywordClass (keyword) {
      // Add custom class for additional styling if needed
      return 'cv-keyword-tag'
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
       * - API supports limited sorting (only specific fields)
       * - Custom sorts (ID extraction, status priority, text comparison)
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
          0: null, // externId - extract numeric part client-side
          1: null, // statementStatus - custom priority order client-side
          2: null, // submitter - name comparison client-side
          3: null, // processingStep - name comparison client-side
          4: null, // keywords - not sortable (display only)
          5: null, // confidence - dummy data, client-side for now
          6: null, // topic - text comparison client-side
          7: null, // textModule - text comparison client-side
          8: null  // expand - not sortable (display only)
        }

        // Handle client-side sorting columns
        if (this.sortBy === 0) {
          this.sortSegmentsClientSide('externId')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure' // Fallback to keep pagination stable
        }
        if (this.sortBy === 1) {
          this.sortSegmentsClientSide('statementStatus')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }
        if (this.sortBy === 2) {
          this.sortSegmentsClientSide('submitter')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }
        if (this.sortBy === 3) {
          this.sortSegmentsClientSide('processingStep')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }
        if (this.sortBy === 5) {
          this.sortSegmentsClientSide('confidence')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }
        if (this.sortBy === 6) {
          this.sortSegmentsClientSide('topic')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }
        if (this.sortBy === 7) {
          this.sortSegmentsClientSide('textModule')

          return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
        }

        // Handle API-supported sorting (currently none specific, but structure for future)
        const field = sortFieldMap[this.sortBy]

        return field ? `${direction}${field}` : 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
      }
      // Default sort: by statement date, then by ID for consistency

      return 'parentStatement.submitDate,parentStatement.externId,orderInProcedure'
    },

    sortSegmentsClientSide (sortField) {
      // Set client-side sorting parameters
      this.clientSortField = sortField
      this.clientSortDirection = this.sortDirection
      // Computed property segments() will be automatically recalculated
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

        return stored ? JSON.parse(stored) : [
          'externId',
          'statementStatus',
          'submitter',
          'processingStep',
          'keywords',
          'confidence',
          'topic',
          'textModule',
          'expand'
        ]
      }

      return [
        'externId',
        'statementStatus',
        'submitter',
        'processingStep',
        'keywords',
        'confidence',
        'topic',
        'textModule',
        'expand'
      ]
    },

    applySearch (term, page = 1) {
      // Check if the search term has changed
      const searchChanged = term !== this.searchValue
      this.searchValue = term

      // Always go to page 1 when search term changes, otherwise use specified page
      const targetPage = searchChanged ? 1 : page

      this.fetchSegmentData(targetPage)
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

    setupCheckboxListeners () {
      /*
       * Manual checkbox handling: Carbon's native selection has bugs with dynamic data
       * and row expansion. We manually bind to checkbox events for reliable state management.
       *
       * Clean up existing listeners first
       */
      this.removeCheckboxListeners()

      this.$nextTick(() => {
        console.log('DOM is ready, looking for checkboxes')
        // Header checkbox for "Select All" - Debug multiple selectors
        const headerSelectors = [
          '#cv-segment-table .bx--table-head .bx--table-column-checkbox input',
          '#cv-segment-table thead .bx--table-column-checkbox input',
          '#cv-segment-table .bx--data-table-header .bx--table-column-checkbox input',
          '#cv-segment-table th .bx--table-column-checkbox input'
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
              this.selectedRows = this.segments.map(s => String(s.id))
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
        const checkboxes = document.querySelectorAll('#cv-segment-table tbody .bx--table-column-checkbox input[type="checkbox"]')

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

    updateBatchActionsVisibility () {
      const batchActions = document.querySelector('#cv-segment-table .bx--batch-actions')
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
      const headerCheckbox = document.querySelector('#cv-segment-table .bx--table-head .bx--table-column-checkbox input')
      if (headerCheckbox) {
        const totalRows = this.segments.length
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
      const checkboxes = document.querySelectorAll('#cv-segment-table .bx--data-table tbody .bx--table-column-checkbox input')
      checkboxes.forEach((checkbox) => {
        const rowValue = checkbox.closest('tr')?.getAttribute('data-value')
        if (rowValue) {
          checkbox.checked = this.selectedRows.includes(rowValue)
        }
      })
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

    removeCheckboxListeners () {
      // Remove all individual checkbox listeners
      this.checkboxListeners.forEach(({ element, handler }) => {
        element.removeEventListener('change', handler)
      })
      this.checkboxListeners = []

      // Remove header checkbox listener
      if (this.headerCheckboxHandler) {
        const headerCheckbox = document.querySelector('#cv-segment-table .bx--table-head .bx--table-column-checkbox input')
        if (headerCheckbox) {
          headerCheckbox.removeEventListener('change', this.headerCheckboxHandler)
        }
        this.headerCheckboxHandler = null
      }
    },

    fetchSegmentData (page = 1) {
      const payload = {
        include: [
          'place',
          'tags',
          'tags.boilerplate',
          'parentStatement'
        ].join(),
        page: {
          number: page,
          size: this.pagination.perPage
        },
        filter: {
          sameProcedure: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.procedureId
            }
          }
        },
        sort: this.getSortString(),
        fields: {
          Place: ['name'].join(),
          Statement: [
            'status',
            'authorName',
            'submitName',
            'initialOrganisationName'
          ].join(),
          StatementSegment: [
            'externId',
            'text',
            'parentStatement',
            'place',
            'tags'
          ].join(),
          Tag: [
            'title',
            'boilerplate'  // Include boilerplate relationship
          ].join(),
          Boilerplate: [
            'title'  // Include boilerplate title for textModule
          ].join()
        }
      }

      // Add search parameter if search term exists
      if (this.searchValue && this.searchValue.trim() !== '') {
        payload.search = {
          value: this.searchValue
          // No fieldsToSearch - let backend search all fields like SegmentsList does
        }
      }

      this.fetchSegments(payload)
        .then(response => {
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
        .catch(error => {
          console.error('Error fetching segments:', error)
        })
    }
  },

  mounted () {
    // Initialize visibleColumns from localStorage
    this.visibleColumns = this.loadColumnSelection()

    // Load initial data
    this.fetchPlaces() // Load places for processingStep
    this.fetchSegmentData() // Load segments with all related data

    // Setup checkbox listeners after initial data load
    this.$nextTick(() => {
      this.setupCheckboxListeners()
    })
  },

  beforeUnmount () {
    this.removeCheckboxListeners()
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>
