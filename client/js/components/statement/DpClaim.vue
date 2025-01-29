<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
claiming component that shows the claim status of the entity (statement, fragment, statement cluster, segment). The logic of
assignment update is in parent, and the component holds only the state of assignee. The only logic in this component is
to show the correct claim icon, based on the assignee info from the props. On click the component emits event 'click'
without any arguments, and then parent has to handle it properly.

The props passed to component are:
 - required: assignedOrganisation, assignedName, assignedId
 - optional: isLoading (to show loader), currentUserId (default ''), currentUserName (default ''),
   lastClaimedUserId (default ''), entityType (default 'statement').

The component is used in the assessment table where it indicates the claim state of statements, clusters and fragments
(if enabled) and in fragmentList for Fachbehörde.

The lastClaimedUser is the property used for fragments, indicating to whom the fragment should be reassigned after
the FB is ready with editing of fragments.
  -->
  <usage>
    <dp-claim
      assigned-organisation="Organisation Name from the currently assigned User"
      assigned-name="Name from the currently assigned User"
      assigned-id="Id from the currently assigned User"
      current-user-id="Id from the currently logged in User"
      current-user-name="Name from the currently logged in User"
      last-claimed-user-id="Id from the lastClaimed user"
      entity-type="fragement or statement"
      :is-loading="true|false (optional)"
    />
  </usage>
</documentation>

<template>
  <!--data-assigned is needed for batch edit-->
  <button
    class="flex items-center space-inline-xs btn--blank o-link--default"
    :class="{'cursor-pointer' : false === isLoading}"
    :data-assigned="isAssignedToMe /* needed for checking checked elements*/"
    @click.prevent.stop="updateAssignment"
    data-cy="claimIcon"
    :aria-label="status.text"
    v-tooltip="isLoading ? null : status.text">
    <dp-loading
      v-if="isLoading"
      hide-label />
    <i
      v-else
      class="fa"
      :class="status.icon"
      aria-hidden="true" />
    <span
      v-if="label"
      v-text="label" />
  </button>
</template>

<script>
import { DpLoading } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpClaim',
  components: {
    DpLoading
  },

  props: {
    assignedOrganisation: {
      type: String,
      required: true
    },

    assignedName: {
      type: String,
      required: true
    },

    assignedId: {
      type: String,
      required: true
    },

    currentUserId: {
      type: String,
      required: false,
      default: ''
    },

    currentUserName: {
      type: String,
      required: false,
      default: ''
    },

    entityType: {
      type: String,
      required: false,
      default: 'statement'
    },

    isLoading: {
      type: Boolean,
      required: false,
      default: false
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    lastClaimedUserId: {
      type: String,
      required: false,
      default: ''
    }
  },

  computed: {
    isAssigned () {
      return typeof this.assignedId === 'string' && this.assignedId.length > 0
    },

    isAssignedToMe () {
      return this.assignedId === this.currentUserId
    },

    isLastClaimedByMe () {
      return this.lastClaimedUserId === this.currentUserId
    },

    // Helper to generate the translation keys
    assignedTextBaseKey () {
      let baseKey = 'statement.'

      if (this.entityType === 'fragment') {
        baseKey += 'fragment.'
      }
      return baseKey
    },

    /**
     * @trans statement.assignment.assigned
     * @trans statement.assignment.assigned.self
     * @trans statement.assignment.unassigned
     * @trans statement.fragment.assignment.assigned
     * @trans statement.fragment.assignment.assigned.self
     * @trans statement.fragment.assignment.assigned.self.delegated.locked
     * @trans statement.fragment.assignment.assigned.self.delegated.unlocked
     * @trans statement.fragment.assignment.unassigned
     * @returns {Object} | {status: String, icon: String}
     */
    status () {
      let status // = 'assigned';
      let icon

      if (this.isLastClaimedByMe === false || (this.isLastClaimedByMe && this.isAssignedToMe)) {
        // Not assigned and not last claimed by me
        if (this.isAssigned === false) {
          icon = 'fa-unlock'
          status = 'unassigned'
        }

        // Assigned to me
        if (this.isAssignedToMe) {
          icon = 'fa-user'
          status = 'assigned.self'
        }

        // Assigned to someone else and not last claimed by me
        if (this.isAssigned && this.isAssignedToMe === false) {
          icon = 'fa-lock'
          status = 'assigned'
        }
      } else {
        // If (this.isLastClaimedByMe && false === this.isAssignedToMe)
        icon = 'fa-user-o'
        status = 'assigned.self.delegated'

        // Last claimed by me and assigned to someone else
        if (this.isAssigned) {
          status += '.locked'
        } else { // Last claimed by me and unassigned
          status += '.unlocked'
        }
      }

      status = Translator.trans(
        this.assignedTextBaseKey + 'assignment.' + status,
        {
          name: this.assignedName,
          organisation: this.assignedOrganisation,
          delegator: this.currentUserName
        }
      )
      return { text: status, icon }
    }
  },

  methods: {
    // Triggers click-event to the outer component
    updateAssignment () {
      if (this.isLoading) {
        return
      }
      // If the user want to 'steal' the entity from another user, ask if it's by purpose
      if (this.assignedId !== this.currentUserId && this.assignedId !== '') {
        let transkey
        switch (this.entityType) {
          case 'Statement':
            transkey = 'warning.statement.needLock.generic'
            break
          case 'Fragment':
            transkey = 'warning.fragment.needLock.generic'
            break
          case 'Segment':
            transkey = 'warning.segment.needLock.generic'
            break
        }
        if (window.dpconfirm(Translator.trans(transkey))) {
          this.$emit('click')
        }
      } else {
        this.$emit('click')
      }
    }
  }
}
</script>
