<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="consolidateModal"
    content-classes="u-1-of-2"
    @modal:toggled="handleToggle">
    <!-- header -->
    <template v-slot:header>
      {{ Translator.trans('statement.consolidate') }}
    </template>

      <!-- content -->
      <fieldset
        class="u-pb-0 u-mt"
        id="consolidationMethod">
        <legend
          class="sr-only"
          v-text="Translator.trans('action.choose')" />
        <input
          type="radio"
          id="consolidateStatements"
          name="consolidationMethod"
          value="consolidateStatements"
          v-model="consolidationMethod"
          checked="checked">
        <label
          class="u-mb-0 inline-block"
          for="consolidateStatements">
          {{ Translator.trans('statement.cluster.create') }}
        </label>
        <input
          type="radio"
          class="u-ml inline-block"
          id="mergeIntoCluster"
          name="consolidationMethod"
          value="mergeIntoCluster"
          v-model="consolidationMethod">
        <label
          class="u-mb-0 inline-block"
          for="mergeIntoCluster">
          {{ Translator.trans('cluster.add.statements') }}
        </label>
        <!-- display if the user has selected group statements and NO other statements -->
        <div
          class="flash flash-warning flow-root"
          v-if="(selectedStatementsWithoutGroups.length === 0) && ('consolidateStatements' === consolidationMethod)">
          <i
            class="fa fa-exclamation-triangle u-mt-0_125 float-left"
            aria-hidden="true" />
          <div class="u-ml">
            <p
              class="u-mb-0"
              :inner-html.prop="Translator.trans('warning.consolidate.only.clusters.selected')" />
          </div>
        </div>
        <!-- display if the user has selected group statements -->
        <div
          class="flash flash-warning flow-root"
          v-else-if="selectionHasGroups">
          <i
            class="fa fa-exclamation-triangle u-mt-0_125 float-left"
            aria-hidden="true" />
          <div class="u-ml">
            <p
              class="u-mb-0"
              :inner-html.prop="Translator.trans('warning.statement.cluster.resolve')" />
          </div>
        </div>
      </fieldset>
      <label
        class="u-mb-0_25 u-mt"
        for="statementSelection">
        {{ Translator.trans('statements.selected.no.count') }}
      </label>
      <dp-multiselect
        id="statementSelection"
        v-model="selectedStatements"
        :allow-empty="false"
        :class="validations.selection ? 'u-mb' : 'u-mb-0_25'"
        :custom-label="option =>`${option.extid}`"
        :max-height="150"
        multiple
        :options="initialStatementSelection"
        track-by="id"
        @input="checkSelectionValidity">
        <template v-slot:option="{ props }">
          {{ props.option.extid }}
        </template>
        <template v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ props.option.extid }}
            <i
              aria-hidden="true"
              class="multiselect__tag-icon"
              tabindex="1"
              @click="props.remove(props.option)" />
          </span>
        </template>
      </dp-multiselect>
      <div
        class="u-mb inline-block"
        v-if="false === validations.selection && 'consolidateStatements' === consolidationMethod">
        <i
          class="fa fa-exclamation-circle color-message-severe-fill"
          aria-hidden="true" />
        <span class="u-ml-0_25 color-message-severe-text">
          {{ Translator.trans('confirm.consolidation.not.enough.statements') }}
        </span>
      </div>

    <fieldset v-if="consolidationMethod === 'consolidateStatements'">
      <legend
        class="hide-visually"
        v-text="Translator.trans('cluster.data')" />
      <label
        for="groupName"
        class="u-mt u-mb-0_25">
        {{ Translator.trans('statement.cluster.name') }}
      </label>
      <input
        type="text"
        id="groupName"
        name="groupName"
        class="w-full"
        style="height: 28px"
        v-model="groupName">

      <label class="u-mt u-mb-0_25">{{ Translator.trans('statement.main') }}*</label>
      <p class="color--grey">
        {{ Translator.trans('statement.cluster.create.help') }}
      </p>
      <dp-multiselect
        id="clusters-single-select"
        v-model="headStatement"
        :class="{ 'u-mb': validations.headStatement, 'u-mb-0_25': false === validations.headStatement }"
        :custom-label="option => option.extid"
        :options="selectedStatementsWithoutGroups"
        ref="multiselect"
        track-by="id"
        @input="checkHeadStatementValidity">
        <template v-slot:option="{ props }">
          {{ props.option.extid }}
        </template>
        <template
          v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ props.option.extid }}
            <i
              aria-hidden="true"
              class="multiselect__tag-icon"
              tabindex="1"
              @click="props.remove(props.option)" />
          </span>
        </template>
      </dp-multiselect>
      <div
        class="inline-block"
        v-if="false === validations.headStatement && 'consolidateStatements' === consolidationMethod">
        <i
          class="fa fa-exclamation-circle color-message-severe-fill"
          aria-hidden="true" />
        <span class="u-ml-0_25 color-message-severe-text">
          {{ Translator.trans('field.required') }}
        </span>
      </div>
    </fieldset>
    <fieldset v-if="consolidationMethod === 'mergeIntoCluster'">
      <legend
        class="hide-visually"
        v-text="Translator.trans('cluster.choose')" />
      <label class="u-mt u-mb-0_25 inline-block">{{ Translator.trans('consolidate.add.to.cluster') }}</label>
      <div
        class="u-ml inline-block"
        v-if="false === validations.cluster && 'mergeIntoCluster' === consolidationMethod">
        <i
          class="fa fa-exclamation-circle color-message-severe-fill"
          aria-hidden="true" />
        <span class="u-ml-0_25 color-message-severe-text">
          {{ Translator.trans('field.required') }}
        </span>
      </div>
      <dp-select-statement-cluster
        :class="{ 'u-mb': validations.cluster, 'u-mb-0_25': false === validations.cluster }"
        :init-cluster-list="clusterList"
        :current-user-id="currentUserId"
        :procedure-id="procedureId"
        @selected-cluster="setClusterSelection"
        ref="clusterSelect" />
    </fieldset>
    <dp-button
      class="sm:float-right"
      :busy="isLoading"
      :text="Translator.trans('send')"
      @click.prevent="submitCluster" />
  </dp-modal>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpButton,
  DpModal,
  DpMultiselect,
  handleResponseMessages,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpSelectStatementCluster from '@DpJs/components/statement/statement/SelectStatementCluster'

const emptyAssignee = {
  id: '',
  name: '',
  organisation: ''
}

export default {
  name: 'ConsolidateModal',

  components: {
    DpButton,
    DpModal,
    DpMultiselect,
    DpSelectStatementCluster
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      allClustersInProcedure: [],
      consolidationMethod: 'consolidateStatements',
      groupName: '',
      headStatement: {},
      initialStatementSelection: [],
      isLoading: false,
      selectedCluster: [],
      validations: {
        headStatement: true,
        selection: true,
        cluster: true
      }
    }
  },

  computed: {
    ...mapState('AssessmentTable', [
      'currentUserId'
    ]),

    ...mapState('Statement', [
      'selectedElements',
      'statements'
    ]),

    selectedStatements: {
      get () {
        return Object.keys(this.selectedElements).map(id => this.selectedElements[id])
      },

      set (value) {
        const newSelection = value
          .map(statement => {
            return {
              [statement.id]: {
                isCluster: statement.isCluster,
                extid: statement.extid,
                // Shows if element is on page. Will break if store contains more statements than on page
                hiddenElement: Object.keys(this.statements).indexOf(statement.id) === -1,
                id: statement.id,
                movedToProcedure: statement.movedToProcedureId !== ''
              }
            }
          })
          .reduce((element, accumulator) => {
            return { ...accumulator, ...element }
          }, {})

        this.replaceElementSelection(newSelection)

        if (Object.keys(newSelection).includes(this.selectedCluster.id)) {
          this.$refs.clusterSelect.selected = this.$refs.clusterSelect.emptyCluster
          this.setClusterSelection(this.$refs.clusterSelect.emptyCluster)
        }
      }
    },
    initClusterList () {
      return Object.values(this.allClustersInProcedure).map(stn => ({ id: stn.id, assignee: stn.attributes.assignee ? stn.attributes.assignee : emptyAssignee, externId: stn.attributes.externId, name: stn.attributes.name }))
    },

    clusterList () {
      return this.initClusterList.filter(cluster => !this.selectedStatements.map(el => el.id).includes(cluster.id))
    },

    selectedStatementsAsRelationship () {
      return this.selectedStatements.map(statement => {
        return { id: statement.id, type: 'statement' }
      })
    },

    selectedStatementsWithoutGroups () {
      return this.selectedStatements.filter(statement => !statement.isCluster)
    },

    selectionHasGroups () {
      return this.selectedStatements.filter(statement => statement.isCluster).length !== 0
    }
  },

  methods: {
    ...mapActions('Statement', [
      'createClusterAction',
      'updateClusterAction'
    ]),

    ...mapMutations('AssessmentTable', [
      'setModalProperty'
    ]),

    ...mapMutations('Statement', [
      'addElementToSelection',
      'removeElementFromSelection',
      'replaceElementSelection'
    ]),

    buildJsonApiCluster (method) {
      let id; let type; let headStatementId; let clusterName = {}

      if (method === 'post') {
        type = { type: 'statement' }
        headStatementId = { headStatementId: this.headStatement.id }

        // Include clusterName if user has specified a name for the new cluster
        clusterName = this.groupName === '' ? {} : { clusterName: this.groupName }
      }

      if (method === 'patch') {
        id = { id: this.selectedCluster.id }
        type = { type: 'headstatement' }
      }

      return {
        ...id,
        ...type,
        attributes: {
          ...clusterName,
          ...headStatementId
        },
        relationships: {
          statements: {
            data: [...this.selectedStatementsAsRelationship]
          }
        }
      }
    },

    checkClusterValidity () {
      return this.checkConditionalValidity('mergeIntoCluster', () => undefined !== this.selectedCluster.id && this.selectedCluster.id !== '', 'cluster')
    },

    checkClusterAssignmentValidity () {
      return this.checkConditionalValidity('mergeIntoCluster', () => (hasOwnProp(this.selectedCluster, 'assignee') && this.selectedCluster.assignee.id === this.currentUserId), 'cluster')
    },

    checkConditionalValidity (consolidationMethodCondition, assertion, validationKey) {
      if (consolidationMethodCondition === this.consolidationMethod) {
        this.validations[validationKey] = assertion()
      } else {
        this.validations[validationKey] = true
      }
      return this.validations[validationKey]
    },

    checkHeadStatementValidity () {
      return this.checkConditionalValidity('consolidateStatements', () => undefined !== this.headStatement.id, 'headStatement')
    },

    checkSelectionValidity () {
      return this.checkConditionalValidity('consolidateStatements', () => this.selectedStatements.length >= 2, 'selection')
    },

    // Get clusters in procedure to have the current assignee state in selectStatementCluster
    fetchClusters () {
      const url = Routing.generate('api_resource_list', { resourceType: 'Cluster' })
      const params = {
        include: 'Claim',
        fields: {
          Cluster: [
            'assignee'
          ].join(),
          Claim: [
            'name',
            'orgaName'
          ].join()
        }
      }
      return dpApi.get(url, params)
        .then(checkResponse)
        .then(response => response.data)
    },

    async handleOpenModal () {
      this.allClustersInProcedure = await this.fetchClusters()
      this.setInitialStatementSelection()

      this.toggleModal()
    },

    handleToggle (isOpen) {
      this.resetModal()

      if (!isOpen) {
        this.setModalProperty({ prop: 'consolidateModal', val: { show: false } })
      }
    },

    isValidForm () {
      const validationChecks = [this.checkSelectionValidity(), this.checkHeadStatementValidity(), this.checkClusterValidity()]
      if (hasPermission('feature_statement_assignment')) {
        validationChecks.push(this.checkClusterAssignmentValidity())
      }
      return validationChecks
        .filter(test => !test).length === 0
    },

    resetModal () {
      this.consolidationMethod = 'consolidateStatements'
      this.headStatement = {}
      this.selectedCluster = {}
      this.groupName = ''
      Object.keys(this.validations).forEach(key => this.validations[key] = true)
    },

    // Set initialStatementSelection to show options in multiselect
    setInitialStatementSelection () {
      this.initialStatementSelection = this.selectedStatements
    },

    toggleModal () {
      this.$refs.consolidateModal.toggle()
    },

    setClusterSelection (cluster) {
      this.selectedCluster = cluster
      this.checkClusterValidity()
    },

    submitCluster () {
      if (this.isValidForm() === false) {
        return false
      }
      // Submit data depending on consolidationMethod
      this.isLoading = true;
      (this.consolidationMethod === 'consolidateStatements'
        ? this.createClusterAction(this.buildJsonApiCluster('post'))
        : this.updateClusterAction(this.buildJsonApiCluster('patch')))
        .then((response) => {
          this.isLoading = false
          this.resetModal()
          this.$refs.consolidateModal.toggle()

          if (hasOwnProp(response, 'data') && hasOwnProp(response.data, 'id') && response.data.id !== '') {
            location.hash = '#itemdisplay_' + response.data.id
            sessionStorage.setItem('saveAndReturn', true)
            sessionStorage.setItem('messagesToRender', JSON.stringify(response.meta))
            location.reload()
          }
        })
        .catch(e => handleResponseMessages(e.response.data.meta))
    }
  },

  mounted () {
    this.$nextTick(() => {
      this.handleOpenModal()
    })
  }

}
</script>
