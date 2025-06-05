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
          @search="val => handleSearch(val)" />

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
      </div>

      <dp-pager
        v-if="totalItems > itemsPerPageOptions[0]"
        :current-page="currentPage"
        :limits="itemsPerPageOptions"
        :per-page="itemsPerPage"
        :total-items="totalItems"
        :total-pages="totalPages"
        class="flex-shrink-0 mt-2"
        @page-change="page => getInstitutionsWithContacts(page)"
        @size-change="handleItemsPerPageChange" />
    </template>

    <dp-data-table
      ref="dataTable"
      :header-fields="headerFields"
      :items="rowItems"
      :translations="{ lockedForSelection: Translator.trans('add_orga.email_hint') }"
      class="mt-2"
      lock-checkbox-by="hasNoEmail"
      track-by="id"
      is-expandable
      is-selectable
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
    </dp-data-table>
    <div class="mt-2 pt-2 flex">
      <div class="w-1/3 inline-block">
            <span
              v-if="selectedItems.length"
              class="weight--bold line-height--1_6">
              {{ selectedItemsText }}
            </span>
      </div>
      <div class="w-2/3 text-right inline-block space-x-2">
        <dp-button
          :text="Translator.trans('invitable_institution.add')"
          data-cy="addPublicAgency"
          @click="addPublicInterestBodies(selectedItems)"/>
        <a
          :href="Routing.generate('DemosPlan_procedure_member_index', { procedure: procedureId })"
          data-cy="organisationList:abortAndBack"
          class="btn btn--secondary">
          {{ Translator.trans('abort.and.back') }}
        </a>
      </div>
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton, DpCheckbox, DpDataTable, DpFlyout, DpIcon, DpPager, DpSearchField  } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { filterCategoriesStorage } from '@DpJs/lib/procedure/FilterFlyout/filterStorage'
import { filterCategoryHelpers } from '@DpJs/lib/procedure/FilterFlyout/filterHelpers'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

export default {
  name: 'DpAddOrganisationList',

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
    FilterFlyout,
    DpIcon,
    DpPager,
    DpSearchField
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

  mixins: [paginationMixin],

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 50
      },
      filterManager: null,
      initiallySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      invitableToebFields: [
        'legalName',
        'participationFeedbackEmailAddress',
        'locationContacts',
        ...(hasPermission('feature_institution_tag_read') ? ['assignedTags'] : [])
      ],
      isLoading: true,
      itemsPerPage: 50,
      itemsPerPageOptions: [10, 50, 100, 200],
      locationContactFields: ['street', 'postalcode', 'city'],
      selectedItems: [],
      searchTerm: '',
      pagination: {}
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
      return Object.values(this.invitableToebItems).map(item => {
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

    totalItems () {
      return this.pagination.total || 0
    },

    totalPages () {
      return this.pagination.totalPages || 0
    },

    currentPage () {
      return this.pagination.currentPage || 1
    },

    itemsPerPage () {
      return this.pagination.perPage || this.defaultPagination.perPage
    },

    itemsPerPageOptions () {
      return this.pagination.limits || this.defaultPagination.limits
    },

    storageKeyPagination () {
      return `addOrganisationList:${this.procedureId}:pagination`
    },

    selectedItemsText () {
      const count = this.selectedItems.length
      const translationKey = count === 1 ? 'entry.selected' : 'entries.selected'
      return `${count} ${Translator.trans(translationKey)}`
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
      this.filterManager.applyFilter(filter, categoryId)
    },

    createFilterOptions (params) {
      this.filterManager.createFilterOptions(params)
    },

    setFilterOptionsFromFilterQuery () {
      this.filterManager.setFilterOptionsFromFilterQuery()
    },

    getInstitutionsByPage (page = 1, categoryId = null) {
      if (categoryId) {
        this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })
      }
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
          InvitableToeb: this.invitableToebFields.concat(this.returnPermissionChecksValuesArray(permissionChecksToeb)).join(),
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
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        if (hasPermission('field_organisation_competence')) {
          filters.competencefilter = {
            condition: {
              path: 'competenceDescription',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        if (hasPermission('feature_institution_tag_read')) {
          filters.tagfilter = {
            condition: {
              path: 'assignedTags.name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        filters.searchFieldsGroup = {
          group: {
            conjunction: 'OR'
          }
        }

        requestParams.filter = filters
      }

      return this.getInstitutions(requestParams)
        .then(data => {
          this.setLocalStorage(data.meta.pagination)
          this.updatePagination(data.meta.pagination)
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

    handleChange (filterCategoryName, isSelected) {
      this.filterManager.handleChange(filterCategoryName, isSelected)
    },

    hasAdress () {
      return this.rowItems.locationContacts?.street || this.rowItems.locationContacts?.postalcode || this.rowItems.locationContacts?.city
    },

    handleSearch (searchValue) {
      this.searchTerm = searchValue
      this.getInstitutionsWithContacts(1)
        .then(() => {
          this.isLoading = false
        })
    },

    handleReset () {
      this.searchTerm = ''
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

    setFilterQueryFromStorage () {
      return this.filterManager.setFilterQueryFromStorage()
    },

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    },

    setInitiallySelectedFilterCategories () {
      const selectedFilterCategoriesInStorage = filterCategoriesStorage.get()
      this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null
        ? selectedFilterCategoriesInStorage
        : this.institutionTagCategoriesValues.slice(0, 5).map(category => category.attributes.name)
    },

    toggleAllSelectedFilterCategories () {
      this.filterManager.toggleAllCategories()
    }
  },

  mounted () {
    this.initPagination()
    this.filterManager = filterCategoryHelpers.createFilterManager(this)
    this.isLoading = true

    this.filterManager.setAppliedFilterQueryFromStorage()
    this.filterManager.setFilterQueryFromStorage()

    const promises = [
      this.getInstitutionsWithContacts(this.pagination.currentPage),
      this.getInstitutionTagCategories(true)
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  }
}
</script>
