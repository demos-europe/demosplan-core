<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    style="height: calc(100vh - 68px) /* The 68px is the height for the x-close spacing */"
    class="overflow-y-auto">
    <h2 class="u-mb-1_5">
      {{ heading }}
    </h2>
    <div
      class="space-stack-s"
      v-if="hasPermission('feature_segment_comment_create')">
      <div>
        <dp-button
          v-if="segment"
          class="w-full u-mt u-mb"
          data-cy="addCommentToSegment"
          @click="toggleForm"
          :text="Translator.trans('comment.add.to_segment', { segmentExternId: segment.attributes.externId })"
          variant="outline" />
      </div>
      <create-comment-form
        v-show="showForm"
        ref="createForm"
        :current-user="currentUser"
        :segment-id="commentsList.segmentId" />
    </div>
    <dp-loading
      v-if="isLoading"
      class="u-mt" />
    <template v-if="hasComments">
      <segment-comment
        :current-user="currentUser"
        v-for="(comment, idx) in comments"
        :key="idx"
        :comment="comment"
        :segment-id="commentsList.segmentId"
        class="u-mt u-mb-0_5"
        :class="{'border--bottom' : idx < (comments.length -1) }"
        data-cy="commentsListItem" />
    </template>
    <dp-inline-notification
      v-else
      type="info"
      class="u-mt-1_5 mb-4"
      :message="Translator.trans('explanation.noentries')" />
  </div>
</template>

<script>
import { DpButton, DpLoading } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import CreateCommentForm from './CreateCommentForm'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'SegmentCommentsList',

  components: {
    CreateCommentForm,
    DpButton,
    DpInlineNotification: defineAsyncComponent(async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    }),
    DpLoading,
    SegmentComment: defineAsyncComponent(() => import(/* webpackChunkName: "segment-comment" */ './SegmentComment'))
  },

  props: {
    currentUser: {
      type: Object,
      required: true
    }
  },

  computed: {
    ...mapState('SegmentSlidebar', [
      'isLoading'
    ]),

    ...mapState('StatementSegment', {
      segments: 'items'
    }),

    ...mapGetters('SegmentSlidebar', [
      'commentsList',
      'procedureId',
      'showForm',
      'statementId'
    ]),

    comments () {
      return this.segment?.hasRelationship('comments')
        ? Object.values(this.segment.rel('comments'))
          .filter(comment => typeof comment !== 'undefined')
          .sort((a, b) => new Date(b.attributes.creationDate) - new Date(a.attributes.creationDate))
        : []
    },

    hasComments () {
      return this.comments.length > 0
    },

    heading () {
      return Translator.trans('segment') + ' ' + this.segment?.attributes.externId + ' - ' + Translator.trans('comments')
    },

    segment () {
      return this.segments[this.commentsList.segmentId] || null
    }
  },

  methods: {
    ...mapActions('StatementSegment', {
      listSegments: 'list'
    }),

    ...mapActions('SegmentComment', {
      listComments: 'list'
    }),

    ...mapMutations('SegmentSlidebar', [
      'setContent'
    ]),

    toggleForm () {
      this.setContent({ prop: 'commentsList', val: { ...this.commentsList, showForm: !this.showForm } })
    }
  }
}
</script>
