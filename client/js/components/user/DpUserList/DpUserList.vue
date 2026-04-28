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
        class="shadow-sm rounded-lg mt-2 py-4 px-6"
      >
        <div class="flex items-center justify-between border-b border-neutral py-2">
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
            <div
              v-for="user in selectedUsersForDropdown"
              v-else
              :key="user.id"
              class="grid grid-cols-[1fr_2fr_auto] gap-4 items-center border-b border-neutral py-2 my-2"
            >
              <div class="font-semibold">
                {{ user.attributes.firstname }} {{ user.attributes.lastname }}
              </div>
              <div>
                {{ user.attributes.email }}
              </div>
              <dp-button
                variant="subtle"
                icon="x"
                icon-weight="bold"
                :text="Translator.trans('remove')"
                hide-text
                @click="toggleOne(user.id)"
              />
            </div>
          </div>
        </dp-transition-expand>
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
            v-if="hasPermission('feature_user_delete')"
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
          <label
            for="select_all"
            class="cursor-pointer font-semibold text-interactive inline-block"
          >
            <input
              id="select_all"
              type="checkbox"
              data-cy="allSelected"
              :checked="allOnPageSelected"
              @change="toggleAll(!allOnPageSelected)"
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
        class="o-list space-y-2 mb-4"
        data-cy="userList:userListWrapper"
      >
        <dp-user-list-item
          v-for="(item, idx, index) in items"
          :key="idx"
          class="o-list__item bg-surface border border-neutral"
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
      const toggledSet = new Set(this.toggledItems)
      return Object.keys(this.items).reduce((acc, id) => {
        const isInToggled = toggledSet.has(id)
        acc[id] = this.trackDeselected ? !isInToggled : isInToggled
        return acc
      }, {})
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
      return '<h3 class="mt-4 text-on-dark">' + Translator.trans('search.options') + '</h3>' +
        Translator.trans('search.options.description') +
        '<h3 class="mt-4 text-on-dark">' + Translator.trans('search.special.characters') + '</h3>' +
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
      let ids
      try {
        ids = await this.resolveSelectedIds()
      } catch (error) {
        console.error('Failed to resolve selected user ids for invite:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))
        return
      }
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

      let ids
      try {
        ids = await this.resolveSelectedIds()
      } catch (error) {
        console.error('Failed to resolve selected user ids for delete:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))
        return
      }

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
      const filter = this.buildUserFilter()

      // Store not used so we stay on selected page
      const response = await dpApi.get('/api/2.0/AdministratableUser', {
        filter,
      })

      const users = response.data?.data || []
      this.selectedUsersMap = users.reduce((acc, user) => {
        if (!this.toggledItems.includes(user.id)) {
          acc[user.id] = user
        }
        return acc
      }, {})
      this.allUsersFetched = true
    },

    async fetchAllUserIds () {
      const filter = this.buildUserFilter()

      // Direct API call instead of `userList`, so the store-backed current page is not replaced.
      const response = await dpApi.get('/api/2.0/AdministratableUser', {
        filter,
      })

      return (response.data?.data || []).map(user => user.id)
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

      if (this.allUsersFetched) {
        return Object.keys(this.selectedUsersMap)
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
      if (this.isLoadingSelectionList) {
        return
      }

      if (!this.showSelectionList && this.trackDeselected && !this.allUsersFetched) {
        this.showSelectionList = true
        this.isLoadingSelectionList = true
        try {
          await this.fetchAllUsersForSelectionMap()
        } catch (error) {
          console.error('Failed to load selected users:', error)
          dplan.notify.notify('error', Translator.trans('error.api.generic'))
          this.showSelectionList = false
        } finally {
          this.isLoadingSelectionList = false
        }
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
