<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

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
      <!-- 'Select item' checkbox -->
      <div class="u-mt-0_75 display--inline-block u-valign--top o-accordion--checkbox">
        <input
          type="checkbox"
          :id="`selected` + user.id"
          :checked="selected"
          name="elementsToAdminister[]"
          :value="user.id"
          data-cy="userItemSelect"
          @change="$emit('item:selected', user.id)">
      </div><!--
         Toggle expanded/collapsed
   --><div
        @click="isOpen = false === isOpen"
        class="display--inline-block o-accordion--header-content">
          <div>
           <div class="weight--bold u-10-of-12 u-mt-0_75 u-mb-0_5 display--inline-block">
              {{ initialUser.attributes.firstname }} {{ initialUser.attributes.lastname }}
           </div><!--
         --><div class="o-accordion--button float--right text--right u-mt-0_75 display--inline">
            <button
              type="button"
              data-cy="userListItemToggle"
              class="btn--blank o-link--default">
              <i
                class="fa"
                :class="isOpen ? 'fa-angle-up': 'fa-angle-down'" />
            </button>
          </div>

        <div
          data-cy="editItemToggle"
          class="layout u-mb-0_5"
          data-dp-validate="organisationForm">
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
              class="u-1-of-2 display--inline">
              {{ Translator.trans(userDepartment.attributes.name) }}
            </div>
          </div>

          <!--  Registration status --><!--
       --><div class="layout__item u-pt-0_5 u-pb-0_5">
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
    </div>
    </template>

    <!-- Item content / editable data -->
    <div
      class="u-mt"
      data-cy="userForm"
      data-dp-validate="userForm">
      <dp-user-form-fields
        :user="user"
        :user-id="user.id"
        @user-update="updateUser"
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
import { mapActions, mapMutations, mapState } from 'vuex'
import DpButtonRow from '@DpJs/components/core/DpButtonRow'
import DpTableCard from '@DemosPlanCoreBundle/components/DpTableCardList/DpTableCard'
import DpUserFormFields from './DpUserFormFields'
import dpValidateMixin from '@DpJs/lib/validation/dpValidateMixin'

export default {
  name: 'DpUserListItem',

  components: {
    DpButtonRow,
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

  data () {
    return {
      isOpen: false,
      editMode: false,
      isLoading: true
    }
  },

  computed: {
    ...mapState('user', {
      initialUser (state) {
        return state.initial[this.user.id]
      }
    }),
    ...mapState('role', {
      roles: 'items'
    }),

    hasDepartment () {
      return this.initialUser.hasRelationship('department')
    },

    hasOrga () {
      return this.initialUser.hasRelationship('orga') && typeof this.initialUser.relationships.orga !== 'undefined' && this.initialUser.relationships.orga !== null
    },

    hasRoles () {
      return this.initialUser.hasRelationship('roles')
    },

    // Check if user name, roles, organisation or department match the entered search string
    isInFilter () {
      return (this.filterValue === '' ||
        this.initialUser.attributes.fullName.toLowerCase().includes(this.filterValue.toLowerCase()) ||
        /*
         * For now, we don't want to filter by email, because email is not displayed in closed user item - this might confuse users
         * this.user.attributes.email.toLowerCase().includes(this.filterValue.toLowerCase()) ||
         */
        this.userRolesNames.toString().toLowerCase().includes(this.filterValue.toLowerCase()) ||
        (this.userOrga !== null && this.userOrga.toString().toLowerCase().includes(this.filterValue.toLowerCase())) ||
        (this.userDepartment !== null && this.userDepartment.toString().toLowerCase().includes(this.filterValue.toLowerCase()))
      )
    },

    userDepartment () {
      return this.hasDepartment ? this.initialUser.relationships.department.get() : null
    },

    userOrga () {
      return this.hasOrga ? this.initialUser.relationships.orga.get() : null
    },

    userRoles () {
      return this.hasRoles ? this.initialUser.relationships.roles.list() : null
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
    ...mapActions('user', {
      saveUserAction: 'save',
      restoreUser: 'restoreFromInitial'
    }),

    ...mapMutations('user', ['setItem']),

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
      this.setItem({ group: null, ...payload })
    }
  }
}
</script>
