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
  <div class="u-mt">
    <!-- search -->
    <div class="layout u-mt">
      <div class="layout__item u-1-of-1">
        <input
          type="text"
          v-model="searchValue"
          class="o-form__control-input u-mb-0_5"
          style="height: 28px;"
          data-cy="userList:searchUser"
          @keypress.enter.prevent="getFilteredItems"
          :placeholder="Translator.trans('search')"><!--
     --><dp-button
          class="u-ml-0_5"
          data-cy="userList:searchUserBtn"
          :text="Translator.trans('searching')"
          @click="getFilteredItems" />
        <dp-contextual-help
          class="float-right"
          :text="tooltipContent" />
      </div>
    </div>

    <dp-loading
      v-if="isLoading"
      class="u-ml u-mt" />
    <!-- List of all items -->
    <div
      class="layout"
      v-if="false === isLoading">
      <div class="u-mt flex">
        <!-- 'Select all'-Checkbox -->
        <div class="layout__item u-3-of-7">
          <input
            type="checkbox"
            id="select_all"
            data-cy="allSelected"
            :checked="allSelected"
            @change="dpToggleAll(!allSelected, items)">
          <label
            v-if="hasPermission('feature_user_delete') || true"
            for="select_all"
            class="cursor-pointer btn-icns inline-block">
            {{ Translator.trans('select.all.on.page') }}
          </label>
        </div>
        <!--Button row -->
        <div class="text-right u-4-of-7 u-mb-0_5">
          <button
            class="btn btn--primary mb-1.5"
            data-cy="userList:manageUsers"
            value="inviteSelected"
            name="manageUsers"
            type="submit">
            {{ Translator.trans('user.marked.invite') }}
          </button>

          <button
            v-if="hasPermission('feature_user_delete') || true"
            class="btn btn--warning mb-1.5"
            type="button"
            data-cy="deleteSelectedItems"
            @click="deleteItems(selectedItems)">
            {{ deleteSelectedUsersLabel }}
          </button>
        </div>
      </div>
    </div>
    <template
      v-if="false === isLoading">
      <ul
        class="o-list o-list--card u-mb"
        data-cy="userList:userListWrapper">
        <dp-user-list-item
          class="o-list__item"
          v-for="(item, idx, index) in items"
          :key="idx"
          :selected="hasOwnProp(itemSelections, item.id) && itemSelections[item.id] === true"
          :user="item"
          :data-cy="`userList:userListBlk:${index}`"
          :project-name="projectName"
          @item:selected="dpToggleOne" />
      </ul>

      <dp-sliding-pagination
        :current="currentPage"
        :total="totalPages"
        :non-sliding-size="10"
        @page-change="getItemsByPage" />
    </template>
  </div>
</template>

<script>
import { debounce, DpButton, DpContextualHelp, DpLoading, dpSelectAllMixin, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'DpUserList',

  components: {
    DpButton,
    DpContextualHelp,
    DpLoading,
    DpSlidingPagination: defineAsyncComponent(async () => {
      const { DpSlidingPagination } = await import('@demos-europe/demosplan-ui')
      return DpSlidingPagination
    }),
    DpUserListItem: defineAsyncComponent(() => import('./DpUserListItem'))
  },

  mixins: [dpSelectAllMixin],

  provide () {
    return {
      presetUserOrgaId: this.presetUserOrgaId,
      projectName: this.projectName
    }
  },

  props: {
    /**
     * Needed for translationKey in DpUserFormFields
     */
    projectName: {
      type: String,
      required: false,
      default: ''
    },

    presetUserOrgaId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      searchValue: '',
      isLoading: true,
      itemSelections: {}
    }
  },

  computed: {
    ...mapState('User', {
      items: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    deleteSelectedUsersLabel () {
      return Translator.trans('entities.marked.delete', { entities: Translator.trans('users'), sum: this.selectedItems.length })
    },

    selectedItems () {
      return Object.keys(this.items).filter(id => this.itemSelections[id])
    },

    tooltipContent () {
      return '<h3 class="u-mt color--white">' + Translator.trans('search.options') + '</h3>' +
        Translator.trans('search.options.description') +
        '<h3 class="u-mt color--white">' + Translator.trans('search.special.characters') + '</h3>' +
        Translator.trans('search.special.characters.description')
    }
  },

  methods: {
    ...mapActions('Department', {
      departmentList: 'list'
    }),
    ...mapActions('UserFormFields', [
      'fetchOrgaSuggestions'
    ]),
    ...mapActions('Orga', {
      organisationList: 'list',
      deleteOrganisation: 'delete'
    }),
    ...mapActions('Role', {
      roleList: 'list'
    }),
    ...mapActions('User', {
      userList: 'list',
      deleteUser: 'delete'
    }),

    deleteItems (ids) {
      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning', Translator.trans('warning.select.entries'))
      } else {
        if (window.dpconfirm(Translator.trans('check.user.delete', { count: this.selectedItems.length }))) {
          ids.forEach(id => {
            this.deleteUser(id)
              .then(() => {
                // Remove deleted item from itemSelections
                delete this.itemSelections[id]
                dplan.notify.notify('confirm', Translator.trans('confirm.user.deleted'))
              })
          })
        }
      }
    },

    getFilteredItems: debounce(function () {
      this.getItemsByPage(1)
    }, 500),

    getItemsByPage (page) {
      this.isLoading = true
      page = page || this.currentPage
      const userFilter = {
        name: {
          group: {
            conjunction: 'OR'
          }
        }
      }

      this.searchValue.split(' ').filter(Boolean).forEach((value, index) => {
        userFilter[`firstnameFilter${index}`] = {
          condition: {
            path: 'firstname',
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            value,
            memberOf: 'name'
          }
        }
        userFilter[`lastnameFilter${index}`] = {
          condition: {
            path: 'lastname',
            operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
            value,
            memberOf: 'name'
          }
        }
      })

      this.userList({
        page: {
          number: page ?? 1
        },
        filter: userFilter,
        include: ['roles', 'orga', 'department', 'orga.allowedRoles'].join()
      })
        .then(() => {
          this.isLoading = false
        })
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
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
    }
  },

  mounted () {
    this.loadItems()
    this.fetchOrgaSuggestions()
  }
}
</script>
