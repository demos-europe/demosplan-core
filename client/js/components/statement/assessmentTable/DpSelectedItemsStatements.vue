<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- second template is needed because we have two root-elements-->
  <div class="inline-block">
    <!--if all chosen items are not in the procedure -->
    <button
      type="button"
      class="btn--blank o-link--default u-mr-0_5"
      data-cy="selectedItemsStatements:edit"
      disabled
      :title="Translator.trans('statement.moved.not.editable')"
      v-if="Object.values(this.selectedElements).every(elem => elem.movedToProcedure === true)">
      <i
        aria-hidden="true"
        class="fa fa-pencil u-mr-0_125" />
      {{ Translator.trans('edit') }}
    </button>
    <!--if all items are claimed and at least one statement in this procedure is chosen, go to group edit or if claiming is not enabled in project -->
    <a
      role="button"
      class="btn--blank u-mr-0_5"
      data-cy="selectedItemsStatements:edit"
      :href="Routing.generate('dplan_assessment_table_assessment_table_statement_bulk_edit_action', { procedureId: procedureId })"
      v-else-if="editable || false === hasPermission('feature_statement_assignment')">
      <i
        aria-hidden="true"
        class="fa fa-pencil u-mr-0_125" />
      {{ Translator.trans('edit') }}
    </a>
    <!--if at least one item is not claimed -->
    <button
      class="btn--blank o-link--default u-mr-0_5"
      type="button"
      data-cy="claimAll"
      @click="claimAll"
      v-else>
      <dp-loading
        v-if="loading"
        class="inline-block"
        hide-label />
      <i
        v-else
        aria-hidden="true"
        class="fa fa-user u-mr-0_125" />
      {{ Translator.trans('assign.to.me') }}
    </button>

    <button
      v-if="canConsolidate"
      type="button"
      class="btn--blank o-link--default u-mr-0_5"
      data-cy="selectedItemsStatements:consolidate"
      @click="openConsolidateModal">
      <i
        aria-hidden="true"
        class="fa fa-object-group u-mr-0_125" />
      {{ Translator.trans('consolidate') }}
    </button>

    <button
      type="button"
      class="btn--blank o-link--default u-mr-0_5"
      data-cy="selectedItemsStatements:copy"
      @click="copyElements">
      <i
        aria-hidden="true"
        class="fa fa-files-o u-mr-0_125" />
      {{ Translator.trans('copy') }}
    </button>

    <button
      type="button"
      class="btn--blank o-link--default u-mr-0_5"
      data-cy="selectedItemsStatements:delete"
      @click="triggerDeletion">
      <i
        aria-hidden="true"
        class="fa fa-trash u-mr-0_125" />
      {{ Translator.trans('delete') }}
    </button>

    <button
      type="button"
      class="btn--blank o-link--default u-mr-0_5"
      data-cy="selectedItemsStatements:export"
      @click.prevent="$root.$emit('exportModal:toggle', 'docx')">
      <i
        aria-hidden="true"
        class="fa fa-share-square u-mr-0_125" />
      {{ Translator.trans('export') }}
    </button>
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpLoading,
  dpRpc,
  handleResponseMessages
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { v4 as uuid } from 'uuid'

export default {
  name: 'DpSelectedItemsStatements',

  components: {
    DpLoading
  },

  props: {
    procedureId: {
      required: true,
      type: String
    },

    currentUserId: {
      required: false,
      type: String,
      default: ''
    },

    currentUserName: {
      required: false,
      type: String,
      default: ''
    }
  },

  emits: [
    'exportModal:toggle',
    'update-assessment-table',
    'update-pagination-assessment-table'
  ],

  data () {
    return {
      loading: false
    }
  },

  computed: {
    ...mapGetters('Statement', [
      'selectedElementsLength',
      'selectedElements'
    ]),

    ...mapGetters('Fragment', [
      'fragmentsByStatement'
    ]),

    ...mapState('Statement', [
      'filterHash',
      'statements'
    ]),

    ...mapState('Fragment', [
      'fragments'
    ]),

    editable () {
      if (Object.values(this.selectedElements).every(elem => elem.movedToProcedure === true)) {
        return false
      }

      return Object.values(this.selectedElements).filter(elem => (elem.movedToProcedure === false)).every(elem => elem.assignee.id === this.currentUserId)
    },

    canConsolidate () {
      return hasPermission('feature_statement_cluster')
    },

    selectionContainsMovedStatements () {
      return !Object.values(this.selectedElements).every(statement => statement.movedToProcedure === false)
    },

    selectionContainsUnclaimedFragments () {
      return Object.keys(this.selectedElements)
        .map(id => this.fragments[id] || [])
        .map(storeFragment => storeFragment.fragments || [])
        .reduce((acc, fragments) => {
          return [...acc, ...fragments]
        })
        .filter(fragment => {
          return (fragment.assignee.id !== this.currentUserId) || (fragment.departmentId && fragment.departmentId !== '')
        }).length > 0
    },

    selectionContainsUnclaimedStatements () {
      return !Object.values(this.selectedElements).every(statement => statement.assignee.id === this.currentUserId)
    }
  },

  watch: {
    selectedElements () {
      this.fetchRelatedFragments()
    }
  },

  methods: {
    ...mapActions('Fragment', [
      'loadFragments'
    ]),

    ...mapMutations('Statement', [
      'updateStatement',
      'resetSelection'
    ]),

    ...mapMutations('AssessmentTable', [
      'setModalProperty'
    ]),

    claimAll () {
      const unclaimedElements = Object.values(this.selectedElements).filter(elem => elem.assignee.id !== this.currentUserId).map(elem => ({ id: elem.id, type: 'statement' }))
      const payload = {
        data: {
          id: uuid(),
          type: 'statementBulkEdit',
          attributes: {
            markedStatementsCount: this.selectedElementsLength
          },
          relationships: {
            assignee: {
              data: { type: 'User', id: this.currentUserId }
            },
            statements: {
              data: unclaimedElements
            }
          }
        }
      }

      this.loading = true
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_assessment_table_assessment_table_statement_bulk_edit_api_action', {
          procedureId: this.procedureId,
          include: ['assignee', 'statements'].join()
        }),
        data: payload
      })
        .then(checkResponse)
        .then(response => {
          const assignee = response.included.find(elem => elem.id === response.data.relationships.assignee.data.id)
          const orgaName = response.included.find(elem => elem.type === 'Claim').attributes.orgaName

          // Commit mutation for each element
          response.data.relationships.statements.data.forEach(statement => this.$store.commit('Statement/updateStatement', {
            id: statement.id,
            assignee: {
              id: assignee.id,
              name: assignee.attributes.name,
              orgaName,
              uId: assignee.id
            },
            currentUserId: this.currentUserId
          }))
        })
        .catch(error => {
          console.log(error)
          handleResponseMessages(error.response.data.meta)
        })
        .then(() => { this.loading = false })
    },

    copyElements () {
      const params = {
        statementIds: []
      }

      const placeholderStatements = Object.values(this.selectedElements)
        .filter(el => el.movedToProcedure)
        .map(el => el.extid)

      params.statementIds = Object.values(this.selectedElements)
        .filter(el => !el.movedToProcedure)
        .map(el => el.id)

      if (params.statementIds.length > 0) {
        dpRpc('statements.bulk.copy', params)
          .then(checkResponse)
          .then((response) => {
            if (response[0].error) {
              dplan.notify.notify('error', Translator.trans('error.copy'))
              return
            }

            if (placeholderStatements.length > 0) {
              dplan.notify.notify('warning', Translator.trans('statement.copy.to.assessment.table.error', { count: placeholderStatements.length, extids: JSON.stringify(placeholderStatements) }))
            }

            this.$root.$emit('update-pagination-assessment-table')
            this.$root.$emit('update-assessment-table')
            dplan.notify.notify('confirm', Translator.trans('statement.copy.to.assessment.table.confirm', { count: params.statementIds.length }))
          })
          .catch(() => {
            dplan.notify.notify('error', Translator.trans('error.copy'))
          })
          .finally(() => {
            sessionStorage.clear()
            this.resetSelection()
          })
      } else {
        dplan.notify.warning('error', Translator.trans('statement.copy.to.assessment.table.no.selection'))
      }
    },

    deleteElements (event) {
      sessionStorage.setItem('selectedElements', '{}')
      window.submitForm(event, 'delete', this.filterHash)
    },

    fetchFragmentByStatement (statement) {
      let fragments = this.fragmentsByStatement(statement.id).fragments
      if (fragments.length < statement.fragmentsCount) {
        return this.loadFragments({ procedureId: this.procedureId, statementId: statement.id }).then(() => {
          fragments = this.fragmentsByStatement(statement.id).fragments
          return fragments
        })
      } else {
        const prom = new Promise((resolve, reject) => {
          resolve(fragments)
        })
        return prom.then(frag => {
          return frag
        })
      }
    },

    fetchRelatedFragments () {
      Object.keys(this.selectedElements)
        .filter(id => typeof this.statements[id] !== 'undefined') // If the statement is not on the same pagination page we dont have the informations here and dont need the fragments
        .map(id => (this.statements[id]))
        .forEach(statement => this.fetchFragmentByStatement(statement))
    },

    openConsolidateModal () {
      this.runChecks() && this.setModalProperty({ prop: 'consolidateModal', val: { show: true } })
    },

    runChecks () {
      let isAllowed = true

      if (this.selectionContainsMovedStatements && hasPermission('feature_statement_move_to_procedure')) {
        this.triggerWarning('warning.edit.selection.contains.moved.statements')
        isAllowed = false
      }

      if (hasPermission('area_statements_fragment') && this.selectionContainsUnclaimedFragments) {
        this.triggerWarning('warning.edit.selection.fragments.not.claimed')
        isAllowed = false
      }

      if (this.selectionContainsUnclaimedStatements && hasPermission('feature_statement_assignment')) {
        this.triggerWarning('warning.edit.selection.statements.not.claimed')
        isAllowed = false
      }

      return isAllowed
    },

    triggerDeletion () {
      this.runChecks() && this.deleteElements()
    },

    triggerWarning (msg) {
      dplan.notify.notify('warning', Translator.trans(msg))
    }
  },

  created () {
    this.fetchRelatedFragments()
  }
}
</script>
