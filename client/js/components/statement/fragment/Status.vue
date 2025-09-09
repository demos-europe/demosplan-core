<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- If component is used with :tooltip="false" -->
    <div
      class="cursor-help"
      :class="{'c-at-item__badge': badge}"
      :title="voteString"
      v-if="!tooltip">
      <slot name="title" />

      <i
        class="fa color--grey"
        :class="{
          'fa-hourglass-half': hasPermission('feature_statements_fragment_add_reviewer') && voteAdvicePending,
          'fa-question': status === '',
          'fa-check': status !== ''
        }"
        aria-hidden="true" />
    </div>

    <!-- If component is used without specifying `tooltip` prop (which defaults to true)... -->
    <v-popover
      v-else
      placement="top"
      trigger="hover focus">
      <!-- Target/Trigger (for the events and position) -->
      <div>
        <slot name="title" />
        <i
          class="fa color--grey"
          :class="{
            'fa-hourglass-half': hasPermission('feature_statements_fragment_add_reviewer') && voteAdvicePending,
            'fa-question': status === '',
            'fa-check': status !== ''
          }"
          aria-hidden="true" />
      </div>

      <!-- Content -->
      <template v-slot:popover>
        <div>
          <!-- fragment is assigned to reviewer, planners see this -->
          <div
            v-if="hasPermission('feature_statements_fragment_add_reviewer') && voteAdvicePending"
            v-cleanhtml="voteAdvicePending" />
          <!-- no process until now -->
          <div
            v-else-if="status === ''"
            v-cleanhtml="Translator.trans(transNone)" />
          <!-- show state -->
          <div
            v-else
            v-cleanhtml="voteSentence" />
        </div>
      </template>
    </v-popover>
  </div>
</template>

<script>
import { CleanHtml, VPopover } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpFragmentStatus',

  components: {
    VPopover
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    status: {
      required: false,
      type: String,
      default: ''
    },

    fragmentId: {
      required: false,
      type: String,
      default: ''
    },

    //  If a reviewer reassigned a fragment, the reviewers orga/department is displayed with the given voteAdvice
    archivedOrgaName: {
      required: false,
      type: String,
      default: ''
    },

    archivedDepartmentName: {
      required: false,
      type: String,
      default: ''
    },

    /*
     *  If the fragment is assigned to a reviewer, planners do not see the voteAdvice (if set by reviewer)
     *  until the reviewer reassigns the fragment to planner
     */
    voteAdvicePending: {
      required: false,
      type: String,
      default: ''
    },

    //  Layout property: display status inside a badge
    badge: {
      required: false,
      type: Boolean,
      default: false
    },

    //  Layout property: display a tooltip with further information about status
    tooltip: {
      required: false,
      type: Boolean,
      default: true
    },

    //  Translation keys
    transNone: {
      required: false,
      type: String,
      default: 'fragment.voteAdvice.status.none'
    },

    transDone: {
      required: false,
      type: String,
      default: 'fragment.voteAdvice.status.done'
    }
  },

  computed: {
    archivedReviewerFullName () {
      if (this.archivedOrgaName && this.archivedDepartmentName) {
        return this.archivedOrgaName + ' (' + this.archivedDepartmentName + ')'
      } else {
        return ''
      }
    },

    adviceValues () {
      return this.$store.getters['AssessmentTable/adviceValues']
    },

    voteString () {
      const currentStatus = this.status !== '' ? this.adviceValues.find(val => val.id === this.status).title : 'fragment.vote.none'
      return Translator.trans(currentStatus)
    },

    voteSentence () {
      const transParams = {
        reviewer: this.archivedReviewerFullName,
        vote: this.voteString
      }
      return Translator.trans(this.transDone, transParams)
    }
  }
}
</script>
