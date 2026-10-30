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
    <div class="flow-root">
      <dp-search-field
        class="inline-block u-pv-0_5"
        @search="handleSearch"
        @reset="resetSearch"
      />
      <dp-checkbox-group
        class="inline-block u-pv-0_5 float-right"
        data-cy="organisationList:filterItems"
        :label="filterLabel"
        :options="filterItems"
        inline
        @update="handleFilter"
      />
    </div>

    <template v-if="canBulkEdit">
      <dp-selection-card
        v-if="isOrganisationSelected"
        :count-text="Translator.trans('organisations.selected', { count: selectedCount })"
        :items="selectedOrganisationsList"
        :show-items="showSelectionList"
        data-cy="organisationList:selectionCard"
        @remove:item="toggleOne"
        @reset="resetSelection"
        @toggle:items="showSelectionList = !showSelectionList"
      >
        <template v-slot:actions>
          <dp-button
            v-if="hasPermission('feature_orga_delete')"
            :disabled="isUpdatingTwoFactor"
            :text="Translator.trans('delete') + ` (${selectedCount})`"
            class="p-1"
            color="primary"
            data-cy="deleteSelectedItems"
            variant="outline"
            @click.prevent="deleteItems"
          />
          <dp-button
            v-if="canEditTwoFactor"
            :disabled="isUpdatingTwoFactor"
            :text="Translator.trans('2fa.required.change') + ` (${selectedCount})`"
            class="p-1"
            color="primary"
            data-cy="organisationList:changeTwoFactor"
            @click="openTwoFactorModal"
          />
        </template>
      </dp-selection-card>
      <dp-checkbox
        id="select_all"
        :checked="allOnPageSelected"
        :label="{ text: Translator.trans('select.all.on.page') }"
        class="my-4 ml-2"
        data-cy="allSelected"
        @change="toggleAll(!allOnPageSelected)"
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
      v-cleanhtml="Translator.trans('search.no.results', {searchterm: searchTerm})"
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
  DpButton,
  DpButtonRow,
  DpCheckbox,
  DpCheckboxGroup,
  DpInlineNotification,
  DpLoading,
  DpModal,
  DpRadio,
  DpSearchField,
  DpSkeletonBox,
  DpSlidingPagination,
  hasPermission,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import DpOrganisationListItem from './DpOrganisationListItem'
import DpSelectionCard from '@DpJs/components/shared/DpSelectionCard'

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
    DpSelectionCard,
    DpSkeletonBox,
    DpSlidingPagination,
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
      filterItems: this.availableOrgaTypes.map(el => ({ id: el.value, label: Translator.trans(el.label) })),
      filterLabel: Translator.trans('organisation.kind') + ':',
      isInitialLoad: true,
      isLoading: true,
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

    allOnPageSelected () {
      const ids = Object.keys(this.items)

      return ids.length > 0 && ids.every(id => this.isSelected(id))
    },

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
      return this.selectedIds.length
    },

    selectedIds () {
      return Object.keys(this.selectedOrganisations)
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
     * Writes the value chosen in the modal to every selected organisation, one request each, then reports once.
     */
    async applyTwoFactorRequired () {
      if (!this.isTwoFactorChoiceChanged || !this.isOrganisationSelected || this.isUpdatingTwoFactor) {
        return
      }

      const required = this.twoFactorTarget

      this.isUpdatingTwoFactor = true

      const results = await Promise.allSettled(
        this.selectedIds.map(id => this.patchTwoFactorRequired(id, required)),
      )
      const failedCount = results.filter(result => result.status === 'rejected').length
      const successCount = results.length - failedCount

      if (successCount > 0) {
        dplan.notify.notify('confirm', Translator.trans('2fa.required.updated', { count: successCount }))
      }

      if (failedCount > 0) {
        dplan.notify.notify('error', Translator.trans('2fa.required.update.failed', { count: failedCount }))
      }

      this.isUpdatingTwoFactor = false
      this.closeTwoFactorModal()
      this.resetSelection()
      this.fetchPendingAndAllOrganisations()
    },

    closeTwoFactorModal () {
      this.$refs.twoFactorModal?.toggle()
    },

    deleteItems () {
      if (!this.isOrganisationSelected || dpconfirm(Translator.trans('check.entries.marked.delete')) === false) {
        return
      }

      const deleteOrganisations = this.selectedIds.map(id =>
        this.deleteOrganisation(id)
          .then(() => {
            this.removeFromSelection(id)
            // Confirm notification for organisations is done in BE
          }),
      )

      Promise.all(deleteOrganisations)
        .then(() => {
          this.fetchPendingAndAllOrganisations()
        })
    },

    fetchFilteredOrganisations (selected, page) {
      this.isLoading = true
      const filterObject = {}

      Object.keys(selected).forEach(filter => {
        if (selected[filter]) {
          filterObject[filter] = {
            condition: {
              path: 'statusInCustomers.orgaType.name',
              value: filter,
              memberOf: 'orgaType',
            },
          }

          filterObject.orgaStatus = {
            condition: {
              path: 'statusInCustomers.status',
              operator: '<>',
              value: 'rejected',
            },
          }
        }
      })
      filterObject.orgaType = {
        group: {
          conjunction: 'OR',
        },
      }

      filterObject.namefilter = {
        condition: {
          path: 'name',
          operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
          value: this.searchTerm,
        },
      }

      this.list({
        page: {
          number: page,
        },
        sort: 'name',
        filter: filterObject,
        fields: {
          Customer: orgaFields.Customer,
          Orga: orgaFields.Orga,
          OrgaStatusInCustomer: orgaFields.OrgaStatusInCustomer,
          ...(orgaFields.Branding ? { Branding: orgaFields.Branding } : {}),
        },
        include: includeFields.join(),
      })
        .then(() => {
          this.isLoading = false
        })
    },

    fetchAllOrganisations (page) {
      this.isLoading = true

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
        filter: {
          namefilter: {
            condition: {
              path: 'name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm,
            },
          },
        },
        include: includeFields.join(),
      })
        .then(() => {
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
          this.noResults = Object.keys(this.items || {}).length === 0
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
          this.noResults = Object.keys(this.items || {}).length === 0
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
          this.fetchFilteredOrganisations(this.selectedFilters, page)
        } else {
          this.fetchAllOrganisations(page)
        }
      }
    },

    handleFilter (selected) {
      this.selectedFilters = selected
      this.fetchFilteredOrganisations(selected, 1)
    },

    handleSearch (searchTerm) {
      this.searchTerm = searchTerm
      this.getItemsByPage(1)
    },

    isSelected (id) {
      return this.selectedOrganisations[id] !== undefined
    },

    onTwoFactorModalToggled (isOpen) {
      if (!isOpen) {
        this.twoFactorTarget = null
      }
    },

    /**
     * Preselects the value all selected organisations share, nothing when they differ
     */
    openTwoFactorModal () {
      if (!this.isOrganisationSelected) {
        return
      }

      this.twoFactorTarget = this.hasMixedTwoFactorValues ? null : [...this.selectedTwoFactorValues][0]
      this.$refs.twoFactorModal?.toggle()
    },

    /**
     * Every backend message becomes a toast when sent through dpApi, which would be one per organisation here.
     * A bare request keeps the single summary notification of the caller.
     */
    async patchTwoFactorRequired (id, required) {
      const response = await fetch(Routing.generate('organisation_update', { id }), {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'x-csrf-token': dplan.csrfToken,
          'X-JWT-Authorization': `Bearer ${dplan.jwtToken}`,
        },
        body: JSON.stringify({
          data: {
            id,
            type: 'Orga',
            attributes: {
              twoFactorRequired: required,
            },
          },
        }),
      })

      if (!response.ok) {
        throw new Error(`Updating organisation ${id} failed with status ${response.status}`)
      }

      return response
    },

    removeFromSelection (id) {
      const rest = { ...this.selectedOrganisations }

      delete rest[id]

      this.selectedOrganisations = rest
    },

    resetSelection () {
      this.selectedOrganisations = {}
      this.showSelectionList = false
    },

    resetSearch () {
      this.searchTerm = ''
      this.noResults = false
      this.getItemsByPage()
    },

    setAdditionalFieldOptions (options) {
      this.additionalFieldOptions = options
    },

    /**
     * @param {Boolean} status  Select or deselect every organisation on the current page
     */
    toggleAll (status) {
      if (!status) {
        Object.keys(this.items).forEach(id => this.removeFromSelection(id))

        return
      }

      const pageSelection = Object.values(this.items).reduce((acc, item) => {
        acc[item.id] = this.toSelectionEntry(item)

        return acc
      }, {})

      this.selectedOrganisations = { ...this.selectedOrganisations, ...pageSelection }
    },

    toggleOne (id) {
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
