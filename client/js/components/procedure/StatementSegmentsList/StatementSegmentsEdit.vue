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
      <!-- Pagination above segments list -->
      <div
        v-if="pagination && pagination.currentPage"
        class="flex justify-between items-center mb-4"
      >
        <dp-pager
          :key="`segmentsPagerTopEdit_${pagination.currentPage}_${pagination.count || 0}`"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :limits="pagination.limits || defaultPagination.limits"
          :per-page="pagination.perPage || defaultPagination.perPage"
          :total-pages="pagination.totalPages || 1"
          :total-items="pagination.total || 0"
          @page-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>

      <div
        v-for="segment in segments"
        :id="'segmentTextEdit_' + segment.id"
        :key="segment.id"
        class="px-1 hover:bg-interactive-secondary-subtle-hover"
      >
        <div class="inline-block w-[5%]">
          <dp-claim
            class="c-at-item__row-icon inline-block"
            :assigned-id="assigneeBySegment(segment.id).id"
            :assigned-name="assigneeBySegment(segment.id).name"
            :assigned-organisation="assigneeBySegment(segment.id).orgaName"
            :current-user-id="currentUser.id"
            :current-user-name="currentUser.firstname + ' ' + currentUser.lastname"
            entity-type="segment"
            :is-loading="claimLoading === segment.id"
            @click="() => toggleClaimSegment(segment)"
          />
        </div><!--
     --><div class="inline-block break-words w-[95%]">
          <dp-edit-field
            :ref="`editField_${segment.id}`"
            class="c-styled-html"
            :editable="isAssigneeEditable(segment)"
            label=""
            :label-grid-cols="0"
            no-margin
            persist-icons
            @reset="() => reset(segment.id)"
            @toggle-editing="() => addToEditing(segment.id)"
            @save="() => saveSegment(segment.id)"
          >
            <template v-slot:display>
              <text-content-renderer
                class="pr-3"
                :text="segment.attributes.text"
              />
            </template>
            <template v-slot:edit>
              <dp-editor
                class="mr-4 pt-1"
                :toolbar-items="{ linkButton: true, obscure: hasPermission('feature_obscure_text') }"
                :value="segment.attributes.text"
                @transform-obscure-tag="transformObscureTag"
                @input="(val) => updateSegmentText(segment.id, val)"
              />
            </template>
          </dp-edit-field>
        </div>
      </div>

      <!-- Pagination below segments list -->
      <div
        v-if="pagination && pagination.currentPage"
        class="flex justify-between items-center mt-4"
      >
        <dp-pager
          :key="`segmentsPagerBottomEdit_${pagination.currentPage}_${pagination.count || 0}`"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :limits="pagination.limits || defaultPagination.limits"
          :per-page="pagination.perPage || defaultPagination.perPage"
          :total-pages="pagination.totalPages || 1"
          :total-items="pagination.total || 0"
          @page-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </template>

    <!-- if statement has no segments, display statement -->
    <template v-else-if="statement">
      <template v-if="editable && !hasDraftSegments">
        <dp-editor
          hidden-input="statementText"
          required
          :toolbar-items="{ linkButton: true}"
          :value="statement.attributes.fullText || ''"
          @transform-obscure-tag="transformObscureTag"
          @input="updateStatementText"
        />
        <dp-button-row
          class="u-mv"
          primary
          secondary
          :secondary-text="Translator.trans('discard.changes')"
          @primary-action="dpValidateAction('segmentsStatementForm', saveStatement, false)"
          @secondary-action="resetStatement"
        />
      </template>
      <div
        v-else
        class="border space-inset-s"
      >
        <dp-inline-notification
          v-if="hasDraftSegments"
          class="mt mb-2"
          :message="Translator.trans('warning.statement.in.segmentation.cannot.be.edited')"
          type="warning"
        />
        <p class="font-semibold">
          {{ Translator.trans('statement.text.short') }}
        </p>
        <div v-cleanhtml="statement.attributes.fullText || ''" />
      </div>
    </template>
  </div>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpButtonRow,
  DpInlineNotification,
  DpLoading,
  DpPager,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import DpClaim from '@DpJs/components/statement/DpClaim'
import DpEditField from '@DpJs/components/statement/assessmentTable/DpEditField'
import { handleSegmentNavigation } from '@DpJs/lib/segment/handleSegmentNavigation'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import { scrollTo } from 'vue-scrollto'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'

export default {
  name: 'StatementSegmentsEdit',

  components: {
    DpButtonRow,
    DpClaim,
    DpEditField,
    DpLoading,
    DpPager,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }),
    DpInlineNotification,
    TextContentRenderer,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  mixins: [dpValidateMixin, paginationMixin],

  props: {
    currentUser: {
      type: Object,
      required: true,
    },

    editable: {
      type: Boolean,
      required: false,
      default: false,
    },

    hasDraftSegments: {
      type: Boolean,
      required: false,
      default: false,
    },

    statementId: {
      type: String,
      required: true,
    },
  },

  emits: [
    'saveStatement',
    'statementText:updated',
  ],

  data () {
    return {
      claimLoading: null,
      editingSegmentIds: [],
      isLoading: false,
      obscuredText: '',
      defaultPagination: {
        currentPage: 1,
        limits: [10, 20, 50],
        perPage: 20,
      },
      pagination: {},
      storageKeyPagination: `segmentsEdit_${this.statementId}_pagination`,
      segmentNavigation: null,
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segments: 'items',
    }),

    ...mapState('Statement', {
      statements: 'items',
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
            orgaName: orga ? orga.attributes.name : '',
          }
        } catch (err) {
          console.error(err)

          if (segment.hasRelationship('assignee') && segment.relationships.assignee.data.id === this.currentUser.id) {
            return {
              id: this.currentUser.id,
              name: this.currentUser.firstname + ' ' + this.currentUser.lastname,
              orgaName: this.currentUser.orgaName,
            }
          } else {
            return {
              id: '',
              name: '',
              orgaName: '',
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
    },
  },

  methods: {
    ...mapMutations('StatementSegment', {
      updateSegment: 'update',
      setSegment: 'setItem',
    }),

    ...mapActions('StatementSegment', {
      updateSegmentAction: 'update',
      restoreSegmentAction: 'restoreFromInitial',
      saveSegmentAction: 'save',
      listSegments: 'list',
    }),

    ...mapActions('Statement', {
      restoreStatementAction: 'restoreFromInitial',
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem',
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
                id: this.currentUser.id,
              },
            },
          },
        },
      }

      return dpApi.patch(
        Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: segment.id }),
        {},
        payload,
        {
          messages: {
            200: {
              text: Translator.trans('segment.claim.success'),
              type: 'confirm',
            },
            204: {
              text: Translator.trans('segment.claim.success'),
              type: 'confirm',
            },
            400: {
              text: Translator.trans('segment.claim.fail'),
              type: 'error',
            },
          },
        },
      )
        .then(() => {
          this.claimLoading = null
        })
        .catch((err) => {
          console.error(err)
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
      if (!this.segments[segmentId].attributes.text) {
        this.$refs[`editField_${segmentId}`][0].loading = false

        return dplan.notify.error(Translator.trans('error.segment.empty.text'))
      }

      // Use the transformed text if available
      const textToSave = this.obscuredText || this.segments[segmentId].attributes.text

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
      this.$emit('saveStatement', this.statement)
    },

    scrollToSegment () {
      const queryParams = new URLSearchParams(window.location.search)
      const segmentId = queryParams.get('segment')

      if (segmentId) {
        scrollTo('#segmentTextEdit_' + segmentId, { offset: -110 })
      }
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
              data: null,
            },
          },
        },
      }
      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: segment.id }), {}, payload)
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
      const fullText = this.obscuredText && this.obscuredText !== val ? this.obscuredText : val
      const updated = {
        ...this.segments[segmentId],
        attributes: {
          ...this.segments[segmentId].attributes,
          text: fullText,
        },
      }
      this.setSegment({ ...updated, id: segmentId })
    },

    updateStatementText (val) {
      const fullText = this.obscuredText && this.obscuredText !== val ? this.obscuredText : val

      this.$emit('statementText:updated')

      const updated = {
        ...this.statement,
        attributes: {
          ...this.statement.attributes,
          fullText,
        },
      }
      this.setStatement({ ...updated, id: this.statement.id })
    },

    transformObscureTag (val) {
      this.obscuredText = val
    },

    async fetchSegments (page = 1) {
      this.isLoading = true

      // Calculate correct page for segment parameter (only runs once)
      const { calculatedPage, perPage } = await this.segmentNavigation.calculatePageForSegment()
      let shouldRemoveSegmentParam = false

      if (calculatedPage) {
        page = calculatedPage
        this.pagination.currentPage = calculatedPage

        if (perPage) {
          this.pagination.perPage = perPage
        }

        // Mark that we need to remove segment param after scroll completes
        shouldRemoveSegmentParam = true
      }

      const statementSegmentFields = [
        'tags',
        'text',
        'assignee',
        'place',
        'comments',
        'externId',
        'internId',
        'orderInStatement',
        'polygon',
        'recommendation',
      ]

      if (hasPermission('field_segments_custom_fields')) {
        statementSegmentFields.push('customFields')
      }

      const response = await this.listSegments({
        include: ['assignee', 'comments', 'place', 'tags', 'assignee.orga', 'comments.submitter', 'comments.place'].join(),
        sort: 'orderInStatement',
        fields: {
          Place: ['name', 'sortIndex'].join(),
          SegmentComment: ['creationDate', 'place', 'submitter', 'text'].join(),
          StatementSegment: statementSegmentFields.join(),
          User: ['lastname', 'firstname', 'orga'].join(),
          Orga: ['name'].join(),
        },
        page: {
          number: page,
          size: this.pagination?.perPage || this.defaultPagination.perPage,
        },
        filter: {
          parentStatementOfSegment: {
            condition: {
              path: 'parentStatement.id',
              value: this.statementId,
            },
          },
        },
      })

      // Update pagination with response metadata
      if (response && response.meta && response.meta.pagination) {
        this.setLocalStorage(response.meta.pagination)
        this.updatePagination(response.meta.pagination)
      }

      this.isLoading = false

      await this.$nextTick()

      this.scrollToSegment()

      // Remove segment parameter after scroll completes to prevent re-navigation on tab toggle
      if (shouldRemoveSegmentParam) {
        this.segmentNavigation.removeSegmentParameter()
      }
    },

    handlePageChange (page) {
      this.fetchSegments(page)
    },

    handleSizeChange (newSize) {
      if (newSize <= 0) {
        // Prevent division by zero or negative page size
        return
      }
      // Compute new page with current page for changed number of items per page
      const page = Math.floor((this.pagination?.perPage * (this.pagination?.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.fetchSegments(page)
    },
  },

  created () {
    this.segmentNavigation = handleSegmentNavigation({
      statementId: this.statementId,
      storageKey: this.storageKeyPagination,
      currentPerPage: this.pagination?.perPage,
      defaultPagination: this.defaultPagination,
    })
  },

  mounted () {
    if (hasPermission('area_statement_segmentation')) {
      /**
       * Check if the user navigated here from a specific segment in the segments list; if so, navigate to the page on which
       * that segment is found (i.e., override pagination)
       */
      const paginationOverride = this.segmentNavigation.initializeSegmentPagination(() => this.initPagination())

      if (paginationOverride) {
        this.pagination = paginationOverride
      }

      /**
       * Fetch segments for current page from pagination (either based on the segment the user navigated from or on localStorage),
       * default to 1st page
       */
      this.fetchSegments(this.pagination?.currentPage || 1)
    }
  },

  beforeUnmount () {
    if (this.editingSegmentIds.length > 0 && hasPermission('area_statement_segmentation')) {
      this.editingSegmentIds.forEach(segment => this.reset(segment.id))
    }
    if (this.hasSegments === false && this.segment) {
      this.resetStatement()
    }
  },
}
</script>
