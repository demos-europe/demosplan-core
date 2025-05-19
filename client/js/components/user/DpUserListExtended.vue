<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout">
    <!-- table header -->
    <dp-table-card-list-header
      :items="headerItems"
      class="u-pt"
      @reset-search="resetSearch"
      @select-all="val => dpToggleAll(val, users)"
      @search="val => handleSearch(val)"
      search-placeholder="search.users"
      searchable
      selectable>
      <template
        v-if="hasPermission('feature_user_delete')"
        v-slot:header-buttons>
        <div class="layout__item u-1-of-2 text-right u-mb-0_5">
          <dp-button
            color="warning"
            data-cy="deleteSelectedItems"
            :text="deleteSelectedUserLabel"
            @click.prevent="deleteUsers(selectedItems)" />
          <dp-button
            class="u-ml-0_25"
            color="secondary"
            data-cy="resetSelectedItems"
            :text="Translator.trans('unselect')"
            @click="dpToggleAll(false, users)" />
        </div>
      </template>
    </dp-table-card-list-header>

    <dp-loading
      v-if="isLoading"
      class="u-ml u-mt" />

    <!-- card items -->
    <ul
      v-if="isLoading === false"
      class="u-ml-0">
      <dp-user-list-extended-item
        v-for="(user, id) in users"
        :key="user.id"
        :all-organisations="organisations"
        :user="user"
        @delete="deleteSingelUser(user.id)"
        :is-open="expandedCardId === id"
        @card:toggle="setExpandedCardId(id)"
        @item:selected="dpToggleOne"
        :selected="Object.hasOwn(itemSelections, user.id) && itemSelections[user.id] === true" />
    </ul>

    <!-- pager -->
    <dp-sliding-pagination
      v-if="totalPages > 1 && isLoading === false"
      class="u-mr-0_25 u-ml-0_5 u-mt-0_5"
      :current="currentPage"
      :total="totalPages"
      @page-change="getUsersByPage" />
  </div>
</template>

<script>
import {
  debounce,
  dpApi, DpButton,
  DpLoading,
  dpSelectAllMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import DpTableCardListHeader from '@DpJs/components/user/DpTableCardList/DpTableCardListHeader'
import DpUserListExtendedItem from './DpUserListExtendedItem'

export default {
  name: 'DpUserListExtended',

  components: {
    DpButton,
    DpLoading,
    DpSlidingPagination: defineAsyncComponent(async () => {
      const { DpSlidingPagination } = await import('@demos-europe/demosplan-ui')
      return DpSlidingPagination
    }),
    DpTableCardListHeader,
    DpUserListExtendedItem
  },

  mixins: [dpSelectAllMixin],

  data () {
    return {
      // Contains id of currently expanded card
      expandedCardId: '',
      filterValue: '',
      headerItems: [
        { label: 'Name', width: 'u-1-of-4' },
        { label: 'Login', width: 'u-1-of-4' },
        { label: 'E-Mail', width: 'u-1-of-4' }
      ],
      isFiltered: false,
      isLoading: true,
      organisations: []
    }
  },

  computed: {
    ...mapState('Role', {
      roles: 'items'
    }),
    ...mapState('AdministratableUser', {
      users: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    deleteSelectedUserLabel () {
      return Translator.trans('entities.marked.delete', { entities: Translator.trans('user'), sum: Object.keys(this.selectedItems).length })
    },

    selectedItems () {
      // The prop `itemSelections` and the method `dpToggleOne` are from `dpSelectAllMixin`
      return Object.keys(this.users).filter(id => this.itemSelections[id])
    }
  },

  methods: {
    ...mapActions('Department', {
      departmentList: 'list'
    }),
    ...mapActions('Role', {
      roleList: 'list'
    }),
    ...mapActions('AdministratableUser', {
      userList: 'list',
      deleteUser: 'delete'
    }),

    deleteSingelUser (id) {
      if (dpconfirm(Translator.trans('check.user.delete', { count: 1 })) === false) {
        return
      }

      return this.deleteUser(id)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.user.deleted'))
        })
    },

    deleteUsers (ids) {
      if (!this.selectedItems.length || dpconfirm(Translator.trans('check.entries.marked.delete')) === false) {
        return
      }

      ids.map(id => {
        return this.deleteUser(id)
          .then(() => {
            dplan.notify.notify('confirm', Translator.trans('confirm.user.deleted'))
          })
      })
    },

    /**
     * Fetch all organisations
     * passed to child components to determine masterToeb filter
     */
    fetchOrganisations () {
      const url = Routing.generate('api_resource_list', { resourceType: 'Orga' })
      return dpApi.get(url, {
        include: ['departments', 'masterToeb'].join(),
        fields: {
          Orga: ['departments', 'masterToeb', 'name'].join()
        }
      })
        .then((response) => {
          this.organisations = response?.data?.data ?? {}
        })
        .catch(e => console.error(e))
    },

    /**
     * Fetch users and their relationships
     */
    fetchResources () {
      const reqs = [this.departmentList(), this.fetchOrganisations(), this.roleList()]
      Promise.all(reqs)
        .then(() => {
          this.getUsersByPage()
        })
    },

    getUsersByPage (page) {
      this.isLoading = true
      page = page || this.currentPage
      const filter = {
        name: {
          group: {
            conjunction: 'OR'
          }
        }
      }
      this.filterValue.split(' ').forEach((subString, idx) => {
        filter[`firstname_${idx}`] = {
          condition: {
            path: 'firstname',
            value: subString,
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            memberOf: 'name'
          }
        }
        filter[`lastname_${idx}`] = {
          condition: {
            path: 'lastname',
            value: subString,
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            memberOf: 'name'
          }
        }
        filter[`login_${idx}`] = {
          condition: {
            path: 'login',
            value: subString,
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            memberOf: 'name'
          }
        }
      })

      this.userList({
        page: {
          number: page ?? 1
        },
        filter: (this.filterValue !== '') ? filter : {},
        include: ['roles', 'orga', 'department'].join()
      })
        .then(() => {
          this.isLoading = false
        })
    },

    getFilteredUsers: debounce(function () {
      this.getUsersByPage(1)
    }, 500),

    handleSearch (val) {
      this.filterValue = val
      this.getFilteredUsers()
      this.isFiltered = true
      this.setExpandedCardId('')
    },

    resetSearch () {
      this.filterValue = ''
      if (this.isFiltered) {
        this.getFilteredUsers()
        this.setExpandedCardId('')
      }
      this.isFiltered = false
    },

    /**
     *
     * @param id
     */
    setExpandedCardId (id) {
      this.expandedCardId = this.expandedCardId === id ? '' : id
    }
  },

  mounted () {
    this.fetchResources()
  }
}
</script>
