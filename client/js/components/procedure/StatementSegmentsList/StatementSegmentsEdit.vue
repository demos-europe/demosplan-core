<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div data-dp-validate="segmentsStatementForm">
    <dp-loading v-if="isLoading" />

    <!-- if statement has segments and user has the permission, display segments -->
    <template v-else-if="hasSegments">
      <div
        v-for="segment in segments"
        class="u-ph-0_25"
        :class="{ 'bg-color--grey-light-2': hoveredSegment === segment.id }"
        :id="'segmentTextEdit_' + segment.id"
        :key="segment.id"
        @mouseenter="hoveredSegment = segment.id"
        @mouseleave="hoveredSegment = null">
        <div
          class="inline-block"
          style="width: 5%">
          <dp-claim
            class="c-at-item__row-icon inline-block"
            :assigned-id="assigneeBySegment(segment.id).id"
            :assigned-name="assigneeBySegment(segment.id).name"
            :assigned-organisation="assigneeBySegment(segment.id).orgaName"
            :current-user-id="currentUser.id"
            :current-user-name="currentUser.firstname + ' ' + currentUser.lastname"
            entity-type="segment"
            :is-loading="claimLoading === segment.id"
            @click="() => toggleClaimSegment(segment)" />
        </div><!--
        --><div
            class="inline-block break-words"
            style="width: 95%">
          <dp-edit-field
            :editable="isAssigneeEditable(segment)"
            label=""
            :label-grid-cols="0"
            no-margin
            persist-icons
            :ref="`editField_${segment.id}`"
            @reset="() => reset(segment.id)"
            @toggleEditing="() => addToEditing(segment.id)"
            @save="() => saveSegment(segment.id)">
            <template v-slot:display>
              <text-content-renderer :text="segment.attributes.text" />
            </template>
            <template v-slot:edit>
              <dp-editor
                class="u-mr u-pt-0_25"
                :toolbar-items="{ linkButton: true, obscure: hasPermission('feature_obscure_text') }"
                :value="segment.attributes.text"
                @transformObscureTag="transformObscureTag"
                @input="(val) => updateSegmentText(segment.id, val)" />
            </template>
          </dp-edit-field>
        </div>
      </div>
    </template>

    <!-- if statement has no segments, display statement -->
    <template v-else-if="statement">
      <template v-if="editable && !segmentDraftList">
        <dp-editor
          hidden-input="statementText"
          required
          :toolbar-items="{ linkButton: true, obscure: hasPermission('feature_obscure_text') }"
          :value="statement.attributes.fullText || ''"
          @transformObscureTag="transformObscureTag"
          @input="updateStatementText" />
        <dp-button-row
          class="u-mv"
          primary
          secondary
          :secondary-text="Translator.trans('discard.changes')"
          @primary-action="dpValidateAction('segmentsStatementForm', saveStatement, false)"
          @secondary-action="resetStatement" />
      </template>
      <div
        v-else
        class="border space-inset-s">
        <dp-inline-notification
          v-if="segmentDraftList"
          class="mt mb-2"
          :message="Translator.trans('warning.statement.in.segmentation.cannot.be.edited')"
          type="warning" />
        <p class="weight--bold">
          {{ Translator.trans('statement.text.short') }}
        </p>
        <div v-cleanhtml="statement.attributes.fullText || ''" />
      </div>
    </template>
  </div>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  dpApi,
  DpButtonRow,
  DpInlineNotification,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpClaim from '@DpJs/components/statement/DpClaim'
import DpEditField from '@DpJs/components/statement/assessmentTable/DpEditField'
import { scrollTo } from 'vue-scrollto'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'

export default {
  name: 'StatementSegmentsEdit',

  components: {
    DpButtonRow,
    DpClaim,
    DpEditField,
    DpLoading,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    },
    DpInlineNotification,
    TextContentRenderer
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [dpValidateMixin],

  props: {
    currentUser: {
      type: Object,
      required: true
    },

    editable: {
      type: Boolean,
      required: false,
      default: false
    },

    segmentDraftList: {
      type: Object,
      required: false,
      default: () => {}
    },

    statementId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      claimLoading: null,
      editingSegmentIds: [],
      hoveredSegment: null,
      isLoading: false,
      transformedText: ''
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segments: 'items'
    }),

    ...mapState('Statement', {
      statements: 'items'
    }),

    assigneeBySegment () {
      return segmentId => {
        const segment = this.segments[segmentId]
        try {
          const assignee = segment.rel('assignee')
          const orga = assignee ? assignee.rel('orga') : ''

          return {
            id: assignee.id,
            name: assignee.attributes.firstname + ' ' + assignee.attributes.lastname,
            orgaName: orga ? orga.attributes.name : ''
          }
        } catch (err) {
          if (segment.hasRelationship('assignee') && segment.relationships.assignee.data.id === this.currentUser.id) {
            return {
              id: this.currentUser.id,
              name: this.currentUser.firstname + ' ' + this.currentUser.lastname,
              orgaName: this.currentUser.orgaName
            }
          } else {
            return {
              id: '',
              name: '',
              orgaName: ''
            }
          }
        }
      }
    },

    hasSegments () {
      return Object.keys(this.segments).length > 0 && hasPermission('area_statement_segmentation')
    },

    statement () {
      return this.statements[this.statementId] || null
    }
  },

  methods: {
    ...mapMutations('StatementSegment', {
      updateSegment: 'update',
      setSegment: 'setItem'
    }),

    ...mapActions('StatementSegment', {
      updateSegmentAction: 'update',
      restoreSegmentAction: 'restoreFromInitial',
      saveSegmentAction: 'save',
      listSegments: 'list'
    }),

    ...mapActions('Statement', {
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem'
    }),

    addToEditing (id) {
      this.editingSegmentIds.push(id)
    },

    claimSegment (segment) {
      const dataToUpdate = { ...segment, ...{ relationships: { ...segment.relationships, ...{ assignee: { data: { type: 'AssignableUser', id: this.currentUser.id } } } } } }
      this.setSegment({ ...dataToUpdate, id: segment.id })

      const payload = {
        data: {
          id: segment.id,
          type: 'StatementSegment',
          relationships: {
            assignee: {
              data: {
                type: 'AssignableUser',
                id: this.currentUser.id
              }
            }
          }
        }
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: segment.id }), {}, payload)
        .then(checkResponse)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('segment.claim.success'))
          this.claimLoading = null
        })
        .catch((err) => {
          console.error(err)
          dplan.notify.notify('error', Translator.trans('segment.claim.fail'))
          // Restore segment in store if it didn't work
          this.restoreSegmentAction(segment.id)
          this.claimLoading = null
        })
    },

    isAssigneeEditable (segment) {
      return segment?.relationships?.assignee?.data?.id === this.currentUser.id
    },

    reset (segmentId) {
      // Restore initial text value
      const initText = this.$store.state.StatementSegment.initial[segmentId].attributes.text
      this.updateSegmentText(segmentId, initText)
      if (this.$refs[`editField_${segmentId}`][0]) {
        this.$refs[`editField_${segmentId}`][0].loading = false
        this.$refs[`editField_${segmentId}`][0].editingEnabled = false
      }
      const segmentIdIndex = this.editingSegmentIds.indexOf(segmentId)
      this.editingSegmentIds.splice(segmentIdIndex, 1)
    },

    resetStatement () {
      this.restoreStatementAction(this.statement.id)
    },

    saveSegment (segmentId) {
      // Use the transformed text if available
      const textToSave = this.transformedText || this.segments[segmentId].attributes.text

      // Update the segment text with the transformed text
      this.updateSegmentText(segmentId, textToSave)

      this.saveSegmentAction(segmentId)
        .catch(() => {
          this.restoreSegmentAction(segmentId)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
        .finally(() => {
          const segmentIdIndex = this.editingSegmentIds.indexOf(segmentId)
          this.editingSegmentIds.splice(segmentIdIndex, 1)

          if (this.$refs[`editField_${segmentId}`][0]) {
            this.$refs[`editField_${segmentId}`][0].loading = false
            this.$refs[`editField_${segmentId}`][0].editingEnabled = false
          }
        })
    },

    saveStatement () {
      this.$emit('save-statement', this.statement)
    },

    scrollToSegment () {
      const queryParams = new URLSearchParams(window.location.search)
      const segmentId = queryParams.get('segment')
      scrollTo('#segmentTextEdit_' + segmentId, { offset: -110 })
    },

    /*
     * Don't use vuex-json-api lib for claiming and un-claiming because there is a problem if data in relationship is
     * null (=un-claiming); using vuex-json-api lib only for claiming but not for un-claiming doesn't work because the
     * initial items in the lib store are not updated when un-claiming outside of lib
     */
    toggleClaimSegment (segment) {
      this.claimLoading = segment.id
      const userIdToSet = segment.hasRelationship('assignee') && segment.relationships.assignee.data.id === this.currentUser.id ? null : this.currentUser.id
      const isClaim = userIdToSet !== null

      if (isClaim) {
        this.claimSegment(segment)
      } else {
        this.unclaimSegment(segment)
      }
    },

    unclaimSegment (segment) {
      const payload = {
        data: {
          type: 'StatementSegment',
          id: segment.id,
          relationships: {
            assignee: {
              data: null
            }
          }
        }
      }
      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: segment.id }), {}, payload)
        .then(checkResponse)
        .then(() => {
          const dataToUpdate = JSON.parse(JSON.stringify(segment))
          delete dataToUpdate.relationships.assignee
          // Set segment in store without the assignee
          this.setSegment({ ...dataToUpdate, id: segment.id })
          this.claimLoading = null
        })
        .catch((err) => {
          console.error(err)
          this.claimLoading = null
        })
    },

    updateSegmentText (segmentId, val) {
      let fullText = val
      if (this.transformedText && this.transformedText !== fullText) {
        fullText = this.transformedText
      }
      const updated = { ...this.segments[segmentId], ...{ attributes: { ...this.segments[segmentId].attributes, ...{ text: fullText } } } }

      this.setSegment({ ...updated, id: segmentId })
    },

    updateStatementText (val) {
      let fullText = val
      if (this.transformedText && this.transformedText !== fullText) {
        
        fullText = this.transformedText
      }

      this.$emit('statement-text-updated')

      const updated = { ...this.statement, ...{ attributes: { ...this.statement.attributes, ...{ fullText } } } }
      this.setStatement({ ...updated, id: this.statement.id })
    },

    transformObscureTag (val) {
      this.transformedText = val
    }
  },

  mounted () {
    if (Object.keys(this.segments).length === 0 && hasPermission('area_statement_segmentation')) {
      this.isLoading = true
      this.listSegments({
        include: ['assignee', 'comments', 'place', 'tag', 'assignee.orga', 'comments.submitter', 'comments.place'].join(),
        sort: 'orderInProcedure',
        fields: {
          Place: ['name', 'sortIndex'].join(),
          SegmentComment: ['creationDate', 'place', 'submitter', 'text'].join(),
          StatementSegment: ['assignee', 'comments', 'externId', 'recommendation', 'text', 'place'].join(),
          User: ['lastname', 'firstname', 'orga'].join(),
          Orga: ['name'].join()
        },
        filter: {
          parentStatementOfSegment: {
            condition: {
              path: 'parentStatement.id',
              value: this.statementId
            }
          }
        }
      })
        .then(() => {
          this.isLoading = false
          this.$nextTick(() => {
            this.scrollToSegment()
          })
        })
        .finally(() => {
          this.isLoading = false
        })
    }
  },

  beforeDestroy () {
    if (this.editingSegmentIds.length > 0 && hasPermission('area_statement_segmentation')) {
      this.editingSegmentIds.forEach(segment => this.reset(segment.id))
    }
    if (this.hasSegments === false && this.segment) {
      this.resetStatement()
    }
  }
}
</script>
