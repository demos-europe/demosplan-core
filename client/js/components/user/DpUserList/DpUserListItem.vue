<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Child component of DpUserList
      Displays user data, some of which can be edited
   -->
</documentation>

<template>
  <dp-table-card
    :id="user.id"
    class="o-accordion u-ph-0_5"
    :open="isOpen">
    <!-- Item header -->
    <template v-slot:header>
      <div class="flex items-start">
        <div class="relative z-above-zero u-mt-0_75">
          <input
            type="checkbox"
            :checked="selected"
            name="elementsToAdminister[]"
            :value="user.id"
            data-cy="userItemSelect"
            @change="$emit('item:selected', user.id)">
        </div>
        <div
          @click="isOpen = false === isOpen"
          class="cursor-pointer u-pv-0_75 u-ph-0_25 grow"
          data-cy="organisationListTitle">
          <div
            data-cy="editItemToggle"
            class="layout">
            <div class="layout__item u-1-of-1 weight--bold u-mb-0_5 o-hellip--nowrap">
              {{ user.attributes.firstname }} {{ user.attributes.lastname }}
            </div>
            <div
              class="u-1-of-2 layout__item"
              v-if="hasRoles">
              <div
                v-for="(role, idx) in userRoles"
                :key="idx">
                {{ Translator.trans(role.attributes.name) }}<br>
              </div>
            </div><!--
         --><div
              v-else
              class="u-4-of-12 layout__item">
              {{ Translator.trans('unknown') }}<br>
            </div><!--
         --><div
              v-if="userOrga"
              class="layout__item u-1-of-2">
              {{ Translator.trans(userOrga.attributes.name) }}
              <br>
              <div
                v-if="userDepartment !== null"
                class="u-1-of-2 inline">
                {{ Translator.trans(userDepartment.attributes.name) }}
              </div>
            </div>
            <!--  Registration status -->
            <div class="layout__item u-pt-0_5">
              <div v-if="user.attributes.profileCompleted">
                {{ Translator.trans('user.registration.completed') }}
              </div>
              <div v-else-if="user.attributes.accessConfirmed">
                {{ Translator.trans('user.registration.confirmed') }}
              </div>
              <div v-else-if="user.attributes.invited">
                {{ Translator.trans('user.registration.invitation.sent') }}
              </div>
              <div v-else>
                {{ Translator.trans('user.registration.invitation.outstanding') }}
              </div>
            </div>
          </div>
        </div>
        <button
          @click="isOpen = false === isOpen"
          type="button"
          data-cy="userListItemToggle"
          class="btn--blank o-link--default u-pv-0_75">
          <dp-icon
            aria-hidden="true"
            :aria-label="ariaLabel"
            :icon="icon" />
        </button>
      </div>
    </template>

    <!-- Item content / editable data -->
    <div
      data-cy="userForm"
      data-dp-validate="userForm">
      <dp-user-form-fields
        :user="user"
        :user-id="user.id"
        @user:update="updateUser"
        :ref="'user-form-fields-' + user.id" />

      <dp-button-row
        form-name="userForm"
        primary
        secondary
        @primary-action="dpValidateAction('userForm', save, false)"
        @secondary-action="reset" />
    </div>
  </dp-table-card>
</template>

<script>
import { DpButtonRow, DpIcon, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpTableCard from '@DpJs/components/user/DpTableCardList/DpTableCard'
import DpUserFormFields from './DpUserFormFields'

export default {
  name: 'DpUserListItem',

  components: {
    DpButtonRow,
    DpIcon,
    DpTableCard,
    DpUserFormFields
  },

  mixins: [dpValidateMixin],

  props: {
    filterValue: {
      required: false,
      type: String,
      default: ''
    },

    selected: {
      required: false,
      type: Boolean,
      default: false
    },

    // User from store
    user: {
      required: true,
      type: Object
    }
  },

  emits: [
    'item:selected'
  ],

  data () {
    return {
      isOpen: false,
      editMode: false,
      isLoading: true
    }
  },

  computed: {
    ...mapState('Role', {
      roles: 'items'
    }),

    ariaLabel () {
      return Translator.trans(this.isOpen ? 'aria.collapse' : 'aria.expand')
    },

    hasDepartment () {
      return this.user.hasRelationship('department')
    },

    hasOrga () {
      return this.user.hasRelationship('orga') && typeof this.user.relationships.orga !== 'undefined' && this.user.relationships.orga !== null
    },

    hasRoles () {
      return this.user.hasRelationship('roles')
    },

    // Check if user name, roles, organisation or department match the entered search string
    isInFilter () {
      return (this.filterValue === '' ||
        this.user.attributes.fullName.toLowerCase().includes(this.filterValue.toLowerCase()) ||
        /*
         * For now, we don't want to filter by email, because email is not displayed in closed user item - this might confuse users
         * this.user.attributes.email.toLowerCase().includes(this.filterValue.toLowerCase()) ||
         */
        this.userRolesNames.toString().toLowerCase().includes(this.filterValue.toLowerCase()) ||
        (this.userOrga !== null && this.userOrga.toString().toLowerCase().includes(this.filterValue.toLowerCase())) ||
        (this.userDepartment !== null && this.userDepartment.toString().toLowerCase().includes(this.filterValue.toLowerCase()))
      )
    },

    icon () {
      return this.isOpen ? 'chevron-up' : 'chevron-down'
    },

    userDepartment () {
      return this.hasDepartment ? this.user.relationships.department.get() : null
    },

    userOrga () {
      return this.hasOrga ? this.user.relationships.orga.get() : null
    },

    userRoles () {
      return this.hasRoles ? this.user.relationships.roles.list() : null
    },

    userRolesNames () {
      let names = []
      if (this.hasRoles) {
        const roles = Object.values(this.userRoles)
        names = roles.map(role => role.attributes.name)
      }
      return names
    }
  },

  methods: {
    ...mapActions('AdministratableUser', {
      saveUserAction: 'save',
      restoreUser: 'restoreFromInitial'
    }),

    ...mapMutations('AdministratableUser', ['setItem']),

    // Close item and reset roles multiselect
    reset () {
      this.restoreUser(this.user.id).then(() => {
        const userFormFields = this.$refs[`user-form-fields-${this.user.id}`]
        userFormFields.$data.localUser = JSON.parse(JSON.stringify(userFormFields.$props.user))
        userFormFields.setInitialOrgaData()
        this.isOpen = !this.isOpen

        const inputsWithErrors = this.$el.querySelector('[data-dp-validate="userForm"]').querySelectorAll('.is-invalid')
        Array.from(inputsWithErrors).forEach(input => {
          input.classList.remove('is-invalid')
          const inputNodeName = input.nodeName
          if (inputNodeName === 'INPUT' || inputNodeName === 'SELECT') {
            input.setCustomValidity('')
          }
        })
      })
    },

    toggleItem (open) {
      this.isOpen = open
    },

    save () {
      this.isOpen = !this.isOpen
      this.saveUserAction(this.user.id)
    },

    updateUser (payload) {
      this.setItem({ ...payload })
    }
  }
}
</script>
