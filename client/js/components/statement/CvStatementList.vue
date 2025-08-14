<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-list">
    <div class="cv-container">
    <!-- Single Header Row with all 3 elements -->
    <div class="cv-header-row">
      <h4 class="main-title">Stellungnahmen zum aktuellen Verfahren</h4>
      <span class="cv-switch-label">Darstellung</span>

      <!-- Content Switcher -->
    <cv-content-switcher @selected="onTabSwitch">
      <cv-content-switcher-button
        content-selector=".statements-content"
        :selected="activeTab === 'statements'">
        Gesamte Stellungnahmen
      </cv-content-switcher-button>
      <cv-content-switcher-button
        content-selector=".sections-content"
        :selected="activeTab === 'sections'">
        Aufteilung in Abschnitte
      </cv-content-switcher-button>
    </cv-content-switcher>
    </div>

    <!-- Tab Content -->
    <cv-data-table
      id="cv-statement-table"
      v-if="activeTab === 'statements'"
      v-model:rows-selected="selectedRows"
      :columns="columns"
      :data="statements"
      sortable
      @sort="onSort">

      <template #actions>
        <cv-search
          light
          placeholder="Suchen"
          :value="searchValue"
          @input="applySearch"
        />
        <cv-button kind="tertiary" class="cv-export-btn">
          Exportieren <Export16 />
        </cv-button>
        <cv-button kind="primary" class="cv-add-btn" @click="createNewStatement">
          Neue Stellungnahme hinzufügen <DocumentAdd16 />
        </cv-button>
      </template>

      <!-- Checkboxen: -->
      <template #batch-actions>
      </template>



      <!-- Custom Data Slot mit Status Tags -->
      <template #data>
        <template v-for="(statement, index) in statements" :key="index">
          <cv-data-table-row :value="statement.id">
            <cv-data-table-cell>{{ statement.id }}</cv-data-table-cell>
            <cv-data-table-cell>
              {{ formatDate(statement.statusDate) }}
              <br>
              <cv-tag
                :label="statement.status"
                :kind="getStatusType(statement.status)"
                :class="getStatusClass(statement.status)" />
            </cv-data-table-cell>
            <cv-data-table-cell>{{ statement.author }}</cv-data-table-cell>
            <cv-data-table-cell>
              <div class="text-content">
                {{ statement.text }}
              </div></cv-data-table-cell>
            <cv-data-table-cell>{{ statement.sections }}</cv-data-table-cell>
            <cv-data-table-cell>
              <cv-button
                kind="ghost"
                size="sm"
                @click="toggleRowExpansion(statement.id)"
                :aria-label="`Expand row ${statement.id}`">
                <ChevronDown16 :style="{ transform: expandedRows.includes(statement.id) ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s' }" />
              </cv-button>
            </cv-data-table-cell>

          </cv-data-table-row>

          <!-- Expanded Content Row (manuell eingefügt) -->
          <cv-data-table-row v-if="expandedRows.includes(statement.id)" class="expanded-row" :selectable="false">
            <cv-data-table-cell :colspan="6">
              <div class="expanded-statement-content">
                <!-- Statement Metadaten -->
                <div class="statement-metadata">
                  <h4>Statement Details</h4>

                  <!-- Zwei-spaltige Metadaten -->
                  <div class="metadata-layout">
                    <div>
                      <dl style="margin: 0;">
                        <dt><strong>Statement ID:</strong></dt>
                        <dd>{{ statement.id }}</dd>
                        <dt><strong>Status:</strong></dt>
                        <dd>{{ statement.status }}</dd>
                        <dt><strong>Autor:</strong></dt>
                        <dd>{{ statement.author }}</dd>
                      </dl>
                    </div>
                    <div>
                      <dl style="margin: 0;">
                        <dt><strong>Status Datum:</strong></dt>
                        <dd>{{ formatDate(statement.statusDate) }}</dd>
                        <dt><strong>Abschnitte:</strong></dt>
                        <dd>{{ statement.sections }}</dd>
                      </dl>
                    </div>
                  </div>

                  <!-- Vollständiger Text -->
                  <div style="margin-top: 16px;">
                    <h5>Vollständiger Text:</h5>
                    <div class="statement-full-text" style="background: white; padding: 12px; border-radius: 4px; border: 1px solid #e0e0e0;">
                      <div v-html="statement.text"></div>
                    </div>
                  </div>
                </div>
              </div>
            </cv-data-table-cell>
          </cv-data-table-row>
        </template>
      </template>
    </cv-data-table>

    <cv-pagination
      v-if="pagination.totalPages > 1"
      :page="pagination.currentPage"
      :page-sizes="[10, 25, 50, 100]"
      :page-size="pagination.perPage"
      :total-items="pagination.total"
      :number-of-pages="pagination.totalPages"
      @change="onPaginationChange"
      class="cv-pagination"
      :key="`pagination_${pagination.currentPage}_${pagination.totalPages}_${pagination.perPage}`" />
    </div>
  </div>


  <!-- Sections Content -->
    <div v-if="activeTab === 'sections'">
      <p>Aufteilung in Abschnitte Content - Coming Soon</p>
    </div>
</template>

<script>
import {
  CvButton,
  CvBreadcrumb,
  CvBreadcrumbItem,
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
    }
  },

  data() {
    return {
      activeTab: 'statements',
      columns: [
        { key: 'id', label: 'ID', sortable: true },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'text', label: 'Text' },
        { key: 'sections', label: 'Abschnitte' },
        { key: 'expand', label: '' }
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
      expandedRows: [] // Track welche Rows expandiert sind
    }
  },

  computed: {
    ...mapState('Statement', {
      statementsObject: 'items'
    }),

    statements() {
      const rawData = Object.values(this.statementsObject) || []

      // Mapping von API-Daten zu Component-Format
      let mappedData = rawData.map(stmt => {
        // Verschiedene Wege, die Segments zu finden
        const segments = stmt.relationships?.segments?.data ||
                         stmt.relationships?.statementFragments?.data ||
                         stmt.segments?.data ||
                         stmt.segments ||
                         stmt.statementFragments?.data ||
                         stmt.statementFragments ||
                         []

        console.log(`Statement ${stmt.id} segments debug:`, segments)
        console.log(`Statement ${stmt.id} full relationships:`, stmt.relationships)

        return {
          id: stmt.attributes?.externId || stmt.id,
          status: this.mapApiStatusToDisplay(stmt.attributes.status),
          statusDate: stmt.attributes.submitDate,
          author: `${stmt.attributes.authorName}\n${this.formatDate(stmt.attributes.authoredDate)}`,
          text: stmt.attributes?.text || stmt.text,
          sections: segments.length > 0 ? segments.length : '-'
        }
      })

      // Keine client-seitige Sortierung mehr - alles server-seitig wie ListStatements.vue

      return mappedData
    }
  },

  methods: {
    // Vuex Actions importieren
    ...mapActions('Statement', {
      fetchStatements: 'list'
    }),

    applySearch(term, page = 1) {
      console.log('applySearch called with term:', term, 'page:', page, 'size:', this.pagination.perPage)

      // Prüfen ob sich der Suchbegriff geändert hat
      const searchChanged = term !== this.searchValue
      this.searchValue = term

      // Bei geändertem Suchbegriff IMMER zu Seite 1, sonst verwende angegebene Seite
      const targetPage = searchChanged ? 1 : page

      console.log('📡 API call with page:', targetPage, 'size:', this.pagination.perPage)
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
        console.log('📨 API Response pagination:', response?.meta?.pagination)
        if (response?.meta?.pagination) {
          console.log('📨 API returned per_page:', response.meta.pagination.per_page, 'total_pages:', response.meta.pagination.total_pages)
          console.log('🔧 Before update - local perPage:', this.pagination.perPage)

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
          console.log('✅ Final pagination state:', this.pagination)
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
      console.log('Pagination change event:', event)
      console.log('Current pagination state:', this.pagination)

      // Carbon Vue Pagination Format: { start, page, length }
      if (event.length && event.length !== this.pagination.perPage) {
        // Page size changed - zurück zu Seite 1
        console.log('🔄 Page size change: from', this.pagination.perPage, 'to', event.length)
        
        // Sofort perPage aktualisieren
        this.pagination.perPage = event.length
        this.pagination.currentPage = 1
        
        console.log('🔄 Updated local pagination:', this.pagination)
        console.log('🔄 About to call API with size:', this.pagination.perPage)
        
        // Force re-render der Pagination-Komponente durch nextTick
        this.$nextTick(() => {
          this.applySearch(this.searchValue, 1)
        })
      } else if (event.page) {
        // Page changed - begrenzt auf tatsächlich verfügbare Seiten
        const maxPage = this.pagination.totalPages
        const targetPage = Math.max(1, Math.min(event.page, maxPage))

        console.log('Requested page:', event.page, 'Max page:', maxPage, 'Target page:', targetPage)

        this.applySearch(this.searchValue, targetPage)
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
      console.log('Sort event received:', sortBy)

      // Toggle sort direction if same column, otherwise set to ascending
      if (this.sortBy === sortBy.index) {
        this.sortDirection = this.sortDirection === 'ascending' ? 'descending' : 'ascending'
      } else {
        this.sortBy = sortBy.index
        this.sortDirection = 'ascending'
      }

      console.log('Sort state:', { sortBy: this.sortBy, direction: this.sortDirection })

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

  mounted() {
    // Statt fester size: 100
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
  }
}
</script>


