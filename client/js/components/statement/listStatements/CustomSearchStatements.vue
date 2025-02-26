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
            <dp-icon icon="settings" />
          </template>
          <div class="space-stack-s space-inset-s w-14">
            <div class="flex">
                <span
                  class="weight--bold"
                  v-text="Translator.trans('search.custom.limit_fields')" />
              <button
                class="btn--blank o-link--default ml-auto"
                data-cy="customSearch:searchCustomToggleAll"
                v-text="Translator.trans('toggle_all')"
                @click="toggleAllFields(selectedFields.length < filterCheckBoxesItems.length)" />
            </div>

            <!-- Checkboxes -->
            <div class="layout--flush">
              <dp-checkbox
                v-for="({label, value}, i) in filterCheckBoxesItems"
                :data-cy="`searchModal:${value}`"
                :id="'filteredCheckbox' + i"
                :key="i"
                :checked="selectedFields.includes(value)"
                class="layout__item u-1-of-2"
                :label="{ text: Translator.trans(label) }"
                @change="handleChange(value, !selectedFields.includes(value))" />

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
                checked="checked">
              <!-- fragment consideration is added as hidden field if consideration is selected -->
              <input
                class="hidden"
                type="hidden"
                v-if="selectedFields.includes('recommendation')"
                name="search_fields[]"
                value="fragments_consideration"
                checked="checked">
            </div>

            <!-- Search options and special characters -->
            <div class="space-stack-s">
              <hr class="border--top u-m-0">
              <dp-details
                v-for="(explanation, index) in explanations"
                :key="index"
                :summary="explanation.title">
                <span v-html="explanation.description" />
              </dp-details>
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
import lscache from 'lscache'

const fields = [
  'authorName',
  'department',
  'internId',
  'memo',
  'municipalitiesNames',
  'orgaCity',
  'organisationName',
  'orgaPostalCode',
  'statementId',
  'statementText',
  'typeOfSubmission']

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
    /**
     * Which key is used when storing current selection.
     * If omitted, the selection is not stored at all.
     */
    localStorageKey: {
      type: String,
      required: false,
      default: ''
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
    availableFilterFields,
    fields,
    selectedFields: []
  }),

  computed: {
    filterCheckBoxesItems () {
      return this.availableFilterFields.filter(checkbox => {
        const allowedToShow = typeof checkbox.permissions === 'undefined' || hasAnyPermissions(checkbox.permissions)
        const showInView = fields.includes(checkbox.id)
        return allowedToShow && showInView
      })
    },
    storeSelection () {
      return this.localStorageKey !== ''
    }
  },

  methods: {
    broadcastChanges () {
      this.storeSelection && lscache.set(this.localStorageKey, this.selectedFields)
      this.$emit('changeFields', this.selectedFields)
    },

    handleChange (field, selected = null) {
      this.toggleField(field, selected)
      this.broadcastChanges()
      this.handleSearch(this.currentSearchTerm)
    },

    handleSearch (term) {
      this.currentSearchTerm = term
      this.$emit('search', this.currentSearchTerm)
    },

    // Check or uncheck single field. To prevent duplication, the array is changed into a Set on the fly.
    toggleAllFields (selectAll) {
      this.filterCheckBoxesItems.forEach(({ value: field }) => this.toggleField(field, selectAll))
      this.broadcastChanges()
    },

    toggleField (field, selectField) {
      if (selectField === true) {
        const set = new Set(this.selectedFields)
        set.add(field)
        this.selectedFields = [...set]
      } else if (selectField === false) {
        const set = new Set(this.selectedFields)
        set.delete(field)
        this.selectedFields = [...set]
      }
    }
  }
}
</script>
