<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <portal to="vueModals">
    <dp-modal
      ref="exportModal"
      content-classes="u-1-of-2"
      content-body-classes="u-m-0 u-p-0">
      <!-- no modal header -->

      <!-- modal content -->
      <div
        class="c-tabs__modal u-ph-0 u-pb-0 u-mv-0 h-auto"
        :style="{ minHeight: minHeight + 'px' }"
        ref="exportModalContent">
        <div
          class="tab-header u-mt-0_75 u-mh-0_75"
          role="tablist">
          <button
            class="tab u-1-of-6"
            :class="activeTab(key)"
            @click="switchTab(key)"
            type="button"
            role="tab"
            v-for="(option, key) in tabsOptions"
            :key="key">
            {{ Translator.trans(option.tabLabel) }}
          </button>
        </div>

        <div class="tab-context u-p-0_75">
          <!-- PDF -->
          <div
            class="tab-content"
            :class="activeTab('pdf')"
            role="tabpanel"
            v-if="options.pdf">
            <fieldset
              v-if="options.pdf.anonymize || options.pdf.obscure"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.type')" />
              <label
                for="pdfAnonymous"
                class="u-mb-0_25">
                <input
                  type="checkbox"
                  name="pdfAnonymous"
                  id="pdfAnonymous"
                  value="anonymous"
                  v-model="exportChoice.pdf.anonymous">
                {{ Translator.trans('export.anonymous') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">{{ Translator.trans('explanation.export.anonymous') }}</p>
              </label>
            </fieldset>

            <fieldset
              v-if="options.pdf.templates"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.format')" />
              <label
                v-for="(templateInfo, identifier) in pdfTemplateOptions"
                :key="identifier"
                :for="'pdfTemplate_'+identifier"
                class="u-mb-0_25">
                <input
                  type="radio"
                  :id="'pdfTemplate_'+identifier"
                  name="pdfTemplate"
                  v-model="exportChoice.pdf.template"
                  :value="identifier">
                {{ Translator.trans(templateInfo.name) }}
                <p
                  class="lbl__hint u-ml-0_75 u-mb-0"
                  v-if="templateInfo.explanation">
                  {{ Translator.trans(templateInfo.explanation) }}
                </p>
              </label>
            </fieldset>

            <fieldset
              v-if="options.pdf.exportTypes && exportChoice.pdf.template == 'condensed' && view == 'assessment_table'"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.data')" />
              <label
                for="pdfExportTypeStatementsOnly"
                class="u-mb-0_25">
                <input
                  type="radio"
                  name="pdfExportType"
                  id="pdfExportTypeStatementsOnly"
                  value="statementsOnly"
                  v-model="exportChoice.pdf.exportType">
                {{ Translator.trans('statements') }}
              </label>
              <label
                for="pdfExportTypeStatementsAndFragments"
                class="u-mb-0_25">
                <input
                  type="radio"
                  name="pdfExportType"
                  id="pdfExportTypeStatementsAndFragments"
                  value="statementsAndFragments"
                  v-model="exportChoice.pdf.exportType">
                {{ Translator.trans('fragments') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">
                  {{ Translator.trans('explanation.export.statementsAndFragments') }}
                </p>
              </label>
            </fieldset>

            <p
              v-if="!options.pdf.anonymize && !options.pdf.obscure && !options.pdf.exportTypes && !options.pdf.templates"
              class="u-ml-0_5 u-mt-2">
              {{ Translator.trans('explanation.export.anonymous') }}
            </p>

            <div
              v-if="!isDefaultViewMode"
              class="flash flash-info u-mb-0">
              {{ Translator.trans('explanation.export.disabled.viewMode') }}
            </div>
          </div>

          <!-- Word -->
          <div
            v-if="options.docx"
            class="tab-content"
            :class="activeTab('docx')"
            role="tabpanel">
            <fieldset
              v-if="options.docx.anonymize || options.docx.obscure"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.type')" />
              <label
                for="docxAnonymous"
                class="u-mb-0_25">
                <input
                  type="checkbox"
                  id="docxAnonymous"
                  value="anonymous"
                  v-model="exportChoice.docx.anonymous">
                {{ Translator.trans('export.anonymous') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">{{ Translator.trans('explanation.export.anonymous') }}</p>
              </label>
            </fieldset>

            <fieldset
              v-if="options.docx.templates"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.format')" />
              <label
                v-for="(templateInfo, identifier) in docxTemplateOptions"
                :key="identifier"
                :for="'docxTemplate_'+identifier"
                class="u-mb-0_25">
                <input
                  type="radio"
                  :id="'docxTemplate_'+identifier"
                  name="docxTemplate"
                  v-model="exportChoice.docx.template"
                  :value="identifier">
                {{ Translator.trans(templateInfo.name) }}
                <p
                  class="lbl__hint u-ml-0_75 u-mb-0"
                  v-if="templateInfo.explanation">
                  {{ Translator.trans(templateInfo.explanation) }}
                </p>
              </label>
            </fieldset>

            <fieldset
              v-if="options.docx.exportTypes && exportChoice.docx.template === 'condensed' && view === 'assessment_table'"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.data')" />
              <label
                for="docxExportTypeStatementsOnly"
                class="u-mb-0_25">
                <input
                  type="radio"
                  id="docxExportTypeStatementsOnly"
                  value="statementsOnly"
                  @change="() => { exportChoice.docx.sortType = 'default' }"
                  v-model="exportChoice.docx.exportType">
                {{ Translator.trans('statements') }}
              </label>

              <label
                for="docxExportTypeStatementsAndFragments"
                class="u-mb-0_25">
                <input
                  type="radio"
                  id="docxExportTypeStatementsAndFragments"
                  value="statementsAndFragments"
                  @change="() => { exportChoice.docx.sortType = 'default' }"
                  v-model="exportChoice.docx.exportType">
                {{ Translator.trans('fragments') }}
              </label>
            </fieldset>

            <!--choose sorting type-->
            <fieldset
              v-if="options.docx.exportTypes && exportChoice.docx.template === 'condensed' && view === 'assessment_table'"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.structure')" />
              <label
                for="docxSortTypeDefault"
                class="u-mb-0_25">
                <input
                  type="radio"
                  value="default"
                  id="docxSortTypeDefault"
                  v-model="exportChoice.docx.sortType">
                {{ Translator.trans('assessmenttable.view.mode.default') }}
                <p
                  v-if="exportChoice.docx.exportType === 'statementsAndFragments'"
                  class="lbl__hint u-ml-0_75 u-mb-0">{{ Translator.trans('explanation.export.statementsAndFragments') }}</p>
              </label>
              <label
                for="docxSortTypeByParagraph"
                class="u-mb-0_25">
                <input
                  type="radio"
                  :value="exportChoice.docx.exportType === 'statementsAndFragments' ? 'byParagraphFragmentsOnly' : 'byParagraph'"
                  id="docxSortTypeByParagraph"
                  v-model="exportChoice.docx.sortType">
                {{ Translator.trans('groupedBy.elements') }}
              </label>
            </fieldset>
            <!--end of sorting type-->

            <p
              class="u-ml-0_5 u-mt-2"
              v-if="!options.docx.anonymize && !options.docx.obscure && !options.docx.exportTypes && !options.docx.templates">
              {{ Translator.trans('explanation.export.anonymous') }}
            </p>

            <div
              v-if="!isDefaultViewMode"
              class="flash flash-info u-mb-0">
              {{ Translator.trans('explanation.export.disabled.viewMode') }}
            </div>
          </div>

          <!-- Excel -->
          <div
            v-if="options.xlsx"
            class="tab-content"
            :class="activeTab('xlsx')"
            role="tabpanel">
            <fieldset
              v-if="options.xlsx.anonymize || options.xlsx.obscure"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.type')" />
              <label
                for="xlsxAnonymous"
                class="u-mb-0_25">
                <input
                  type="checkbox"
                  id="xlsxAnonymous"
                  value="anonymous"
                  v-model="exportChoice.xlsx.anonymous">
                {{ Translator.trans('export.anonymous') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">
                  {{ Translator.trans('explanation.export.anonymous') }}
                </p>
              </label>
            </fieldset>
            <fieldset
              v-if="options.xlsx.exportTypes"
              class="u-mb-0_5 u-pb-0_5">
              <legend
                class="hide-visually"
                v-text="Translator.trans('export.data')" />
              <label
                for="xlsxExportTypeTopicsAndTags"
                class="u-mb-0_25">
                <input
                  type="radio"
                  name="xlsxExportType"
                  id="xlsxExportTypeTopicsAndTags"
                  value="topicsAndTags"
                  v-model="exportChoice.xlsx.exportType">
                {{ Translator.trans('export.topicsAndTags') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">
                  {{ Translator.trans('explanation.export.topicsAndTags') }}
                </p>
              </label>
              <label
                for="xlsxExportTypePotentialAreas"
                class="u-mb-0_25"
                v-if="hasPermission('field_statement_priority_area')">
                <input
                  type="radio"
                  name="xlsxExportType"
                  id="xlsxExportTypePotentialAreas"
                  value="potentialAreas"
                  v-model="exportChoice.xlsx.exportType">
                {{ Translator.trans('export.potentialAreas') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">
                  {{ Translator.trans('explanation.export.potentialAreas') }}
                </p>
              </label>
              <label
                for="xlsxExportTypeStatement"
                class="u-mb-0_25"
                v-if="hasPermission('feature_admin_assessmenttable_export_statement_generic_xlsx')">
                <input
                  type="radio"
                  name="xlsxExportType"
                  id="xlsxExportTypeStatement"
                  value="statements"
                  v-model="exportChoice.xlsx.exportType">
                {{ Translator.trans('statements') }}
                <p class="lbl__hint u-ml-0_75 u-mb-0">
                  {{ Translator.trans('explanation.export.statements', { hasSelectedElements: hasSelectedElements }) }}
                </p>
              </label>
            </fieldset>
            <p
              class="u-ml-0_5 u-mt-2"
              v-if="!options.xlsx.anonymize && !options.xlsx.obscure && !options.xlsx.exportTypes && !options.xlsx.templates">
              {{ Translator.trans('explanation.export.anonymous') }}
            </p>
          </div>

          <!-- Zip -->
          <div
            v-if="options.zip"
            class="tab-content"
            :class="activeTab('zip')"
            role="tabpanel">
            <p class="lbl__hint u-ml-0_75 u-mb-0">
              {{ Translator.trans('explanation.export.statements.zip', { hasSelectedElements: hasSelectedElements }) }}
            </p>
          </div>

          <button
            type="button"
            class="btn btn--primary submitBtn"
            @click.prevent="handleSubmit">
            {{ submitLabel }}
          </button>
        </div>
      </div>
    </dp-modal>
  </portal>
</template>

<script>
import { DpModal, hasOwnProp } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpExportModal',

  components: {
    DpModal
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
     *  Therefore, the option to export with fragments are simply disabled as a workaround.
     */
    viewMode: {
      required: false,
      type: String,
      default: 'view_mode_default'
    }
  },

  data () {
    const options = this.options
    const data = {}
    let o
    let opt
    let k

    for (o in options) {
      opt = options[o]
      data[o] = {}
      if (!opt) continue
      for (k in opt._defaults) {
        data[o][k] = opt._defaults[k]
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

    pdfTemplateOptions () {
      const optionsPdfFilter = Object.entries(this.options.pdf.templates).filter(([key, value]) => {
        return value ? this.hasVisibleTemplate({ [key]: value }) : false
      })
      return Object.fromEntries(optionsPdfFilter)
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
    }
  },

  methods: {
    activeTab (tab) {
      return tab === this.currentTab ? 'active' : false
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
