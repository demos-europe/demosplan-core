<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div v-if="availableImportOptions.length > 0">
    <dp-tabs
      v-if="allComponentsLoaded"
      :active-id="activeTabId"
      use-url-fragment
      @change="setActiveTabId"
    >
      <dp-tab
        v-for="(option, index) in availableImportOptions"
        :id="option.name"
        :key="index"
        :is-active="activeTabId === option.name"
        :label="Translator.trans(option.title)"
      >
        <slot>
          <keep-alive>
            <component
              :is="option.component || option.name"
              :csrf-token="csrfToken"
              :demosplan-ui="demosplanUi"
              class="u-mt"
            />
          </keep-alive>
        </slot>
      </dp-tab>
    </dp-tabs>

    <dp-loading
      v-else
      class="u-mv"
    />
  </div>
</template>

<script>
import * as demosplanUi from '@demos-europe/demosplan-ui'
import { DpLoading, DpTab, DpTabs, hasAnyPermissions } from '@demos-europe/demosplan-ui'
import AdministrationImportNone from './AdministrationImportNone'
import ExcelImport from './ExcelImport/ExcelImport'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'
import ParticipationImport from './ParticipationImport/ParticipationImport'
import { shallowRef } from 'vue'
import StatementFormImport from './StatementFormImport/StatementFormImport'

export default {
  name: 'AdministrationImport',

  components: {
    AdministrationImportNone,
    DpLoading,
    DpTab,
    DpTabs,
    ExcelImport,
    ParticipationImport,
    StatementFormImport,
  },

  provide () {
    return {
      currentExternalPhaseDefinitionId: this.currentExternalPhaseDefinitionId,
      currentInternalPhaseDefinitionId: this.currentInternalPhaseDefinitionId,
      currentUserId: this.currentUserId,
      newestInternId: this.newestInternId,
      procedureId: this.procedureId,
      submitTypeOptions: this.submitTypeOptions,
      tags: this.tags,
      usedInternIds: this.usedInternIds,
    }
  },

  props: {
    csrfToken: {
      type: String,
      required: true,
    },

    currentExternalPhaseDefinitionId: {
      type: String,
      required: false,
      default: '',
    },

    currentInternalPhaseDefinitionId: {
      type: String,
      required: false,
      default: '',
    },

    currentUserId: {
      type: String,
      required: true,
    },

    newestInternId: {
      type: String,
      required: false,
      default: '-',
    },

    procedureId: {
      type: String,
      required: true,
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    tags: {
      type: Array,
      required: false,
      default: () => [],
    },

    usedInternIds: {
      type: Array,
      required: false,
      default: () => [],
    },
  },

  data () {
    return {
      activeTabId: '',
      allComponentsLoaded: false,
      asyncComponents: [],
      demosplanUi: shallowRef(demosplanUi),
    }
  },

  computed: {
    availableImportOptions () {
      return [
        {
          name: ExcelImport.name,
          permissions: ['feature_statements_import_excel', 'feature_statements_import_csv', 'feature_segments_import_excel'],
          title: hasPermission('feature_statements_import_csv') ?
            'import.options.xls_csv' :
            'import.options.xls',
        },
        {
          name: StatementFormImport.name,
          permissions: ['feature_simplified_new_statement_create'],
          title: 'import.options.form',
        },
        {
          name: ParticipationImport.name,
          permissions: ['feature_statements_participation_import_excel'],
          title: 'import.options.participation',
        },
      ].filter((component) => {
        return hasAnyPermissions(component.permissions)
      }).concat(this.asyncComponents)
    },
  },

  methods: {
    setActiveTabId (id) {
      if (id) {
        window.localStorage.setItem('importCenterActiveTabId', id)
      }

      if (window.localStorage.getItem('importCenterActiveTabId')) {
        this.activeTabId = window.localStorage.getItem('importCenterActiveTabId')
      }
    },

    loadComponents (hookName) {
      return loadAddonComponents(hookName)
        .then((addons) => {
          this.asyncComponents.push(...addons.map((addon) => ({
            component: addon.component,
            name: addon.name,
            title: addon.options.title,
          })))
        })
    },
  },

  mounted () {
    const promises = [this.loadComponents('email.import')]

    if (hasPermission('feature_import_statement_pdf')) {
      promises.push(this.loadComponents('import.tabs'))
    }

    Promise.allSettled(promises)
      .then(() => {
        this.allComponentsLoaded = true
        this.setActiveTabId()
      })
  },
}
</script>
