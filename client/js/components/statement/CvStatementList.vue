<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="cv-statement-list">
    <!-- Single Header Row with all 3 elements -->
    <div class="header-row">
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
      v-if="activeTab === 'statements'"
      v-model:rows-selected="selectedRows"
      :columns="columns"
      :data="statements">

      <template #actions>
        <cv-search
          light
          placeholder="Suchen"
          :value="searchValue"
          @input="applySearch"
        />
        <cv-button kind="ghost" icon-only>
          <Filter16 />
        </cv-button>
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
        <cv-data-table-row v-for="(row, index) in statements" :key="index" :value="row.id">
          <cv-data-table-cell>{{ row.id }}</cv-data-table-cell>
          <cv-data-table-cell>
            {{ formatDate(row.statusDate) }}
            <br>
            <cv-tag
              :label="row.status"
              :kind="getStatusType(row.status)"
              :class="getStatusClass(row.status)" />
          </cv-data-table-cell>
          <cv-data-table-cell>{{ row.author }}</cv-data-table-cell>
          <cv-data-table-cell>
            <div class="text-content">
              {{ row.text }}
            </div></cv-data-table-cell>
          <cv-data-table-cell>{{ row.sections }}</cv-data-table-cell>
        </cv-data-table-row>
      </template>
    </cv-data-table>

    <!-- Sections Content -->
    <div v-if="activeTab === 'sections'">
      <p>Aufteilung in Abschnitte Content - Coming Soon</p>
    </div>
  </div>
</template>

<script>
import {
  CvButton,
  CvDataTable,
  CvDataTableRow,
  CvDataTableCell,
  CvSearch,
  CvTag,
  CvContentSwitcher,
  CvContentSwitcherButton
} from '@carbon/vue'
import DocumentAdd16 from '@carbon/icons-vue/es/document--add/16'
import Export16 from '@carbon/icons-vue/es/export/16'
import Filter16 from '@carbon/icons-vue/es/filter/16'
import Search16 from '@carbon/icons-vue/es/search/16'
import { mapState, mapActions } from 'vuex'

export default {
  name: 'CvStatementList',

  components: {
    CvButton,
    CvDataTable,
    CvDataTableRow,
    CvDataTableCell,
    CvSearch,
    CvTag,
    CvContentSwitcher,
    CvContentSwitcherButton,
    DocumentAdd16,
    Export16,
    Filter16,
    Search16
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
        { key: 'id', label: 'ID' },
        { key: 'status', label: 'Stn.-Status' },
        { key: 'author', label: 'Einreicher*in' },
        { key: 'text', label: 'Text' },
        { key: 'sections', label: 'Abschnitte' }
      ],
      selectedRows: [],
      searchValue: '',
    }
  },

  computed: {
    ...mapState('Statement', {
      statementsObject: 'items'
    }),

    statements() {
      const rawData = Object.values(this.statementsObject) || []
      console.log('RAW DATA LENGTH:', rawData.length)
      console.log('VUEX STORE:', this.statementsObject)

      if (rawData.length > 0) {
        console.log('Echte Statement-Struktur:', rawData[0])
        console.log('Alle relationships:', rawData[0].relationships)
        console.log('Segments-Struktur:', rawData[0].relationships?.segments)
        console.log('Segments-Data:', rawData[0].relationships?.segments?.data)
      }

      // Mapping von API-Daten zu Component-Format
      return rawData.map(stmt => ({
        id: stmt.attributes?.externId || stmt.id,
        status: this.mapApiStatusToDisplay(stmt.attributes.status),
        statusDate: stmt.attributes.submitDate,
        author: `${stmt.attributes.authorName}\n${this.formatDate(stmt.attributes.authoredDate)}`,
        text: stmt.attributes?.text || stmt.text,
        sections: (() => {
          const segments = stmt.relationships?.segments?.data
          console.log(`Statement ${stmt.id} segments:`, segments)
          return segments?.length || '-'
        })()
      }))
    }
  },

  methods: {
    // Vuex Actions importieren
    ...mapActions('Statement', {
      fetchStatements: 'list'
    }),

    applySearch(term) {
      this.searchValue = term

      this.fetchStatements({
        page: { number: 1, size: 10 }, // Wie ListStatements
        search: { value: this.searchValue },
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        sort: '-submitDate', // Default sort!
        include: ['segments', 'assignee', 'sourceAttachment', 'sourceAttachment.file'].join(),
        fields: {
          Statement: [
            'authoredDate', 'authorName', 'externId', 'isSubmittedByCitizen',
            'initialOrganisationName', 'internId', 'status', 'submitDate',
            'submitName', 'text', 'textIsTruncated'
          ].join(),
          SourceStatementAttachment: ['file'].join()
        }
      })
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

    createNewStatement() {
      const hasSimplifiedCreate = hasPermission('feature_simplified_new_statement_create')
      const route = hasSimplifiedCreate ? 'DemosPlan_procedure_import' : 'DemosPlan_statement_new_submitted'

      window.location.href = Routing.generate(route, { procedureId: this.procedureId })
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

    onTabSwitch(selectedButton) {
      if (selectedButton.includes('statements')) {
        this.activeTab = 'statements'
      } else if (selectedButton.includes('sections')) {
        this.activeTab = 'sections'
      }
    }
  },

  mounted() {
    console.log('MOUNTING - fetching statements')

    this.fetchStatements({
      page: {
        number: 1,
        size: 100
      },
      include: ['segments'].join()
    })
  }
}
</script>


