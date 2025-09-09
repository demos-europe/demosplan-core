<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!-- This component contains search field and search options for assessment table and assessment table with original statements -->
</documentation>

<template>
  <div class="flex">
    <!-- Search Field -->
    <label class="relative u-m-0">
      <button
        class="btn-icns fa fa-search c-at__controls-input-button"
        data-cy="searchAssessmentWordButton"
        :class="{'color-highlight': true === highlighted}"
        @click="submit" />
      <dp-input
        has-icon
        id="searchterm"
        name="search_word2"
        data-cy="searchAssessmentWordField"
        :placeholder="placeholder"
        v-model="searchString"
        width="w-12"
        :aria-label="Translator.trans('search.assessment.table')"
        @enter="submit" />
    </label>

    <!-- Advanced Search button to open modal -->
    <button
      type="button"
      data-cy="searchAdvanced"
      @click.prevent="toggleModal"
      :class="{'color-highlight':true === highlighted}"
      class="btn--blank o-link--default inline-block u-m-0 u-p-0 u-ml-0_5">
      {{ Translator.trans('search.advanced') }}
    </button>

    <!-- Modal content -->
    <dp-modal
      ref="searchModal"
      content-classes="u-4-of-8-wide u-2-of-3-desk-down"
      @modal:toggled="modalToggled">
      <h2>{{ Translator.trans('search.advanced') }}</h2>

      <!-- Search Field -->
      <label class="layout__item u-pl-0 u-mb-0_25 u-mt-0_75 relative">
        <dp-input
          id="searchterm2"
          name="search_word"
          data-cy="searchModal:searchAssessmentTableAdvanced"
          :placeholder="Translator.trans('searchterm')"
          v-model="searchString"
          :aria-label="Translator.trans('search.assessment.table')"
          @enter="submit" />
      </label>

      <!-- search hint -->
      <p class="lbl__hint u-pt-0 u-mt-0 u-pb-0_5">
        {{ Translator.trans('assessmenttable.searchfield.characters') }}
      </p>

      <!-- Search options and special characters -->
      <div class="space-stack-s">
        <dp-details
          v-for="(explanation, index) in explanations"
          :key="index"
          :summary="explanation.title">
          <span v-html="explanation.description" />
        </dp-details>
      </div>

      <!-- Checkboxes -->
      <h3 class="u-mt-0_25">
        {{ Translator.trans('search.in') }}
      </h3>

      <div class="max-h-12 w-full align-top overflow-auto u-mb">
        <div class="layout--flush">
          <dp-checkbox
            v-for="checkbox in filterCheckBoxesItems"
            :data-cy="`searchModal:${checkbox.id}`"
            :id="checkbox.id"
            :key="'checkbox_' + checkbox.id"
            v-model="checkbox.checked"
            class="layout__item u-1-of-2"
            :label="{
              text: Translator.trans(checkbox.label)
            }"
            name="search_fields[]" />

          <!-- department is added as hidden field when organisation is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('oName') && hasPermission('feature_institution_participation')"
            name="search_fields[]"
            value="dName"
            checked="checked">
          <!-- last name is added as hidden field if submitter is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('uName')"
            name="search_fields[]"
            value="meta_submitLastName"
            checked="checked">
          <!-- sachbearbeiter is added as hidden field if submitter is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('uName')"
            name="search_fields[]"
            value="meta_caseWorkerLastName"
            checked="checked">
          <!-- group name is added as hidden field if submitter is selected - this is probably the author of the head statement (so the main STN in cluster) -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('uName')"
            name="search_fields[]"
            value="cluster_uName"
            checked="checked">
          <!-- paragraph is added as hidden field if document is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('documentTitle')"
            name="search_fields[]"
            value="paragraphTitle"
            checked="checked">
          <!-- element title is added as hidden field if document is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('documentTitle')"
            name="search_fields[]"
            value="elementTitle"
            checked="checked">
          <!-- public/external id of group is added as hidden field if statement id is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('externId')"
            name="search_fields[]"
            value="cluster_externId"
            checked="checked">
          <!-- counties is added as hidden field if municipalities is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('municipalityNames') && hasPermission('field_statement_municipality')"
            name="search_fields[]"
            value="countyNames"
            checked="checked">
          <!-- tags is added as hidden field if topics is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('topicNames') && hasPermission('feature_statements_tag') || hasPermission('feature_statement_fragments_tag')"
            name="search_fields[]"
            value="tagNames"
            checked="checked"
            aria-hidden="true">
          <!-- fragment consideration is added as hidden field if consideration is selected -->
          <input
            class="hidden"
            type="hidden"
            v-if="selectedFields.includes('recommendation')"
            name="search_fields[]"
            value="fragments_consideration"
            checked="checked">
        </div>
      </div>

      <!-- Button row -->
      <div class="text-right">
        <button
          class="btn btn--primary u-mr"
          type="button"
          data-cy="searchModal:submitSearchAdvanced"
          @click="submit">
          {{ Translator.trans('apply') }}
        </button><!--

     --><button
          class="btn btn--secondary"
          data-cy="searchModal:resetSearchAdvanced"
          @click.prevent="reset">
          {{ Translator.trans('reset') }}
        </button>
      </div>
    </dp-modal>
  </div>
</template>

<script>
import { CleanHtml, DpCheckbox, DpDetails, DpInput, DpModal, hasAnyPermissions } from '@demos-europe/demosplan-ui'
import availableFilterFields from '../../listStatements/availableFilterFields.json'
import { mapMutations } from 'vuex'

export default {
  name: 'SearchModal',

  components: {
    DpCheckbox,
    DpDetails,
    DpModal,
    DpInput
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    searchInFields: {
      required: false,
      type: Array,
      default: () => [
        'authorName',
        'clusterName',
        'consideration',
        'department',
        'fragmentText',
        'internId',
        'memo',
        'municipalitiesNames',
        'orgaCity',
        'organisationName',
        'orgaPostalCode',
        'planDocument',
        'potentialAreas',
        'statementId',
        'statementText',
        'topics',
        'typeOfSubmission',
        'voters'
      ]
    },

    preselectedFields: {
      required: false,
      type: Array,
      default: () => []
    },

    preselectedExactSearch: {
      type: Boolean,
      default: false
    },

    // Search string entered in the search field
    tableSearch: {
      required: false,
      type: String,
      default: ''
    },

    isForm: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  emits: [
    'close',
    'search'
  ],

  data () {
    return {
      exactSearch: this.preselectedExactSearch,
      searchString: this.tableSearch,
      isOpenModal: false,
      explanations: [
        {
          title: Translator.trans('search.options'),
          description: Translator.trans('search.options.description')
        },
        {
          title: Translator.trans('search.special.characters'),
          description: Translator.trans('search.special.characters.description')
        }
      ],
      availableFilterFields
    }
  },

  computed: {
    filterCheckBoxesItems () {
      return this.availableFilterFields.filter(checkbox => {
        const allowedToShow = typeof checkbox.permissions === 'undefined' || hasAnyPermissions(checkbox.permissions)
        const showInView = this.searchInFields.includes(checkbox.id)
        return allowedToShow && showInView
      })
    },

    highlighted () {
      return (this.selectedFields.length > 0 || this.searchString.length > 0)
    },

    placeholder () {
      return this.isOpenModal === false ? Translator.trans('searchterm') : ''
    },

    selectedFields () {
      return this.availableFilterFields.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value)
    }
  },

  methods: {
    ...mapMutations('Filter', ['setCurrentSearch']),

    loadSelectedCheckboxes () {
      const savedCheckboxes = JSON.parse(localStorage.getItem('selectedCheckboxes'))

      if (savedCheckboxes) {
        this.availableFilterFields.forEach(checkbox => {
          const savedCheckbox = savedCheckboxes.find(savedCheckbox => savedCheckbox.id === checkbox.id)

          if (savedCheckbox) {
            checkbox.checked = savedCheckbox.checked
          }
        })
      }
    },

    toggleModal () {
      this.$refs.searchModal.toggle()
    },

    modalToggled (isOpenModal) {
      this.isOpenModal = isOpenModal

      if (!isOpenModal) {
        this.$emit('close')
      }
    },

    reset () {
      this.searchString = ''
      this.availableFilterFields.forEach(checkbox => {
        checkbox.checked = false
      })
      localStorage.removeItem('selectedCheckboxes')
    },

    saveSelectedCheckboxes () {
      const selectedCheckboxes = this.filterCheckBoxesItems.map(checkbox => ({
        id: checkbox.id,
        checked: checkbox.checked
      }))

      localStorage.setItem('selectedCheckboxes', JSON.stringify(selectedCheckboxes))
    },

    submit (event) {
      if (this.isForm) {
        const searchWordInput = document.querySelector('input[name="search_word2"]')
        searchWordInput.value = this.searchString
        window.submitForm(event, 'search')
      } else {
        this.$emit('search', this.searchString, this.selectedFields)
        if (this.isOpenModal) {
          this.toggleModal()
        }
      }

      this.saveSelectedCheckboxes()
    }
  },

  created () {
    if (this.isForm) {
      this.setCurrentSearch(this.tableSearch)
    }
  },

  mounted () {
    this.availableFilterFields.forEach(checkbox => {
      checkbox.checked = this.preselectedFields.includes(checkbox.id)
    })

    this.loadSelectedCheckboxes()
  }
}
</script>
