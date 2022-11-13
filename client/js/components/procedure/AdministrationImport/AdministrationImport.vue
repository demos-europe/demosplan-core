<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-tabs
    :active-id="activeTabId"
    use-url-fragment
    @change="setActiveTabId">
    <dp-tab
      v-for="(option, index) in availableImportOptions"
      :key="index"
      :id="option.name"
      :label="Translator.trans(option.title)">
      <slot>
        <keep-alive>
          <component
            class="u-mt"
            :is="option.name" />
        </keep-alive>
      </slot>
    </dp-tab>
  </dp-tabs>
</template>

<script>
import AdministrationImportNone from './AdministrationImportNone'
import DpTab from '@DpJs/components/core/DpTabs/DpTab'
import DpTabs from '@DpJs/components/core/DpTabs/DpTabs'
import ExcelImport from './ExcelImport/ExcelImport'
import { hasAnyPermissions } from 'demosplan-utils'
import StatementFormImport from './StatementFormImport/StatementFormImport'
import StatementPdfImport from './StatementPdfImport/StatementPdfImport'

export default {
  name: 'AdministrationImport',

  components: {
    AdministrationImportNone,
    DpTab,
    DpTabs,
    ExcelImport,
    StatementFormImport,
    StatementPdfImport
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
      addons: [],
      activeTabId: '',
      asyncComponents: []
    }
  },

  computed: {
    availableImportOptions () {
      return [
        ...this.asyncComponents,
        {
          name: ExcelImport.name,
          permissions: ['feature_statements_import_excel', 'feature_segments_import_excel'],
          title: 'import.options.xls'
        },
        {
          name: StatementFormImport.name,
          permissions: ['feature_simplified_new_statement_create'],
          title: 'import.options.form'
        }
      ].filter((component) => {
        return hasAnyPermissions(component.permissions)
      })
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

    async loadExternalComponentScripts (addon) {
      const name = addon.url.split('/').reverse()[0].match(/^(.*?)\.umd/)[1]

      if (window[name]) return window[name]

      window[name] = new Promise((resolve, reject) => {
        const script = document.createElement('script')

        script.async = true
        script.addEventListener('load', () => {
          resolve(window[name])
          this.$options.components[name] = window[name]

          this.asyncComponents.push({
            name: name,
            permissions: addon.permissions,
            title: addon.title
          })
        })

        script.addEventListener('error', () => {
          reject(new Error(`Error loading ${url}`))
        })

        script.src = addon.url
        document.head.appendChild(script)
      });

      return window[name]
    }
  },

  mounted () {
    this.addons.forEach(addon => {
      this.loadExternalComponentScripts(addon).then(this.setActiveTabId())
    })
  }
}
</script>
