<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is used to display a paginated list of users
      It retrieves organisations, departments, roles and users via vuex-json-api library
   -->
</documentation>

<template>
  <div class="mt-4">
    <!-- search -->
    <div class="flex mt-4">
      <dp-search-field
        data-cy="search:currentSearchTerm"
        :placeholder="Translator.trans('searchterm')"
        @search="handleSearch"
        @reset="handleReset"
      />
      <dp-contextual-help :text="tooltipContent" />
    </div>
    <dp-loading
      v-if="isLoading"
      class="ml-4 mt-4"
    />
    <!-- List of all items -->
    <div v-if="false === isLoading">
      <div
        v-if="isUserSelected"
        class="border rounded-lg mt-2 py-4 px-6"
      >
        <div class="flex items-center justify-between">
          <span>{{ Translator.trans('users.selected', { count: selectedItemsCount }) }}</span>
          <dp-button
            :disabled="trackDeselected && toggledItems.length === 0"
            :icon="showSelectionList ? 'caret-up' : 'caret-down'"
            :text="Translator.trans('selection.show')"
            type="button"
            variant="subtle"
            @click="toggleSelectionList"
          />
        </div>
        <dp-transition-expand>
          <div v-show="showSelectionList">
            <dp-loading
              v-if="isLoadingSelectionList"
              class="mt-1"
            />
            <ul
              v-else
              class="o-list mt-0.5"
            >
              <li
                v-for="user in selectedUsersForDropdown"
                :key="user.id"
                class="py-1"
              >
                <div>{{ user.attributes.firstname }} {{ user.attributes.lastname }}</div>
                <div class="text-muted text-sm">{{ user.attributes.email }}</div>
              </li>
            </ul>
          </div>
        </dp-transition-expand>
        <hr>
        <!--Button row -->
        <div class="text-right mt-4 space-x-2">
          <dp-button
            class="p-1"
            color="secondary"
            variant="outline"
            :text="Translator.trans('unselect')"
            @click="resetSelection"
          />
          <dp-button
            v-if="hasPermission('feature_user_delete') || true"
            class="p-1"
            color="warning"
            data-cy="deleteSelectedItems"
            type="button"
            variant="outline"
            :disabled="!isUserSelected"
            :text="Translator.trans('delete') + ` (${selectedItemsCount})`"
            @click="deleteItems"
          />
          <dp-button
            class="p-1"
            color="primary"
            data-cy="userList:manageUsers"
            type="button"
            :disabled="!isUserSelected"
            :text="Translator.trans('invite') + ` (${selectedItemsCount})`"
            @click="inviteItems"
          />
        </div>
      </div>
      <div class="mt-4 flex items-center justify-between">
        <!-- 'Select all'-Checkbox -->
        <div>
          <input
            id="select_all"
            type="checkbox"
            data-cy="allSelected"
            :checked="allOnPageSelected"
            @change="toggleAll(!allOnPageSelected)"
          >
          <label
            v-if="hasPermission('feature_user_delete') || true"
            for="select_all"
            class="cursor-pointer font-semibold text-interactive inline-block"
          >
            {{ Translator.trans('select.all') }}
          </label>
        </div>
      </div>
    </div>
    <template
      v-if="false === isLoading"
    >
      <ul
        class="o-list o-list--card mb-4"
        data-cy="userList:userListWrapper"
      >
        <dp-user-list-item
          v-for="(item, idx, index) in items"
          :key="idx"
          class="o-list__item"
          :selected="currentPageSelections[item.id] || false"
          :user="item"
          :data-cy="`userList:userListBlk:${index}`"
          :project-name="projectName"
          @item:selected="toggleOne"
        />
      </ul>

      <dp-sliding-pagination
        :current="currentPage"
        :total="totalPages"
        :non-sliding-size="10"
        @page-change="getItemsByPage"
      />
    </template>
  </div>
</template>

<script>
import {
  debounce,
  dpApi,
  DpButton,
  DpContextualHelp,
  DpLoading,
  DpSearchField,
  DpTransitionExpand,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'DpUserList',

  components: {
    DpButton,
    DpContextualHelp,
    DpLoading,
    DpSearchField,
    DpTransitionExpand,
    DpSlidingPagination: defineAsyncComponent(async () => {
      const { DpSlidingPagination } = await import('@demos-europe/demosplan-ui')
      return DpSlidingPagination
    }),
    DpUserListItem: defineAsyncComponent(() => import('./DpUserListItem')),
  },

  provide () {
    return {
      presetUserOrgaId: this.presetUserOrgaId,
      projectName: this.projectName,
    }
  },

  props: {
    /**
     * Needed for translationKey in DpUserFormFields
     */
    projectName: {
      type: String,
      required: false,
      default: '',
    },

    presetUserOrgaId: {
      type: String,
      required: false,
      default: '',
    },
  },

  data () {
    return {
      allItemsCount: 0,
      allUsersFetched: false,
      isLoading: true,
      isLoadingSelectionList: false,
      searchValue: '',
      selectedUsersMap: {},
      showSelectionList: false,
      toggledItems: [],
      trackDeselected: false,
    }
  },

  computed: {
    ...mapState('AdministratableUser', {
      items: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages',
    }),

    allOnPageSelected () {
      const itemIds = Object.keys(this.items)
      return itemIds.length > 0 && itemIds.every(id => this.currentPageSelections[id])
    },

    currentPageSelections () {
      return Object.keys(this.items).reduce((acc, id) => {
        const isInToggled = this.toggledItems.includes(id)
        acc[id] = this.trackDeselected ? !isInToggled : isInToggled
        return acc
      }, {})
    },

    deleteSelectedUsersLabel () {
      return Translator.trans('entities.marked.delete', { entities: Translator.trans('users'), sum: this.selectedItemsCount })
    },

    isUserSelected () {
      return this.selectedItemsCount > 0
    },

    selectedItemsCount () {
      return this.trackDeselected ?
        this.allItemsCount - this.toggledItems.length :
        this.toggledItems.length
    },

    selectedUsersForDropdown () {
      return Object.values(this.selectedUsersMap)
    },

    tooltipContent () {
      return '<h3 class="u-mt color--white">' + Translator.trans('search.options') + '</h3>' +
        Translator.trans('search.options.description') +
        '<h3 class="u-mt color--white">' + Translator.trans('search.special.characters') + '</h3>' +
        Translator.trans('search.special.characters.description')
    },
  },

  methods: {
    ...mapActions('Department', {
      departmentList: 'list',
    }),
    ...mapActions('UserFormFields', [
      'fetchOrgaSuggestions',
    ]),
    ...mapActions('Orga', {
      organisationList: 'list',
      deleteOrganisation: 'delete',
    }),
    ...mapActions('Role', {
      roleList: 'list',
    }),
    ...mapActions('AdministratableUser', {
      userList: 'list',
      deleteAdministratableUser: 'delete',
    }),

    async inviteItems () {
      const ids = await this.resolveSelectedIds()
      const form = this.$el.closest('form')
      const currentPageIds = new Set(Object.keys(this.items))

      // Add hidden inputs only for users not on the current page (those already have checkboxes)
      ids.filter(id => !currentPageIds.has(id)).forEach(id => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'elementsToAdminister[]'
        input.value = id
        form.appendChild(input)
      })

      // Add the action that the original submit button would have sent
      const actionInput = document.createElement('input')
      actionInput.type = 'hidden'
      actionInput.name = 'manageUsers'
      actionInput.value = 'inviteSelected'
      form.appendChild(actionInput)

      form.submit()
    },

    async deleteItems () {
      if (!this.selectedItemsCount) {
        return dplan.notify.notify('warning', Translator.trans('warning.select.entries'))
      }

      const isConfirmed = window.dpconfirm(
        Translator.trans('check.user.delete', { count: this.selectedItemsCount }),
      )

      if (!isConfirmed) return

      const ids = await this.resolveSelectedIds()

      let successCount = 0
      let errorCount = 0

      await Promise.allSettled(
        ids.map(async id => {
          try {
            const response = await this.deleteAdministratableUser(id)
            if (response && response.status >= 400) {
              errorCount++
            } else {
              successCount++
            }
          } catch (error) {
            console.error(`Failed to delete user with ID ${id}:`, error)
            errorCount++
          }
        }),
      )

      if (successCount > 0) {
        dplan.notify.notify('confirm', Translator.trans('confirm.entries.marked.deleted'))
      }
      if (errorCount > 0) {
        dplan.notify.notify('error', Translator.trans('error.delete.user'))
      }

      this.resetSelection()
      this.loadItems()
    },

    buildUserFilter () {
      const userFilter = {
        name: {
          group: {
            conjunction: 'OR',
          },
        },
      }

      this.searchValue.split(' ').filter(Boolean).forEach((value, index) => {
        userFilter[`firstnameFilter${index}`] = {
          condition: {
            path: 'firstname',
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            value,
            memberOf: 'name',
          },
        }
        userFilter[`lastnameFilter${index}`] = {
          condition: {
            path: 'lastname',
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            value,
            memberOf: 'name',
          },
        }
      })

      return userFilter
    },

    async fetchAllUsersForSelectionMap () {
      const allUsers = {}
      const filter = this.buildUserFilter()
      const include = ['roles', 'orga', 'department', 'orga.allowedRoles'].join()
      let page = 1
      let totalPages = 1

      while (page <= totalPages) {
        // Store not used so we stay on selected page
        const response = await dpApi.get('/api/2.0/AdministratableUser', {
          page: { number: page },
          filter,
          include,
        })

        const users = response.data?.data || []
        users.forEach(user => {
          if (!this.toggledItems.includes(user.id)) {
            allUsers[user.id] = user
          }
        })
        totalPages = response.data?.meta?.pagination?.total_pages ?? 1
        page++
      }

      this.selectedUsersMap = allUsers
      this.allUsersFetched = true
    },

    async fetchAllUserIds () {
      const allIds = []
      const filter = this.buildUserFilter()
      const include = ['roles', 'orga', 'department', 'orga.allowedRoles'].join()
      let page = 1
      let totalPages = 1

      while (page <= totalPages) {
        const response = await this.userList({
          page: { number: page },
          filter,
          include,
        })

        const resourceData = response.data.AdministratableUser || response.data
        allIds.push(...Object.keys(resourceData))
        totalPages = response.meta.pagination.total_pages
        page++
      }

      // Restore the current page view since userList replaces store items on each call
      this.getItemsByPage(this.currentPage)

      return allIds
    },

    getFilteredItems: debounce(function () {
      this.getItemsByPage(1)
    }, 500),

    getItemsByPage (page) {
      this.isLoading = true
      page = page || this.currentPage

      this.userList({
        page: {
          number: page ?? 1,
        },
        filter: this.buildUserFilter(),
        include: ['roles', 'orga', 'department', 'orga.allowedRoles'].join(),
      })
        .then((response) => {
          this.allItemsCount = response.meta.pagination.total
          this.isLoading = false
        })
    },

    async resolveSelectedIds () {
      if (!this.trackDeselected) {
        return [...this.toggledItems]
      }

      const allIds = await this.fetchAllUserIds()
      return allIds.filter(id => !this.toggledItems.includes(id))
    },

    handleSearch (term) {
      this.searchValue = term
      this.resetSelection()
      this.getFilteredItems()
    },

    handleReset () {
      this.searchValue = ''
      this.resetSelection()
      this.getFilteredItems()
    },

    resetSelection () {
      this.allUsersFetched = false
      this.selectedUsersMap = {}
      this.showSelectionList = false
      this.trackDeselected = false
      this.toggledItems = []
    },

    toggleAll (status) {
      this.allUsersFetched = false
      this.selectedUsersMap = {}
      this.trackDeselected = status
      this.toggledItems = []
    },

    toggleOne (id) {
      const isInToggled = this.toggledItems.includes(id)
      if (this.trackDeselected) {
        if (isInToggled) {
          // Re-selecting: remove from exclusions, add back to map
          this.toggledItems = this.toggledItems.filter(item => item !== id)
          this.selectedUsersMap = { ...this.selectedUsersMap, [id]: this.items[id] }
        } else {
          // Deselecting: add to exclusions, remove from map if fetched
          this.toggledItems = [...this.toggledItems, id]
          this.selectedUsersMap = Object.fromEntries(
            Object.entries(this.selectedUsersMap).filter(([key]) => key !== id),
          )
        }
      } else if (isInToggled) {
        this.toggledItems = this.toggledItems.filter(item => item !== id)
        this.selectedUsersMap = Object.fromEntries(
          Object.entries(this.selectedUsersMap).filter(([key]) => key !== id),
        )
      } else {
        this.toggledItems = [...this.toggledItems, id]
        this.selectedUsersMap = { ...this.selectedUsersMap, [id]: this.items[id] }
      }
    },

    async toggleSelectionList () {
      if (!this.showSelectionList && this.trackDeselected && !this.allUsersFetched) {
        this.showSelectionList = true
        this.isLoadingSelectionList = true
        await this.fetchAllUsersForSelectionMap()
        this.isLoadingSelectionList = false
      } else {
        this.showSelectionList = !this.showSelectionList
      }
    },

    loadItems () {
      const arr = []
      if (hasPermission('feature_organisation_user_list')) {
        arr.push(this.organisationList({ include: ['departments', 'allowedRoles'].join() }))
      } else {
        arr.push(this.departmentList())
        arr.push(this.roleList())
      }
      Promise.all(arr)
        .then(() => {
          this.getItemsByPage()
        })
    },
  },

  mounted () {
    this.loadItems()
    this.fetchOrgaSuggestions()
  },
}
</script>
