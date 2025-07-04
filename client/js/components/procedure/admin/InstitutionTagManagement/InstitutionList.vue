<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-inline-notification
      class="mt-3 mb-2"
      dismissible
      :message="Translator.trans('explanation.invitable_institution.group.tags')"
      type="info" />

    <div class="mt-4">
      <dp-loading
        v-if="isLoading"
        class="mt-4" />

      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-1">
          <dp-search-field
            class="h-fit mt-1 col-span-1 sm:col-span-3"
            data-cy="institutionList:searchField"
            input-width="u-1-of-1"
            @reset="handleReset"
            @search="val => handleSearch(val)" />

          <div class="sm:relative flex flex-col sm:flex-row flex-wrap space-x-1 space-x-reverse space-y-1 col-span-1 sm:col-span-7 ml-0 pl-0 sm:ml-2 sm:pl-[38px]">
            <div class="sm:absolute sm:top-0 sm:left-0 mt-1">
              <dp-flyout
                align="left"
                :aria-label="Translator.trans('filters.more')"
                class="bg-surface-medium rounded pb-1 pt-[4px]"
                data-cy="institutionList:filterCategories">
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
                    data-cy="institutionList:toggleAllFilterCategories"
                    v-text="Translator.trans('toggle_all')"
                    @click="filterManager.toggleAllCategories" />
                  <div v-if="!isLoading">
                    <dp-checkbox
                      v-for="category in allFilterCategories"
                      :key="category.id"
                      :id="`filterCategorySelect:${category.label}`"
                      :checked="selectedFilterCategories.includes(category.label)"
                      :data-cy="`institutionList:filterCategoriesSelect:${category.label}`"
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
              :data-cy="`institutionListFilter:${category.label}`"
              :initial-query-ids="queryIds"
              :member-of="category.memberOf"
              :operator="category.comparisonOperator"
              :path="category.rootPath"
              @filterApply="(filtersToBeApplied) => filterManager.applyFilter(filtersToBeApplied, category.id)"
              @filterOptions:request="(params) => filterManager.createFilterOptions({ ...params, categoryId: category.id})" />
          </div>

          <dp-button
            class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
            data-cy="institutionList:resetFilter"
            :disabled="!isQueryApplied"
            :text="Translator.trans('reset')"
            variant="outline"
            v-tooltip="Translator.trans('search.filter.reset')"
            @click="filterManager.reset" />
        </div>

        <div class="flex justify-end mt-4">
          <dp-column-selector
            data-cy="institutionList:selectableColumns"
            :initial-selection="initiallySelectedColumns"
            local-storage-key="institutionList"
            :selectable-columns="selectableColumns"
            use-local-storage
            @selection-changed="setCurrentlySelectedColumns" />
        </div>

        <dp-data-table
          ref="dataTable"
          class="mt-1 overflow-x-auto scrollbar-none"
          data-dp-validate="tagsTable"
          data-cy="institutionList:dataTable"
          :header-fields="headerFields"
          is-resizable
          :items="institutionList"
          track-by="id">
          <template v-slot:name="institution">
            <ul class="o-list max-w-12">
              <li>
                {{ institution.name }}
              </li>
              <li class="o-list__item o-hellip--nowrap">
                {{ date(institution.createdDate) }}
              </li>
            </ul>
          </template>
          <template
            v-for="(category, idx) in institutionTagCategoriesCopy"
            v-slot:[category.attributes.name]="institution">
            <dp-multiselect
              v-if="institution.edit"
              :key="idx"
              v-model="editingInstitutionTags[category.id]"
              :data-cy="`institutionList:tags${category.attributes.name}`"
              label="name"
              multiple
              :options="getCategoryTags(category.id)"
              track-by="id" />
            <div
              v-else
              :key="`tags:${idx}`"
              v-text="separateByCommas(institution.tags.filter(tag => tag.category.id === category.id))" />
          </template>
          <template v-slot:action="institution">
            <div class="float-right">
              <template v-if="institution.edit">
                <button
                  :aria-label="Translator.trans('save')"
                  class="btn--blank o-link--default mr-1"
                  data-cy="institutionList:saveTag"
                  @click="addTagsToInstitution(institution.id)">
                  <dp-icon
                    icon="check"
                    aria-hidden="true" />
                </button>
                <button
                  :aria-label="Translator.trans('abort')"
                  class="btn--blank o-link--default"
                  data-cy="institutionList:abortTag"
                  @click="abortEdit()">
                  <dp-icon
                    icon="xmark"
                    aria-hidden="true" />
                </button>
              </template>
              <button
                v-else
                :aria-label="Translator.trans('item.edit')"
                class="btn--blank o-link--default"
                data-cy="institutionList:editTag"
                @click="editInstitution(institution.id)">
                <dp-icon
                  icon="edit"
                  aria-hidden="true" />
              </button>
            </div>
          </template>
        </dp-data-table>

        <div
          ref="scrollBar"
          class="sticky bottom-0 left-0 right-0 h-3 overflow-x-scroll overflow-y-hidden">
          <div />
        </div>
      </template>
    </div>

    <dp-sliding-pagination
      v-if="totalPages > 1"
      class="mr-1 ml-2 mt-2"
      :current="currentPage"
      :total="totalPages"
      :non-sliding-size="50"
      @page-change="getInstitutionsByPage" />
  </div>
</template>

<script>
import {
  DpButton,
  DpCheckbox,
  DpColumnSelector,
  DpDataTable,
  DpFlyout,
  DpIcon,
  DpInlineNotification,
  DpLoading,
  DpMultiselect,
  DpSearchField,
  DpSlidingPagination,
  formatDate
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { filterCategoriesStorage } from '@DpJs/lib/procedure/FilterFlyout/filterStorage'
import { filterCategoryHelpers } from '@DpJs/lib/procedure/FilterFlyout/filterHelpers'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'

export default {
  name: 'InstitutionList',

  setup () {
    return {
      filterCategoryHelpers
    }
  },

  components: {
    DpButton,
    DpCheckbox,
    DpColumnSelector,
    DpDataTable,
    DpMultiselect,
    DpFlyout,
    DpIcon,
    DpInlineNotification,
    DpLoading,
    DpSearchField,
    DpSlidingPagination,
    FilterFlyout
  },

  mixins: [tableScrollbarMixin],

  props: {
    isActive: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedColumns: [],
      currentlySelectedFilterCategories: [],
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: {},
      filterManager: filterCategoryHelpers.createFilterManager(this),
      initiallySelectedColumns: [],
      initiallySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      isLoading: true,
      searchTerm: ''
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', {
      filterQuery: 'getFilterQuery'
    }),

    ...mapState('InstitutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InvitableInstitution', {
      invitableInstitutionList: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    allFilterCategories () {
      return this.institutionTagCategoriesValues.reduce((acc, category) => {
        const { id, attributes } = category
        const groupKey = `${id}_group`

        acc[id] = {
          id,
          comparisonOperator: 'ARRAY_CONTAINS_VALUE',
          label: attributes.name,
          rootPath: 'assignedTags',
          selected: false,
          memberOf: groupKey
        }

        return acc
      }, {})
    },

    categoryFieldsAvailable () {
      return this.institutionTagCategoriesValues.map(category => ({
        field: category.attributes.name,
        label: category.attributes.name
      }))
    },

    filterCategoriesToBeDisplayed () {
      return Object.values(this.allFilterCategories).filter(filter => this.currentlySelectedFilterCategories.includes(filter.label))
    },

    headerFields () {
      const institutionField = {
        field: 'name',
        label: Translator.trans('institution')
      }

      const categoryFields = this.categoryFieldsAvailable.filter(headerField => this.currentlySelectedColumns.includes(headerField.field))

      const actionField = {
        field: 'action'
      }

      return [institutionField, ...categoryFields, actionField]
    },

    isQueryApplied () {
      const isFilterApplied = Object.keys(this.appliedFilterQuery).length > 0
      const isSearchApplied = this.searchTerm !== ''

      return isFilterApplied || isSearchApplied
    },

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          createdDate: attributes.createdDate.date,
          edit: this.editingInstitutionId === id,
          id,
          name: attributes.name,
          tags: relationships.assignedTags.data.map(tag => {
            const tagDetails = this.getTagById(tag.id)

            return {
              id: tag.id,
              type: tag.type,
              name: tagDetails.name,
              category: tagDetails.category
            }
          })
        }
      })
    },

    institutionTagCategoriesValues () {
      return Object.values(this.institutionTagCategoriesCopy)
        .sort((a, b) => new Date(a.attributes.creationDate) - new Date(b.attributes.creationDate))
    },

    queryIds () {
      let ids = []
      const isFilterApplied = Object.keys(this.appliedFilterQuery).length > 0

      if (isFilterApplied) {
        ids = Object.values(this.appliedFilterQuery).map(el => el.condition.value)
      }

      return ids
    },

    selectableColumns () {
      return this.categoryFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    tagList () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          id,
          name: attributes.name,
          category: relationships?.category?.data
        }
      })
    }
  },

  watch: {
    isActive (newValue) {
      if (newValue) {
        this.getInstitutionTagCategories()
      }
    }
  },

  methods: {
    ...mapActions('FilterFlyout', [
      'updateFilterQuery'
    ]),

    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapActions('InvitableInstitution', {
      fetchInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    ...mapMutations('InvitableInstitution', {
      updateInvitableInstitution: 'setItem'
    }),

    abortEdit () {
      this.editingInstitutionId = null
      this.editingInstitutionTags = {}
    },

    addTagsToInstitution (id) {
      const institutionTagsArray = Object.values(this.editingInstitutionTags).flatMap(category => Object.values(category))
      const payload = institutionTagsArray.map(el => {
        return {
          id: el.id,
          type: 'InstitutionTag'
        }
      })

      this.updateInvitableInstitution({
        id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: payload
          }
        }
      })

      this.saveInvitableInstitution(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          this.restoreInstitutionFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingInstitutionId = null
        })
    },

    /**
     * Format date for display
     * @param {string} d - Date string to format
     * @returns {string} Formatted date
     */
    date (d) {
      return formatDate(d)
    },

    editInstitution (id) {
      this.editingInstitutionTags = {}
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]

      // Initialize editingInstitutionTags with categoryId
      this.institutionTagCategoriesValues.forEach(category => {
        if (!this.editingInstitutionTags[category.id]) {
          this.$set(this.editingInstitutionTags, category.id, [])
        }
      })
      this.editingInstitution.relationships.assignedTags.data.forEach(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags[tag.category.id].push(tag)
      })
    },

    getCategoryTags (categoryId) {
      const tags = this.institutionTagCategoriesCopy[categoryId].relationships?.tags?.data.length > 0 ? this.institutionTagCategoriesCopy[categoryId].relationships.tags.list() : []

      return Object.values(tags).map(tag => {
        return {
          id: tag.id,
          name: tag.attributes.name
        }
      })
    },

    getInstitutionsByPage (page, categoryId = null) {
      const args = {
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate',
        fields: {
          InvitableInstitution: [
            'name',
            'createdDate',
            'assignedTags'
          ].join(),
          InstitutionTag: [
            'category',
            'name'
          ].join(),
          InstitutionTagCategory: [
            'name'
          ].join()
        },
        filter: {
          namefilter: {
            condition: {
              path: 'name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm
            }
          }
        },
        include: [
          'assignedTags',
          'assignedTags.category'
        ].join()
      }

      if (Object.keys(this.filterQuery).length > 0) {
        args.filter = {
          ...args.filter,
          ...this.filterQuery
        }
      }

      return this.fetchInvitableInstitution(args)
        .then(() => {
          if (categoryId) {
            this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })
          }
        })
        .catch(err => {
          console.error(err)
        })
    },

    getInstitutionTagCategories (isInitial = false) {
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
          // Copy the object to avoid issues with filter requests that update the categories in the store
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

          if (isInitial) {
            this.setInitiallySelectedColumns()
            this.setInitiallySelectedFilterCategories()
            this.setCurrentlySelectedFilterCategories(this.initiallySelectedFilterCategories)
          }
        })
        .catch(err => {
          console.error(err)
        })
    },

    getFilterQueryFromLocalStorage () {
      const filterQueryInStorage = localStorage.getItem('filterQuery')

      return filterQueryInStorage && filterQueryInStorage !== 'undefined' ? JSON.parse(filterQueryInStorage) : {}
    },

    getTagById (tagId) {
      return this.tagList.find(el => el.id === tagId) ?? null
    },

    getTagNameById (tagId) {
      return this.tagList
        .filter(el => el.id === tagId)
        .map(el => el.name)
    },

    handleChange (filterCategoryName, isSelected) {
      this.filterManager.handleChange(filterCategoryName, isSelected)
    },

    handleReset () {
      this.searchTerm = ''
      this.getInstitutionsByPage(1)
    },

    handleSearch (searchTerm) {
      this.isLoading = true
      this.searchTerm = searchTerm

      this.getInstitutionsByPage(1)
        .then(() => {
          this.isLoading = false
        })
    },

    setAppliedFilterQuery (filter) {
      return this.filterManager.setAppliedFilterQuery(filter)
    },

    separateByCommas (institutionTags) {
      const tagsLabels = []

      institutionTags.forEach(el => {
        const name = this.getTagNameById(el.id)

        tagsLabels.push(name)
      })

      return tagsLabels.join(', ')
    },

    setCurrentlySelectedColumns (selectedColumns) {
      this.currentlySelectedColumns = selectedColumns
    },

    setCurrentlySelectedFilterCategories (selectedCategories) {
      this.currentlySelectedFilterCategories = selectedCategories
    },

    setAppliedFilterQueryFromStorage () {
      return this.filterManager.setAppliedFilterQueryFromStorage()
    },

    setFilterOptionsFromFilterQuery () {
      this.filterManager.setFilterOptionsFromFilterQuery()
    },

    setFilterQueryFromStorage () {
      return this.filterManager.setFilterQueryFromStorage()
    },

    setInitiallySelectedColumns () {
      this.initiallySelectedColumns = this.institutionTagCategoriesValues
        .slice(0, 5)
        .map(category => category.attributes.name)
    },

    setInitiallySelectedFilterCategories () {
      const selectedFilterCategoriesInStorage = filterCategoriesStorage.get()

      this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null ? selectedFilterCategoriesInStorage : this.initiallySelectedColumns
    },

    toggleAllSelectedFilterCategories () {
      this.filterManager.toggleAllCategories()
    }
  },

  mounted () {
    this.filterManager.setAppliedFilterQueryFromStorage()
    this.filterManager.setFilterQueryFromStorage()

    const promises = [
      this.getInstitutionsByPage(1),
      this.getInstitutionTagCategories(true)
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  }
}
</script>
