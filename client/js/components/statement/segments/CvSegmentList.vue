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
        sortable
      >
        <template v-slot:actions>
          <cv-search
            light
            placeholder="Suchen"
            :value="searchValue"
            @input="updateSearch"
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

        <!-- Custom Data Slot -->
        <template v-slot:data>
          <template v-for="(segment, index) in segments" :key="index">
            <cv-data-table-row :value="String(segment.id)">

              <cv-data-table-cell v-if="visibleColumns.includes('externId')">
                {{ segment.externId }}
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('statementStatus')">
                <cv-tag
                  :label="segment.statementStatus"
                  :kind="getStatusType(segment.statementStatus)"
                  :class="getStatusClass(segment.statementStatus)" />
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('submitter')">
                <div class="cv-submitter-cell">
                  <div class="cv-submitter-name">
                    {{ segment.submitter }}
                  </div>
                  <div class="cv-submitter-org" v-if="segment.organisation">
                    {{ segment.organisation }}
                  </div>
                </div>
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('processingStep')">
                {{ segment.processingStep }}
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('keywords')">
                <div class="cv-keywords-cell">
                  <cv-tag
                    v-for="keyword in segment.keywords"
                    :key="keyword.id"
                    :label="keyword.title"
                    :kind="getKeywordType(keyword.title)"
                    :class="getKeywordClass(keyword.title)" />
                </div>
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('confidence')">
                <cv-tag
                  :label="`${segment.confidence}%`"
                  :kind="getConfidenceType(segment.confidence)"
                  :class="getConfidenceClass(segment.confidence)" />
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('topic')">
                {{ segment.topic }}
              </cv-data-table-cell>

              <cv-data-table-cell v-if="visibleColumns.includes('textModule')">
                {{ segment.textModule }}
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
    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      searchValue: '',
      isColumnSelectorOpen: false,
      visibleColumns: ['externId', 'statementStatus', 'submitter', 'processingStep', 'keywords', 'confidence', 'topic', 'textModule'],
      columns: [
        { key: 'externId', label: 'ID' },
        { key: 'statementStatus', label: 'Stn.-Status' },
        { key: 'submitter', label: 'Einreicher*in' },
        { key: 'processingStep', label: 'Bearbeitungsschritt' },
        { key: 'keywords', label: 'Schlagworte' },
        { key: 'confidence', label: 'Konfidenz' },
        { key: 'topic', label: 'Thema' },
        { key: 'textModule', label: 'Textbaustein' }
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

    segments () {
      const segmentValues = Object.values(this.segmentsObject)
      
      if (segmentValues.length === 0) {
        return []
      }
      
      return segmentValues.map(segment => {
        // Get related statement data
        const parentStatement = this.statementsObject[segment.relationships?.parentStatement?.data?.id]
        
        // Get place name for processing step
        const placeId = segment.relationships?.place?.data?.id
        const place = placeId ? this.placesObject[placeId] : null
        
        // Get tags for keywords
        const tagIds = segment.relationships?.tags?.data?.map(tag => tag.id) || []
        const tags = tagIds.map(id => this.tagsObject[id]).filter(tag => tag)
        
        // Get textModule from first tag's boilerplate
        const firstTagWithBoilerplate = tags.find(tag => tag?.relationships?.boilerplate)
        const textModule = firstTagWithBoilerplate?.relationships?.boilerplate?.attributes?.title || '-'
        
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
          textModule
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

  methods: {
    ...mapActions('StatementSegment', {
      fetchSegments: 'list'
    }),

    ...mapActions('Place', {
      fetchPlaces: 'list'
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

    getKeywordType (keyword) {
      // Use warm-gray for keywords like in CvStatementList confidence tags
      return 'warm-gray'
    },

    getKeywordClass (keyword) {
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
      // TODO: Save to localStorage if needed
    },

    updateSearch (term) {
      this.searchValue = term
      // TODO: Implement search functionality
    },

    onPaginationChange (event) {
      // TODO: Implement pagination
      console.log('Pagination change:', event)
    },

    fetchSegmentData () {
      const payload = {
        include: [
          'place',
          'tags',
          'tags.boilerplate',  // Include boilerplate for textModule
          'parentStatement'
        ].join(),
        page: {
          number: this.pagination.currentPage,
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

      this.fetchSegments(payload)
        .then(response => {
          if (response?.meta?.pagination) {
            this.pagination = {
              ...this.pagination,
              currentPage: response.meta.pagination.current_page,
              total: response.meta.pagination.total,
              totalPages: response.meta.pagination.total_pages
            }
          }
        })
        .catch(error => {
          console.error('Error fetching segments:', error)
        })
    }
  },

  mounted () {
    // Load initial data
    this.fetchPlaces() // Load places for processingStep
    this.fetchSegmentData() // Load segments with all related data
  },

  beforeUnmount () {
    document.removeEventListener('click', this.handleOutsideClick)
  }
}
</script>
