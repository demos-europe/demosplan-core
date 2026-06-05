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
    <div class="mt-4 inline-flex items-center">
      <dp-search-field
        data-cy="search:currentSearchTerm"
        :placeholder="Translator.trans('searchterm')"
        @search="handleSearch"
        @reset="handleReset"
      />
      <dp-contextual-help
        class="ml-1"
        :text="tooltipContent"
      />
    </div>
    <dp-loading
      v-if="isLoading"
      class="ml-4 mt-4"
    />
    <!-- List of all users -->
    <div v-if="!isLoading">
      <div
        v-if="isUserSelected"
        class="shadow-sm rounded-lg mt-2 py-4 px-6"
      >
        <div class="flex items-center justify-between border-b border-neutral py-2">
          <span>{{ Translator.trans('users.selected', { count: selectedUsersCount }) }}</span>
          <dp-button
            :disabled="trackDeselected && toggledUsers.length === 0"
            :icon="showSelectionList ? 'caret-up' : 'caret-down'"
            :text="Translator.trans('selection.show')"
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
            <template v-else>
              <div
                v-for="user in selectedUsersForDropdown"
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
            </template>
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
            variant="outline"
            :disabled="!isUserSelected"
            :text="Translator.trans('delete') + ` (${selectedUsersCount})`"
            @click="deleteUsers"
          />
          <dp-button
            class="p-1"
            color="primary"
            data-cy="userList:manageUsers"
            :disabled="!isUserSelected"
            :text="Translator.trans('invite') + ` (${selectedUsersCount})`"
            @click="inviteUsers"
          />
        </div>
      </div>
      <dp-checkbox
        id="select_all"
        class="my-4 ml-2"
        data-cy="allSelected"
        :checked="allOnPageSelected"
        :label="{ text: Translator.trans('select.all') }"
        @change="toggleAll"
      />
    </div>
    <template
      v-if="!isLoading"
    >
      <ul
        class="o-list space-y-2 mb-4"
        data-cy="userList:userListWrapper"
      >
        <dp-user-list-item
          v-for="(user, idx, index) in users"
          :key="idx"
          class="o-list__item bg-surface border border-neutral"
          :selected="currentPageSelections[user.id] || false"
          :user="user"
          :data-cy="`userList:userListBlk:${index}`"
          :project-name="projectName"
          @item:selected="toggleOne"
        />
      </ul>

      <dp-sliding-pagination
        :current="currentPage"
        :total="totalPages"
        :non-sliding-size="10"
        @page-change="getUsersByPage"
      />
    </template>
  </div>
</template>

<script>
import {
  debounce,
  dpApi,
  DpButton,
  DpCheckbox,
  DpContextualHelp,
  DpLoading,
  DpSearchField,
  DpTransitionExpand,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { addFormHiddenField } from '@DpJs/lib/core/libs/FormActions'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'DpUserList',

  components: {
    DpButton,
    DpCheckbox,
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
      allUsersCount: 0,
      allUsersFetched: false,
      isLoading: true,
      isLoadingSelectionList: false,
      searchValue: '',
      selectedUsersMap: {},
      showSelectionList: false,
      toggledUsers: [],
      trackDeselected: false,
    }
  },

  computed: {
    ...mapState('AdministratableUser', {
      users: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages',
    }),

    allOnPageSelected () {
      const userIds = Object.keys(this.users)

      return userIds.length > 0 && userIds.every(id => this.currentPageSelections[id])
    },

    currentPageSelections () {
      const toggledSet = new Set(this.toggledUsers)

      return Object.keys(this.users).reduce((acc, id) => {
        const isInToggled = toggledSet.has(id)

        acc[id] = this.trackDeselected ? !isInToggled : isInToggled

        return acc
      }, {})
    },

    isUserSelected () {
      return this.selectedUsersCount > 0
    },

    selectedUsersCount () {
      return this.trackDeselected ?
        this.allUsersCount - this.toggledUsers.length :
        this.toggledUsers.length
    },

    selectedUsersForDropdown () {
      return Object.values(this.selectedUsersMap)
    },

    tooltipContent () {
      return `<h3 class="mt-4 text-on-dark">${Translator.trans('search.options')}</h3>${Translator.trans('search.options.description')}<h3 class="mt-4 text-on-dark">${Translator.trans('search.special.characters')}</h3>${Translator.trans('search.special.characters.description')}`
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

    addToSelection (id) {
      this.toggledUsers = [...this.toggledUsers, id]
      this.selectedUsersMap = { ...this.selectedUsersMap, [id]: this.users[id] }
    },

    buildUserFilter () {
      const searchTerms = this.searchValue.split(' ').filter(Boolean)

      if (searchTerms.length === 0) {
        return {}
      }

      const userFilter = {
        name: {
          group: {
            conjunction: 'OR',
          },
        },
      }

      searchTerms.forEach((value, index) => {
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

    async deleteUsers () {
      if (!this.selectedUsersCount) {
        return dplan.notify.notify('warning', Translator.trans('warning.select.entries'))
      }

      const isConfirmed = window.dpconfirm(
        Translator.trans('check.user.delete', { count: this.selectedUsersCount }),
      )

      if (!isConfirmed) {
        return
      }

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
      this.loadUsers()
    },

    deselectUser (id) {
      this.toggledUsers = [...this.toggledUsers, id]
      this.selectedUsersMap = Object.fromEntries(
        Object.entries(this.selectedUsersMap).filter(([key]) => key !== id),
      )
    },

    async fetchAllUsers () {
      const filter = this.buildUserFilter()
      const params = {
        ...(Object.keys(filter).length > 0 ? { filter } : {}),
        'fields[AdministratableUser]': 'firstname,lastname,email',
      }
      const url = Routing.generate('api_resource_list', { resourceType: 'AdministratableUser' })

      // Store not used so we stay on selected page
      const response = await dpApi.get(url, params)

      const users = response.data?.data || []

      this.selectedUsersMap = users.reduce((acc, user) => {
        if (!this.toggledUsers.includes(user.id)) {
          acc[user.id] = user
        }

        return acc
      }, {})
      this.allUsersFetched = true
    },

    getFilteredUsers: debounce(function () {
      this.getUsersByPage(1)
    }, 500),

    getUsersByPage (page) {
      this.isLoading = true
      page = page || this.currentPage

      this.userList({
        page: {
          number: page ?? 1,
        },
        filter: this.buildUserFilter(),
        include: ['roles', 'orga', 'department', 'orga.allowedRoles'].join(),
      })
        .then(response => {
          this.allUsersCount = response.meta.pagination.total
          this.isLoading = false
        })
    },

    handleReset () {
      this.searchValue = ''
      this.resetSelection()
      this.getFilteredUsers()
    },

    handleSearch (term) {
      this.searchValue = term
      this.resetSelection()
      this.getFilteredUsers()
    },

    async inviteUsers () {
      let ids

      try {
        ids = await this.resolveSelectedIds()
      } catch (error) {
        console.error('Failed to resolve selected user ids for invite:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))

        return
      }

      const form = this.$el.closest('form')
      const currentPageIds = new Set(Object.keys(this.users))

      // Add hidden inputs only for users not on the current page (those already have checkboxes)
      ids.filter(id => !currentPageIds.has(id)).forEach(id => {
        addFormHiddenField(form, 'elementsToAdminister[]', id)
      })

      // Add the action that the original submit button would have sent
      addFormHiddenField(form, 'manageUsers', 'inviteSelected')

      form.submit()
    },

    loadUsers () {
      const arr = []

      if (hasPermission('feature_organisation_user_list')) {
        arr.push(this.organisationList({ include: ['departments', 'allowedRoles'].join() }))
      } else {
        arr.push(this.departmentList())
        arr.push(this.roleList())
      }

      Promise.all(arr)
        .then(() => {
          this.getUsersByPage()
        })
    },

    removeFromSelection (id) {
      this.toggledUsers = this.toggledUsers.filter(user => user !== id)
      this.selectedUsersMap = Object.fromEntries(
        Object.entries(this.selectedUsersMap).filter(([key]) => key !== id),
      )
    },

    reselectUser (id) {
      this.toggledUsers = this.toggledUsers.filter(user => user !== id)
      if (this.users[id]) {
        this.selectedUsersMap = { ...this.selectedUsersMap, [id]: this.users[id] }
      }
    },

    resetSelection () {
      this.allUsersFetched = false
      this.selectedUsersMap = {}
      this.showSelectionList = false
      this.trackDeselected = false
      this.toggledUsers = []
    },

    async resolveSelectedIds () {
      if (!this.trackDeselected) {
        return [...this.toggledUsers]
      }

      if (!this.allUsersFetched) {
        await this.fetchAllUsers()
      }

      return Object.keys(this.selectedUsersMap)
    },

    toggleAll (status) {
      this.allUsersFetched = false
      this.selectedUsersMap = {}
      this.trackDeselected = status
      this.toggledUsers = []
    },

    toggleInDeselectMode (id) {
      if (this.toggledUsers.includes(id)) {
        this.reselectUser(id)
      } else {
        this.deselectUser(id)
      }
    },

    toggleInSelectMode (id) {
      if (this.toggledUsers.includes(id)) {
        this.removeFromSelection(id)
      } else {
        this.addToSelection(id)
      }
    },

    toggleOne (id) {
      if (this.trackDeselected) {
        this.toggleInDeselectMode(id)
      } else {
        this.toggleInSelectMode(id)
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
          await this.fetchAllUsers()
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
  },

  mounted () {
    this.loadUsers()
    this.fetchOrgaSuggestions()
  },
}
</script>
