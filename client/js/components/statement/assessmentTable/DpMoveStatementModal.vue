<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="moveStatementModal"
    content-classes="u-1-of-2"
    @modal:toggled="handleModalToggled"
  >
    <!-- modal header -->
    <template v-slot:header>
      {{ Translator.trans('statement.moveto.procedure') }}
    </template>

    <!-- modal content -->
    <div>
      <dp-loading
        v-if="isLoading"
        class="u-pv-0_5"
      />
      <template v-else>
        <dp-inline-notification
          class="mb-2"
          :message="Translator.trans('statement.moveto.procedure.description')"
          type="warning"
        />
        <dp-inline-notification
          v-if="hasPermission('feature_statement_move_to_foreign_procedure')"
          class="mb-2"
          :message="Translator.trans('statement.moveto.procedure.description.foreignProcedures')"
          type="warning"
        />
        <!-- display if user is not the assignee of all fragments of this statement or if any fragments of this statement are currently assigned to departments -->
        <dp-inline-notification
          v-if="!userIsAssigneeOfAllFragments || isAnyFragmentAssignedToDepartment"
          class="mb-2"
          :message="Translator.trans('statement.moveto.procedure.fragments.not.claimed.warning')"
          type="warning"
        />
        <!-- When both permissions are available, the user is prompted to choose which type of procedure she wants to move the statement to -->
        <template v-if="hasPermission('feature_statement_move_to_foreign_procedure')">
          <label class="u-mb-0_5 inline-block">
            <input
              v-model="procedurePermissions"
              type="radio"
              name="procedure_permissions"
              value="accessibleProcedures"
              required
            >
            {{ Translator.trans('procedure.accessible') }}
          </label>
          <label class="u-mb-0_5 u-ml inline-block">
            <input
              v-model="procedurePermissions"
              type="radio"
              name="procedure_permissions"
              value="inaccessibleProcedures"
            >
            {{ Translator.trans('procedure.inaccessible') }}
          </label>
        </template>

        <label
          class="u-mb-0"
          for="r_target_procedure"
        >
          {{ Translator.trans('statement.moveto.procedure.target') }}
        </label>
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
        <div
          v-if="hasPermission('feature_statement_content_changes_view') || hasPermission('feature_statement_content_changes_save')"
          class="u-mb"
        >
          <input
            id="deleteVersionHistory"
            v-model="deleteVersionHistory"
            type="checkbox"
            aria-describedby="deleteHistoryDesc"
          >
          <label
            for="deleteVersionHistory"
            class="inline-block u-mb-0"
          >
            {{ Translator.trans('delete.history') }}
          </label>
          <p
            id="deleteHistoryDesc"
            class="lbl__hint"
          >
            {{ Translator.trans('delete.history.description') }}
          </p>
        </div>
        <!-- The button disabled-attribute is set to true when the user is not the assignee of all fragments or if any fragments are assigned to departments -->
        <button
          type="button"
          class="btn btn--primary float-right"
          :disabled="!userIsAssigneeOfAllFragments || isAnyFragmentAssignedToDepartment"
          @click.prevent.stop="moveStatement"
        >
          {{ Translator.trans('statement.moveto.procedure.action') }}
        </button>
      </template>
    </div>
  </dp-modal>
</template>

<script>
import { DpInlineNotification, DpLoading, DpModal, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'

export default {
  name: 'DpMoveStatementModal',

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

  emits: [
    'statement:moveToProcedure',
  ],

  data () {
    return {
      isLoading: true,
      procedurePermissions: 'accessibleProcedures',
      selectedProcedureId: '',
      statementId: null,
      statementFragments: [],
      deleteVersionHistory: false,
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', ['moveStatementModal']),
    ...mapGetters('Fragment', ['fragmentsByStatement']),
    ...mapState('AssessmentTable', ['currentUserId']),
    ...mapState('Statement', ['statements']),

    statement () {
      return this.statementId ? this.statements[this.statementId] : null
    },

    userIsAssigneeOfAllFragments () {
      return this.statementFragments.filter(fragment => this.currentUserId === fragment.assigneeId).length === this.statementFragments.length
    },

    /*
     * DepartmentId is set when a fragment is assigned to a department. If it is assigned to a department, the user can't move the statement despite being the assignee of the fragment.
     ** The check prevents failure of moveStatement due to fragments being assigned to departments.
     ** departmentId is either set to null or to '' (empty string) when the fragment is not assigned to any departments.
     */
    isAnyFragmentAssignedToDepartment () {
      return this.statementFragments.some(fragment => fragment.departmentId)
    },

    availableProcedures () {
      //  Always pick the list that is specified by radio inputs
      return this[this.procedurePermissions]
    },

    selectedProcedureName () {
      //  Get the object corresponding with the current selection
      return this.selectedProcedureId ? this.availableProcedures[this.selectedProcedureId].name : ''
    },
  },

  watch: {
    procedurePermissions: {
      handler () {
        //  Reset selection when radio list changes
        this.selectedProcedureId = ''
      },
      deep: true,
    },
  },

  methods: {
    ...mapActions('Fragment', [
      'loadFragments',
    ]),

    ...mapMutations('AssessmentTable', [
      'setModalProperty',
    ]),

    handleModalToggled (isOpen) {
      if (!isOpen) {
        this.setModalProperty({ prop: 'moveStatementModal', val: { show: false, statementId: null } })
        this.resetFragments()
      }
    },

    handleToggleModal () {
      this.selectedProcedureId = ''
      this.statementId = this.moveStatementModal.statementId
      this.toggleModal()
      this.handleFragments()
    },

    handleFragments () {
      if (this.statementId) {
        this.setFragments(this.statementId).then(() => { this.isLoading = false })
      } else {
        this.resetFragments()
      }
    },

    setFragments (statementId) {
      const setFragmentsInComponent = () => {
        const fragments = this.fragmentsByStatement(statementId).fragments
        this.statementFragments = fragments.map(fragment => {
          return {
            id: fragment.id,
            assigneeId: fragment.assignee?.id || '',
            departmentId: fragment.departmentId,
          }
        })
      }

      // If fragments are already loaded don't load them again
      if (this.statement.fragmentsTotal === this.fragmentsByStatement(statementId).fragments.length) {
        setFragmentsInComponent()
        return Promise.resolve(true)
      } else {
        return this.loadFragments({ procedureId: this.procedureId, statementId })
          .then(() => {
            setFragmentsInComponent()
          })
      }
    },

    resetFragments () {
      this.statementFragments = []
      this.isLoading = true
    },

    movedToAccessibleProcedure (procedureId) {
      return hasOwnProp(this.accessibleProcedures, procedureId)
    },

    moveStatement () {
      //  Return when no procedure is selected
      if (this.selectedProcedureId === '') {
        dplan.notify.notify('error', Translator.trans('warning.select.entry'))
        return
      }

      //  Trigger confirm
      if (!dpconfirm(Translator.trans('statement.check.procedure.move', { name: this.selectedProcedureName }))) {
        return
      }

      //  Fire action from store
      this.$store.dispatch('Statement/moveStatementAction', {
        procedureId: this.selectedProcedureId,
        statementId: this.statementId,
        deleteVersionHistory: this.deleteVersionHistory,
      })
        .then(response => {
        // If the user is not authorized to move the statement, the movedStatementId in the response is an empty string
          if (hasOwnProp(response, 'data') && response.data.movedStatementId !== '') {
            const { movedToProcedureId, movedStatementId, placeholderStatementId, movedToProcedureName } = response.data.data

            const moveToProcedureParams = {
              movedToProcedureId,
              statementId: this.statementId,
              movedStatementId,
              placeholderStatementId,
              movedToAccessibleProcedure: this.movedToAccessibleProcedure(movedToProcedureId),
              movedToProcedureName: movedToProcedureName || '',
            }

            // Handle update of assessment table ui from TableCard.vue
            this.$root.$emit('statement:moveToProcedure', moveToProcedureParams)
          }
          this.toggleModal()
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.results.loading'))
          this.toggleModal()
        })
    },

    toggleModal () {
      this.$refs.moveStatementModal.toggle()
    },
  },

  mounted () {
    this.$nextTick(() => {
      this.handleToggleModal()
    })
  },
}
</script>
