<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="exportModal"
    content-classes="w-1/2"
    content-body-classes="m-0 p-0">
    <!-- no modal header -->

    <!-- modal content -->
    <div
      ref="exportModalContent"
      class="c-tabs__modal px-0 pb-0 my-0 h-auto"
      :style="{ minHeight: minHeight + 'px' }">
      <div
        class="tab-header mt-3 mx-3"
        role="tablist">
        <button
          v-for="(option, key) in tabsOptions"
          :key="`${option.tabLabel}:${key}`"
          class="tab w-1/6"
          :class="activeTab(key)"
          :data-cy="`exportModal:${option.tabLabel}`"
          role="tab"
          type="button"
          @click="switchTab(key)">
          {{ Translator.trans(option.tabLabel) }}
        </button>
      </div>

      <div class="tab-context p-3">
        <!-- PDF -->
        <div
          v-if="options.pdf"
          id="pdf"
          class="tab-content"
          :class="activeTab('pdf')"
          role="tabpanel">
          <fieldset
            v-if="options.pdf.anonymize || options.pdf.obscure"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.type')" />
            <dp-checkbox
              id="pdfAnonymous"
              v-model="exportChoice.pdf.anonymous"
              data-cy="exportModal:pdfAnonymous"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.anonymous'),
                  text: Translator.trans('export.anonymous')
                }"
              name="pdfAnonymous" />
          </fieldset>

          <fieldset
            v-if="options.pdf.newPagePerStn && view === 'original_statements'"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.pageLayout')" />
            <dp-checkbox
              id="pdfNewPagePerStn"
              v-model="exportChoice.pdf.newPagePerStn"
              data-cy="exportModal:newPagePerStn"
              :label="{
                  bold: true,
                  text: Translator.trans('export.newPagePerStatement')
                }"
              name="newPagePerStn" />
          </fieldset>

          <fieldset
            v-if="options.pdf.templates"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.format')" />
            <dp-radio
              v-for="(identifier, index) in Object.keys(pdfTemplateOptions)"
              :id="`pdfTemplate_${identifier}`"
              :key="identifier"
              :checked="exportChoice.pdf.template === identifier"
              :class="{ 'mb-1': index !== Object.keys(pdfTemplateOptions).length - 1 }"
              :data-cy="`exportModal:pdfTemplate_${identifier}`"
              :label="{
                  bold: true,
                  hint: pdfTemplateOptions[identifier].explanation || '',
                  text: Translator.trans(pdfTemplateOptions[identifier].name)
                }"
              name="pdfTemplate"
              :value="identifier"
              @change="exportChoice.pdf.template = identifier" />
          </fieldset>

          <fieldset
            v-if="options.pdf.exportTypes && exportChoice.pdf.template == 'condensed' && view == 'assessment_table'"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.data')" />
            <dp-radio
              id="pdfExportTypeStatementsOnly"
              :checked="exportChoice.pdf.exportType === 'statementsOnly'"
              class="mb-1"
              data-cy="exportModal:pdfExportTypeStatementsOnly"
              :label="{
                  bold: true,
                  text: Translator.trans('statements')
                }"
              name="pdfExportType"
              value="statementsOnly"
              @change="exportChoice.pdf.exportType = 'statementsOnly'" />
            <dp-radio
              id="pdfExportTypeStatementsAndFragments"
              :checked="exportChoice.pdf.exportType === 'statementsAndFragments'"
              data-cy="exportModal:pdfExportTypeStatementsAndFragments"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.statementsAndFragments'),
                  text: Translator.trans('fragments')
                }"
              name="pdfExportType"
              value="statementsAndFragments"
              @change="exportChoice.pdf.exportType = 'statementsAndFragments'" />
          </fieldset>

          <p
            v-if="!options.pdf.anonymize && !options.pdf.obscure && !options.pdf.exportTypes && !options.pdf.templates"
            class="ml-2 mt-6">
            {{ Translator.trans('explanation.export.anonymous') }}
          </p>

          <div
            v-if="!isDefaultViewMode"
            class="flash flash-info mb-0">
            {{ Translator.trans('explanation.export.disabled.viewMode') }}
          </div>
        </div>

        <!-- Docx -->
        <div
          v-if="options.docx"
          id="docx"
          class="tab-content"
          :class="activeTab('docx')"
          role="tabpanel">
          <fieldset
            v-if="options.docx.anonymize || options.docx.obscure"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.type')" />
            <dp-checkbox
              id="docxNumberStatements"
              class="mb-1"
              data-cy="exportModal:docxNumberStatements"
              :label="{
                  bold: true,
                  text: Translator.trans('export.numbered_statements'),
                  hint: Translator.trans('explanation.export.numbered_statements')
                  }"
              v-model="exportChoice.docx.numberStatements" />
            <dp-checkbox
              id="docxAnonymous"
              v-model="exportChoice.docx.anonymous"
              data-cy="exportModal:docxObscure"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.anonymous'),
                  text: Translator.trans('export.anonymous')
                }" />
          </fieldset>

          <fieldset
            v-if="options.docx.templates"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.format')" />
            <dp-radio
              v-for="(identifier, index) in Object.keys(docxTemplateOptions)"
              :id="`docxTemplate_${identifier}`"
              :key="identifier"
              :checked="exportChoice.docx.template === identifier"
              :class="{ 'mb-1': index !== Object.keys(docxTemplateOptions).length - 1 }"
              :data-cy="`exportModal:docxTemplate_${identifier}`"
              :label="{
                  bold: true,
                  hint: docxTemplateOptions[identifier].explanation ? Translator.trans(docxTemplateOptions[identifier].explanation) : '',
                  text: Translator.trans(docxTemplateOptions[identifier].name)
                }"
              name="docxTemplate"
              :value="identifier"
              @change="exportChoice.docx.template = identifier" />
          </fieldset>

          <fieldset
            v-if="options.docx.exportTypes && exportChoice.docx.template === 'condensed' && view === 'assessment_table'"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.data')" />
            <dp-radio
              id="docxExportTypeStatementsOnly"
              :checked="exportChoice.docx.exportType === 'statementsOnly'"
              class="mb-1"
              data-cy="exportModal:docxExportTypeStatementsOnly"
              :label="{
                  bold: true,
                  text: Translator.trans('statements')
                }"
              value="statementsOnly"
              @change="() => handleDocxExportTypeChange('statementsOnly')" />
            <dp-radio
              id="docxExportTypeStatementsAndFragments"
              :checked="exportChoice.docx.exportType === 'statementsAndFragments'"
              data-cy="exportModal:docxExportTypeStatementsAndFragments"
              :label="{
                  bold: true,
                  text: Translator.trans('fragments')
                }"
              value="statementsAndFragments"
              @change="() => handleDocxExportTypeChange('statementsAndFragments')" />
          </fieldset>

          <!--choose sorting type-->
          <fieldset
            v-if="options.docx.exportTypes && exportChoice.docx.template === 'condensed' && view === 'assessment_table'"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.structure')" />
            <dp-radio
              id="docxSortTypeDefault"
              :checked="exportChoice.docx.sortType === 'default'"
              class="mb-1"
              data-cy="exportModal:docxSortTypeDefault"
              :label="{
                  bold: true,
                  hint: exportChoice.docx.exportType === 'statementsAndFragments' ? Translator.trans('explanation.export.statementsAndFragments') : '',
                  text: Translator.trans('assessmenttable.view.mode.default')
                }"
              value="default"
              @change="exportChoice.docx.sortType = 'default'" />
            <dp-radio
              id="docxSortTypeByParagraph"
              :checked="isDocxSortTypeByParagraphChecked"
              data-cy="exportModal:docxSortTypeByParagraph"
              :label="{
                  bold: true,
                  text: Translator.trans('groupedBy.elements')
                }"
              :value="exportChoice.docx.exportType === 'statementsAndFragments' ? 'byParagraphFragmentsOnly' : 'byParagraph'"
              @change="handleDocxSortTypeByParagraphChange" />
          </fieldset>
          <!--end of sorting type-->

          <p
            v-if="!options.docx.anonymize && !options.docx.obscure && !options.docx.exportTypes && !options.docx.templates">
            class="ml-2 mt-2"
            {{ Translator.trans('explanation.export.anonymous') }}
          </p>

          <div
            v-if="!isDefaultViewMode"
            class="flash flash-info mb-0">
            {{ Translator.trans('explanation.export.disabled.viewMode') }}
          </div>
        </div>

        <!-- Excel -->
        <div
          v-if="options.xlsx"
          id="xlsx"
          class="tab-content"
          :class="activeTab('xlsx')"
          role="tabpanel">
          <fieldset
            v-if="options.xlsx.anonymize || options.xlsx.obscure"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.type')" />
            <dp-checkbox
              id="xlsxAnonymous"
              v-model="exportChoice.xlsx.anonymous"
              data-cy="exportModal:xlsxAnonymous"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.anonymous'),
                  text: Translator.trans('export.anonymous')
                }" />
          </fieldset>

          <fieldset
            v-if="options.xlsx.exportTypes"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.data')" />
            <dp-radio
              id="xlsxExportTypeTopicsAndTags"
              :checked="exportChoice.xlsx.exportType === 'topicsAndTags'"
              class="mb-1"
              data-cy="exportModal:xlsxExportTypeTopicsAndTags"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.topicsAndTags'),
                  text: Translator.trans('export.topicsAndTags')
                }"
              name="xlsxExportType"
              value="topicsAndTags"
              @change="exportChoice.xlsx.exportType = 'topicsAndTags'" />
            <dp-radio
              v-if="hasPermission('feature_admin_assessmenttable_export_potential_areas_xlsx')"
              id="xlsxExportTypePotentialAreas"
              :checked="exportChoice.xlsx.exportType === 'potentialAreas'"
              :class="{'mb-1': hasPermission('feature_admin_assessmenttable_export_statement_generic_xlsx')}"
              data-cy="exportModal:xlsxExportTypePotentialAreas"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.potentialAreas'),
                  text: Translator.trans('export.potentialAreas')
                }"
              name="xlsxExportType"
              value="potentialAreas"
              @change="exportChoice.xlsx.exportType = 'potentialAreas'" />
            <dp-radio
              v-if="hasPermission('feature_admin_assessmenttable_export_statement_generic_xlsx')"
              id="xlsxExportTypeStatement"
              :checked="exportChoice.xlsx.exportType === 'statements'"
              data-cy="exportModal:xlsxExportTypeStatement"
              :label="{
                  bold: true,
                  hint: Translator.trans('explanation.export.statements', { hasSelectedElements: hasSelectedElements }),
                  text: Translator.trans('statements')
                }"
              name="xlsxExportType"
              value="statements"
              @change="exportChoice.xlsx.exportType = 'statements'" />
          </fieldset>
          <p
            v-if="!options.xlsx.anonymize && !options.xlsx.obscure && !options.xlsx.exportTypes && !options.xlsx.templates"
            class="ml-2 mt-6">
            {{ Translator.trans('explanation.export.anonymous') }}
          </p>
        </div>

        <!-- Zip -->
        <div
          v-if="options.zip"
          id="zip"
          class="tab-content"
          :class="activeTab('zip')"
          role="tabpanel">
          <p
            class="lbl__hint ml-2 mb-3"
            v-text="explanationZip" />
          <fieldset
            v-if="options.zip.templates"
            class="u-mb-0_5 pb-2">
            <legend
              class="sr-only"
              v-text="Translator.trans('export.format')" />
            <dp-radio
              v-for="(identifier, index) in Object.keys(zipTemplateOptions)"
              :id="`zipTemplate_${identifier}`"
              :key="identifier"
              :checked="exportChoice.zip.template === identifier"
              :class="{ 'mb-1': index !== Object.keys(zipTemplateOptions).length - 1 }"
              :data-cy="`exportModal:zipTemplate_${identifier}`"
              :label="{
                  bold: true,
                  hint: zipTemplateOptions[identifier].explanation || '',
                  text: Translator.trans(zipTemplateOptions[identifier].name)
                }"
              name="zipTemplate"
              :value="identifier"
              @change="exportChoice.zip.template = identifier" />
          </fieldset>
        </div>

        <dp-button
          class="submitBtn"
          data-cy="exportModal:submit"
          :text="submitLabel"
          @click.prevent="handleSubmit" />
      </div>
    </div>
  </dp-modal>
</template>

<script>
import {
  DpButton,
  DpCheckbox,
  DpModal,
  DpRadio,
  hasOwnProp
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ExportModal',

  components: {
    DpButton,
    DpCheckbox,
    DpModal,
    DpRadio
  },

  props: {
    currentTableSort: {
      required: false,
      type: String,
      default: ''
    },

    hasSelectedElements: {
      required: false,
      type: Boolean,
      default: false
    },

    //  Export options that define which formats / fields to display
    options: {
      required: true,
      type: Object
    },

    procedureId: {
      required: true,
      type: String
    },

    view: {
      required: true,
      type: String
      // Validator: ['assessment_table', 'original_statements', 'fragment_list'].includes
    },

    /*
     *  With special viewModes (showing statements ordered by elements/tag reference, see T8624 + T8715)
     *  there is no proper way to show fragments in the exports (will be fixed one time).
     *  Therefore, the option to export with fragments is simply disabled as a workaround.
     */
    viewMode: {
      required: false,
      type: String,
      default: 'view_mode_default'
    }
  },

  emits: [
    'submit'
  ],

  data () {
    // Set default values for exportChoice
    const options = this.options
    const data = {}
    let optGroupKey // 'docx', 'pdf', etc.
    let optGroup // All the options defined for an optGroupKey
    let optKey // Key of a single option, e.g. 'exportType', 'sortType'

    for (optGroupKey in options) {
      optGroup = options[optGroupKey]
      data[optGroupKey] = {}

      if (!optGroup) continue

      for (optKey in optGroup._defaults) {
        data[optGroupKey][optKey] = optGroup._defaults[optKey]
      }
    }

    return {
      //  Object where user input is saved
      exportChoice: data,
      isOpenModal: false,
      currentTab: null,
      minHeight: 0
    }
  },

  computed: {
    explanationZip () {
      if (this.options.zip.exportType === 'originalStatements') {
        return Translator.trans('explanation.export.original_statements.zip', { hasSelectedElements: this.hasSelectedElements })
      }

      return Translator.trans('explanation.export.statements.zip', { hasSelectedElements: this.hasSelectedElements })
    },

    //  Get first tab to activate
    defaultTab () {
      for (const key in this.options) {
        // Skip loop if the property is from prototype
        if (!hasOwnProp(this.options, key)) {
          continue
        }

        if (this.options[key]) {
          return key
        }
      }
      return false
    },

    docxTemplateOptions () {
      const optionsDocxFilter = Object.entries(this.options.docx.templates).filter(([key, value]) => {
        return value ? this.hasVisibleTemplate({ [key]: value }) : false
      })
      return Object.fromEntries(optionsDocxFilter)
    },

    //  Return exportChoice for currently selected format
    format () {
      return this.exportChoice[this.currentTab]
    },

    isDefaultViewMode () {
      return this.viewMode === 'view_mode_default'
    },

    isDocxSortTypeByParagraphChecked () {
      return this.exportChoice.docx.exportType === 'statementsAndFragments'
        ? this.exportChoice.docx.sortType === 'byParagraphFragmentsOnly'
        : this.exportChoice.docx.sortType === 'byParagraph'
    },

    pdfTemplateOptions () {
      return this.getTemplateOptions(this.options.pdf)
    },

    //  Return export route for current view
    route () {
      if (this.view === 'assessment_table') {
        return 'DemosPlan_assessment_table_export'
      }

      if (this.view === 'original_statements') {
        return 'DemosPlan_assessment_table_original_export'
      }

      // TODO: fragment list

      return null
    },

    submitLabel () {
      let transKey

      switch (this.currentTab) {
        case 'pdf':
        case 'docx':
        default:
          transKey = 'export.verb'
          break
      }

      return Translator.trans(transKey, {})
    },

    /**
     * Only show truthy options as tabs.
     */
    tabsOptions () {
      return Object.keys(this.options)
        .filter(option => this.options[option])
        .reduce((obj, key) => {
          obj[key] = this.options[key]
          return obj
        }, {})
    },

    zipTemplateOptions () {
      return this.getTemplateOptions(this.options.zip)
    }
  },

  methods: {
    activeTab (tab) {
      return tab === this.currentTab ? 'active' : false
    },

    getTemplateOptions (options) {
      const visibleOptions = Object.entries(options.templates).filter(([key, value]) => {
        return value ? this.hasVisibleTemplate({ [key]: value }) : false
      })

      return Object.fromEntries(visibleOptions)
    },

    handleDocxExportTypeChange (value) {
      this.exportChoice.docx.exportType = value
      this.exportChoice.docx.sortType = 'default'
    },

    handleDocxSortTypeByParagraphChange () {
      this.exportChoice.docx.sortType = this.exportChoice.docx.exportType === 'statementsAndFragments'
        ? 'byParagraphFragmentsOnly'
        : 'byParagraph'
    },

    handleSubmit () {
      this.submit()

      this.$refs.exportModal.toggle()
      this.$emit('submit')
    },

    /**
     *  Only show template if not hidden by hideForViewModes restriction
     * @param templateInfo {Object}
     * @return {boolean|{hideForViewModes}|*}
     */
    hasVisibleTemplate (templateInfo) {
      const hideForViewModes = templateInfo.hideForViewModes || false
      if (hideForViewModes) {
        return !templateInfo.hideForViewModes.includes(this.viewMode)
      } else {
        return templateInfo
      }
    },

    switchTab (tab) {
      this.currentTab = tab
    },

    getSearchFields () {
      const allSearchFields = Array.from(document.getElementsByName('search_fields[]'))
      const checkedSearchFields = []
      allSearchFields.forEach(function (searchField) {
        if (searchField.checked) {
          checkedSearchFields.push(searchField.id)
        }
      }
      )
      return checkedSearchFields.join()
    },

    submit () {
      const oldAction = document.bpform.action

      document.bpform.action = Routing.generate(this.route, {
        procedureId: this.procedureId
      })

      // Set data params
      document.bpform.r_export_format.value = this.currentTab
      document.bpform.r_export_choice.value = JSON.stringify(this.format)
      document.bpform.searchFields.value = this.getSearchFields()

      // Add sorting only if neccessary
      if (hasOwnProp(document.bpform, 'currentTableSort')) {
        document.bpform.currentTableSort.value = this.currentTableSort
      }

      //  Submit form
      document.bpform.submit()

      //  Restore original form action
      document.bpform.action = oldAction
    },

    toggleModal (tab) {
      // If modal doesn't have any options to choose from, don't show modal but do a submit instead
      const options = this.options
      let hasContent = false
      let o
      let opt

      for (o in options) {
        opt = options[o]
        if (opt.exportTypes || opt.anonymize || opt.obscure || opt.templates || opt.sortType) {
          hasContent = true
          break
        }
      }
      if (!hasContent) {
        this.switchTab(tab)
        this.submit()
        this.$emit('submit')
      } else {
        this.isOpenModal = !this.isOpenModal
        setTimeout(() => this.setBodyMaxHeight(), 100)
        this.switchTab(tab)
        this.$refs.exportModal.toggle()
      }
    },

    setBodyMaxHeight () {
      const contentHeights = []
      const tabs = this.$refs.exportModalContent.querySelectorAll('.tab-content')
      tabs.forEach(tabContent => {
        if (!tabContent.classList.contains('active')) {
          tabContent.style.display = 'block'
        }
        contentHeights.push(tabContent.clientHeight)
        tabContent.style.display = ''
      })
      this.minHeight = Math.max.apply(null, contentHeights) + 155
    }
  },

  mounted () {
    this.currentTab = this.defaultTab
    this.$root.$on('exportModal:toggle', (tab) => this.toggleModal(tab))
  }
}
</script>
