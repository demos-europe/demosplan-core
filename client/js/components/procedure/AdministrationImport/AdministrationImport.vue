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
import { DpTab, DpTabs } from '@demos-europe/demosplan-ui'
import AdministrationImportNone from './AdministrationImportNone'
import ExcelImport from './ExcelImport/ExcelImport'
import { dpRpc, hasAnyPermissions } from '@demos-europe/demosplan-utils'
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

    /**
     * Append a script tag to the head section which will be used to load a vue component dynamically
     *
     * @param {string} component
     */
    addComponentScript (component) {
      const script = document.createElement('script')
      script.id = component.name
      script.type = 'text/javascript'
      script.text = component.text

      document.head.appendChild(script)
      script.addEventListener('load', () => this.attachComponent(component))
    },

    /**
     * Add component to this Vue wrapper component
     */
    addComponent (component) {
      this.asyncComponents.push({
        name: component.name,
        permissions: component.permissions,
        title: component.title
      })
    },

    attachComponent (component) {
      this.$options.components[component.name] = window[component.name]
      const t = { ...component, component: this.$options.components[component.name] }
      this.addComponent(t)
    },

    loadComponents (hookName) {
      const params = {
        hookName: hookName
      }

      dpRpc('addons.assets.load', params, 'rpc_generic_post')
        .then(response => {
          this.addComponent(response)
          this.addComponentScript(response)
        })
    }
  },

  mounted () {
    this.loadComponents('import.tabs')
  }
}
</script>
