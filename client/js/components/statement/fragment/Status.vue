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
      v-if="!tooltip"
      class="cursor-help"
      :class="{'c-at-item__badge': badge}"
      :title="voteString"
    >
      <slot name="title" />

      <i
        class="fa color--grey"
        :class="{
          'fa-hourglass-half': hasPermission('feature_statements_fragment_add_reviewer') && voteAdvicePending,
          'fa-question': status === '',
          'fa-check': status !== ''
        }"
        aria-hidden="true"
      />
    </div>

    <!-- If component is used without specifying `tooltip` prop (which defaults to true)... -->
    <div
      v-else
      v-tooltip="tooltipContent"
    >
      <slot name="title" />
      <i
        class="fa color--grey"
        :class="{
          'fa-hourglass-half': hasPermission('feature_statements_fragment_add_reviewer') && voteAdvicePending,
          'fa-question': status === '',
          'fa-check': status !== ''
        }"
        aria-hidden="true"
      />
    </div>
  </div>
</template>

<script>
import DOMPurify from 'dompurify'

export default {
  name: 'DpFragmentStatus',

  props: {
    status: {
      required: false,
      type: String,
      default: '',
    },

    fragmentId: {
      required: false,
      type: String,
      default: '',
    },

    //  If a reviewer reassigned a fragment, the reviewers orga/department is displayed with the given voteAdvice
    archivedOrgaName: {
      required: false,
      type: String,
      default: '',
    },

    archivedDepartmentName: {
      required: false,
      type: String,
      default: '',
    },

    /*
     *  If the fragment is assigned to a reviewer, planners do not see the voteAdvice (if set by reviewer)
     *  until the reviewer reassigns the fragment to planner
     */
    voteAdvicePending: {
      required: false,
      type: String,
      default: '',
    },

    //  Layout property: display status inside a badge
    badge: {
      required: false,
      type: Boolean,
      default: false,
    },

    //  Layout property: display a tooltip with further information about status
    tooltip: {
      required: false,
      type: Boolean,
      default: true,
    },

    //  Translation keys
    transNone: {
      required: false,
      type: String,
      default: 'fragment.voteAdvice.status.none',
    },

    transDone: {
      required: false,
      type: String,
      default: 'fragment.voteAdvice.status.done',
    },
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

    // The tooltip inserts its content as HTML, and the reviewer and orga names are user input
    tooltipContent () {
      if (hasPermission('feature_statements_fragment_add_reviewer') && this.voteAdvicePending) {
        return DOMPurify.sanitize(this.voteAdvicePending)
      }

      return DOMPurify.sanitize(this.status === '' ? Translator.trans(this.transNone) : this.voteSentence)
    },

    voteSentence () {
      const transParams = {
        reviewer: this.archivedReviewerFullName,
        vote: this.voteString,
      }

      return Translator.trans(this.transDone, transParams)
    },
  },
}
</script>
