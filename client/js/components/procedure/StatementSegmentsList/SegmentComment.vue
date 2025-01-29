<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="u-mb-0_25 weight--bold">
      {{ submitter }}
    </p>
    <div class="u-mb-0_5 color--grey font-size-small">
      <span>
        {{ createdDateTimeItem(comment.attributes.creationDate) }}
      </span>
      <span class="u-ml-0_25 o-badge--small o-badge--light rounded-full">
        {{ place }}
      </span>
    </div>
    <p v-cleanhtml="comment.attributes.text" />
  </div>
</template>

<script>
import { CleanHtml, formatDate } from '@demos-europe/demosplan-ui'
import { mapState } from 'vuex'

export default {
  name: 'SegmentComment',

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    comment: {
      type: Object,
      required: true
    },

    currentUser: {
      type: Object,
      required: true
    },

    segmentId: {
      type: String,
      required: true
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segments: 'items'
    }),

    place () {
      if (this.comment && this.comment?.relationships?.place?.data && Object.keys(this.comment.relationships.place.data).length) {
        const place = this.comment.rel('place')
        if (place.attributes.name) {
          return place.attributes.name
        }
      }
      return Translator.trans('workflow.place.deleted')
    },

    segment () {
      return this.segments[this.segmentId]
    },

    submitter () {
      if (this.comment && this.comment?.relationships?.submitter?.data && Object.keys(this.comment.relationships.submitter.data).length) {
        const submitter = this.comment.rel('submitter')

        /*
         * This is a workaround in case a segmentated statement does not contain a first initial comment.
         * In this case the submitter const will be undefined and hence the initialization of this component will fail.
         * By returning the currentUser.firstname and currentUser.lastname props this error will be avoided.
         */
        if (!submitter) {
          return `${this.currentUser.firstname} ${this.currentUser.lastname}`
        }

        if (submitter.attributes.firstname && submitter.attributes.lastname) {
          return `${submitter.attributes.firstname} ${submitter.attributes.lastname}`
        }
      }
      return Translator.trans('user.deleted')
    }
  },

  methods: {
    createdDateItem (date) {
      return formatDate(date)
    },

    createdDateTimeItem (date) {
      return `${formatDate(date, 'long')}`
    }
  }
}
</script>
