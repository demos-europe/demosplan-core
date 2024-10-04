<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="false === hidden && (!shouldCheckPermissionOnFileFilter || hasPermission('feature_statement_file_filter_set'))"
    :data-cy="filterItem.attributes.label">
    <label
      :for="filterItem.id"
      class="layout__item u-1-of-3 u-pl-0 text-right">
      <dp-loading
        v-if="isUpdating"
        hide-label
        class="inline-block u-mr-0_5" />
      {{ filterItem.attributes.label }}
    </label><!--

     --><div class="layout__item u-2-of-3">
          <dp-multiselect
            :id="filterItem.id"
            :close-on-select="false"
            :data-cy="filterItem.attributes.name"
            label="label"
            :loading="isLoading"
            multiple
            :name="filterItem.attributes.name + '_multiselect'"
            :options="availableOptions"
            selection-controls
            track-by="label"
            :value="selected"
            @close="updateFilterOptions"
            @open="loadFilterOptions"
            @remove="removeFilterOption"
            @select="selectFilterOption">
            <!-- selected options -->
            <template v-slot:tag="{ props }">
              <span
                class="multiselect__tag"
                :data-cy="'tag-' + generateDataCy(filterItem.attributes.name, props.option.label)">
                <span>
                  {{ props.option.label }}
                  <template v-if="'fragment' !== filterGroup.type">
                    ({{ props.option.count }})
                  </template>
                </span>
                <i
                  aria-hidden="true"
                  class="multiselect__tag-icon"
                  tabindex="1"
                  @click="props.remove(props.option)" />
              </span>
            </template>

            <!-- sorting -->
            <!-- don't remove slot-scope=props, without it, the button won't be displayed -->
            <template
              v-if="'fragment' !== filterGroup.type"
              v-slot:beforeList>
              <li>
                <button
                  type="button"
                  @click="toggleSorting(filterItem.id)"
                  v-cleanhtml="sortingLabel"
                  class="btn--blank o-link--default" />
              </li>
            </template>

            <!-- selectable options -->
            <template
              v-slot:option="{ props }">
              <span :data-cy="'option-' + generateDataCy(filterItem.attributes.name, props.option.label)">
                {{ props.option.label }}
              </span>
              <template v-if="'fragment' !== filterGroup.type">
                ({{ props.option.count }})
              </template>
            </template>
          </dp-multiselect>
        </div>
  </div>

  <!-- hidden select -->
  <select
    v-else-if="hidden && filteredSelectedOptions.length > 0"
    :id="filterItem.attributes.name+ '[]'"
    :name="filterItem.attributes.name + '[]'"
    multiple
    style="display: none">
    <option
      v-for="(option, idx) in filteredSelectedOptions"
      :key="idx"
      :value="option.value"
      selected>
      {{ option.label }}
    </option>
  </select>
</template>

<script>
import { CleanHtml, DpLoading, DpMultiselect } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'

export default {
  name: 'DpFilterModalSelectItem',

  components: {
    DpLoading,
    DpMultiselect
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    appliedFilterOptions: {
      required: false,
      type: Array,
      default: () => []
    },

    // Tab that selectItem is displayed in
    filterGroup: {
      required: true,
      type: Object
    },

    filterItem: {
      required: true,
      type: Object
    },

    hidden: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      isUpdating: false,
      sortingType: 'count',
      isInitialLoad: true,
      isLoading: false,
      // All available options for this filter
      availableOptions: [],
      selected: []
    }
  },

  computed: {
    ...mapGetters('Filter', {
      // Available options for current filter
      getFilterOptionsByFilter: 'filterOptionsByFilter',
      // Selected options for current filter
      selectedFilterOptionsFromStore: 'selectedFilterOptionsByFilter',
      optionsForFilterHash: 'allSelectedFilterOptionsWithFilterName'
    }),

    ...mapState('Filter', [
      'original',
      'procedureId',
      'selectedOptions'
    ]),

    filteredSelectedOptions () {
      return this.selectedFilterOptionsFromStore(this.filterItem.id)
    },

    shouldCheckPermissionOnFileFilter () {
      return this.filterItem.attributes.label === 'Datei'
    },

    sortingLabel () {
      if (this.sortingType === 'count') {
        return '<i aria-hidden="true" class="fa fa-sort-numeric-desc u-pr-0_25"></i>' + Translator.trans('sort.count.desc')
      } else {
        return '<i aria-hidden="true" class="fa fa-sort-alpha-asc u-pr-0_25"></i>' + Translator.trans('sort.alphabet.asc')
      }
    },

    shouldCheckPermissionOnFileFilter () {
      return this.filterItem.attributes.label === 'Datei'
    }
  },

  watch: {
    filteredSelectedOptions: function () {
      this.selected = this.filteredSelectedOptions
    }
  },

  methods: {
    ...mapActions('Filter', [
      'getFilterOptionsAction'
    ]),

    ...mapMutations('Filter', [
      'sortFilterOptions',
      'updateSelectedOptions'
    ]),

    generateDataCy (name, option) {
      return name + '-' + option.replace(/\s+/g, '-').toLowerCase()
    },

    /**
     * Called when opening filter dropdown
     * Prepare currently selected options to send via updateFilterHash, update filterHash, get updated options from BE
     */
    loadFilterOptions () {
      // Used in DpFilterModal to disable submit-button while updating
      this.$emit('updating-filters')
      this.isLoading = true

      const optionsForFilterHash = this.prepareOptionsForFilterHash()

      window.updateFilterHash(this.procedureId, optionsForFilterHash)
        .then((filterHash) => {
          // Get filter options for current filter
          this.getFilterOptionsAction({ filterHash: filterHash, filterId: this.filterItem.id })
            .then(() => {
              this.availableOptions = this.getFilterOptionsByFilter(this.filterItem.id)
              if (this.isInitialLoad) {
                this.sortFilterOptions({ id: this.filterItem.id, sortingType: 'alphabetic' })
                this.isInitialLoad = false
              }
              // Used in DpFilterModal to enable submit-button after updating
              this.$emit('updated-filters')
              this.isLoading = false
            })
        })
    },

    /**
     * For updating the filterHash, for each selected option we need an object containing input name and value
     */
    prepareOptionsForFilterHash () {
      // Get all selected filter options from store - in the format needed to update the filterHash
      const currentlySelected = this.optionsForFilterHash

      // For the currently opened filter item, we always want to send the value '' to receive all options
      const optionsToSend = [{ name: this.filterItem.attributes.name + '[]', value: '' }]

      if (currentlySelected.length !== 0) {
        currentlySelected.forEach(option => {
          if (option.name !== this.filterItem.attributes.name + '[]') {
            optionsToSend.push(option)
          }
        })
      }

      return optionsToSend
    },

    removeFilterOption (option) {
      /*
       *  If nothing is chosen (multiselect is empty), the value is undefined and throws errors,
       *  that is why we have to do this validation
       */
      if (undefined === option) {
        return
      }

      // Used in DpFilterModal to disable submit-button while updating
      this.$emit('updating-filters')

      // Remove option from selectedOptions in store
      this.updateSelectedOptions({ selectedOption: option, filterId: this.filterItem.id })

      // Used in DpFilterModal to update filterHash and get all selected options from store
      this.$emit('update-selected', this.filterItem.id)
    },

    // @select of filter dropdown
    selectFilterOption (option) {
      if (this.selected.indexOf(option) < 0) {
        this.selected.push(option)
        this.updateSelectedOptions({ selectedOption: option, filterId: this.filterItem.id })
      }
    },

    // @close of filter dropdown
    updateFilterOptions () {
      this.isLoading = true

      // Used in DpFilterModal to disable submit-button while updating
      this.$emit('updating-filters')

      /*
       * Used in DpFilterModal to update the filterHash with all selected options; DpFilterModal then emits the filterHash
       * and gets updated filterOptions for the selected filters, which are then loaded from the store into this.availableOptions
       */
      this.$emit('update-selected')
    },

    toggleSorting (id) {
      // Sort options in store
      this.sortFilterOptions({ id: id, sortingType: this.sortingType })
      // Get sorted options from store
      this.availableOptions = this.getFilterOptionsByFilter(this.filterItem.id)
      this.sortingType = this.sortingType === 'count' ? 'alphabetic' : 'count'
    }
  },

  mounted () {
    /*
     * To make each filter render its selected items after the filterModal opens,
     * the logic that is also applied inside the watch() is executed here, too.
     * Otherwise, selected filters are not shown.
     */
    this.selected = this.filteredSelectedOptions

    /*
     * Emitted by DpFilterModal after it updates filterHash after filterOption has been selected
     * after the filterHash is updated, we get the updated options from the store
     */
    this.$root.$on('selected-updated', () => {
      this.isLoading = false
      this.availableOptions = this.getFilterOptionsByFilter(this.filterItem.id)
    })
  }
}
</script>
