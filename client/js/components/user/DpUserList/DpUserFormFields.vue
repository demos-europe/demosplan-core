<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="u-1-of-2 display--inline-block u-pr-0_5">
      <label
        class="push--bottom validation--fail u-mb-0_25"
        :for="userId + ':firstName'">
        {{ Translator.trans('name.first') }}*
      </label>
      <input
        required
        :id="userId + ':firstName'"
        class="layout__item u-mb-0_5 u-pl-0_25"
        style="height: 28px;"
        type="text"
        v-model="localUser.attributes.firstname"
        @input="emitUserUpdate"
        data-cy="firstName">
    </div><!--
     --><div class="u-1-of-2 display--inline-block u-pl-0_5">
      <label
        class="push--bottom u-mt-0_5 u-mb-0_25"
        :for="userId + ':lastName'">
        {{ Translator.trans('name.last') }}*
      </label>
      <input
        required
        :id="userId + ':lastName'"
        class="layout__item u-mb-0_5"
        style="height: 28px;"
        type="text"
        v-model="localUser.attributes.lastname"
        @input="emitUserUpdate"
        data-cy="lastName">
    </div>

    <!-- Email -->
    <div class="u-1-of-2 u-pr-0_5">
      <label
        :for="userId + ':email'"
        class="push--bottom u-mt-0_25 u-mb-0_25">
        {{ Translator.trans('email') }}*
        <p class="milli weight--normal flush--bottom">
          {{ Translator.trans('explanation.user.email', { projectName: projectName }) }}
        </p>
      </label>
      <input
        required
        :id="userId + ':email'"
        class="layout__item u-pl-0_25"
        style="height: 28px;"
        type="email"
        v-model="localUser.attributes.email"
        @input="emitUserUpdate"
        data-cy="userEmail">
    </div>

    <div class="u-1-of-2 u-pr-0_5 u-mt-0_25 display--inline-block">
      <label
        class="push--bottom u-mb-0_25 u-mt-0_5"
        :for="userId + ':organisationId'">
        {{ Translator.trans('organisation') }}*
      </label>
      <dp-multiselect
        v-if="hasPermission('area_organisations')"
        :id="userId + ':organisationId'"
        data-cy="organisation"
        label="name"
        :loading="isLoading"
        :options="initialOrgaSuggestions"
        :placeholder="Translator.trans('search.three.signs')"
        ref="orgasDropdown"
        required
        track-by="id"
        :value="currentUserOrga"
        @select="changeUserOrga">
        <template v-slot:option="{ props }">
          <span>{{ props.option.name }}</span>
        </template>
        <template v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ props.option.name }}
          </span>
        </template>
      </dp-multiselect>
      <span v-else>
        {{ currentUserOrga.name || '' }}
      </span>
    </div><!--
    --><div class="u-1-of-2 display--inline-block u-pl-0_5">
      <label
        class="push--bottom u-mb-0_25 u-mt-0_5"
        :for="userId + ':departmentId'">
        {{ Translator.trans('department') }}*
        <p
          v-if="localUser.relationships.orga.data.id === ''"
          class="lbl__hint">
          {{ Translator.trans('organisation.select.first') }}
        </p>
      </label>
      <select
        required
        class="layout__item"
        style="height: 28px; background: white;"
        :id="userId + ':departmentId'"
        :disabled="noOrgaSelected"
        @change="changeUserDepartment"
        data-cy="department">
        <option
          v-for="department in currentOrgaDepartments"
          :key="department.id || null"
          :value="department.id"
          :selected="localUser.relationships.department.data.id === department.id">
          {{ department.name }}
        </option>
      </select>
    </div>

    <!-- Role -->
    <div
      v-if="organisations[this.currentUserOrga.id]"
      class=" u-1-of-2 u-pr-0_5">
      <label
        class="push--bottom u-mt-0_75 u-mb-0_25"
        :for="userId + ':userRoles'">
        {{ Translator.trans('role') }}*
      </label>
      <dp-multiselect
        :id="userId + ':userRoles'"
        class="u-mb-0_5"
        :custom-label="props =>`${ roles[props.option.id].attributes.name }`"
        data-cy="role"
        label="name"
        multiple
        :options="allowedRolesForOrga"
        ref="rolesDropdown"
        required
        track-by="id"
        :value="localUser.relationships.roles.data"
        @select="addRole"
        @remove="removeRole">
        <template v-slot:option="{ props }">
          <span>{{ roles[props.option.id].attributes.name }}</span>
        </template>
        <template v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ roles[props.option.id].attributes.name }}
            <i
              aria-hidden="true"
              tabindex="1" class="multiselect__tag-icon"
              @click="props.remove(props.option)" />
            <input
              :name="userId + ':userRoles[]'"
              type="hidden"
              :value="props.option.id">
          </span>
        </template>
      </dp-multiselect>
    </div>
  </div>
</template>

<script>
import { dpApi, DpMultiselect, hasOwnProp, sortAlphabetically } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import qs from 'qs'

export default {
  name: 'DpUserFormFields',
  components: {
    DpMultiselect
  },

  inject: [
    'presetUserOrgaId',
    'projectName'
  ],

  props: {
    user: {
      type: Object,
      required: false,
      default: () => {
        return {
          attributes: {
            firstname: '',
            lastname: '',
            email: ''
          },
          relationships: {
            roles: {
              data: []
            },
            orga: {
              data: {
                id: ''
              }
            },
            department: {
              data: {
                id: ''
              }
            }
          }
        }
      }
    },

    userId: {
      type: String,
      required: false,
      default: () => ''
    }
  },

  data () {
    return {
      currentUserOrga: {
        id: '',
        name: '',
        departments: {}
      },
      isLoading: false,
      localUser: {}
    }
  },

  computed: {
    ...mapGetters('role', {
      rolesInRelationshipFormat: 'itemsInRelationshipFormat'
    }),

    ...mapGetters('orga', {
      orgasInRelationshipFormat: 'itemsInRelationshipFormat'
    }),

    ...mapState('orga', {
      organisations: 'items'
    }),

    ...mapState('department', {
      departments: 'items'
    }),

    ...mapState('role', {
      roles: 'items'
    }),

    ...mapGetters('UserFormFields', {
      initialOrgaSuggestions: 'getOrgaSuggestions'
    }),

    /**
     * - user is not set: all roles
     * - user is set: roles for current organisation
     */
    allowedRolesForOrga () {
      let allowedRoles

      if (this.currentUserOrga.id === '') {
        allowedRoles = this.rolesInRelationshipFormat
      } else if (hasOwnProp(this.organisations[this.currentUserOrga.id].relationships, 'allowedRoles')) {
        allowedRoles = Object.values(this.organisations[this.currentUserOrga.id].relationships.allowedRoles.list())
      } else {
        allowedRoles = this.getOrgaAllowedRoles(this.currentUserOrga.id)
      }

      return allowedRoles
    },

    currentOrgaDepartments () {
      const departments = sortAlphabetically(Object.values(this.currentUserOrga.departments), 'name')
      const noDepartmentIdx = departments.findIndex(el => el.name === Translator.trans('department.none'))

      if (noDepartmentIdx > -1) {
        const noDepartment = departments.splice(noDepartmentIdx, 1)[0]
        departments.unshift(noDepartment)
      }

      return departments
    },

    isDepartmentSet () {
      return this.localUser.relationships.department.data.id !== ''
    },

    isManagingSingleOrganisation () {
      return hasPermission('area_organisations') === false && this.presetUserOrgaId
    },

    isUserSet () {
      return this.localUser.id && this.localUser.id !== ''
    },

    noOrgaSelected () {
      return Object.values(this.currentUserOrga.departments).length === 0
    }
  },

  methods: {
    ...mapActions('orga', {
      organisationList: 'list'
    }),

    ...mapMutations('orga', ['setItem']),

    addRole (role) {
      this.localUser.relationships.roles.data.push(role)
      this.emitUserUpdate('relationships.roles.data', role, 'roles', 'add')
    },

    changeUserDepartment (e) {
      const departmentId = e.target.value
      this.localUser.relationships.department.data = {
        id: departmentId,
        type: 'department'
      }

      this.$emit('user-update', this.localUser)
    },

    changeUserOrga (orga) {
      this.setCurrentUserOrganisation(orga)
      this.setDefaultDepartment(orga)
      this.resetRoles()
      this.$emit('user-update', this.localUser)
    },

    emitUserUpdate () {
      // NextTick is needed because the selects do not update the local user before the emitUserUpdate method is invoked
      this.$nextTick(() => {
        this.$emit('user-update', this.localUser)
      })
    },

    /**
     * Fetch organisation of user or, in DpCreateItem, of currently logged-in user
     */
    fetchCurrentOrganisation () {
      const orgaId = this.user.relationships.orga && this.user.relationships.orga.data.id
        ? this.user.relationships.orga.data.id
        : this.presetUserOrgaId
      if (orgaId !== '') {
        const url = Routing.generate('dplan_api_orga_get', { id: orgaId }) + '?' + qs.stringify({ include: 'departments' })
        return dpApi.get(url)
      }

      return new Promise((resolve, reject) => resolve(true))
    },

    /**
     *  Handle cases in which organisation lost allowedRoles from the relationships after user update action
     *  a separate method is used to avoid fetching allowedRoles by mounting (DpUserList component fetches orga.allowedRoles)
     *  @param types {String}
     */
    fetchOrgaById (orgaId) {
      const url = Routing.generate('dplan_api_orga_get', { id: orgaId })

      return dpApi.get(url, { include: ['allowedRoles', 'departments'].join() })
    },

    getOrgaAllowedRoles (orgaId) {
      let allowedRoles = this.rolesInRelationshipFormat

      this.fetchOrgaById(orgaId).then((orga) => {
        this.setOrga(orga.data.data)
        if (hasOwnProp(this.organisations[this.currentUserOrga.id].relationships, 'allowedRoles')) {
          allowedRoles = this.organisations[this.currentUserOrga.id].relationships.allowedRoles.list()
        }
      })

      return allowedRoles
    },

    /**
     *  Handle cases in which organisation or department are undefined so this component still gets rendered
     *  @param types {Array}
     */
    handleUndefinedRelationships (types) {
      types.forEach(type => {
        if (typeof this.localUser.relationships[type] === 'undefined' || this.localUser.relationships[type] === null) {
          this.localUser.relationships[type] = {
            data: {
              id: ''
            }
          }
        }
      })
    },

    removeRole (role) {
      this.localUser.relationships.roles.data = this.localUser.relationships.roles.data.filter(r => r.id !== role.id)
      this.emitUserUpdate('relationships.roles.data', role, 'roles', 'remove')
    },

    resetData () {
      if (hasPermission('area_organisations') === false) {
        const plainUser = JSON.parse(JSON.stringify(this.user))
        delete plainUser.relationships
        plainUser.relationships = this.localUser.relationships
        plainUser.relationships.roles.data = []
        this.localUser = plainUser
      } else {
        this.localUser = JSON.parse(JSON.stringify(this.user))
        this.currentUserOrga = {
          id: '',
          name: '',
          departments: {}
        }
      }
    },

    resetRoles () {
      this.localUser.relationships.roles.data = []
    },

    setCurrentUserOrganisation (organisation, rels) {
      this.currentUserOrga = { ...organisation, relationships: rels }
      this.localUser.relationships.orga.data = { id: organisation.id, type: organisation.type }
      this.localUser.relationships.orga.relationships = rels
    },

    setDefaultDepartment (organisation) {
      const defaultDepartment = Object.values(organisation.departments).find(dep => dep.name === 'Keine Abteilung') || Object.values(organisation.departments)[0]
      this.localUser.relationships.department.data = { id: defaultDepartment.id, type: defaultDepartment.type }
    },

    setInitialOrgaData () {
      /*
       * Fetch organisation only
       * - in DpUserListItem (= isUserSet), not in DpCreateItem (= isUserSet === false)
       * - for users who can only create users for their own organisation (currently Fachplaner-Masteruser)
       */
      if (this.isUserSet || this.isManagingSingleOrganisation) {
        this.fetchCurrentOrganisation()
          .then((response) => {
            if (response && response.data) {
              this.setOrganisationWithDepartments(response)
            }
          })
      }
    },

    setOrganisationDepartments (departments) {
      const orgaDepartments = {}

      departments.forEach(dep => {
        if (dep.type === 'Department') {
          orgaDepartments[dep.id] = { ...dep.attributes, id: dep.id, type: dep.type }
        }
      })

      return orgaDepartments
    },

    setOrganisationWithDepartments (response) {
      const userOrga = { ...response.data.data.attributes, id: response.data.data.id, type: response.data.data.type }
      const relationships = response.data.data.relationships
      const departments = response.data.included
      userOrga.departments = this.setOrganisationDepartments(departments)

      this.setCurrentUserOrganisation(userOrga, relationships)

      if (this.isDepartmentSet === false) {
        this.setDefaultDepartment(userOrga)
      }
    },

    setOrga (payload) {
      const payloadRel = payload.relationships
      const payloadWithNewType = {
        ...payload,
        id: payload.id,
        attributes: {
          ...payload.attributes
        },
        // We have to hack it like this, because the types for relationships have to be in camelCase and not in PascalCase
        relationships: {
          allowedRoles: {
            data: payloadRel.allowedRoles?.data[0].id
              ? payloadRel.allowedRoles.data.map(el => {
                return {
                  ...el,
                  type: 'role'
                }
              })
              : null
          },
          currentSlug: {
            data: {
              id: payloadRel.currentSlug.data.id,
              type: 'slug'
            }
          },
          departments: {
            data: payloadRel.departments?.data[0].id
              ? payloadRel.departments.data.map(el => {
                return {
                  ...el,
                  type: 'department'
                }
              })
              : null
          }
        }
      }

      this.setItem(payloadWithNewType)
    }
  },

  created () {
    this.localUser = JSON.parse(JSON.stringify(this.user))
    this.handleUndefinedRelationships(['orga', 'department'])
  },

  mounted () {
    this.setInitialOrgaData()

    this.$root.$on('user-reset', () => {
      if (!this.isUserSet) {
        this.resetData()
      }
    })
  }
}
</script>
