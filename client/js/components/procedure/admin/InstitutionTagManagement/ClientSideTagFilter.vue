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
            @click="toggleAllCategories" />
          <div v-if="!isLoading">
            <dp-checkbox
              v-for="category in filterCategories"
              :key="category.id"
              :id="`filterCategorySelect:${category.label}`"
              :checked="selectedFilterCategories.includes(category.label)"
              :data-cy="`dpAddOrganisationList:filterCategoriesSelect:${category.label}`"
              :disabled="checkIfDisabled(appliedFilterQuery, category.id)"
              :label="{
                      text: `${category.label} (${getSelectedOptionsCount(appliedFilterQuery, category.id)})`
                    }"
              @change="handleChange(category.label, !selectedFilterCategories.includes(category.label))" />
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
      @filterApply="(filtersToBeApplied) => applyFilter(filtersToBeApplied, category.id)"
      @filterOptions:request="(params) => createFilterOptions({ ...params, categoryId: category.id})" />
  </div>

  <dp-button
    class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
    data-cy="dpAddOrganisationList:resetFilter"
    :disabled="!isQueryApplied"
    :text="Translator.trans('reset')"
    variant="outline"
    v-tooltip="Translator.trans('search.filter.reset')"
    @click="reset" />
</template>

<script>
import { DpButton, DpCheckbox, DpIcon, DpFlyout } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState} from 'vuex'
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

  props: {
    filterCategories: {
      type: Array,
      required: true
    },

    rawItems: {
      type: Array,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      initiallySelectedFilterCategories: [],
      isLoading: false
    }
  },

  computed: {
    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items'
    }),

    isQueryApplied () {
      return this.currentlySelectedFilterCategories.length > 0
    },

    filterAndEmitItems() {
      console.log('Debug rawItems:', this.rawItems)
      console.log('Debug rawItems type:', typeof this.rawItems)

      if (!this.rawItems) {
        console.error('rawItems is undefined!')
        return
      }

      let filteredItems = this.rawItems

      // Wenn Filter angewendet sind
      if (Object.keys(this.appliedFilterQuery).length > 0) {
        filteredItems = this.rawItems.filter(item => {
          // Prüfe ob Item die Filter erfüllt
          return Object.values(this.appliedFilterQuery).every(filterCondition => {
            if (!filterCondition.condition) return true

            const tagIds = item.assignedTags?.map(tag => tag.id) || []
            return tagIds.includes(filterCondition.condition.value)
          })
        })
      }

      console.log('Filtered items:', filteredItems.length, 'of', this.rawItems.length)
      this.$emit('items-filtered', filteredItems)
    },

    filterCategoriesToBeDisplayed () {
      return (this.filterCategories || [])
        .filter(filter =>
          this.currentlySelectedFilterCategories.includes(filter.label))
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    queryIds () {
      if (Object.keys(this.appliedFilterQuery).length === 0) {
        return []
      }
      return Object.values(this.appliedFilterQuery)
        .filter(el => el && el.condition && el.condition.value)
        .map(el => el.condition.value)
    }
  },

  watch: {
    appliedFilterQuery: {
      handler() {
        const result = this.filterAndEmitItems
      },
      deep: true,
      immediate: true
    }
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    applyFilter (filter, categoryId) {
      console.log('applyFilter called with:', filter, categoryId)

      // Filter setzen
      this.appliedFilterQuery = { ...this.appliedFilterQuery, ...filter }
      console.log('Updated appliedFilterQuery:', this.appliedFilterQuery)
    },

    // Einfache Version von checkIfDisabled
    checkIfDisabled(appliedFilterQuery, categoryId) {
      return false  // Erstmal alle enabled
    },

    createFilterOptions (params) {
      console.log('createFilterOptions called with:', params)
      const { categoryId, isInitialWithQuery } = params

      // Hole Tags für diese Kategorie
      let filterOptions = this.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length > 0
        ? this.institutionTagCategoriesCopy[categoryId].relationships.tags.list()
        : []

      if (Object.keys(filterOptions).length > 0) {
        filterOptions = Object.values(filterOptions).map(option => {
          const { id, attributes } = option
          const { name } = attributes

          return {
            id,
            label: name,
            selected: false
          }
        })
      }

      // INS NOTIZBUCH SCHREIBEN (das war das Fehlende!):
      this.setUngroupedFilterOptions({ categoryId, options: filterOptions })
      this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

      console.log('Written to store:', filterOptions)
    },


    getInstitutionTagCategories(isInitial = false) {
      return this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'creationDate',
            'name',
            'tags'
          ].join(),
          InstitutionTag: [
            'creationDate',
            'isUsed',
            'name',
            'category'
          ].join()
        },
        include: [
          'tags',
          'tags.category'
        ].join()
      })
        .then(() => {
          // Copy the object to avoid issues with filter requests
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

          if (isInitial) {
            // TODO: Später setInitiallySelectedFilterCategories implementieren
          }

          return this.institutionTagCategoriesCopy
        })
        .catch(err => {
          console.error('Error loading tag categories:', err)
          return {}
        })
    },

    // Einfache Version von getSelectedOptionsCount
    getSelectedOptionsCount(appliedFilterQuery, categoryId) {
      return 0  // Erstmal zeige (0) für alle Kategorien
    },

    // Methode 2: Handle einzelne Kategorie ändern
    handleChange(categoryLabel, isSelected) {
      console.log('Category changed:', categoryLabel, 'selected:', isSelected)

      if (isSelected) {
        // Filter-Kategorie zu currentlySelectedFilterCategories hinzufügen
        if (!this.currentlySelectedFilterCategories.includes(categoryLabel)) {
          this.currentlySelectedFilterCategories.push(categoryLabel)
        }
      } else {
        // Filter-Kategorie entfernen
        const index = this.currentlySelectedFilterCategories.indexOf(categoryLabel)
        if (index > -1) {
          this.currentlySelectedFilterCategories.splice(index, 1)
        }
      }

      console.log('Selected categories:', this.currentlySelectedFilterCategories)
    },

    // Methode 3: Reset alle Filter
    reset() {
      console.log('Reset clicked')

      // 1. Alle ausgewählten Filter-Kategorien zurücksetzen
      this.currentlySelectedFilterCategories = []

      // 2. Alle angewendeten Filter zurücksetzen
      this.appliedFilterQuery = {}

      // 3. Später: Auch Search zurücksetzen (kommt noch)
      // this.$emit('search-reset')

      console.log('Reset completed - all filters cleared')
    },

    // Methode 1: Toggle alle Kategorien
    toggleAllCategories() {
      console.log('Toggle all categories clicked')

      if (this.currentlySelectedFilterCategories.length === 0) {
        // Alle Kategorien auswählen
        this.currentlySelectedFilterCategories = this.filterCategories.map(cat => cat.label)
      } else {
        // Alle Kategorien abwählen
        this.currentlySelectedFilterCategories = []
      }

      console.log('All categories toggled:', this.currentlySelectedFilterCategories)
    },
  },

  mounted() {
    const promises = [
      this.getInstitutionTagCategories(true)  // ← Lädt alle Tags
    ]
  }
}
</script>
