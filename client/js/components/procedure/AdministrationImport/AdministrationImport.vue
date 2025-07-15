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
      @change="setActiveTabId">
      <dp-tab
        v-for="(option, index) in availableImportOptions"
        :key="index"
        :id="option.name"
        :is-active="activeTabId === option.name"
        :label="Translator.trans(option.title)">
        <slot>
          <keep-alive>
            <component
              class="u-mt"
              :is="option.name"
              :demosplan-ui="demosplanUi"
              :csrf-token="csrfToken" />
          </keep-alive>
        </slot>
      </dp-tab>
    </dp-tabs>

    <dp-loading
      v-else
      class="u-mv" />
  </div>
</template>

<script>
import * as demosplanUi from '@demos-europe/demosplan-ui'
import { checkResponse, DpLoading, dpRpc, DpTab, DpTabs, hasAnyPermissions } from '@demos-europe/demosplan-ui'
import AdministrationImportNone from './AdministrationImportNone'
import ExcelImport from './ExcelImport/ExcelImport'
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
    StatementFormImport
  },

  provide () {
    return {
      currentUserId: this.currentUserId,
      newestInternId: this.newestInternId,
      procedureId: this.procedureId,
      submitTypeOptions: this.submitTypeOptions,
      tags: this.tags,
      usedInternIds: this.usedInternIds
    }
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    currentUserId: {
      type: String,
      required: true
    },

    newestInternId: {
      type: String,
      required: false,
      default: '-'
    },

    procedureId: {
      type: String,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    tags: {
      type: Array,
      required: false,
      default: () => []
    },

    usedInternIds: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      activeTabId: '',
      allComponentsLoaded: false,
      asyncComponents: [],
      demosplanUi: shallowRef(demosplanUi)
    }
  },

  computed: {
    availableImportOptions () {
      return [
        {
          name: ExcelImport.name,
          permissions: ['feature_statements_import_excel', 'feature_segments_import_excel'],
          title: 'import.options.xls'
        },
        {
          name: StatementFormImport.name,
          permissions: ['feature_simplified_new_statement_create'],
          title: 'import.options.form'
        },
        {
          name: ParticipationImport.name,
          permissions: ['feature_statements_participation_import_excel'],
          title: 'import.options.participation'
        }
      ].filter((component) => {
        return hasAnyPermissions(component.permissions)
      }).concat(this.asyncComponents)
    }
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
      const params = {
        hookName
      }

      return dpRpc('addons.assets.load', params)
        .then(response => checkResponse(response))
        .then(response => {
          const result = response[0].result

          for (const key of Object.keys(result)) {
            const addon = result[key]
            const contentKey = addon.entry + '.umd.js'
            const content = addon.content[contentKey]

            /**
             * The evaluation of the response content automatically binds the vue component
             * to the window object. This way we can implement it in vue's internals to render
             * the component.
             */
            eval(content)
            this.$options.components[addon.entry] = window[addon.entry].default

            this.asyncComponents.push({
              name: addon.entry,
              title: addon.options.title
            })
          }
        })
    }
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
  }
}
</script>
