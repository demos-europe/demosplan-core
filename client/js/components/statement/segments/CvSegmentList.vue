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
            size="default"
            @click="openEditModal">
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
                  :kind="getStatusType(segment.statementStatus)"
                  :class="getStatusClass(segment.statementStatus)" />
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
                  :kind="getConfidenceType(segment.confidence)"
                  :class="getConfidenceClass(segment.confidence)" />
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

    <!-- Edit Modal -->
    <cv-multiselect-modal
      v-model:visible="isEditModalVisible"
      :initial-selected-assignee="currentAssignee"
      :initial-selected-place="currentPlace"
      @save="handleModalSave"
      @cancel="handleModalCancel"
    />
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
import { mapActions, mapMutations, mapState } from 'vuex'
import ChevronDown16 from '@carbon/icons-vue/lib/chevron--down/16'
import CvMultiselectModal from '@DpJs/components/statement/CvMultiselectModal'

export default {
  name: 'CvSegmentList',

  components: {
    ChevronDown16,
    CvButton,
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell,
    CvMultiselectModal,
    CvPagination,
    CvSearch,
    CvTag
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
      default: 'cvSegmentList'
    },
    useLocalStorage: {
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      searchValue: '',
      isColumnSelectorOpen: false,
      selectedRows: [],
      sortBy: '',
      sortDirection: '',
      headerCheckboxHandler: null,
      expandedRows: [], // Track which rows are expanded
      lastPaginationEventTime: 0, // Debouncing for pagination events
      isEditModalVisible: false,
      currentAssignee: {},
      currentPlace: { id: '', type: 'Place' },
      visibleColumns: [],
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

    ...mapState('AssignableUser', {
      assignableUserItems: 'items'
    }),

    segments () {
      const segmentValues = Object.values(this.segmentsObject)

      if (segmentValues.length === 0) {
        return []
      }

      return segmentValues.map((segment, index) => {
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

        return {
          id: segment.id,
          externId: segment.attributes?.externId || '-',
          statementStatus: this.mapApiStatusToDisplay(parentStatement?.attributes?.status || 'new'),
          submitter: parentStatement?.attributes?.authorName || parentStatement?.attributes?.submitName || '-',
          organisation: parentStatement?.attributes?.initialOrganisationName || '',
          processingStep: place?.attributes?.name || '-',
          keywords: tags.map(tag => ({
            id: tag.id,
            title: tag.attributes?.title || ''
          })),
          confidence: Math.floor(Math.random() * 100) + 1, // Dummy data
          topic: 'Umweltschutz', // Dummy data
          textModule,
          text: segment.attributes?.text || '' // For expanded row
        }
      })
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
      fetchSegments: 'list',
      saveSegmentAction: 'save'
    }),

    ...mapActions('Place', {
      fetchPlaces: 'list'
    }),

    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list'
    }),

    ...mapMutations('StatementSegment', {
      setSegment: 'setItem'
    }),

    mapApiStatusToDisplay (apiStatus) {
      const statusMap = {
        new: 'Neu',
        processing: 'In Bearbeitung',
        completed: 'Abgeschlossen'
      }
      return statusMap[apiStatus] || apiStatus
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

    getConfidenceType (confidence) {
      if (confidence <= 33) return 'red'
      if (confidence <= 66) return 'warm-gray' // Medium = Warm-Gray + Custom Orange
      return 'green'
    },

    getConfidenceClass (confidence) {
      if (confidence <= 33) return ''
      if (confidence <= 66) return 'cv-confidence-medium' // CSS Override to orange
      return ''
    },

    getKeywordType () {
      // Use warm-gray for keywords like in CvStatementList confidence tags
      return 'warm-gray'
    },

    getKeywordClass () {
      // Add custom class for additional styling if needed
      return 'cv-keyword-tag'
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
        return stored ? JSON.parse(stored) : ['externId', 'statementStatus', 'submitter', 'processingStep', 'keywords', 'confidence', 'topic', 'textModule', 'expand']
      }
      return ['externId', 'statementStatus', 'submitter', 'processingStep', 'keywords', 'confidence', 'topic', 'textModule', 'expand']
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
      // TODO: Implement sorting
    },

    setupCheckboxListeners () {
      // Wait for DOM to be fully rendered with segments
      this.$nextTick(() => {
        // Header checkbox for "Select All"
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

    openEditModal() {
      if (this.selectedRows.length === 0) {
        // Show notification that no segments are selected
        if (window.dplan && window.dplan.notify) {
          window.dplan.notify.notify('error', 'Bitte wählen Sie mindestens ein Segment zum Bearbeiten aus.')
        }
        return
      }

      // Reset modal state
      this.currentAssignee = {}
      this.currentPlace = { id: '', type: 'Place' }
      this.isEditModalVisible = true
    },

    handleModalSave(formData) {
      // Close modal first
      this.isEditModalVisible = false

      // Update each selected segment - based on StatementSegment.vue logic
      const savePromises = this.selectedRows.map(segmentId => {
        return this.updateSingleSegment(segmentId, formData)
      })

      // Execute all saves
      Promise.all(savePromises)
        .then(() => {
          // Success notification
          if (window.dplan && window.dplan.notify) {
            window.dplan.notify.notify('confirm', `${this.selectedRows.length} Segment(e) wurden erfolgreich aktualisiert.`)
          }

          // Clear selection
          this.selectedRows = []
          this.updateBatchActionsVisibility()
          this.updateHeaderCheckboxState()

          // Refresh data to show changes
          this.fetchSegmentData(this.pagination.currentPage)
        })
        .catch((error) => {
          console.error('Error saving segments:', error)
          if (window.dplan && window.dplan.notify) {
            window.dplan.notify.notify('error', 'Fehler beim Speichern der Segmente.')
          }
        })
    },

    updateSingleSegment(segmentId, formData) {
      const segment = this.segmentsObject[segmentId]
      if (!segment) {
        console.error('Segment not found:', segmentId)
        return Promise.reject(new Error(`Segment ${segmentId} not found`))
      }

      // Log old assignee
      const oldAssigneeId = segment.relationships?.assignee?.data?.id
      const oldAssigneeUser = oldAssigneeId ? this.assignableUserItems[oldAssigneeId] : null
      const oldAssigneeName = oldAssigneeUser ?
        oldAssigneeUser.attributes.firstname + ' ' + oldAssigneeUser.attributes.lastname :
        'Nicht zugewiesen'

      // Log new assignee
      const newAssigneeName = formData.selectedAssignee?.name || 'Nicht zugewiesen'

      // Log old processing step
      const oldPlaceId = segment.relationships?.place?.data?.id
      const oldPlace = oldPlaceId ? this.placesObject[oldPlaceId] : null
      const oldPlaceName = oldPlace?.attributes?.name || 'Nicht zugewiesen'

      // Log new processing step
      const newPlaceName = formData.selectedPlace?.name || 'Nicht zugewiesen'

      console.log(`Segment ${segmentId}: Bearbeiter Änderung von "${oldAssigneeName}" zu "${newAssigneeName}"`)
      console.log(`Segment ${segmentId}: Bearbeitungsschritt Änderung von "${oldPlaceName}" zu "${newPlaceName}"`)

      // Build relationships like in original StatementSegment.vue
      let assignee = { assignee: { data: null } }

      if (formData.selectedAssignee && formData.selectedAssignee.id !== 'noAssigneeId') {
        assignee = {
          assignee: {
            data: {
              id: formData.selectedAssignee.id,
              type: 'AssignableUser'
            }
          }
        }
      }

      const place = {
        place: {
          data: {
            id: formData.selectedPlace.id,
            type: 'Place'
          }
        }
      }

      // Update segment in store first (optimistic update)
      const updatedSegment = {
        id: segment.id,
        type: 'StatementSegment',
        attributes: {
          ...segment.attributes
        },
        relationships: {
          ...segment.relationships,
          ...assignee,
          ...place
        }
      }

      this.setSegment({
        ...updatedSegment,
        id: segment.id
      })

      // Use Vuex action like in original StatementSegment.vue to handle CSRF token
      return this.saveSegmentAction({ id: segment.id })
        .catch((err) => {
          console.error('Error updating segment:', err)
          // Restore original segment on error
          this.setSegment({ ...segment, id: segment.id })
          throw err
        })
    },

    handleModalCancel() {
      this.isEditModalVisible = false
    },

    fetchSegmentData (page = 1) {
      const payload = {
        include: [
          'place',
          'assignee',
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
        sort: 'parentStatement.submitDate,parentStatement.externId,orderInProcedure',
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
            'assignee',
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
    this.fetchAssignableUsers({ // Load assignable users for logging
      include: 'department',
      sort: 'lastname'
    })
    this.fetchSegmentData() // Load segments with all related data

    // Setup checkbox listeners after initial data load
    this.$nextTick(() => {
      this.setupCheckboxListeners()
    })
  },

  beforeUnmount () {
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>
