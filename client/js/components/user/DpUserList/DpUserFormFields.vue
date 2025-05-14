<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="whitespace-nowrap">
    <div class="inline-block w-1/2 pr-3 my-3">
      <dp-input
        :id="userId + ':firstName'"
        v-model="localUser.attributes.firstname"
        data-cy="firstName"
        :label="{
          text: Translator.trans('name.first')
        }"
        required
        @input="emitUserUpdate" />
    </div>

    <div class="inline-block w-1/2 pr-3 my-3">
      <dp-input
        :id="userId + ':lastName'"
        v-model="localUser.attributes.lastname"
        data-cy="lastName"
        :label="{
          text: Translator.trans('name.last')
        }"
        required
        @input="emitUserUpdate" />
    </div>

    <!-- Email -->
    <div class="w-1/2 pr-3 mb-3">
      <dp-input
        :id="userId + ':email'"
        v-model="localUser.attributes.email"
        data-cy="userEmail"
        :label="{
          hint: Translator.trans('explanation.user.email', { projectName: projectName }),
          text: Translator.trans('email')
        }"
        required
        @input="emitUserUpdate" />
    </div>

    <div class="w-1/2 pr-3 inline-block">
      <label
        class="mb-1.5 mt-3"
        :for="userId + ':organisationId'">
        {{ Translator.trans('organisation') }}*
      </label>
      <dp-multiselect
        v-if="hasPermission('area_organisations')"
        :id="userId + ':organisationId'"
        ref="orgasDropdown"
        data-cy="organisation"
        label="name"
        :loading="isLoading"
        :options="initialOrgaSuggestions"
        :placeholder="Translator.trans('search.three.signs')"
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
    </div>

    <div class="w-1/2 pr-3 inline-block">
      <dp-select
        :id="userId + ':departmentId'"
        data-cy="department"
        :disabled="noOrgaSelected"
        :label="{
          hint: localUser.relationships.orga?.data?.id ? '' : Translator.trans('organisation.select.first'),
          text: Translator.trans('department')
        }"
        :options="departmentSelectOptions"
        required
        :selected="localUser.relationships.department.data.id"
        @select="changeUserDepartment" />
    </div>

    <!-- Role -->
    <div
      v-if="organisations[this.currentUserOrga.id]"
      class="w-1/2 pr-3 mt-3">
      <label
        class="u-mt-0_75 mb-1.5"
        :for="userId + ':userRoles'">
        {{ Translator.trans('role') }}*
      </label>
      <dp-multiselect
        :id="userId + ':userRoles'"
        ref="rolesDropdown"
        class="u-mb-0_5 whitespace-normal"
        :custom-label="option =>`${ roles[option.id].attributes.name }`"
        data-cy="role"
        label="name"
        multiple
        :options="allowedRolesForOrga"
        required
        track-by="id"
        :value="localUser.relationships.roles.data"
        @remove="removeRole"
        @select="addRole">
        <template v-slot:option="{ props }">
          <span>{{ roles[props.option.id].attributes.name }}</span>
        </template>
        <template v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ roles[props.option.id].attributes.name }}
            <i
              aria-hidden="true"
              class="multiselect__tag-icon"
              tabindex="1"
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
import { dpApi, DpInput, DpMultiselect, DpSelect, hasOwnProp, sortAlphabetically } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import qs from 'qs'

export default {
  name: 'DpUserFormFields',
  components: {
    DpInput,
    DpMultiselect,
    DpSelect
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

    departmentSelectOptions () {
      return this.currentOrgaDepartments.map(department => ({ label: department.name, value: department.id }))
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

    changeUserDepartment (departmentId) {
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
      Vue.nextTick(() => {
        this.$emit('user-update', this.localUser)
      })
    },

    /**
     * Fetch organisation of user or, in DpCreateItem, of currently logged-in user
     */
    fetchCurrentOrganisation () {
      const orgaId = this.user.relationships.orga?.data?.id
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
          branding: {
            data: {
              id: payloadRel.branding.data.id,
              type: 'branding'
            }
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
