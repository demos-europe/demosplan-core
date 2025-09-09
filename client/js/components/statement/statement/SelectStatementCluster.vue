<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="">
    <!-- tweaking the Validator to check if assignee matches the current User -->
    <!-- validate only if claiming is allowed -->

    <!-- hidden input with userId-->
    <input
      type="hidden"
      :value="currentUserId"
      id="currentUser"
      name="currentUser"
      v-if="hasPermission('feature_statement_assignment')">

    <!-- hidden input with assigneeId of selected statement cluster-->
    <input
      v-if="hasPermission('feature_statement_assignment')"
      type="hidden"
      :value="selectedCluster.assignee.id"
      name="claimedClusterAssignee"
      :data-dp-validate-should-equal="selected.name !== '-' && selected.id ? '#currentUser' : false">

    <!-- select statement cluster -->
    <dp-multiselect
      id="clusters-single-select"
      v-model="selected"
      :allow-empty="false"
      class="u-1-of-1 u-mr-0_75 show-error-from-sibling"
      data-cy="clustersSingleSelect"
      :custom-label="option =>`${option.externId ? option.externId : ''} ${option.name ? option.name : ''}`"
      :options="clusterList"
      ref="multiselect"
      track-by="id"
      @input="closeMultiselect">
      <template v-slot:option="{ props }">
        <strong>{{ props.option.externId ? props.option.externId : '' }}</strong>
        <span class="weight--normal">{{ props.option.name ? ` ${props.option.name}` : '' }}</span>
      </template>
    </dp-multiselect>
    <input
      type="hidden"
      name="r_head_statement"
      id="r_head_statement"
      :value="inputValue">

    <!-- claim icon for selected statement cluster -->
    <div
      v-if="hasPermission('feature_statement_assignment')"
      class="layout__item u-1-of-1 u-pt-0_25 u-pl-0">
      <dp-claim
        class="c-at-item__row-icon inline-block"
        entity-type="statement"
        :ignore-last-claimed="false"
        :assigned-id="(selected.assignee.id || '')"
        :assigned-name="(selected.assignee.name || '')"
        :assigned-organisation="(selected.assignee.organisation || '')"
        :current-user-id="currentUserId"
        :current-user-name="currentUserName"
        :is-loading="updatingClaimState"
        v-if="'' !== inputValue"
        @click="updateClaim"
        :key="selected.assignee.id" />
      <p
        v-if="currentUserId !== selected.assignee.id && inputValue !== ''"
        class="inline-block lbl__hint u-n-ml-0_5">
        {{ Translator.trans('statement.cluster.assign.self') }}
      </p>
    </div>
  </div>
</template>

<script>
import { DpMultiselect, hasOwnProp } from '@demos-europe/demosplan-ui'
import DpClaim from '../DpClaim'
import { mapActions } from 'vuex'

export default {
  name: 'DpSelectStatementCluster',

  components: {
    DpClaim,
    DpMultiselect
  },

  props: {
    initClusterList: {
      required: true,
      type: Array,
      default: () => []
    },

    // String with cluster id
    initSelectedCluster: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
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
    },

    currentUserOrganisation: {
      required: false,
      type: String,
      default: ''
    },

    ignoreLastClaimed: {
      required: false,
      type: Boolean,
      default: true
    },

    emptyCluster: {
      required: false,
      type: Object,
      default: () => {
        return {
          id: '',
          name: '-',
          externId: '',
          assignee: {
            id: '',
            name: '',
            organisation: ''
          }
        }
      }
    }
  },

  emits: [
    'selected-cluster'
  ],

  data () {
    return {
      selected: this.initSelectedCluster !== '' ? this.initClusterList.find(cluster => cluster.id === this.initSelectedCluster) : this.emptyCluster,
      updatingClaimState: false
    }
  },

  computed: {
    clusterList () {
      return this.sortOptions(this.initClusterList)
    },

    inputValue () {
      return this.selectedCluster.id
    },

    selectedCluster () {
      return this.clusterList.find(cluster => this.selected.id === cluster.id) || this.emptyCluster
    }
  },

  methods: {
    ...mapActions('Statement', ['setAssigneeAction']),

    /**
     * Force-close multiselect dropdown on selection - because of the bug the menu stays opened
     */
    closeMultiselect () {
      this.$refs.multiselect.isOpen = false

      // If the selected group is claimed, the error class of multiselect should be removed
      if (this.selected.assignee.id === this.currentUserId) {
        const claimedClusterAssigneeInput = document.querySelector('input[name="claimedClusterAssignee"]')
        if (claimedClusterAssigneeInput && claimedClusterAssigneeInput.classList.contains('is-required-error')) {
          claimedClusterAssigneeInput.classList.remove('is-required-error')
        }
      }

      // Emit current selection so that parent component can obtain the selected cluster
      this.$emit('selected-cluster', this.selectedCluster)
    },

    sortOptions (clusterList) {
      clusterList.sort((a, b) => {
        const aHasName = hasOwnProp(a, 'name') && a.name !== ''
        const bHasName = hasOwnProp(b, 'name') && b.name !== ''

        if (aHasName && bHasName === false) {
          return -1
        }

        if (aHasName === false && bHasName) {
          return 1
        }

        if (aHasName && bHasName) {
          return a.name.localeCompare(b.name, 'de', { sensitivity: 'base' })
        }

        if ((aHasName === false && bHasName === false) || a.name === b.name) {
          // Transform externIds to numbers
          const aId = parseFloat(a.externId.replace(/\D/g, ''))
          const bId = parseFloat(b.externId.replace(/\D/g, ''))
          return aId > bId ? 1 : (aId < bId ? -1 : 0)
        }
        return 0
      })

      const emptyOptionIndex = clusterList.findIndex(opt => opt.id === '')
      if (emptyOptionIndex > -1) {
        clusterList.splice(emptyOptionIndex, 1)
      }
      clusterList.unshift(this.emptyCluster)
      return clusterList
    },

    /**
     * Update Claim triggers store-actions to set/unset the current user as assignee
     */
    updateClaim () {
      this.updatingClaimState = true
      this.setAssigneeAction({
        statementId: this.inputValue,
        assigneeId: this.selected.assignee.id === this.currentUserId ? '' : this.currentUserId
      })
        .then(response => {
          this.updateClaimData(response.assignee)

          if (response.assignee.id === this.currentUserId) {
            // If the selected group is claimed, the error class of multiselect should be removed
            const claimedClusterAssigneeInput = document.querySelector('input[name="claimedClusterAssignee"]')
            if (claimedClusterAssigneeInput && claimedClusterAssigneeInput.classList.contains('is-required-error')) {
              claimedClusterAssigneeInput.classList.remove('is-required-error')
            }
          }
        })
    },

    updateClaimData (data) {
      this.updatingClaimState = false
      data.organisation = data.orgaName
      delete data.orgaName
      this.selected.assignee = data
    }
  }
}
</script>
