<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="sm:relative flex flex-col sm:flex-row flex-wrap space-x-1 space-x-reverse space-y-1 col-span-1 sm:col-span-7 ml-0 pl-0 sm:ml-2 sm:pl-[38px]">
    <div class="sm:absolute sm:top-0 sm:left-0 mt-1">
      <dp-flyout
        align="left"
        :aria-label="Translator.trans('filters.more')"
        class="bg-surface-medium rounded pb-1 pt-[4px]"
        data-cy="dpAddOrganisationList:filterCategories">
        <template v-slot:trigger>
                <span :title="Translator.trans('filters.more')">
                  <dp-icon
                    aria-hidden="true"
                    class="inline"
                    icon="faders" />
                </span>
        </template>
        <!-- 'More filters' flyout -->
        <div>
          <button
            class="btn--blank o-link--default ml-auto"
            data-cy="dpAddOrganisationList:toggleFilterCategories"
            v-text="Translator.trans('toggle_all')"
            @click="filterManager.toggleAllCategories" />
          <div v-if="!isLoading">
            <dp-checkbox
              v-for="category in filterCategories"
              :key="category.id"
              :id="`filterCategorySelect:${category.label}`"
              :checked="selectedFilterCategories.includes(category.label)"
              :data-cy="`dpAddOrganisationList:filterCategoriesSelect:${category.label}`"
              :disabled="filterCategoryHelpers.checkIfDisabled(appliedFilterQuery, category.id)"
              :label="{
                      text: `${category.label} (${filterCategoryHelpers.getSelectedOptionsCount(appliedFilterQuery, category.id)})`
                    }"
              @change="filterManager.handleChange(category.label, !selectedFilterCategories.includes(category.label))" />
          </div>
        </div>
      </dp-flyout>
    </div>

    <filter-flyout
      v-for="category in filterCategoriesToBeDisplayed"
      :key="`filter_${category.label}`"
      ref="filterFlyout"
      :category="{ id: category.id, label: category.label }"
      class="inline-block"
      :data-cy="`dpAddOrganisationList:${category.label}`"
      :initial-query-ids="queryIds"
      :member-of="category.memberOf"
      :operator="category.comparisonOperator"
      :path="category.rootPath"
      @filterApply="(filtersToBeApplied) => filterManager.applyFilter(filtersToBeApplied, category.id)"
      @filterOptions:request="(params) => filterManager.createFilterOptions({ ...params, categoryId: category.id})" />
  </div>

  <dp-button
    class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
    data-cy="dpAddOrganisationList:resetFilter"
    :disabled="!isQueryApplied"
    :text="Translator.trans('reset')"
    variant="outline"
    v-tooltip="Translator.trans('search.filter.reset')"
    @click="filterManager.reset" />
</template>

<script>
import { DpButton, DpCheckbox, DpIcon, DpFlyout } from '@demos-europe/demosplan-ui'
import { filterCategoryHelpers } from '@DpJs/lib/procedure/FilterFlyout/filterHelpers'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout.vue'

export default {
  name: 'ClientSideTagFilter',

  components: {
    DpButton,
    DpCheckbox,
    DpIcon,
    DpFlyout,
    FilterFlyout
  },

  setup () {
    return {
      filterCategoryHelpers
    }
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },
    filterCategories: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      filterManager: filterCategoryHelpers.createFilterManager(this),
      initiallySelectedFilterCategories: [],
      isLoading: false
    }
  },

  computed: {
    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    filterCategoriesToBeDisplayed () {
      return (this.filterCategories || [])
        .filter(filter =>
          this.currentlySelectedFilterCategories.includes(filter.label))
    },

    queryIds () {
      if (Object.keys(this.appliedFilterQuery).length === 0) {
        return []
      }
      return Object.values(this.appliedFilterQuery)
        .filter(el => el && el.condition && el.condition.value)
        .map(el => el.condition.value)
    },

    isQueryApplied () {
      return Object.keys(this.appliedFilterQuery).length > 0
    }
  }

}
</script>
