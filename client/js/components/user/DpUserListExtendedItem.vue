<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-table-card
    class="layout__item c-card u-1-of-1"
    :open="isOpen">
    <!-- card header -->
    <template
      v-slot:header="">
      <div
        :class="{ 'u-pb-0_5 border--bottom': isOpen }"
        class="cursor-pointer"
        @click="handleToggle">
        <div class="w-[20px] u-pv-0_25 inline-block">
          <input
            type="checkbox"
            :id="`selected` + user.id"
            :checked="selected"
            :value="user.id"
            @click.stop="$emit('change')"
            @change="$emit('item:selected', user.id)">
        </div><!--
     --><div
          class="layout__item u-1-of-4 u-pv-0_25">
          {{ user.attributes?.firstname }} {{ user.attributes?.lastname }}
        </div><!--
     --><div
          class="break-words layout__item u-1-of-4 u-pv-0_25">
          {{ user.attributes?.login }}
        </div><!--
     --><div
        class="layout__item u-1-of-4 u-ml-0_25 u-pv-0_25">
          {{ user.attributes?.email }}
        </div><!--
      --><div class="text-right layout__item u-ml-0_5 u-1-of-5 u-pv-0_25">
            <button
              type="button"
              title="Löschen"
              class="btn--blank o-link--default u-mr"
              @click.prevent.stop="$emit('delete')"
              data-cy="deleteItem"
              :aria-label="Translator.trans('item.delete')">
              <i
                class="fa fa-trash"
                aria-hidden="true" />
            </button>
            <button
              type="button"
              class="btn--blank o-link--default">
              <i
                class="fa"
                :class="isOpen ? 'fa-angle-up': 'fa-angle-down'" />
            </button>
        </div><!--
   -->
      </div>
    </template>

    <!-- card content -->
    <dl
      :class="{'u-pt-0_5' : isOpen}"
      class="layout c-at-item__row u-pl-1_5 u-mr u-1-of-1">
      <dd class="layout__item u-pr u-1-of-2">
        <dp-edit-field-single-select
          :entity-id="user.id"
          field-key="organisation"
          label="Organisation"
          :label-grid-cols="4"
          :options="availableOrganisations"
          :readonly="!hasPermission('feature_user_edit')"
          :value="currentOrganisation"
          @field:input="(val) => updateRelationship('currentOrganisation', val)"
          @field:save="saveUser" />
      </dd><!--
   --><dd class="layout__item u-1-of-2">
        <dp-edit-field-single-select
          ref="departmentSelect"
          :entity-id="user.id"
          field-key="department"
          label="Abteilung"
          :label-grid-cols="4"
          :options="availableDepartments"
          :readonly="!hasPermission('feature_user_edit')"
          :value="currentDepartment"
          @field:input="(val) => updateRelationship('currentDepartment', val)"
          @field:save="saveUser" />
      </dd>
    </dl>
  </dp-table-card>
</template>

<script>
import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import DpTableCard from '@DpJs/components/user/DpTableCardList/DpTableCard'

export default {
  name: 'DpUserListExtendedItem',

  components: {
    DpEditFieldSingleSelect: () => import(/* webpackChunkName: "dp-edit-field-single-select" */ '@DpJs/components/statement/assessmentTable/DpEditFieldSingleSelect'),
    DpTableCard
  },

  props: {
    allDepartments: {
      type: Array,
      required: true
    },

    allOrganisations: {
      type: Array,
      required: true
    },

    isOpen: {
      type: Boolean,
      default: false
    },

    selected: {
      required: false,
      type: Boolean,
      default: false
    },

    user: {
      type: Object,
      default: () => ({ })
    }
  },

  data () {
    return {
      currentDepartment: {},
      currentOrganisation: {},
      // Options for department select
      availableDepartments: [],
      // Options for organisation select
      availableOrganisations: []
    }
  },

  computed: {
    ...mapState('Department', {
      departmentsList: 'items'
    }),

    getOrgaId () {
      return this.user?.relationships?.orga.data?.id
    },

    isInstitution () {
      const currentOrg = this.allOrganisations.find(org => org.id === this.currentOrganisation.id)
      return currentOrg ? currentOrg.relationships?.masterToeb?.data !== null : false
    }
  },

  methods: {
    ...mapActions('AdministratableUser', {
      saveUserAction: 'save'
    }),

    initialUserDepartment () {
      return {
        id: this.user?.relationships?.orga.data?.id,
        title: this.getDepartmentName()
      }
    },

    initialUserOrganisation () {
      return {
        id: this.user?.relationships?.orga.data?.id,
        title: this.getOrgaName()
      }
    },

    /**
     * - add departments to organisations
     * - convert to format required by EditFieldSingleSelect.vue
     * - sort alphabetically
     *
     * @param organisations {Array}
     * @param departments {Array}
     *
     * @return organisations {Array}
     */
    convertResponseData (organisations, departments) {
      // Add departments from included to each organisation
      return organisations.map(org => {
        org.departments = org.relationships.departments.data
          .filter(dep => typeof departments.find(el => el.id === dep.id) !== 'undefined')
          .map(dep => ({ id: dep.id, title: departments.find(el => el.id === dep.id).attributes?.name }))
          .sort((a, b) => a.title.localeCompare(b.title, 'de', { sensitivity: 'base' }))
        // Set component state for current org
        if (org.id === this.currentOrganisation.id) {
          this.currentOrganisation = {
            ...this.currentOrganisation,
            departments: org.departments
          }
        }
        // Convert to required format
        return { id: org.id, title: org.attributes?.name, departments: org.departments }
      })
        .sort((a, b) => a.title.localeCompare(b.title, 'de', { sensitivity: 'base' }))
    },

    /**
     * Find options for organisation select
     * filteredOrgas returns an array which are institutions and which belongs to masterToeb
     */
    findAvailableOrganisations () {
      const filteredOrgas = this.allOrganisations.filter(org => {
        if (this.isInstitution) {
          return org.relationships?.masterToeb?.data !== null
        } else {
          return org.relationships?.masterToeb?.data === null
        }
      })
      const allDeps = Object.values(this.departmentsList)

      // Add departments from included to organisations, convert to format required by EditFieldSingleSelect.vue
      const convertedOrgs = this.convertResponseData(filteredOrgas, allDeps)

      // Set component state
      this.setAvailableOrganisations(convertedOrgs)
      this.setAvailableDepartments()
    },

    getDepartmentName () {
      const department = this.allDepartments.find(el => el.id === this.user?.relationships?.department.data?.id)
      return department.attributes?.name
    },

    getOrgaName () {
      const orga = this.allOrganisations.find(el => el.id === this.user?.relationships?.orga?.data?.id)
      return orga?.attributes?.name ?? ''
    },

    handleToggle () {
      if (this.isOpen) {
        this.$root.$emit('reset')
      } else {
        this.findAvailableOrganisations()
      }
      this.$emit('card:toggle')
    },

    /**
     * Reset selected department to 'Keine Abteilung' or first option
     */
    resetCurrentDepartment () {
      const noDepartmentOption = this.currentOrganisation.departments.find(dep => dep.title === 'Keine Abteilung')
      const firstDepartmentOption = this.currentOrganisation.departments[0]
      this.currentDepartment = noDepartmentOption || firstDepartmentOption
    },

    saveUser () {
      // If currently selected department doesn't match current orga, reset to 'Keine Abteilung' or first option of current orga
      if (typeof this.currentOrganisation.departments.find(dep => dep.id === this.currentDepartment.id) === 'undefined') {
        this.resetCurrentDepartment()
      }

      const url = Routing.generate('api_resource_update', { resourceType: 'AdministratableUser', resourceId: this.user.id })
      const payload = {
        data: {
          id: this.user.id,
          relationships: {
            orga: {
              data: {
                id: this.currentOrganisation.id,
                type: 'Orga'
              }
            },
            department: {
              data: {
                id: this.currentDepartment.id,
                type: 'Department'
              }
            }
          },
          type: 'AdministratableUser'
        }
      }

      return dpApi.patch(url, {}, payload)
        .then(checkResponse, {
          200: { type: 'confirm', text: 'info.user.updated' },
          204: { type: 'confirm', text: 'info.user.updated' }
        })
        .then(() => {
          this.$root.$emit('save-success')
          // Update department options
          this.setAvailableDepartments()
        })
    },

    /**
     * Set options for department select
     * @param deps {Array}
     */
    setAvailableDepartments () {
      this.availableDepartments = this.currentOrganisation?.departments?.length > 0 ? this.currentOrganisation?.departments : []
    },

    /**
     * Set options for organisation select
     * @param orgs {Array}
     */
    setAvailableOrganisations (orgs) {
      this.availableOrganisations = orgs
    },

    setInitialUserData () {
      this.currentOrganisation = this.initialUserOrganisation()
      this.currentDepartment = this.initialUserDepartment()
    },

    /**
     * Update relationship in component state
     * @param prop {String}
     * @param val {Object}
     */
    updateRelationship (prop, val) {
      this[prop] = val
    }
  },

  mounted () {
    this.setInitialUserData()
  }
}
</script>
