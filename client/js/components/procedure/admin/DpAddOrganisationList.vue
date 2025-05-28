<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="contentArea"
    class="mt-2">

    <dp-loading
      v-if="isLoading"
      class="mt-4" />
    <template v-else>
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
                data-cy="dpAddOrganisationList:toggleAllFilterCategories"
                v-text="Translator.trans('toggle_all')"
                @click="toggleAllSelectedFilterCategories" />
              <div v-if="!isLoading">
                <dp-checkbox
                  v-for="category in allFilterCategories"
                  :key="category.id"
                  :id="`filterCategorySelect:${category.label}`"
                  :checked="selectedFilterCategories.includes(category.label)"
                  :data-cy="`dpAddOrganisationList:filterCategoriesSelect:${category.label}`"
                  :disabled="checkIfDisabled(category.id)"
                  :label="{
                    text: `${category.label} (${getSelectedOptionsCount(category.id)})`
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
          @filterApply="(filtersToBeApplied) => applyFilterQuery(filtersToBeApplied, category.id)"
          @filterOptions:request="(params) => createFilterOptions({ ...params, categoryId: category.id})" />
      </div>

      <dp-button
        class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
        data-cy="dpAddOrganisationList:resetFilter"
        :disabled="!isQueryApplied"
        :text="Translator.trans('reset')"
        variant="outline"
        v-tooltip="Translator.trans('search.filter.reset')"
        @click="resetQuery" />
    </template>

    <dp-data-table-extended
      ref="dataTable"
      class="mt-2"
      :default-sort-order="sortOrder"
      :header-fields="headerFields"
      :init-items-per-page="itemsPerPage"
      is-expandable
      is-selectable
      :items-per-page-options="itemsPerPageOptions"
      lock-checkbox-by="hasNoEmail"
      :table-items="rowItems"
      :translations="{ lockedForSelection: Translator.trans('add_orga.email_hint') }"
      @items-selected="setSelectedItems">
      <template v-slot:expandedContent="{ participationFeedbackEmailAddress, locationContacts, ccEmailAddresses, contactPerson, assignedTags }">
        <div class="lg:w-2/3 lg:flex pt-4">
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('address') }}
            </dt>
            <template v-if="locationContacts && hasAdress">
              <dd
                v-if="locationContacts.street"
                class="ml-0">
                {{ locationContacts.street }}
              </dd>
              <dd
                v-if="locationContacts.postalcode"
                class="ml-0">
                {{ locationContacts.postalcode }}
              </dd>
              <dd
                v-if="locationContacts.city"
                class="ml-0">
                {{ locationContacts.city }}
              </dd>
            </template>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('notspecified') }}
            </dd>
          </dl>
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('phone') }}
            </dt>
            <dd
              v-if="locationContacts?.hasOwnProperty('phone') && locationContacts.phone"
              class="ml-0">
              {{ locationContacts.phone }}
            </dd>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('notspecified') }}
            </dd>
            <dt class="color--grey mt-2">
              {{ Translator.trans('email.participation') }}
            </dt>
            <dd
              v-if="participationFeedbackEmailAddress"
              class="ml-0">
              {{ participationFeedbackEmailAddress }}
            </dd>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('no.participation.email') }}
            </dd>
            <template v-if="ccEmailAddresses">
              <dt class="color--grey mt-2">
                {{ Translator.trans('email.cc.participation') }}:
              </dt>
              <dd class="ml-0">
                {{ ccEmailAddresses }}
              </dd>
            </template>
            <template v-if="contactPerson">
              <dt class="color--grey mt-2">
                {{ Translator.trans('contact.person') }}:
              </dt>
              <dd class="ml-0">
                {{ contactPerson }}
              </dd>
            </template>
          </dl>
          <dl
            v-if="hasPermission('feature_institution_tag_read') && Array.isArray(assignedTags) && assignedTags.length > 0"
            class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('tags') }}
            </dt>
            <dd class="ml-0">
              <div class="flex flex-wrap gap-1 mt-1">
                <span
                  v-for="tag in assignedTags"
                  :key="tag.id">
                  {{ tag.name }}
                </span>
              </div>
            </dd>
          </dl>
        </div>
      </template>
      <template v-slot:footer>
        <div class="pt-2 flex">
          <div class="w-1/3 inline-block">
            <span
              v-if="selectedItems.length"
              class="weight--bold line-height--1_6">
              {{ selectedItems.length }} {{ (selectedItems.length === 1 && Translator.trans('entry.selected')) || Translator.trans('entries.selected') }}
            </span>
          </div>
          <div class="w-2/3 text-right inline-block space-x-2">
            <dp-button
              data-cy="addPublicAgency"
              :text="Translator.trans('invitable_institution.add')"
              @click="addPublicInterestBodies(selectedItems)" />
            <a
              :href="Routing.generate('DemosPlan_procedure_member_index', { procedure: procedureId })"
              data-cy="organisationList:abortAndBack"
              class="btn btn--secondary">
              {{ Translator.trans('abort.and.back') }}
            </a>
          </div>
        </div>
      </template>
    </dp-data-table-extended>
  </div>
</template>

<script>
import { dpApi, DpButton, DpCheckbox, DpDataTableExtended, DpFlyout, DpIcon } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'

export default {
  name: 'DpAddOrganisationList',

  components: {
    DpButton,
    DpCheckbox,
    DpDataTableExtended,
    DpFlyout,
    FilterFlyout,
    DpIcon
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },

    headerFields: {
      type: Array,
      required: false,
      default: () => [
        { field: 'legalName', label: Translator.trans('invitable_institution') },
        ...hasPermission('field_organisation_competence') ? [{ field: 'competenceDescription', label: Translator.trans('competence.explanation') }] : []
      ]
    }
  },

  data () {
    return {
      invitableToebFields: [
        'legalName',
        'participationFeedbackEmailAddress',
        'locationContacts',
        ...(hasPermission('feature_institution_tag_read') ? ['assignedTags'] : [])

      ],
      isLoading: true,
      itemsPerPageOptions: [10, 50, 100, 200],
      itemsPerPage: 50,
      locationContactFields: ['street', 'postalcode', 'city'],
      sortOrder: { key: 'legalName', direction: 1 },
      selectedItems: [],
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      initiallySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {}

    }
  },

  computed: {
    ...mapGetters('FilterFlyout', {
      filterQuery: 'getFilterQuery'
    }),

    ...mapState('InstitutionLocationContact', {
      institutionLocationContactItems: 'items'
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InvitableToeb', {
      invitableToebItems: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items'
    }),

    institutionTagCategoriesValues () {
      return Object.values(this.institutionTagCategoriesCopy || {})
        .sort((a, b) => new Date(a.attributes?.creationDate || 0) - new Date(b.attributes?.creationDate || 0))
    },

    allFilterCategories () {
      return (this.institutionTagCategoriesValues || []).reduce((acc, category) => {
        if (!category || !category.id || !category.attributes) return acc

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

    filterCategoriesToBeDisplayed () {
      return Object.values(this.allFilterCategories || {})
        .filter(filter => this.currentlySelectedFilterCategories.includes(filter.label))
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    queryIds () {
      let ids = []
      const isFilterApplied = Object.keys(this.appliedFilterQuery).length > 0

      if (isFilterApplied) {
        ids = Object.values(this.appliedFilterQuery).map(el => el.condition.value)
      }

      return ids
    },

    rowItems () {
      let items = Object.values(this.invitableToebItems).reduce((acc, item) => {
        const locationContactId = item.relationships.locationContacts?.data.length > 0 ? item.relationships.locationContacts.data[0].id : null
        const locationContact = locationContactId ? this.getLocationContactById(locationContactId) : null
        const hasNoEmail = !item.attributes.participationFeedbackEmailAddress
        const tagReferences = item.relationships.assignedTags?.data || []
        const institutionTags = tagReferences.map(tag => ({
          id: tag.id,
          name: this.institutionTagItems?.[tag.id]?.attributes?.name || Translator.trans('error.tag.notfound')
        }))

        return [
          ...acc,
          ...[
            {
              id: item.id,
              ...item.attributes,
              locationContacts: locationContact
                ? {
                    id: locationContact.id,
                    ...locationContact.attributes
                  }
                : null,
              assignedTags: institutionTags,
              hasNoEmail
            }
          ]
        ]
      }, []) || []

      // Filter
      if (Object.keys(this.appliedFilterQuery).length > 0) {
        items = items.filter(item => {
          return Object.values(this.appliedFilterQuery).every(filterCondition => {
            if (!filterCondition.condition) return true

            const tagIds = item.assignedTags.map(tag => tag.id)

            return tagIds.includes(filterCondition.condition.value)
          })
        })
      }

      return items
    }
  },

  methods: {
    ...mapActions('FilterFlyout', [
      'updateFilterQuery'
    ]),

    ...mapActions('InvitableToeb', {
      getInstitutions: 'list'
    }),

    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    addPublicInterestBodies (publicAgenciesIds) {
      if (publicAgenciesIds.length === 0) {
        return dplan.notify.notify('warning', Translator.trans('organisation.select.first'))
      }

      dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_procedure_add_invited_public_affairs_bodies', {
          procedureId: this.procedureId
        }),
        data: {
          data: publicAgenciesIds.map(id => {
            return {
              type: 'publicAffairsAgent',
              id
            }
          })
        }
      })
        // Refetch invitable institutions list to ensure that invited institutions are not displayed anymore
        .then(() => {
          this.getInstitutionsWithContacts()
            .then(() => {
              dplan.notify.notify('confirm', Translator.trans('confirm.invitable_institutions.added'))
              this.$refs.dataTable.updateFields()

              // Reset selected items so that the footer updates accordingly
              this.selectedItems = []
              // Also reset selection in DpDataTableExtended as this.selectedItems resets only local variable
              this.$refs.dataTable.resetSelection()
            })
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('warning.invitable_institution.not.added'))
        })
    },

    applyFilterQuery (filter, categoryId) {
      this.setAppliedFilterQuery(filter)
      this.setFilterQueryInLocalStorage('filterQuery', JSON.stringify(this.filterQuery))
      this.getInstitutionsByPage(1, categoryId)
    },

    checkIfDisabled (categoryId) {
      return !!Object.values(this.appliedFilterQuery).find(el => el.condition?.memberOf === `${categoryId}_group`)
    },

    createFilterOptions (params) {
      const { categoryId, isInitialWithQuery } = params
      let filterOptions = this.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length > 0 ? this.institutionTagCategoriesCopy[categoryId].relationships.tags.list() : []
      const filterQueryFromStorage = this.getFilterQueryFromLocalStorage()
      const selectedFilterOptionIds = Object.keys(filterQueryFromStorage).filter(id => !id.includes('_group'))

      if (Object.keys(filterOptions).length > 0) {
        filterOptions = Object.values(filterOptions).map(option => {
          const { id, attributes } = option
          const { name } = attributes
          const selected = selectedFilterOptionIds.includes(id)

          return {
            id,
            label: name,
            selected
          }
        })
      }

      this.setUngroupedFilterOptions({ categoryId, options: filterOptions })
      this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

      if (isInitialWithQuery) {
        this.setFilterOptionsFromFilterQuery()
      }
    },

    getFilterQueryFromLocalStorage () {
      const filterQueryInStorage = localStorage.getItem('filterQuery')

      return filterQueryInStorage && filterQueryInStorage !== 'undefined' ? JSON.parse(filterQueryInStorage) : {}
    },

    setFilterOptionsFromFilterQuery () {
      const filterQueryFromStorage = this.getFilterQueryFromLocalStorage()
      const categoryIdsWithSelectedFilterOptions = Object.keys(filterQueryFromStorage)
        .filter(id => id.includes('_group'))
        .map(id => id.replace('_group', ''))

      categoryIdsWithSelectedFilterOptions.forEach(id => {
        const selectedFilterOptionIds = Object.values(filterQueryFromStorage)
          .filter(el => el.condition?.memberOf === `${id}_group`)
          .map(el => el.condition.value)

        this.setInitialFlyoutFilterIds({ categoryId: id, filterIds: selectedFilterOptionIds })
      })
    },

    getInitiallySelectedFilterCategoriesFromLocalStorage () {
      const selectedFilterCategories = localStorage.getItem('visibleFilterFlyouts')
      return selectedFilterCategories ? JSON.parse(selectedFilterCategories) : null
    },

    getInstitutionsByPage (page = 1, categoryId = null) {
      if (categoryId) {
        this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })
      }
    },

    getInstitutionsWithContacts () {
      const permissionChecksToeb = [
        { permission: 'field_organisation_email2_cc', value: 'ccEmailAddresses' },
        { permission: 'field_organisation_contact_person', value: 'contactPerson' },
        { permission: 'field_organisation_competence', value: 'competenceDescription' }
      ]

      const permissionChecksContact = [
        { permission: 'field_organisation_phone', value: 'phone' }
      ]

      const includeParams = hasPermission('feature_institution_tag_read')
        ? ['locationContacts', 'assignedTags']
        : ['locationContacts']

      const requestParams = {
        include: includeParams.join(),
        fields: {
          InvitableToeb: this.invitableToebFields.concat(this.returnPermissionChecksValuesArray(permissionChecksToeb)).join(),
          InstitutionLocationContact: this.locationContactFields.concat(this.returnPermissionChecksValuesArray(permissionChecksContact)).join()
        }
      }

      if (hasPermission('feature_institution_tag_read')) {
        requestParams.fields.InstitutionTag = 'name'
      }

      return this.getInstitutions(requestParams)
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
            this.setInitiallySelectedFilterCategories()
            this.setCurrentlySelectedFilterCategories(this.initiallySelectedFilterCategories)
          }

          return this.institutionTagCategoriesCopy
        })
        .catch(err => {
          console.error('Error loading tag categories:', err)
          return {}
        })
    },

    getLocationContactById (id) {
      return this.institutionLocationContactItems[id]
    },

    getSelectedOptionsCount (categoryId) {
      return Object.values(this.appliedFilterQuery).filter(el => el.condition?.memberOf === `${categoryId}_group`).length
    },

    handleChange (filterCategoryName, isSelected) {
      this.updateCurrentlySelectedFilterCategories(filterCategoryName, isSelected)
      this.setSelectedFilterCategoriesInLocalStorage(this.currentlySelectedFilterCategories)
    },

    hasAdress () {
      return this.rowItems.locationContacts?.street || this.rowItems.locationContacts?.postalcode || this.rowItems.locationContacts?.city
    },

    isQueryApplied () {
      const isFilterApplied = Object.keys(this.appliedFilterQuery).length > 0
      const isSearchApplied = this.searchTerm !== ''

      return isFilterApplied || isSearchApplied
    },

    resetFilterQueryInLocalStorage () {
      localStorage.setItem('filterQuery', JSON.stringify({}))
    },

    resetQuery () {
      this.searchTerm = ''
      Object.keys(this.allFilterCategories).forEach((filterCategoryId, idx) => {
        const filterFlyoutComponentExists = typeof this.$refs.filterFlyout[idx] !== 'undefined'
        const hasFilterCategorySelectedOption = !!Object.values(this.filterQuery).find(el => el.condition?.memberOf === `${filterCategoryId}_group`)

        if (filterFlyoutComponentExists) {
          const isFilterFlyoutVisible = this.currentlySelectedFilterCategories.includes(this.allFilterCategories[filterCategoryId].label)

          this.$refs.filterFlyout[idx].reset()

          if (!isFilterFlyoutVisible && hasFilterCategorySelectedOption) {
            const selectedFilterOptions = Object.values(this.filterQuery).filter(el => el.condition?.memberOf === `${filterCategoryId}_group`)
            const payload = selectedFilterOptions.reduce((acc, el) => {
              acc[el.condition.value] = el

              return acc
            }, {})

            this.updateFilterQuery(payload)
          }
        }
      })

      this.resetFilterQueryInLocalStorage()
      this.appliedFilterQuery = {}
      this.getInstitutionsByPage(1)
    },

    returnPermissionChecksValuesArray (permissionChecks) {
      return permissionChecks.reduce((acc, check) => {
        if (hasPermission(check.permission)) {
          acc.push(check.value)
        }
        return acc
      }, [])
    },

    setAppliedFilterQuery (filter) {
      // Remove groups from filter
      const selectedFilterOptions = Object.fromEntries(Object.entries(filter).filter(([_key, value]) => value.condition))
      const isReset = Object.keys(selectedFilterOptions).length === 0
      const isAppliedFilterQueryEmpty = Object.keys(this.appliedFilterQuery).length === 0

      if (!isReset && isAppliedFilterQueryEmpty) {
        Object.values(selectedFilterOptions).forEach(option => {
          this.appliedFilterQuery[option.condition.value] = option
        })
      } else if (isReset) {
        const filtersWithConditions = Object.fromEntries(
          Object.entries(this.filterQuery).filter(([key, value]) => value.condition)
        )

        this.appliedFilterQuery = Object.keys(filtersWithConditions).length ? filtersWithConditions : {}
      } else {
        this.appliedFilterQuery = selectedFilterOptions
      }
    },

    setAppliedFilterQueryFromStorage () {
      const filterQueryFromStorage = this.getFilterQueryFromLocalStorage()
      this.setAppliedFilterQuery(filterQueryFromStorage)
    },

    setCurrentlySelectedFilterCategories (selectedCategories) {
      this.currentlySelectedFilterCategories = selectedCategories
    },

    setFilterQueryFromStorage () {
      const filterQueryFromStorage = this.getFilterQueryFromLocalStorage()
      const filterIds = Object.keys(filterQueryFromStorage)

      if (filterIds.length > 0) {
        filterIds.forEach(id => {
          const payload = { [id]: filterQueryFromStorage[id] }

          if (filterQueryFromStorage[id].condition) {
            this.updateFilterQuery(payload)
          }
        })
      }
    },

    setFilterQueryInLocalStorage () {
      localStorage.setItem('filterQuery', JSON.stringify(this.filterQuery))
    },

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    },

    setSelectedFilterCategoriesInLocalStorage (selectedFilterCategories) {
      localStorage.setItem('visibleFilterFlyouts', JSON.stringify(selectedFilterCategories))
    },

    setInitiallySelectedFilterCategories () {
      const selectedFilterCategoriesInStorage = this.getInitiallySelectedFilterCategoriesFromLocalStorage()
      this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null
        ? selectedFilterCategoriesInStorage
        : this.institutionTagCategoriesValues.slice(0, 5).map(category => category.attributes.name)
    },

    toggleAllSelectedFilterCategories () {
      const allSelected = this.currentlySelectedFilterCategories.length === Object.keys(this.allFilterCategories).length
      const selectedFilterOptions = Object.values(this.appliedFilterQuery)
      const categoriesWithSelectedOptions = []

      selectedFilterOptions.forEach(option => {
        const categoryId = option.condition.memberOf.replace('_group', '')
        const category = this.allFilterCategories[categoryId]

        if (category && !categoriesWithSelectedOptions.includes(category.label)) {
          categoriesWithSelectedOptions.push(category.label)
        }
      })

      this.currentlySelectedFilterCategories = allSelected
        ? categoriesWithSelectedOptions
        : Object.values(this.allFilterCategories).map(filterCategory => filterCategory.label)
    },

    updateCurrentlySelectedFilterCategories (filterCategoryName, isSelected) {
      if (isSelected) {
        this.currentlySelectedFilterCategories.push(filterCategoryName)
      } else {
        this.currentlySelectedFilterCategories = this.currentlySelectedFilterCategories.filter(category => category !== filterCategoryName)
      }
    }

  },

  mounted () {
    const promises = [
      this.getInstitutionsWithContacts(),
      this.getInstitutionTagCategories(true)
    ]

    this.isLoading = true
    this.setFilterQueryFromStorage()
    this.setAppliedFilterQueryFromStorage()

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  }
}
</script>
