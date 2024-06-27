<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-label
      :for="`createComment:${segmentId}`"
      :text="Translator.trans('comment.add')" />
    <dp-editor
      :id="`createComment:${segmentId}`"
      ref="createComment"
      :value="text"
      @input="update" />
    <dp-button-row
      class="u-mt"
      primary
      secondary
      :busy="isLoading"
      @primary-action="save"
      @secondary-action="resetCurrentComment" />
  </div>
</template>

<script>
import { dpApi, DpButtonRow, DpEditor, DpLabel } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import dayjs from 'dayjs'

export default {
  name: 'CreateCommentForm',

  components: {
    DpButtonRow,
    DpEditor,
    DpLabel
  },

  props: {
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
    ...mapGetters('SegmentSlidebar', [
      'commentsList',
      'currentCommentText'
    ]),

    ...mapState('StatementSegment', {
      segments: 'items'
    }),

    ...mapState('SegmentSlidebar', [
      'isLoading'
    ]),

    segment () {
      return this.segments[this.segmentId]
    },

    text () {
      return this.currentCommentText || ''
    }
  },

  methods: {
    ...mapActions('StatementSegment', {
      listSegments: 'list',
      restoreSegmentAction: 'restoreFromInitial'
    }),

    ...mapMutations('SegmentSlidebar', [
      'setContent',
      'setProperty'
    ]),

    ...mapMutations('StatementSegment', {
      updateSegment: 'update'
    }),

    ...mapMutations('SegmentComment', {
      setComment: 'setItem'
    }),

    resetCurrentComment (show = true) {
      this.setContent({ prop: 'commentsList', val: { ...this.commentsList, currentCommentText: '', showForm: false, show: show } })
      this.$refs.createComment.resetEditor()
    },

    save () {
      this.setProperty({ prop: 'isLoading', val: true })

      const payload = {
        attributes: {
          text: this.currentCommentText
        },
        relationships: {
          place: {
            data: this.segment.relationships.place?.data?.id
              ? { id: this.segment.relationships.place.data.id, type: 'Place' }
              : null
          },
          segment: {
            data: {
              id: this.segmentId,
              type: 'StatementSegment'
            }
          },
          submitter: {
            data: {
              id: this.currentUser.id,
              type: 'User'
            }
          }
        },
        type: 'SegmentComment'
      }

      return dpApi.post(Routing.generate('api_resource_create', { resourceType: 'SegmentComment' }), {}, { data: payload })
        .then(response => {
          const id = response.data.data.id
          const payloadRel = payload.relationships
          const newCommentData = {
            ...payload,
            id: id,
            attributes: {
              ...payload.attributes,
              creationDate: dayjs().toISOString() // Since we don't have the value from the Backend, this should be close enough for ordering
            },
            // We have to hack it like this, because the types for relationships here are in camelCase and not in PascalCase
            relationships: {
              place: {
                data: payloadRel.place?.data?.id
                  ? {
                      id: payloadRel.place.data.id,
                      type: 'Place'
                    }
                  : null
              },
              segment: {
                data: {
                  id: payloadRel.segment.data.id,
                  type: 'StatementSegment'
                }
              },
              submitter: {
                data: {
                  id: payloadRel.submitter.data.id,
                  type: 'User'
                }
              }
            }
          }
          const relationPayload = {
            id: this.segment.id,
            relationship: 'comments',
            action: 'add',
            value: {
              id: id,
              type: 'SegmentComment'
            }
          }

          this.setComment(newCommentData)
          this.updateSegment(relationPayload)
          this.$emit('update')
          this.resetCurrentComment()
          this.setProperty({ prop: 'isLoading', val: false })
        })
        .catch((err) => {
          console.error(err)
        })
    },

    update (val) {
      this.setContent({ prop: 'commentsList', val: { ...this.commentsList, currentCommentText: val } })
    }
  }
}
</script>
