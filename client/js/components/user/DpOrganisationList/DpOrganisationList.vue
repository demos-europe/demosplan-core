<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="mt-2">
    <!-- Pending organisations list (renders only when the orga-self-registration feature is enabled) -->
    <template v-if="hasPermission('area_organisations_applications_manage')">
      <h3 class="u-mt-2">
        {{ Translator.trans('organisations.pending') }}
      </h3>
      <template v-if="pendingOrganisationsLoading">
        <dp-loading
          v-if="isInitialLoad"
          class="u-ml u-mt u-mb-2"
        />
        <dp-skeleton-box
          v-for="(idx) in pendingOrgs"
          v-else
          :key="`skeleton:${idx}`"
          class="mb-2"
          height="54px"
        />
      </template>
      <template v-else-if="Object.keys(pendingOrgs).length">
        <ul
          class="o-list space-y-2 u-mb"
          data-cy="pendingOrganisationList"
        >
          <dp-organisation-list-item
            v-for="(item, idx) in pendingOrgs"
            :key="`pendingOrganisation:${idx}`"
            :additional-field-options="additionalFieldOptions"
            :available-orga-types="availableOrgaTypes"
            class="o-list__item bg-surface border border-neutral"
            data-cy="pendingOrganisationListBlk"
            module-name="Pending"
            :organisation="item"
            :selectable="false"
            @addon-options:loaded="setAdditionalFieldOptions"
            @items:get="fetchPendingAndAllOrganisations()"
          />
        </ul>
        <dp-sliding-pagination
          v-if="pendingOrganisationsTotalPages > 1"
          :current="pendingOrganisationsCurrentPage"
          :total="pendingOrganisationsTotalPages"
          :non-sliding-size="10"
          @page-change="(page) => getItemsByPage(page, true)"
        />
      </template>
      <p
        v-else-if="Object.keys(pendingOrgs).length === 0 && pendingOrganisationsLoading === false"
        class="color--grey u-mb-2"
      >
        {{ Translator.trans('organisations.pending.none') }}
      </p>

      <!-- List of all items -->
      <!-- show headline only if we have two sections -->
      <h3 class="u-mt-2">
        {{ Translator.trans('organisations.all') }}
      </h3>
    </template>

    <!-- list header -->
    <div class="flex flex-wrap items-center justify-between gap-2 py-2">
      <div class="w-full md:w-3/12">
        <dp-search-field
          @search="handleSearch"
          @reset="resetSearch"
        />
      </div>
      <dp-checkbox-group
        data-cy="organisationList:filterItems"
        :label="filterLabel"
        :options="filterItems"
        inline
        @update="handleFilter"
      />
    </div>
    <div
      v-if="canEditTwoFactor"
      class="flex items-center gap-1 py-2"
    >
      <dp-toggle
        :aria-label="Translator.trans('2fa.required.filter.without')"
        :model-value="showWithoutTwoFactorOnly"
        data-cy="organisationList:withoutTwoFactorFilter"
        @update:model-value="handleTwoFactorFilter"
      />
      <span aria-hidden="true">{{ Translator.trans('2fa.required.filter.without') }}</span>
    </div>

    <template v-if="canBulkEdit">
      <selection-card
        v-if="isOrganisationSelected"
        :count-text="Translator.trans('organisations.selected', { count: selectedCount })"
        :is-loading-items="isLoadingSelectionList"
        :items="selectedOrganisationsList"
        :show-items="showSelectionList"
        data-cy="organisationList:selectionCard"
        @remove:item="toggleOne"
        @reset="resetSelection"
        @toggle:items="toggleSelectionList"
      >
        <template v-slot:actions>
          <dp-button
            v-if="hasPermission('feature_orga_delete')"
            :disabled="isDeleting || isUpdatingTwoFactor || isLoadingSelectionList"
            :text="Translator.trans('delete') + ` (${selectedCount})`"
            class="p-1"
            color="primary"
            data-cy="deleteSelectedItems"
            variant="outline"
            @click.prevent="deleteItems"
          />
          <dp-button
            v-if="canEditTwoFactor"
            :disabled="isDeleting || isUpdatingTwoFactor || isLoadingSelectionList"
            :text="Translator.trans('2fa.required.change') + ` (${selectedCount})`"
            class="p-1"
            color="primary"
            data-cy="organisationList:changeTwoFactor"
            @click="openTwoFactorModal"
          />
        </template>
      </selection-card>
      <dp-checkbox
        id="select_all"
        :checked="isAllSelected"
        :label="{ text: Translator.trans('select.all') }"
        class="my-4 ml-2"
        data-cy="allSelected"
        @change="toggleAll(!isAllSelected)"
      />
    </template>

    <dp-modal
      v-if="canEditTwoFactor"
      ref="twoFactorModal"
      content-classes="w-2/5"
      data-cy="organisationList:twoFactorModal"
      @modal:toggled="onTwoFactorModalToggled"
    >
      <template v-slot:header>
        <h2 class="mb-0">
          {{ Translator.trans('2fa.required.change') }}
        </h2>
      </template>
      <dp-inline-notification
        v-if="hasMixedTwoFactorValues"
        :message="Translator.trans('2fa.required.mixed.hint')"
        class="mb-3"
        data-cy="organisationList:twoFactorMixedHint"
        type="info"
      />
      <p>
        {{ Translator.trans('2fa.required.bulk.explanation') }}
      </p>
      <dp-radio
        id="organisationList:twoFactorRequired:mandatory"
        :checked="twoFactorTarget === true"
        :label="{ text: Translator.trans('2fa.required.mandatory'), hint: Translator.trans('2fa.required.mandatory.hint') }"
        data-cy="organisationList:setTwoFactorRequired"
        name="organisationList:twoFactorRequired"
        value="1"
        @change="twoFactorTarget = true"
      />
      <dp-radio
        id="organisationList:twoFactorRequired:optional"
        :checked="twoFactorTarget === false"
        :label="{ text: Translator.trans('2fa.required.optional'), hint: Translator.trans('2fa.required.optional.hint') }"
        class="mt-2"
        data-cy="organisationList:unsetTwoFactorRequired"
        name="organisationList:twoFactorRequired"
        value="0"
        @change="twoFactorTarget = false"
      />
      <template v-slot:footer>
        <dp-button-row
          :busy="isUpdatingTwoFactor"
          :disabled="{ primary: !isTwoFactorChoiceChanged || isUpdatingTwoFactor, secondary: isUpdatingTwoFactor }"
          data-cy="organisationList:twoFactorButtons"
          primary
          secondary
          secondary-btn-variant="outline"
          @primary-action="applyTwoFactorRequired"
          @secondary-action="closeTwoFactorModal"
        />
      </template>
    </dp-modal>
    <div
      v-if="noResults"
      v-cleanhtml="isFiltered ? Translator.trans('search.results.none') : Translator.trans('search.no.results', {searchterm: searchTerm})"
      class="u-mt-0_75"
    />

    <!-- Organisations list -->
    <template v-if="isLoading">
      <dp-loading
        v-if="isInitialLoad"
        class="u-ml u-mt u-mb-2"
      />
      <dp-skeleton-box
        v-for="(idx) in items"
        v-else
        :key="`skeleton:${idx}`"
        class="u-mb-0_5"
        height="54px"
      />
    </template>
    <div
      v-else
      class="layout"
    >
      <div
        class="layout__item u-1-of-1"
        data-cy="organisationList"
      >
        <ul class="o-list space-y-2 u-mb">
          <dp-organisation-list-item
            v-for="(item, idx) in items"
            :key="`organisation:${idx}`"
            :additional-field-options="additionalFieldOptions"
            :available-orga-types="availableOrgaTypes"
            class="o-list__item bg-surface border border-neutral"
            data-cy="organisationListBlk"
            :selected="isSelected(item.id)"
            :selectable="canBulkEdit"
            :organisation="item"
            @addon-options:loaded="setAdditionalFieldOptions"
            @item:selected="toggleOne"
            @items:get="fetchPendingAndAllOrganisations()"
          />
        </ul>

        <dp-sliding-pagination
          v-if="totalPages > 1"
          :current="currentPage"
          :total="totalPages"
          :non-sliding-size="10"
          @page-change="getItemsByPage"
        />
      </div>
    </div>
  </div>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpButton,
  DpButtonRow,
  DpCheckbox,
  DpCheckboxGroup,
  DpInlineNotification,
  DpLoading,
  DpModal,
  DpRadio,
  dpRpc,
  DpSearchField,
  DpSkeletonBox,
  DpSlidingPagination,
  DpToggle,
  hasPermission,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import DpOrganisationListItem from './DpOrganisationListItem.vue'
import SelectionCard from '@DpJs/components/shared/SelectionCard.vue'

// Upper limit the API accepts for page[size]
const ALL_MATCHING_PAGE_SIZE = 1000

// The API answers too many parallel requests with 429
const DELETE_BATCH_SIZE = 5

const orgaFieldsArrays = {
  Branding: [
    'cssvars',
  ],
  Customer: [
    'name',
    'subdomain',
  ],
  Orga: [
    'addressExtension',
    'ccEmail2',
    'city',
    'competence',
    'contactPerson',
    'copy',
    'copySpec',
    'currentSlug',
    'dataProtection',
    'emailNotificationEndingPhase',
    'emailNotificationNewStatement',
    'email2',
    'houseNumber',
    'imprint',
    'isPlanningOrganisation',
    'name',
    'participationEmail',
    'phone',
    'postalcode',
    'registrationStatuses',
    'reviewerEmail',
    'showlist',
    'showname',
    'state',
    'statusInCustomers',
    'street',
    'submissionType',
    'types',
  ],
  OrgaStatusInCustomer: [
    'customer',
    'status',
  ],
}

if (hasPermission('feature_orga_branding_edit')) {
  orgaFieldsArrays.Branding = ['cssvars']
  orgaFieldsArrays.Orga.push('branding')
}

if (hasPermission('feature_manage_procedure_creation_permission')) {
  orgaFieldsArrays.Orga.push('canCreateProcedures')
}

if (hasPermission('field_organisation_two_factor_required')) {
  orgaFieldsArrays.Orga.push('twoFactorRequired')
}

const orgaFields = {
  Branding: orgaFieldsArrays.Branding.join(),
  Customer: orgaFieldsArrays.Customer.join(),
  Orga: orgaFieldsArrays.Orga.join(),
  OrgaStatusInCustomer: orgaFieldsArrays.OrgaStatusInCustomer.join(),
}

const includeFields = [
  'currentSlug',
  'statusInCustomers.customer',
  'statusInCustomers',
]

if (hasPermission('feature_orga_branding_edit')) {
  includeFields.push('branding')
}

export default {
  name: 'DpOrganisationList',

  components: {
    DpButton,
    DpButtonRow,
    DpCheckbox,
    DpCheckboxGroup,
    DpInlineNotification,
    DpLoading,
    DpModal,
    DpOrganisationListItem,
    DpRadio,
    DpSearchField,
    DpSkeletonBox,
    DpSlidingPagination,
    DpToggle,
    SelectionCard,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  /**
   * These are needed in deeply nested components, passing them via props seemed too messy
   */
  provide () {
    return {
      proceduresDirectLinkPrefix: this.proceduresDirectLinkPrefix,
      projectName: this.projectName,
      subdomain: this.subdomain,
      submissionTypeDefault: this.submissionTypeDefault,
      submissionTypeShort: this.submissionTypeShort,
      showNewStatementNotification: this.showNewStatementNotification,
      writableFields: this.writableFields,
    }
  },

  props: {
    availableOrgaTypes: {
      type: Array,
      required: false,
      default: () => [],
    },

    /**
     * Needed for orgaSlug
     */
    proceduresDirectLinkPrefix: {
      type: String,
      required: false,
      default: '',
    },

    /**
     * Needed for translationKey in DpOrganisationFormFields
     */
    projectName: {
      type: String,
      required: false,
      default: '',
    },

    showNewStatementNotification: {
      type: Boolean,
      required: false,
      default: false,
    },

    subdomain: {
      type: String,
      required: false,
      default: '',
    },

    submissionTypeDefault: {
      type: String,
      required: false,
      default: '',
    },

    submissionTypeShort: {
      type: String,
      required: false,
      default: '',
    },

    writableFields: {
      type: Array,
      required: false,
      default: () => [],
    },
  },

  data () {
    return {
      additionalFieldOptions: [],
      // Organisations matching the current filter, across all pages
      allMatchingCount: 0,
      allMatchingFetched: false,
      // Organisations unticked after 'select all' is selected; only used while trackDeselected is true
      deselectedIds: [],
      filterItems: this.availableOrgaTypes.map(el => ({ id: el.value, label: Translator.trans(el.label) })),
      filterLabel: Translator.trans('organisation.kind') + ':',
      isInitialLoad: true,
      isLoading: true,
      isLoadingSelectionList: false,
      isDeleting: false,
      isUpdatingTwoFactor: false,
      noResults: false,
      pendingOrgs: {},
      pendingOrganisationsLoading: true,
      searchTerm: '',
      selectedFilters: {},
      /**
       * Keyed by id, holds { id, name, twoFactorRequired } so the selection survives paging, and the
       * selection list and the mixed-values hint work without the items of other pages
       */
      selectedOrganisations: {},
      showSelectionList: false,
      showWithoutTwoFactorOnly: false,
      // If true: everything matching the filter is selected except deselectedIds; selectedOrganisations is loaded on demand
      trackDeselected: false,
      // The value chosen in the modal
      twoFactorTarget: null,
    }
  },

  computed: {
    ...mapState('Orga', {
      items: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages',
    }),

    ...mapState('Orga/Pending', {
      pendingOrganisations: 'items',
      pendingOrganisationsCurrentPage: 'currentPage',
      pendingOrganisationsTotalPages: 'totalPages',
    }),

    canBulkEdit () {
      return hasPermission('feature_orga_delete') || this.canEditTwoFactor
    },

    canEditTwoFactor () {
      return hasPermission('field_organisation_two_factor_required')
    },

    hasMixedTwoFactorValues () {
      return this.selectedTwoFactorValues.size > 1
    },

    selectedTwoFactorValues () {
      return new Set(this.selectedOrganisationsList.map(organisation => organisation.twoFactorRequired === true))
    },

    isAllSelected () {
      return this.trackDeselected && this.deselectedIds.length === 0
    },

    isFiltered () {
      return Object.keys(this.selectedFilters).length > 0
    },

    isOrganisationSelected () {
      return this.selectedCount > 0
    },

    /**
     * A choice counts as a change when at least one selected organisation holds a different value
     */
    isTwoFactorChoiceChanged () {
      return this.twoFactorTarget !== null &&
        (this.hasMixedTwoFactorValues || !this.selectedTwoFactorValues.has(this.twoFactorTarget))
    },

    selectedCount () {
      if (this.trackDeselected && !this.allMatchingFetched) {
        return Math.max(this.allMatchingCount - this.deselectedIds.length, 0)
      }

      return Object.keys(this.selectedOrganisations).length
    },

    selectedOrganisationsList () {
      return Object.values(this.selectedOrganisations)
    },
  },

  methods: {
    ...mapActions('Department', {
      departmentList: 'list',
    }),

    ...mapActions('Orga', {
      list: 'list',
      deleteOrganisation: 'delete',
    }),

    ...mapActions('Orga/Pending', {
      pendingOrganisationList: 'list',
    }),

    ...mapActions('Role', {
      roleList: 'list',
    }),

    /**
     * Writes the value chosen in the modal to every selected organisation in one request.
     * The backend reports which ids it could not administrate; those count as failed.
     */
    async applyTwoFactorRequired () {
      if (!this.isTwoFactorChoiceChanged || !this.isOrganisationSelected || this.isUpdatingTwoFactor) {
        return
      }

      this.isUpdatingTwoFactor = true
      let updatedCount = 0
      let failedCount = this.selectedCount

      try {
        const response = await dpRpc('organisations.bulk.twoFactorRequired', {
          organisationIds: await this.resolveSelectedIds(),
          twoFactorRequired: this.twoFactorTarget,
        })
        const result = response.data?.[0]?.result

        if (result) {
          updatedCount = result.updatedIds.length
          failedCount = result.skippedIds.length
        }
      } catch (error) {
        console.error('Bulk two-factor change failed:', error)
      }

      if (updatedCount > 0) {
        dplan.notify.notify('confirm', Translator.trans('2fa.required.updated', { count: updatedCount }))
      }

      if (failedCount > 0) {
        dplan.notify.notify('error', Translator.trans('2fa.required.update.failed', { count: failedCount }))
      }

      this.isUpdatingTwoFactor = false
      this.closeTwoFactorModal()
      this.resetSelection()
      this.fetchPendingAndAllOrganisations()
    },

    /**
     * Search term, orga types and the two-factor toggle combine, so every page request sends all of them
     */
    buildListFilter () {
      const filter = {
        namefilter: {
          condition: {
            path: 'name',
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            value: this.searchTerm,
          },
        },
      }
      const selectedTypes = Object.keys(this.selectedFilters).filter(type => this.selectedFilters[type])

      if (selectedTypes.length > 0) {
        filter.orgaType = {
          group: {
            conjunction: 'OR',
          },
        }
        filter.orgaStatus = {
          condition: {
            path: 'statusInCustomers.status',
            operator: '<>',
            value: 'rejected',
          },
        }
        selectedTypes.forEach(type => {
          filter[type] = {
            condition: {
              path: 'statusInCustomers.orgaType.name',
              value: type,
              memberOf: 'orgaType',
            },
          }
        })
      }

      if (this.showWithoutTwoFactorOnly) {
        filter.withoutTwoFactor = {
          condition: {
            path: 'twoFactorRequired',
            value: false,
          },
        }
      }

      return filter
    },

    buildSelectionFilter () {
      if (this.trackDeselected) {
        return this.buildListFilter()
      }

      return {
        selectedIds: {
          condition: {
            path: 'id',
            operator: 'IN',
            value: Object.keys(this.selectedOrganisations),
          },
        },
      }
    },

    closeTwoFactorModal () {
      this.$refs.twoFactorModal?.toggle()
    },

    async deleteItems () {
      if (this.isDeleting || !this.isOrganisationSelected || dpconfirm(Translator.trans('check.organisations.delete', { count: this.selectedCount })) === false) {
        return
      }

      this.isDeleting = true

      try {
        const ids = await this.resolveSelectedIds()

        // Delete in batches of 5 requests
        for (let i = 0; i < ids.length; i += DELETE_BATCH_SIZE) {
          const currentBatchRequests = ids
            .slice(i, i + DELETE_BATCH_SIZE)
            .map(id => this.deleteOrganisation(id))

          await Promise.allSettled(currentBatchRequests)
        }
      } catch (error) {
        console.error('Bulk delete of organisations failed:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))

        return
      } finally {
        this.isDeleting = false
      }

      this.resetSelection()
      this.fetchPendingAndAllOrganisations()
    },

    /**
     * While all are selected, the selection only holds a count; names and 2FA values are loaded when needed
     */
    async ensureSelectionLoaded () {
      if (this.trackDeselected && !this.allMatchingFetched) {
        await this.fetchSelectedOrganisations()
      }
    },

    /**
     * Pages through the API directly, as the store would replace the page currently shown
     */
    async fetchSelectedOrganisations () {
      const url = Routing.generate('api_resource_list', { resourceType: 'Orga' })
      const fields = [
        'name',
        ...(this.canEditTwoFactor ? ['twoFactorRequired'] : []),
      ].join()
      const organisations = []
      let page = 1
      let totalPages = 1

      while (page <= totalPages) {
        const response = await dpApi.get(url, {
          fields: {
            Orga: fields,
          },
          filter: this.buildSelectionFilter(),
          page: {
            number: page,
            size: ALL_MATCHING_PAGE_SIZE,
          },
          sort: 'name',
        })

        organisations.push(...(response.data?.data || []))
        totalPages = response.data?.meta?.pagination?.total_pages ?? 1
        page++
      }

      this.selectedOrganisations = organisations.reduce((acc, item) => {
        if (!this.deselectedIds.includes(item.id)) {
          acc[item.id] = this.toSelectionEntry(item)
        }

        return acc
      }, {})
      this.allMatchingFetched = this.trackDeselected
    },

    fetchFilteredOrganisations (page) {
      this.isLoading = true
      this.noResults = false

      this.list({
        page: {
          number: page,
        },
        sort: 'name',
        filter: this.buildListFilter(),
        fields: {
          Customer: orgaFields.Customer,
          Orga: orgaFields.Orga,
          OrgaStatusInCustomer: orgaFields.OrgaStatusInCustomer,
          ...(orgaFields.Branding ? { Branding: orgaFields.Branding } : {}),
        },
        include: includeFields.join(),
      })
        .then(response => {
          this.noResults = Object.keys(this.items || {}).length === 0
          this.allMatchingCount = response?.meta?.pagination?.total ?? 0
          this.isLoading = false
        })
    },

    fetchAllOrganisations (page) {
      this.isLoading = true
      this.noResults = false

      this.list({
        page: {
          number: page,
        },
        fields: {
          Customer: orgaFields.Customer,
          Orga: orgaFields.Orga,
          OrgaStatusInCustomer: orgaFields.OrgaStatusInCustomer,
          ...(orgaFields.Branding ? { Branding: orgaFields.Branding } : {}),
        },
        sort: 'name',
        filter: this.buildListFilter(),
        include: includeFields.join(),
      })
        .then(response => {
          this.allMatchingCount = response?.meta?.pagination?.total ?? 0
          this.noResults = Object.keys(this.items || {}).length === 0
          if (this.isInitialLoad) {
            this.isInitialLoad = false
          }
        })
        .finally(() => {
          this.isLoading = false
        })
    },

    fetchPendingOrganisations (page) {
      this.pendingOrganisationsLoading = true

      this.pendingOrganisationList({
        page: {
          number: page,
        },
        fields: {
          Customer: orgaFields.Customer,
          Orga: orgaFields.Orga,
          OrgaStatusInCustomer: orgaFields.OrgaStatusInCustomer,
          ...(orgaFields.Branding ? { Branding: orgaFields.Branding } : {}),
        },
        sort: 'name',
        include: includeFields.join(),
      })
        .then(() => {
          this.pendingOrgs = this.pendingOrganisations || {}
        })
        .finally(() => {
          this.pendingOrganisationsLoading = false
        })
    },

    fetchPendingAndAllOrganisations (page) {
      page = page || this.currentPage
      this.pendingOrganisationsLoading = true

      this.pendingOrganisationList({
        page: {
          number: page,
        },
        include: ['currentSlug', 'orgasInCustomer.customer'].join(),
      })
        .then(() => {
          this.getItemsByPage(page)
        })
        .then(() => {
          this.pendingOrgs = this.pendingOrganisations || {}
        })
        .finally(() => {
          this.pendingOrganisationsLoading = false
        })
    },

    getItemsByPage (page, isPending) {
      page = page || this.currentPage

      if (isPending) {
        this.fetchPendingOrganisations(page)
      } else {
        if (this.isFiltered) {
          this.fetchFilteredOrganisations(page)
        } else {
          this.fetchAllOrganisations(page)
        }
      }
    },

    handleFilter (selected) {
      this.resetSelectAll()
      this.selectedFilters = selected
      this.fetchFilteredOrganisations(1)
    },

    handleSearch (searchTerm) {
      this.resetSelectAll()
      this.searchTerm = searchTerm
      this.getItemsByPage(1)
    },

    handleTwoFactorFilter (showWithoutTwoFactorOnly) {
      this.resetSelectAll()
      this.showWithoutTwoFactorOnly = showWithoutTwoFactorOnly
      this.getItemsByPage(1)
    },

    isSelected (id) {
      if (this.trackDeselected) {
        return !this.deselectedIds.includes(id)
      }

      return this.selectedOrganisations[id] !== undefined
    },

    /**
     * @param {Boolean} reload  Fetch current values even if already loaded
     * @return {Promise<Boolean>} false when loading the selected organisations failed
     */
    async loadSelection (reload = false) {
      if (this.isLoadingSelectionList) {
        return false
      }

      this.isLoadingSelectionList = true

      try {
        await (reload ? this.fetchSelectedOrganisations() : this.ensureSelectionLoaded())

        return true
      } catch (error) {
        console.error('Failed to load the selected organisations:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))

        return false
      } finally {
        this.isLoadingSelectionList = false
      }
    },

    onTwoFactorModalToggled (isOpen) {
      if (!isOpen) {
        this.twoFactorTarget = null
      }
    },

    /**
     * Preselects the value all selected organisations share, nothing when they differ
     */
    async openTwoFactorModal () {
      if (!this.isOrganisationSelected) {
        return
      }

      // Organisations may have been edited since they were selected
      if (!await this.loadSelection(true)) {
        return
      }

      this.twoFactorTarget = this.hasMixedTwoFactorValues ? null : [...this.selectedTwoFactorValues][0]
      this.$refs.twoFactorModal?.toggle()
    },

    removeFromSelection (id) {
      const rest = { ...this.selectedOrganisations }

      delete rest[id]

      this.selectedOrganisations = rest
    },

    resetSelection () {
      this.allMatchingFetched = false
      this.deselectedIds = []
      this.selectedOrganisations = {}
      this.showSelectionList = false
      this.trackDeselected = false
    },

    /**
     * "All" refers to the current filter, so a filter change invalidates it
     */
    resetSelectAll () {
      if (this.trackDeselected) {
        this.resetSelection()
      }
    },

    async resolveSelectedIds () {
      await this.ensureSelectionLoaded()

      return Object.keys(this.selectedOrganisations)
    },

    resetSearch () {
      this.resetSelectAll()
      this.searchTerm = ''
      this.noResults = false
      this.getItemsByPage()
    },

    setAdditionalFieldOptions (options) {
      this.additionalFieldOptions = options
    },

    /**
     * @param {Boolean} status  Select or deselect every organisation matching the current filter
     */
    toggleAll (status) {
      this.resetSelection()
      this.trackDeselected = status
    },

    toggleOne (id) {
      if (this.trackDeselected) {
        this.toggleInDeselectMode(id)

        return
      }

      if (this.isSelected(id)) {
        this.removeFromSelection(id)

        return
      }

      const item = this.items[id]

      if (!item) {
        return
      }

      this.selectedOrganisations = {
        ...this.selectedOrganisations,
        [id]: this.toSelectionEntry(item),
      }
    },

    toggleInDeselectMode (id) {
      if (!this.deselectedIds.includes(id)) {
        this.deselectedIds = [...this.deselectedIds, id]
        this.removeFromSelection(id)

        return
      }

      this.deselectedIds = this.deselectedIds.filter(deselectedId => deselectedId !== id)

      if (this.allMatchingFetched && this.items[id]) {
        this.selectedOrganisations = { ...this.selectedOrganisations, [id]: this.toSelectionEntry(this.items[id]) }
      }
    },

    async toggleSelectionList () {
      if (!this.showSelectionList && !await this.loadSelection()) {
        return
      }

      this.showSelectionList = !this.showSelectionList
    },

    toSelectionEntry (item) {
      return {
        id: item.id,
        name: item.attributes.name,
        twoFactorRequired: item.attributes.twoFactorRequired === true,
      }
    },
  },

  mounted () {
    this.fetchPendingAndAllOrganisations(1)
  },
}
</script>
