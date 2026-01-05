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
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-1">
        <dp-search-field
          class="h-fit mt-1 col-span-1 sm:col-span-3"
          data-cy="addOrganisationList:searchField"
          input-width="u-1-of-1"
          @reset="handleReset"
          @search="handleSearch" />

        <template v-if="hasPermission('feature_institution_tag_read')">
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
                    @click="filterManager.toggleAllCategories" />
                  <div v-if="!isLoading">
                    <dp-checkbox
                      v-for="category in allFilterCategories"
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
        <!-- Slot for bulk actions -->
      </div>
      <slot name="bulkActions" />
    </template>

    <dp-pager
      v-if="totalItems > itemsPerPageOptions[0]"
      class="flex-shrink-0 mt-2"
      :current-page="currentPage"
      :limits="itemsPerPageOptions"
      :per-page="itemsPerPage"
      :total-items="totalItems"
      :total-pages="totalPages"
      @page-change="page => getInstitutionsWithContacts(page)"
      @size-change="handleItemsPerPageChange" />

    <dp-data-table
      class="mt-2"
      ref="DpDataTable"
      :header-fields="headerFields"
      is-expandable
      is-selectable
      :items="rowItems"
      lock-checkbox-by="hasNoEmail"
      track-by="id"
      :translations="{ lockedForSelection: Translator.trans('add_orga.email_hint') }"
      @items-selected="setSelectedItems">
      <!-- Resource-specific content based on resourceType -->
      <template v-slot:expandedContent="{ participationFeedbackEmailAddress, locationContacts, ccEmailAddresses, contactPerson, assignedTags }">
        <div class="lg:w-2/3 lg:flex pt-4">
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('address') }}
            </dt>
            <template v-if="locationContacts && hasAdress(locationContacts)">
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
    </dp-data-table>
  </div>
</template>

<script>
import {
  DpButton,
  DpCheckbox,
  DpDataTable,
  DpFlyout,
  DpIcon,
  DpPager,
  DpSearchField
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { filterCategoriesStorage } from '@DpJs/lib/procedure/FilterFlyout/filterStorage'
import { filterCategoryHelpers } from '@DpJs/lib/procedure/FilterFlyout/filterHelpers'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

export default {
  name: 'OrganisationList',

  setup () {
    return {
      filterCategoryHelpers
    }
  },

  components: {
    DpButton,
    DpCheckbox,
    DpDataTable,
    DpFlyout,
    DpIcon,
    DpPager,
    DpSearchField,
    FilterFlyout
  },

  mixins: [paginationMixin],

  props: {
    headerFields: {
      type: Array,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    },

    resourceType: {
      type: String,
      required: true,
      validator: value => ['InvitableToeb', 'InvitedToeb'].includes(value)
    }
  },

  emits: [
    'selectedItems'
  ],

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 50
      },
      filterManager: filterCategoryHelpers.createFilterManager(this),
      initiallySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      isLoading: true,
      locationContactFields: ['street', 'postalcode', 'city'],
      pagination: {},
      searchTerm: '',
      selectedItems: []
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

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items'
    }),

    allFilterCategories () {
      return (this.institutionTagCategoriesValues || [])
        .filter(category => category && category.id && category.attributes)
        .map(category => {
          const { id, attributes } = category
          const groupKey = `${id}_group`

          return {
            id,
            comparisonOperator: 'ARRAY_CONTAINS_VALUE',
            label: attributes.name,
            rootPath: 'assignedTags',
            selected: false,
            memberOf: groupKey
          }
        })
    },

    apiRequestFields () {
      const headerFieldNames = this.headerFields.map(field => field.field)
      const baseFields = ['participationFeedbackEmailAddress', 'locationContacts']
      const tagFields = hasPermission('feature_institution_tag_read') ? ['assignedTags'] : []

      return [...new Set([...headerFieldNames, ...baseFields,
        ...tagFields])]
    },

    currentPage () {
      return this.pagination.currentPage || 1
    },

    filterCategoriesToBeDisplayed () {
      return (this.allFilterCategories || [])
        .filter(filter =>
          this.currentlySelectedFilterCategories.includes(filter.label))
    },

    institutionTagCategoriesValues () {
      return Object.values(this.institutionTagCategoriesCopy || {})
        .sort((a, b) => new Date(a.attributes?.creationDate || 0) - new Date(b.attributes?.creationDate || 0))
    },

    itemsPerPage () {
      return this.pagination.perPage || this.defaultPagination.perPage
    },

    itemsPerPageOptions () {
      return this.pagination.limits || this.defaultPagination.limits
    },

    rowItems () {
      return Object.values(this.storeItems).map(item => {
        const locationContactId = item.relationships.locationContacts?.data.length > 0 ? item.relationships.locationContacts.data[0].id : null
        const locationContact = locationContactId ? this.getLocationContactById(locationContactId) : null
        const hasNoEmail = !item.attributes.participationFeedbackEmailAddress
        const tagReferences = item.relationships.assignedTags?.data || []
        const institutionTags = tagReferences.map(tag => ({
          id: tag.id,
          name: this.institutionTagItems?.[tag.id]?.attributes?.name || Translator.trans('error.tag.notfound')
        }))

        return {
          id: item.id,
          ...item.attributes,

          // Add icon for hasReceivedInvitationMailInCurrentProcedurePhase
          hasReceivedInvitationMailInCurrentProcedurePhase:
            item.attributes.hasReceivedInvitationMailInCurrentProcedurePhase
              ? '<i class="fa fa-check-circle text-[#4c8b22]" ></i>'
              : '',
          originalStatementsCountInProcedure: item.attributes.originalStatementsCountInProcedure ||
            '-',
          competenceDescription: item.attributes.competenceDescription === '-' ? '' : item.attributes.competenceDescription,
          locationContacts: locationContact
            ? {
                id: locationContact.id,
                ...locationContact.attributes
              }
            : null,
          assignedTags: institutionTags,
          hasNoEmail
        }
      }).filter(item => {
        if (Object.keys(this.appliedFilterQuery).length === 0) return true

        return Object.values(this.appliedFilterQuery).every(filterCondition => {
          if (!filterCondition.condition) return true

          const tagIds = item.assignedTags.map(tag => tag.id)

          return tagIds.includes(filterCondition.condition.value)
        })
      }) || []
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    selectedItemsText () {
      const count = this.selectedItems.length
      const translationKey = count === 1 ? 'entry.selected' : 'entries.selected'
      return `${count} ${Translator.trans(translationKey)}`
    },

    storeItems () {
      return this.$store.state[this.storeModule]?.items || {}
    },

    storeModule () {
      return this.resourceType
    },

    storageKeyPagination () {
      return `addOrganisationList:${this.procedureId}:pagination`
    },

    totalItems () {
      return this.pagination.total || 0
    },

    totalPages () {
      return this.pagination.totalPages || 0
    },

    queryIds () {
      if (Object.keys(this.appliedFilterQuery).length === 0) {
        return []
      }

      return Object.values(this.appliedFilterQuery).map(el => el.condition.value)
    }
  },

  methods: {
    ...mapActions('FilterFlyout', [
      'updateFilterQuery'
    ]),

    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    applyFilterQuery (filter, categoryId) {
      this.filterManager.applyFilter(filter, categoryId)
    },

    createFilterOptions (params) {
      this.filterManager.createFilterOptions(params)
    },

    getInstitutionsByPage (page = 1, categoryId = null) {
      if (categoryId) {
        this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })
      }
    },

    getInstitutionTagCategories (isInitial = false) {
      if (!hasPermission('feature_institution_tag_read')) {
        return
      }

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

    getInstitutionsWithContacts (page = 1) {
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
        page: {
          number: page,
          size: this.pagination.perPage
        },
        include: includeParams.join(),
        fields: {
          [this.resourceType]: this.apiRequestFields.concat(this.returnPermissionChecksValuesArray(permissionChecksToeb)).join(),
          InstitutionLocationContact: this.locationContactFields.concat(this.returnPermissionChecksValuesArray(permissionChecksContact)).join()
        }
      }

      if (hasPermission('feature_institution_tag_read')) {
        requestParams.fields.InstitutionTag = 'name'
      }

      if (this.searchTerm.trim() !== '') {
        const filters = {
          namefilter: {
            condition: {
              path: 'legalName',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup'
            }
          }
        }

        if (hasPermission('field_organisation_competence')) {
          filters.competencefilter = {
            condition: {
              path: 'competenceDescription',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup'
            }
          }
        }

        if (hasPermission('feature_institution_tag_read')) {
          filters.tagfilter = {
            condition: {
              path: 'assignedTags.name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup'
            }
          }
        }

        filters.searchFieldsGroup = {
          group: {
            conjunction: 'OR'
          }
        }

        requestParams.filter = filters

        return this.$store.dispatch(`${this.storeModule}/list`, requestParams)
          .then(data => {
            this.setLocalStorage(data.meta.pagination)
            this.updatePagination(data.meta.pagination)
          })
      }

      return this.$store.dispatch(`${this.storeModule}/list`, requestParams)
        .then(data => {
          this.setLocalStorage(data.meta.pagination)
          this.updatePagination(data.meta.pagination)
        })
    },

    getLocationContactById (id) {
      return this.institutionLocationContactItems[id]
    },

    handleChange (filterCategoryName, isSelected) {
      this.filterManager.handleChange(filterCategoryName, isSelected)
    },

    handleReset () {
      this.searchTerm = ''
      this.getInstitutionsWithContacts(1)
        .then(() => {
          this.isLoading = false
        })
    },

    handleSearch (searchValue) {
      this.searchTerm = searchValue
      this.getInstitutionsWithContacts(1)
        .then(() => {
          this.isLoading = false
        })
    },

    handleItemsPerPageChange (newItemsPerPage) {
      const page = Math.floor((this.pagination.perPage * (this.pagination.currentPage - 1) / newItemsPerPage) + 1)

      this.pagination.perPage = newItemsPerPage
      this.getInstitutionsWithContacts(page)
    },

    hasAdress (locationContacts) {
      return locationContacts?.street || locationContacts?.postalcode ||
        locationContacts?.city
    },

    isQueryApplied () {
      const isFilterApplied = Object.keys(this.appliedFilterQuery).length > 0
      const isSearchApplied = this.searchTerm !== ''

      return isFilterApplied || isSearchApplied
    },

    resetQuery () {
      this.searchTerm = ''
      this.filterManager.reset()
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
      return this.filterManager.setAppliedFilterQuery(filter)
    },

    setAppliedFilterQueryFromStorage () {
      return this.filterManager.setAppliedFilterQueryFromStorage()
    },

    setCurrentlySelectedFilterCategories (selectedCategories) {
      this.currentlySelectedFilterCategories = selectedCategories
    },

    setFilterOptionsFromFilterQuery () {
      this.filterManager.setFilterOptionsFromFilterQuery()
    },

    setFilterQueryFromStorage () {
      return this.filterManager.setFilterQueryFromStorage()
    },

    setInitiallySelectedFilterCategories () {
      const selectedFilterCategoriesInStorage = filterCategoriesStorage.get()

      this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null
        ? selectedFilterCategoriesInStorage
        : this.institutionTagCategoriesValues.slice(0, 5).map(category => category.attributes.name)
    },

    setSelectedItems (items) {
      this.$emit('selectedItems', items)
    },

    toggleAllSelectedFilterCategories () {
      this.filterManager.toggleAllCategories()
    },

    /**
     * Clear all selections in the data table
     * Used after bulk operations like deletion to prevent stale selections
     */
    clearSelections () {
      if (this.$refs.DpDataTable) {
        this.$refs.DpDataTable.forceElementSelections({})

        this.setSelectedItems([])
      }
    }
  },

  mounted () {
    this.initPagination()
    this.getInstitutionsWithContacts()

    this.filterManager.setAppliedFilterQueryFromStorage()
    this.filterManager.setFilterQueryFromStorage()

    const promises = [
      this.getInstitutionTagCategories(true)
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  }
}
</script>
