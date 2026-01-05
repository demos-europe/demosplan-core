<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-mt-0_5">
    <!-- Pending organisations list (renders only when the orga-self-registration feature is enabled) -->
    <template v-if="hasPermission('area_organisations_applications_manage')">
      <h3>
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
          class="u-mb-0_5"
          height="54px"
        />
      </template>
      <template v-else-if="Object.keys(pendingOrgs).length">
        <ul
          class="o-list o-list--card u-mb"
          data-cy="pendingOrganisationList"
        >
          <dp-organisation-list-item
            v-for="(item, idx) in pendingOrgs"
            :key="`pendingOrganisation:${idx}`"
            :additional-field-options="additionalFieldOptions"
            :available-orga-types="availableOrgaTypes"
            class="o-list__item"
            data-cy="pendingOrganisationListBlk"
            module-name="Pending"
            :organisation="item"
            :selectable="false"
            @addon-options:loaded="setAdditionalFieldOptions"
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

      <div
        v-if="hasPermission('feature_orga_delete')"
        class="block u-mb"
      >
        <div
          class="layout__item u-3-of-7 u-mt u-pl-0_5"
        >
          <div class="o-form__element--checkbox">
            <input
              id="select_all"
              type="checkbox"
              data-cy="allSelected"
              class="o-form__control-input"
              :checked="allSelected"
              @change="dpToggleAll(!allSelected, items)"
            >
            <label
              for="select_all"
              class="o-form__label"
            >
              {{ Translator.trans('select.all.on.page') }}
            </label>
          </div>
        </div><!--
     --><div
          class="layout__item text-right u-4-of-7 u-mt u-mb-0_5"
        >
          <dp-button
            color="warning"
            data-cy="deleteSelectedItems"
            :text="Translator.trans('entities.marked.delete', { entities: Translator.trans('organisations'), sum: selectedItems.length })"
            @click.prevent="deleteItems(selectedItems)"
          />
          <dp-button
            class="u-ml-0_25"
            color="secondary"
            data-cy="resetSelectedItems"
            :text="Translator.trans('unselect')"
            @click="dpToggleAll(false, items)"
          />
        </div>
      </div>
    </div>
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
        <ul class="o-list o-list--card u-mb">
          <dp-organisation-list-item
            v-for="(item, idx) in items"
            :key="`organisation:${idx}`"
            :additional-field-options="additionalFieldOptions"
            :available-orga-types="availableOrgaTypes"
            class="o-list__item"
            data-cy="organisationListBlk"
            :selected="hasOwnProp(itemSelections, item.id) && itemSelections[item.id] === true"
            :selectable="hasPermission('feature_orga_delete')"
            :organisation="item"
            @addon-options:loaded="setAdditionalFieldOptions"
            @item:selected="dpToggleOne"
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
  DpCheckboxGroup,
  DpLoading,
  DpSearchField,
  dpSelectAllMixin,
  DpSkeletonBox,
  DpSlidingPagination,
  hasOwnProp,
  hasPermission,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import DpOrganisationListItem from './DpOrganisationListItem'

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
    DpCheckboxGroup,
    DpLoading,
    DpOrganisationListItem,
    DpSearchField,
    DpSkeletonBox,
    DpSlidingPagination,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  mixins: [dpSelectAllMixin],

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
      noResults: false,
      pendingOrgs: {},
      pendingOrganisationsLoading: true,
      searchTerm: '',
      selectedFilters: {},
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

    isFiltered () {
      return Object.keys(this.selectedFilters).length > 0
    },

    selectedItems () {
      return Object.keys(this.items).filter(id => this.itemSelections[id])
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

    deleteItems (ids) {
      if (!this.selectedItems.length || dpconfirm(Translator.trans('check.entries.marked.delete')) === false) {
        return
      }

      const deleteOrganisations = ids.map(id =>
        this.deleteOrganisation(id)
          .then(() => {
            // Remove deleted item from itemSelections
            delete this.itemSelections[id]
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
        .then(() => { this.isLoading = false })
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

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    resetSearch () {
      this.searchTerm = ''
      this.noResults = false
      this.getItemsByPage()
    },

    setAdditionalFieldOptions (options) {
      this.additionalFieldOptions = options
    },
  },

  mounted () {
    this.fetchPendingAndAllOrganisations(1)

    this.$root.$on('items:get', () => {
      this.fetchPendingAndAllOrganisations()
    })
  },
}
</script>
