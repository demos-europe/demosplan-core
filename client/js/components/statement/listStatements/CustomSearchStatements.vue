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
          class="top-px right-0 absolute"
          data-cy="customSearch:searchCustomLimitFields"
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
                @click="toggleAllFields(selectedFields.size < filterCheckBoxesItems.length)" />
            </div>

            <!-- Checkboxes -->
            <div class="layout--flush">
              <dp-checkbox
                v-for="({label, value}, i) in filterCheckBoxesItems"
                :checked="selectedFields.has(value)"
                class="layout__item u-1-of-2"
                :data-cy="`searchModal:${value}`"
                :id="'filteredCheckbox' + i"
                :key="i"
                :label="{ text: Translator.trans(label) }"
                @change="handleChange(value, !selectedFields.has(value))" />

              <!-- department is added as hidden field when organisation is selected -->
              <input
                v-if="selectedFields.has('oName') && hasPermission('feature_institution_participation')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="dName">
              <!-- last name is added as hidden field if submitter is selected -->
              <input
                v-if="selectedFields.has('uName')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="meta_submitLastName">
              <!-- sachbearbeiter is added as hidden field if submitter is selected -->
              <input
                v-if="selectedFields.has('uName')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="meta_caseWorkerLastName">
              <!-- group name is added as hidden field if submitter is selected - this is probably the author of the head statement (so the main STN in cluster) -->
              <input
                v-if="selectedFields.has('uName')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="cluster_uName">
              <!-- paragraph is added as hidden field if document is selected -->
              <input
                v-if="selectedFields.has('documentTitle')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="paragraphTitle">
              <!-- element title is added as hidden field if document is selected -->
              <input
                v-if="selectedFields.has('documentTitle')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="elementTitle">
              <!-- public/external id of group is added as hidden field if statement id is selected -->
              <input
                v-if="selectedFields.has('externId')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="cluster_externId">
              <!-- counties is added as hidden field if municipalities is selected -->
              <input
                v-if="selectedFields.has('municipalityNames') && hasPermission('field_statement_municipality')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="countyNames">
              <!-- tags is added as hidden field if topics is selected -->
              <input
                v-if="selectedFields.has('topicNames') && hasPermission('feature_statements_tag') || hasPermission('feature_statement_fragments_tag')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="tagNames">
              <!-- fragment consideration is added as hidden field if consideration is selected -->
              <input
                v-if="selectedFields.has('recommendation')"
                checked="checked"
                class="hidden"
                name="search_fields[]"
                type="hidden"
                value="fragments_consideration">
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
    selectedFields: new Set()
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
      const selectedFieldsArray =  Array.from(this.selectedFields)

      this.storeSelection && lscache.set(this.localStorageKey, selectedFieldsArray)
      this.$emit('changeFields', selectedFieldsArray)
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

    toggleAllFields (selectAll) {
      this.filterCheckBoxesItems.forEach(({ value: field }) => this.toggleField(field, selectAll))
      this.broadcastChanges()
    },

    toggleField (field, selectField) {
      if (selectField === true) {
        this.selectedFields.add(field)
      } else if (selectField === false) {
        this.selectedFields.delete(field)
      }
    }
  }
}
</script>
