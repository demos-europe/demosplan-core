<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="copyStatementModal"
    content-classes="u-1-of-2"
    @modal:toggled="handleModalToggled"
  >
    <!-- Modal header -->
    <template v-slot:header>
      {{ Translator.trans('statement.copy.to.procedure') }}
    </template>

    <!-- Modal content -->
    <div>
      <dp-loading
        v-if="isLoading"
        class="u-pv-0_5"
      />
      <template v-else>
        <!-- Display if user is not the assignee of all fragments of this statement or if any fragments of this statement are currently assigned to departments -->
        <dp-inline-notification
          v-if="!userIsAssigneeOfAllFragments || isAnyFragmentAssignedToDepartment"
          class="mb-2"
          :message="Translator.trans('statement.copy.to.procedure.fragments.not.claimed.warning')"
          type="warning"
        />
        <!-- When both permissions are available, the user is prompted to choose which type of procedure she wants to move the statement to -->
        <template v-if="hasPermission('feature_statement_copy_to_foreign_procedure')">
          <label class="u-mb-0_5 inline-block">
            <input
              v-model="procedurePermissions"
              type="radio"
              name="procedure_permissions"
              value="accessibleProcedures"
              required
              @change="resetSelectedProcedureId"
            > {{ Translator.trans('procedure.accessible') }}
          </label>
          <label class="u-mb-0_5 u-ml inline-block">
            <input
              v-model="procedurePermissions"
              type="radio"
              name="procedure_permissions"
              value="inaccessibleProcedures"
            > {{ Translator.trans('procedure.inaccessible') }}
          </label>
        </template>

        <label
          class="u-mb-0_5"
          for="r_target_procedure"
        >{{ Translator.trans('target.procedure') }}</label>
        <select
          id="r_target_procedure"
          v-model="selectedProcedureId"
          name="r_target_procedure"
          class="w-full u-mb"
        >
          <option value="">
            -
          </option>
          <option
            v-for="procedure in availableProcedures"
            :key="procedure.id"
            :value="procedure.id"
          >
            {{ procedure.name }}
          </option>
        </select>
        <!-- The button disabled-attribute is set to true when the user is not the assignee of all fragments or if any fragments are assigned to departments -->
        <button
          type="button"
          class="btn btn--primary float-right"
          :disabled="!userIsAssigneeOfAllFragments || isAnyFragmentAssignedToDepartment"
          @click.prevent.stop="copyStatement"
        >
          {{ Translator.trans('statement.copy.to.procedure.action') }}
        </button>
      </template>
    </div>
  </dp-modal>
</template>

<script>
import { DpInlineNotification, DpLoading, DpModal, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'

export default {
  name: 'CopyStatementModal',

  components: {
    DpInlineNotification,
    DpModal,
    DpLoading,
  },

  props: {
    procedureId: {
      required: true,
      type: String,
    },

    accessibleProcedures: {
      required: false,
      type: Object,
      default: () => ({}),
    },

    inaccessibleProcedures: {
      required: false,
      type: Object,
      default: () => ({}),
    },
  },

  data () {
    return {
      isLoading: true,
      procedurePermissions: 'accessibleProcedures',
      selectedProcedureId: '',
      statementId: null,
      statementFragments: [],
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', [
      'copyStatementModal',
    ]),

    ...mapGetters('Fragment', [
      'fragmentsByStatement',
    ]),

    ...mapState('AssessmentTable', [
      'currentUserId',
    ]),

    ...mapState('Statement', [
      'statements',
    ]),

    //  Always pick the list that is specified by radio inputs
    availableProcedures () {
      return this[this.procedurePermissions]
    },

    /*
     * DepartmentId is set when a fragment is assigned to a department. If it is assigned to a department, the user can't copy the statement despite being the assignee of the fragment.
     ** The check prevents failure of copyStatement due to fragments being assigned to departments.
     ** departmentId is either set to null or to '' (empty string) when the fragment is not assigned to any departments.
     */
    isAnyFragmentAssignedToDepartment () {
      return this.statementFragments.some(fragment => fragment.departmentId)
    },

    isNoProcedureSelected () {
      return this.selectedProcedureId === ''
    },

    //  Get the object corresponding with the current selection
    selectedProcedureName () {
      return this.selectedProcedureId ? this.availableProcedures[this.selectedProcedureId].name : ''
    },

    statement () {
      return this.statementId ? this.statements[this.statementId] : null
    },

    userIsAssigneeOfAllFragments () {
      return this.statementFragments.filter(fragment => this.currentUserId === fragment.assigneeId).length === this.statementFragments.length
    },
  },

  methods: {
    ...mapActions('Fragment', [
      'loadFragments',
    ]),

    ...mapMutations('AssessmentTable', [
      'setModalProperty',
    ]),

    copyStatement () {
      if (this.isNoProcedureSelected) {
        dplan.notify.notify('error', Translator.trans('warning.select.entry'))
        return
      }

      //  Trigger confirm
      if (!dpconfirm(Translator.trans('statement.check.procedure.copy', { name: this.selectedProcedureName }))) {
        return
      }

      this.$store.dispatch('Statement/copyStatementAction', {
        procedureId: this.selectedProcedureId,
        statementId: this.statementId,
      })
        .finally(() => {
          this.setModalProperty({
            prop: 'copyStatementModal',
            val: {
              ...this.copyStatementModal,
              statementId: null,
            },
          })
          this.handleToggleModal()
        })
    },

    // Fetch statement fragments to check if user can move this statement
    handleFragments () {
      if (this.statementId) {
        this.setFragments()
          .then(() => { this.isLoading = false })
      } else {
        this.resetFragments()
      }
    },

    // Called when modal is toggled from DpModal
    handleModalToggled (isOpen) {
      if (!isOpen) {
        this.setModalProperty({ prop: 'copyStatementModal', val: { ...this.copyStatementModal, show: false } })
      }

      this.resetFragments()
    },

    handleToggleModal () {
      this.resetSelectedProcedureId()
      this.statementId = this.copyStatementModal.statementId
      this.toggleModal()
      this.handleFragments()
    },

    movedToAccessibleProcedure (procedureId) {
      return hasOwnProp(this.accessibleProcedures, procedureId)
    },

    //  Reset selection when radio list changes
    resetSelectedProcedureId () {
      this.selectedProcedureId = ''
    },

    toggleModal () {
      this.$refs.copyStatementModal.toggle()
    },

    setFragments () {
      const setFragmentsInComponent = () => {
        const fragments = this.fragmentsByStatement(this.statementId).fragments
        this.statementFragments = fragments.map(fragment => {
          return {
            id: fragment.id,
            assigneeId: fragment.assignee?.id || '',
            departmentId: fragment.departmentId,
          }
        })
      }

      // If fragments are already loaded don't load them again
      if (this.statement.fragmentsTotal === this.fragmentsByStatement(this.statementId).fragments.length) {
        setFragmentsInComponent()
        return Promise.resolve(true)
      } else {
        return this.loadFragments({ procedureId: this.procedureId, statementId: this.statementId })
          .then(() => {
            setFragmentsInComponent()
          })
      }
    },

    resetFragments () {
      this.statementFragments = []
      this.isLoading = true
    },
  },

  mounted () {
    this.$nextTick(() => {
      this.handleToggleModal()
    })
  },
}
</script>
