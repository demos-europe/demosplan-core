<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :change-datetime="changeDatetime"
      :close-on-select="closeOnSelect"
      :current-sort="currentSort"
      :delete-elements="deleteElements"
      :designated-to-switch-state="designatedToSwitchState"
      :handle-key-down="handleKeyDown"
      :header-fields="headerFields"
      :manual-sort-update-route="manualSortUpdateRoute"
      :orgas-of-procedure="orgasOfProcedure"
      :reset-close-on-select="resetCloseOnSelect"
      :save-manual-sort="saveManualSort"
      :selected-elements="selectedElements"
      :selected-orgas="selectedOrgas"
      :set-selection="setSelection"
      :set-table-elements="setTableElements"
      :sort-selected="sortSelected"
      :table-elements="tableElements"
      :toggle-auto-switch-state="toggleAutoSwitchState"
      :update-change-datetime="updateChangeDatetime"
      :update-selected-orgas="updateSelectedOrgas"
    />
  </div>
</template>

<script>
import { makeFormPost } from '@demos-europe/demosplan-ui'

export default {
  name: 'ElementAdminEdit',

  props: {
    category: {
      type: String,
      required: true,
    },

    designatedToSwitch: {
      required: false,
      type: Boolean,
      default: false,
    },

    documents: {
      required: true,
      type: Array,
    },

    elementId: {
      required: true,
      type: String,
    },

    initDatetime: {
      required: false,
      type: String,
      default: '',
    },

    initSelectedOrgas: {
      require: false,
      type: Array,
      default: () => [],
    },

    orgasOfProcedure: {
      require: false,
      type: Array,
      default: () => [],
    },

    procedure: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      closeOnSelect: true,
      changeDatetime: '',
      selectedOrgas: this.orgasOfProcedure.filter(orga => this.initSelectedOrgas.includes(orga.id)),
      designatedToSwitchState: this.designatedToSwitch,
      headerFields: [
        ...hasPermission('field_procedure_single_document_title') ? [{ field: 'title', label: Translator.trans('title') }] : [],
        { field: 'file', label: Translator.trans('file') },
        { field: 'status', label: Translator.trans('status') },
        ...hasPermission('feature_single_document_statement') ? [{ field: 'statement', label: Translator.trans('statement') }] : [],
        { field: 'actions', label: Translator.trans('actions') },
      ],
      tableElements: [],
      selectedElements: [],
      manualSortUpdateRoute: Routing.generate('DemosPlan_elements_administration_edit', { procedure: this.procedure, elementId: this.elementId }),
    }
  },

  computed: {
    currentSort () {
      return this.tableElements.map(el => el.id).join(', ')
    },
  },

  methods: {
    handleKeyDown (event) {
      this.closeOnSelect = event.key !== 'Control'
    },

    setTableElements () {
      this.tableElements = this.documents.map(el => {
        let file = `${el.fileName ?? Translator.trans('notspecified')}`
        if (el.size || el.mimeType) {
          file += `(${el.size ?? ''} ${el.mimeType ?? ''})`
        }
        const status = el.status ? Translator.trans('released') : Translator.trans('blocked')
        const statement = el.statementEnabled ? Translator.trans('yes') : Translator.trans('no')
        const { title, id, hash, procedure, hasDocument } = el
        return { title, file, status, statement, id, hash, procedure, hasDocument }
      })
    },

    deleteElements () {
      if (dpconfirm(Translator.trans('check.items.marked.delete'))) {
        document.singleDocumentForm.r_action.value = 'singledocumentdelete'

        for (const el of this.selectedElements) {
          const hiddenInput = document.createElement('input')

          hiddenInput.setAttribute('type', 'hidden')
          hiddenInput.setAttribute('name', 'document_delete[]')
          hiddenInput.setAttribute('value', el)
          hiddenInput.checked = true
          document.singleDocumentForm.appendChild(hiddenInput)
        }

        document.singleDocumentForm.submit()
      }
    },

    resetCloseOnSelect () {
      this.closeOnSelect = true
    },

    saveManualSort (val) {
      const initialSort = structuredClone(this.tableElements)
      this.tableElements.splice(val.moved.newIndex, 0, this.tableElements.splice(val.moved.oldIndex, 1)[0])

      const payload = {
        r_sorting: this.currentSort,
        r_action: 'saveSort',
        r_elementIdent: this.elementId,
        r_category: this.category,
      }

      // This is needed to get a correct document order on page reload (which is a separate form submit)
      document.singleDocumentForm.r_sorting.value = this.currentSort
      document.singleDocumentForm.r_action.value = 'saveSort'

      return makeFormPost(payload, this.manualSortUpdateRoute).then(response => {
        if (response.status === 200) {
          dplan.notify.notify('confirm', Translator.trans('confirm.plandocument.sorted'))
        } else if (response.status >= 400) {
          dplan.notify.notify('error', Translator.trans('error.api.generic'))
          this.tableElements = initialSort
        }
      })
    },

    setSelection (selection) {
      this.selectedElements = selection
    },

    sortSelected (type) {
      const area = `selected${type}`

      this[area].sort((a, b) => {
        if (a.name > b.name) {
          return 1
        }
        if (b.name > a.name) {
          return -1
        }

        return 0
      })
    },

    toggleAutoSwitchState () {
      this.designatedToSwitchState = this.designatedToSwitchState === false
    },

    updateChangeDatetime (value) {
      this.changeDatetime = value
    },

    updateSelectedOrgas (value) {
      this.selectedOrgas = value
      this.sortSelected('Orgas')
    },
  },

  mounted () {
    this.setTableElements()
    this.changeDatetime = this.initDatetime
  },
}
</script>
