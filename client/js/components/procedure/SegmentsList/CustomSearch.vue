<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="flex space-inline-s">
    <div class="relative">
      <dp-search-field
        data-cy="customSearch:currentSearchTerm"
        :placeholder="Translator.trans('searchterm')"
        @search="term => handleSearch(term)"
        @reset="$emit('reset')">
        <dp-flyout
          align="left"
          data-cy="customSearch:searchCustomLimitFields"
          class="top-px right-0 absolute"
          :has-menu="false"
          :padded="false">
          <template #trigger>
            <dp-icon
              :class="{ 'color-message-severe-fill': selectedFields.length > 0 }"
              icon="settings" />
          </template>
          <!-- Checkboxes to specify in which fields to search -->
          <div class="space-stack-s space-inset-s w-14">
            <div class="flex">
            <span
              class="weight--bold"
              v-text="Translator.trans('search.custom.limit_fields')" />
              <button
                class="btn--blank o-link--default ml-auto"
                data-cy="customSearch:searchCustomToggleAll"
                v-text="Translator.trans('toggle_all')"
                @click="toggleAllFields(selectedFields.length < fields.length)" />
            </div>
            <div
              class="o-list--col-3"
              v-if="isLoading === false">
              <dp-checkbox
                v-for="({label, value}, i) in fields"
                :data-cy="'customSearch:' + value"
                :id="value"
                :key="i"
                :checked="selectedFields.includes(value)"
                :label="{
                text: Translator.trans(label)
              }"
                @change="handleChange(value, !selectedFields.includes(value))" />
            </div>
            <div
              class="font-size-small"
              v-text="Translator.trans('search.custom.explanation')" />
          </div>
          <hr class="border--top u-m-0">
          <!-- Explanation of search options and special characters -->
          <div
            class="space-stack-xs space-inset-s w-14 overflow-y-auto"
            :style="maxHeight">
            <dp-details
              v-for="explanation in explanations"
              :key="explanation.title"
              :summary="explanation.title"
              :data-cy="explanation.dataCy">
              <span v-html="explanation.description" />
            </dp-details>
          </div>
        </dp-flyout>
      </dp-search-field>
    </div>
  </div>
</template>

<script>
import {
  checkResponse,
  DpCheckbox,
  DpDetails,
  DpFlyout,
  DpIcon,
  dpRpc,
  DpSearchField,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import lscache from 'lscache'

export default {
  name: 'CustomSearch',

  components: {
    DpCheckbox,
    DpDetails,
    DpFlyout,
    DpIcon,
    DpSearchField
  },

  props: {
    elasticsearchFieldDefinition: {
      required: true,
      type: Object,
      /**
       * Object must contain 3 special props which must not be an empty string.
       * @param obj
       */
      validator: (obj) => {
        return ['entity', 'function', 'accessGroup'].every((prop) => {
          return hasOwnProp(obj, prop) && obj[prop] !== ''
        })
      }
    },

    /**
     * The id is used for the input element.
     */
    id: {
      type: String,
      required: true
    },

    /**
     * Which key is used when storing current selection.
     * If omitted, the selection is not stored at all.
     */
    localStorageKey: {
      type: String,
      required: false,
      default: ''
    },

    searchTerm: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentSearchTerm: this.searchTerm,
      fields: [],
      isLoading: true,
      explanations: [
        {
          title: Translator.trans('search.options'),
          dataCy: 'searchOptions',
          description: Translator.trans('search.options.description')
        },
        {
          title: Translator.trans('search.special.characters'),
          dataCy: 'searchSpecialCharacters',
          description: Translator.trans('search.special.characters.description')
        }
      ],
      maxHeight: null,
      selectedFields: []
    }
  },

  computed: {
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
    },

    handleSearch (term) {
      this.currentSearchTerm = term
      this.$emit('search', this.currentSearchTerm)
    },

    initializeStoredSelection () {
      this.selectedFields = lscache.get(this.localStorageKey)
    },

    reset () {
      this.currentSearchTerm = ''
      this.toggleAllFields(false)
    },

    /**
     * Set the fields that may be searched in for a given entity.
     */
    setFields () {
      dpRpc('elasticsearchFieldDefinition.provide', this.elasticsearchFieldDefinition)
        .then(checkResponse)
        .then((response) => {
          const fields = response[0].result
          // The response has to be transformed as the rpc sends the ids as keys.
          this.fields = Object.keys(fields).map((field) => {
            return {
              label: fields[field],
              value: field
            }
          })
          this.isLoading = false
        })
        .catch(() => {
          console.log('error')
        })
    },

    setMaxHeight () {
      const offsetTop = this.$el.getBoundingClientRect().top + document.documentElement.scrollTop
      this.maxHeight = `max-height: calc(100vh - ${offsetTop + 80}px);`
    },

    toggleAllFields (selectAll) {
      this.fields.forEach(({ value: field }) => this.toggleField(field, selectAll))
      this.broadcastChanges()
    },

    // Check or uncheck single field. To prevent duplication, the array is changed into a Set on the fly.
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
  },

  mounted () {
    this.storeSelection && this.initializeStoredSelection()
    this.setMaxHeight()
    this.setFields()

    // Emit selection in case there was something stored (if storage is enabled).
    this.storeSelection && this.$emit('changeFields', this.selectedFields)
  }
}
</script>
