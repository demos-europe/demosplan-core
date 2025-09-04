<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div
    ref="contentArea"
    class="mt-2"
  >
    <dp-loading
      v-if="isLoading"
      class="mt-4"
    />

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-1">
        <dp-search-field
          ref="searchField"
          class="h-fit mt-1 col-span-1 sm:col-span-3"
          data-cy="addOrganisationList:searchField"
          input-width="u-1-of-1"
          @reset="handleReset"
          @search="handleSearch"
        />

        <client-side-tag-filter
          v-if="hasPermission('feature_institution_tag_read')"
          :filter-categories="allFilterCategories"
          :raw-items="rowItems"
          :search-applied="isSearchApplied"
          @items-filtered="filteredItems = $event"
          @reset="resetSearch"
        />

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
      @size-change="handleItemsPerPageChange"
    />

    <dp-data-table
      ref="DpDataTable"
      class="mt-2"
      :header-fields="headerFields"
      is-expandable
      is-selectable
      :items="filteredItems || rowItems"
      lock-checkbox-by="hasNoEmail"
      track-by="id"
      :translations="{ lockedForSelection: Translator.trans('add_orga.email_hint') }"
      @items-selected="setSelectedItems"
    >
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
                class="ml-0"
              >
                {{ locationContacts.street }}
              </dd>
              <dd
                v-if="locationContacts.postalcode"
                class="ml-0"
              >
                {{ locationContacts.postalcode }}
              </dd>
              <dd
                v-if="locationContacts.city"
                class="ml-0"
              >
                {{ locationContacts.city }}
              </dd>
            </template>
            <dd
              v-else
              class="ml-0"
            >
              {{ Translator.trans('notspecified') }}
            </dd>
          </dl>
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('phone') }}
            </dt>
            <dd
              v-if="locationContacts?.hasOwnProperty('phone') && locationContacts.phone"
              class="ml-0"
            >
              {{ locationContacts.phone }}
            </dd>
            <dd
              v-else
              class="ml-0"
            >
              {{ Translator.trans('notspecified') }}
            </dd>
            <dt class="color--grey mt-2">
              {{ Translator.trans('email.participation') }}
            </dt>
            <dd
              v-if="participationFeedbackEmailAddress"
              class="ml-0"
            >
              {{ participationFeedbackEmailAddress }}
            </dd>
            <dd
              v-else
              class="ml-0"
            >
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
            class="pl-4 w-full"
          >
            <dt class="color--grey">
              {{ Translator.trans('tags') }}
            </dt>
            <dd class="ml-0">
              <div class="flex flex-wrap gap-1 mt-1">
                <span>
                  {{ assignedTags.map(tag => tag.name).join(', ') }}
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
  DpDataTable,
  DpLoading,
  DpPager,
  DpSearchField,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapState } from 'vuex'
import ClientSideTagFilter from '@DpJs/components/procedure/admin/InstitutionTagManagement/ClientSideTagFilter'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

export default {
  name: 'OrganisationTable',

  components: {
    ClientSideTagFilter,
    DpDataTable,
    DpLoading,
    DpPager,
    DpSearchField,
  },

  mixins: [paginationMixin],

  props: {
    headerFields: {
      type: Array,
      required: true,
    },

    procedureId: {
      type: String,
      required: true,
    },

    resourceType: {
      type: String,
      required: true,
      validator: value => ['InvitableToeb', 'InvitedToeb'].includes(value),
    },
  },

  emits: [
    'selectedItems',
  ],

  data () {
    return {
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 50,
      },
      filteredItems: null,
      isLoading: true,
      locationContactFields: ['street', 'postalcode', 'city'],
      pagination: {},
      searchTerm: '',
      selectedItems: [],
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', {
      filterQuery: 'getFilterQuery',
    }),

    ...mapState('InstitutionLocationContact', {
      institutionLocationContactItems: 'items',
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items',
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items',
    }),

    allFilterCategories () {
      return (Object.values(this.institutionTagCategories) || [])
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
            memberOf: groupKey,
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

    isSearchApplied () {
      return this.searchTerm !== ''
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
          name: this.institutionTagItems?.[tag.id]?.attributes?.name || Translator.trans('error.tag.notfound'),
        }))

        return {
          id: item.id,
          ...item.attributes,

          // Add icon for hasReceivedInvitationMailInCurrentProcedurePhase
          hasReceivedInvitationMailInCurrentProcedurePhase:
            item.attributes.hasReceivedInvitationMailInCurrentProcedurePhase ?
              '<i class="fa fa-check-circle text-[#4c8b22]" ></i>' :
              '',
          originalStatementsCountInProcedure: item.attributes.originalStatementsCountInProcedure ||
            '-',
          competenceDescription: item.attributes.competenceDescription === '-' ? '' : item.attributes.competenceDescription,
          locationContacts: locationContact ?
            {
              id: locationContact.id,
              ...locationContact.attributes,
            } :
            null,
          assignedTags: institutionTags,
          hasNoEmail,
        }
      })
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
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list',
    }),

    getInstitutionTagCategories (isInitial = false) {
      if (!hasPermission('feature_institution_tag_read')) {
        return
      }

      return this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'creationDate',
            'name',
            'tags',
          ].join(),
          InstitutionTag: [
            'creationDate',
            'isUsed',
            'name',
            'category',
          ].join(),
        },
        include: [
          'tags',
          'tags.category',
        ].join(),
      })
        .then(() => {
          // Copy the object to avoid issues with filter requests that update the categories in the store
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

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
        { permission: 'field_organisation_competence', value: 'competenceDescription' },
      ]

      const permissionChecksContact = [
        { permission: 'field_organisation_phone', value: 'phone' },
      ]

      const includeParams = hasPermission('feature_institution_tag_read') ?
        ['locationContacts', 'assignedTags'] :
        ['locationContacts']

      const requestParams = {
        page: {
          number: page,
          size: this.pagination.perPage,
        },
        include: includeParams.join(),
        fields: {
          [this.resourceType]: this.apiRequestFields.concat(this.returnPermissionChecksValuesArray(permissionChecksToeb)).join(),
          InstitutionLocationContact: this.locationContactFields.concat(this.returnPermissionChecksValuesArray(permissionChecksContact)).join(),
        },
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
            },
          },
        }

        if (hasPermission('field_organisation_competence')) {
          filters.competencefilter = {
            condition: {
              path: 'competenceDescription',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            },
          }
        }

        if (hasPermission('feature_institution_tag_read')) {
          filters.tagfilter = {
            condition: {
              path: 'assignedTags.name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            },
          }
        }

        filters.searchFieldsGroup = {
          group: {
            conjunction: 'OR',
          },
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

    resetSearch () {
      this.$refs.searchField.handleReset()
    },

    resetSelection () {
      this.$refs.DpDataTable.resetSelection()
      this.$refs.DpDataTable.elementSelections = {}
      this.$refs.DpDataTable.selectedElements = []
    },

    returnPermissionChecksValuesArray (permissionChecks) {
      return permissionChecks.reduce((acc, check) => {
        if (hasPermission(check.permission)) {
          acc.push(check.value)
        }
        return acc
      }, [])
    },

    setSelectedItems (items) {
      this.$emit('selectedItems', items)
    },
  },

  mounted () {
    this.initPagination()
    this.getInstitutionsWithContacts()

    const promises = [
      this.getInstitutionTagCategories(true),
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  },
}
</script>
