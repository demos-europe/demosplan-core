<template>
  <div>
    <dp-search-field
      data-cy="customSearchStatements:search"
      :placeholder="Translator.trans('searchterm')"
      @search="term => handleSearch(term)"
      @reset="$emit('reset')">
      <template v-slot:default>
        <dp-flyout
          align="left"
          data-cy="customSearch:searchCustomLimitFields"
          class="top-px right-0 absolute"
          :has-menu="false"
          :padded="false">
          <template v-slot:trigger>
            <dp-icon
              icon="settings" />
          </template>
          <div class="space-stack-s space-inset-s w-14">
            <h2>{{ Translator.trans('search.advanced') }}</h2>

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
            <div class="layout--flush">
              <dp-checkbox
                v-for="checkbox in filterCheckBoxesItems"
                :data-cy="`searchModal:${checkbox.id}`"
                :id="checkbox.id"
                :key="'checkbox_' + checkbox.id"
                v-model="checkbox.checked"
                class="layout__item u-1-of-2"
                :label="{ text: Translator.trans(checkbox.label) }"
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

            <!-- Button row -->
            <div class="text-right">
              <button
                class="btn btn--primary u-mr"
                type="button"
                data-cy="searchModal:submitSearchAdvanced"
                @click="submit">
                {{ Translator.trans('apply') }}
              </button>
              <button
                class="btn btn--secondary"
                data-cy="searchModal:resetSearchAdvanced"
                @click.prevent="reset">
                {{ Translator.trans('reset') }}
              </button>
            </div>
          </div>
        </dp-flyout>
      </template>
    </dp-search-field>
  </div>
</template>
<script>

import {
  DpCheckbox,
  DpDetails,
  DpFlyout,
  DpIcon,
  DpSearchField,
  hasAnyPermissions
} from '@demos-europe/demosplan-ui'
import availableFilterFields from './availableFilterFields.json'

export default {
  name: 'CustomSearchStatements',
  components: {
    DpCheckbox,
    DpDetails,
    DpFlyout,
    DpIcon,
    DpSearchField
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
    }
  },

  data: () => ({
    currentSearchTerm: '',
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
  }),

  computed: {
    filterCheckBoxesItems () {
      return this.availableFilterFields.filter(checkbox => {
        const allowedToShow = typeof checkbox.permissions === 'undefined' || hasAnyPermissions(checkbox.permissions)
        const showInView = this.searchInFields.includes(checkbox.id)
        return allowedToShow && showInView
      })
    },
    selectedFields () {
      return this.availableFilterFields.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value)
    }
  },

  methods: {
    handleSearch (term) {
      this.currentSearchTerm = term
      this.$emit('search', this.currentSearchTerm)
    },

    reset () {
      this.currentSearchTerm = ''
      this.$emit('reset')
      this.availableFilterFields.forEach(checkbox => {
        checkbox.checked = false
      })
      localStorage.removeItem('selectedCheckboxes')
    },

    submit () {
      // Add logic for submit action
      this.$emit('submit', this.selectedFields)
    }
  }
}
</script>
