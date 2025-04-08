<template>
  <dp-search-field
    data-cy="customSearchStatements:search"
    :placeholder="Translator.trans('searchterm')"
    @reset="$emit('reset')"
    @search="term => handleSearch(term)">
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
              @click="toggleAllFields(selectedFields.length < filterCheckBoxesItems.length)" />
          </div>

          <!-- Checkboxes -->
          <div class="layout--flush">
            <dp-checkbox
              v-for="({label, value}, i) in filterCheckBoxesItems"
              :checked="selectedFields.includes(value)"
              class="layout__item u-1-of-2"
              :data-cy="`searchModal:${value}`"
              :id="'filteredCheckbox' + i"
              :key="i"
              :label="{ text: Translator.trans(label) }"
              @change="handleChange(value, !selectedFields.includes(value))" />
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

  emits: [
    'changeFields',
    'reset',
    'search'
  ],

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

    toggleAllFields (selectAll) {
      this.selectedFields = selectAll ? this.filterCheckBoxesItems.map(({ value }) => value) : []
      this.broadcastChanges()
    },

    // Check or uncheck single field. To prevent duplication, the array is checked for the field.
    toggleField (field, selectField) {
      if (selectField && !this.selectedFields.includes(field)) {
        this.selectedFields.push(field)
      } else if (!selectField) {
        this.selectedFields = this.selectedFields.filter(f => f !== field)
      }
    }
  }
}
</script>
