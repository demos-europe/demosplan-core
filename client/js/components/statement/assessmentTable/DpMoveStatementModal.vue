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
    @modal:toggled="resetFragments">
    <!-- modal header -->
    <template v-slot:header>
      {{ Translator.trans('statement.moveto.procedure') }}
    </template>

    <!-- modal content -->
    <div>
      <dp-loading
        v-if="isLoading"
        class="u-pv-0_5" />
      <template v-else>
        <div class="flash flash-warning flow-root">
          <i class="fa fa-exclamation-triangle u-mt-0_125 float-left" />
          <div class="u-ml">
            <p
              :class="{'u-mb-0': false === hasPermission('feature_statement_move_to_foreign_procedure')}"
              :inner-html="Translator.trans('statement.moveto.procedure.description')" />
            <p
              class="u-mb-0"
              v-if="hasPermission('feature_statement_move_to_foreign_procedure')"
              :inner-html="Translator.trans('statement.moveto.procedure.description.foreignProcedures')" />
          </div>
        </div>

        <!-- display if user is not the assignee of all fragments of this statement or if any fragments of this statement are currently assigned to departments -->
        <div
          class="flash flash-warning flow-root"
          v-if="(userIsAssigneeOfAllFragments && fragmentsAreNotAssignedToDepartments) === false">
          <i class="fa fa-exclamation-triangle u-mt-0_125 float-left" />
          <div class="u-ml">
            <p
              class="u-mb-0"
              :inner-html="Translator.trans('statement.moveto.procedure.fragments.not.claimed.warning')" />
          </div>
        </div>

        <!-- When both permissions are available, the user is prompted to choose which type of procedure she wants to move the statement to -->
        <template v-if="hasPermission('feature_statement_move_to_foreign_procedure')">
          <label class="u-mb-0_5 inline-block">
            <input
              type="radio"
              name="procedure_permissions"
              v-model="procedurePermissions"
              value="accessibleProcedures"
              required> {{ Translator.trans('procedure.accessible') }}
          </label>
          <label class="u-mb-0_5 u-ml inline-block">
            <input
              type="radio"
              name="procedure_permissions"
              v-model="procedurePermissions"
              value="inaccessibleProcedures"> {{ Translator.trans('procedure.inaccessible') }}
          </label>
        </template>

        <label
          class="u-mb-0"
          for="r_target_procedure">{{ Translator.trans('statement.moveto.procedure.target') }}</label>
        <select
          id="r_target_procedure"
          name="r_target_procedure"
          class="w-full u-mb"
          v-model="selectedProcedureId">
          <option value="">
            -
          </option>
          <option
            v-for="procedure in availableProcedures"
            :key="procedure.id"
            :value="procedure.id">
            {{ procedure.name }}
          </option>
        </select>
        <div
          v-if="hasPermission('feature_statement_content_changes_view') || hasPermission('feature_statement_content_changes_save')"
          class="u-mb">
          <input
            type="checkbox"
            id="deleteVersionHistory"
            v-model="deleteVersionHistory"
            aria-describedby="deleteHistoryDesc">
          <label
            for="deleteVersionHistory"
            class="inline-block u-mb-0">
            {{ Translator.trans('delete.history') }}
          </label>
          <p
            class="lbl__hint"
            id="deleteHistoryDesc">
            {{ Translator.trans('delete.history.description') }}
          </p>
        </div>
        <!-- The button disabled-attribute is set to true when the user is not the assignee of all fragments or if any fragments are assigned to departments -->
        <button
          type="button"
          class="btn btn--primary float-right"
          @click.prevent.stop="moveStatement"
          :disabled="!userIsAssigneeOfAllFragments || !fragmentsAreNotAssignedToDepartments">
          {{ Translator.trans('statement.moveto.procedure.action') }}
        </button>
      </template>
    </div>
  </dp-modal>
</template>

<script>
import { DpLoading, DpModal, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapState } from 'vuex'

export default {
  name: 'DpMoveStatementModal',

  components: {
    DpModal,
    DpLoading
  },

  props: {
    procedureId: {
      required: true,
      type: String
    },

    accessibleProcedures: {
      required: false,
      type: Object,
      default: () => ({})
    },

    inaccessibleProcedures: {
      required: false,
      type: Object,
      default: () => ({})
    }
  },

  emits: [
    'statement:moveToProcedure'
  ],

  data () {
    return {
      isLoading: true,
      procedurePermissions: 'accessibleProcedures',
      selectedProcedureId: '',
      statementId: null,
      statementFragments: [],
      deleteVersionHistory: false
    }
  },

  computed: {
    ...mapGetters('Fragment', ['fragmentsByStatement']),
    ...mapState('AssessmentTable', ['currentUserId']),
    ...mapState('Statement', ['statements']),

    statement () {
      return this.statementId ? this.statements[this.statementId] : null
    },

    userIsAssigneeOfAllFragments () {
      return this.statementFragments.filter(fragment => this.currentUserId === fragment.assigneeId).length === this.statementFragments.length
    },

    fragmentsAreNotAssignedToDepartments () {
      /*
       * DepartmentId is set when a fragment is assigned to a department. If it is assigned to a department, the user can't move the statement despite being the assignee of the fragment.
       ** The check prevents failure of moveStatement due to fragments being assigned to departments.
       ** departmentId is either set to null or to '' (empty string) when the fragment is not assigned to any departments.
       */
      return this.statementFragments.filter(fragment => (fragment.departmentId === null || fragment.departmentId === '')).length === this.statementFragments.length
    },

    availableProcedures () {
      //  Always pick the list that is specified by radio inputs
      return this[this.procedurePermissions]
    },

    selectedProcedureName () {
      //  Get the object corresponding with the current selection
      return this.selectedProcedureId ? this.availableProcedures[this.selectedProcedureId].name : ''
    }
  },

  watch: {
    procedurePermissions () {
      //  Reset selection when radio list changes
      this.selectedProcedureId = ''
    }
  },

  methods: {
    ...mapActions('Fragment', ['loadFragments']),
    toggleModal (statementId) {
      //  Reset selection when radio list changes
      this.selectedProcedureId = ''
      //  Set actual statement id
      this.statementId = statementId
      //  Actually toggle the modal
      this.$refs.moveStatementModal.toggle()

      // Get statement fragments to check if user can move this statement
      if (statementId) {
        this.setFragments(statementId).then(() => { this.isLoading = false })
      } else {
        this.resetFragments()
      }
    },

    setFragments (statementId) {
      const setFragmentsInComponent = () => {
        const fragments = this.fragmentsByStatement(statementId).fragments
        this.statementFragments = fragments.map(fragment => {
          return { id: fragment.id, assigneeId: fragment.assignee.id, departmentId: fragment.departmentId }
        })
      }

      // If fragments are already loaded don't load them again
      if (this.statement.fragmentsTotal === this.fragmentsByStatement(statementId).fragments.length) {
        setFragmentsInComponent()
        return Promise.resolve(true)
      } else {
        return this.loadFragments({ procedureId: this.procedureId, statementId: statementId })
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
        deleteVersionHistory: this.deleteVersionHistory
      })
        .then(response => {
        // If the user is not authorized to move the statement, the movedStatementId in the response is an empty string
          if (hasOwnProp(response, 'data') && response.data.movedStatementId !== '') {
            const moveToProcedureParams = {
              movedToProcedureId: response.data.movedToProcedureId,
              statementId: this.statementId,
              movedStatementId: response.data.movedStatementId,
              placeholderStatementId: response.data.placeholderStatementId,
              movedToAccessibleProcedure: this.movedToAccessibleProcedure(response.data.movedToProcedureId),
              movedToProcedureName: this.movedToAccessibleProcedure(response.data.movedToProcedureId) ? Object.values(this.accessibleProcedures).find(entry => entry.id === response.data.movedToProcedureId).name : Object.values(this.inaccessibleProcedures).find(entry => entry.id === response.data.movedToProcedureId).name
            }

            // Handle update of assessment table ui from TableCard.vue
            this.$root.$emit('statement:moveToProcedure', moveToProcedureParams)
          }
          this.toggleModal(null)
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.results.loading'))
          this.toggleModal(null)
        })
    }
  },

  mounted () {
    //  Emitted from TableCard.vue
    this.$root.$on('moveStatement:toggle', (statementId) => this.toggleModal(statementId))
  }
}
</script>
