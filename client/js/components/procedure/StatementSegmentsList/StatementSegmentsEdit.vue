<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
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
            <template v-if="isSegmentLocked(segment)">
              <dp-button
                v-if="hasPermission('feature_administrate_segment_lock')"
                :text="lockTooltip"
                class="text-interactive inline-block ml-0.5 align-middle bg-transparent! border-transparent! hover:bg-interactive-subtle-hover!"
                icon="prohibit"
                icon-weight="fill"
                variant="subtle"
                hide-text
                @click="openUnlockModal(segment)"
              />
              <dp-tooltip
                v-else
                :text="lockTooltip"
              >
                <dp-icon
                  class="text-interactive inline-block ml-1"
                  icon="prohibit"
                  size="small"
                  weight="fill"
                />
              </dp-tooltip>
            </template>
            <dp-claim
              v-else
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
                  :routes="{ getFileByHash: (hash) => Routing.generate('core_file_procedure', { procedureId, hash }) }"
                  :toolbar-items="{ imageButton: true, linkButton: true, obscure: hasPermission('feature_obscure_text') }"
                  :tus-endpoint="dplan.paths.tusEndpoint"
                  :value="getSegmentInitialText(segment.id)"
                  @transform-obscure-tag="(val) => transformObscureTag(segment.id, val)"
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
            :value="getStatementInitialText()"
            @transform-obscure-tag="transformObscureStatementTag"
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
    <segment-unlock-modal
      v-if="hasPermission('feature_administrate_segment_lock')"
      ref="unlockModal"
      :assignable-users="assignableUsers"
      :places="places"
      @unlock="payload => unlockSegment(payload, () => fetchSegments(pagination?.currentPage || 1))"
    />
  </div>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInlineNotification,
  DpLoading,
  DpPager,
  DpTooltip,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import DpClaim from '@DpJs/components/statement/DpClaim'
import DpEditField from '@DpJs/components/statement/assessmentTable/DpEditField'
import { handleSegmentNavigation } from '@DpJs/lib/segment/handleSegmentNavigation'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import { scrollTo } from 'vue-scrollto'
import SegmentUnlockModal from '@DpJs/components/procedure/StatementSegmentsList/SegmentUnlockModal'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'
import { useSegmentUnlock } from '@DpJs/composables/useSegmentUnlock'
import { useUnsavedChangesGuard } from '@DpJs/composables/useUnsavedChangesGuard'

export default {
  name: 'StatementSegmentsEdit',

  components: {
    DpButton,
    DpButtonRow,
    DpClaim,
    DpEditField,
    DpIcon,
    DpLoading,
    DpPager,
    DpTooltip,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')

      return DpEditor
    }),
    DpInlineNotification,
    SegmentUnlockModal,
    TextContentRenderer,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  mixins: [dpValidateMixin, paginationMixin],

  inject: ['procedureId'],

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

  setup () {
    const { unlockModal, openUnlockModal, unlockSegment } = useSegmentUnlock()
    const { init, cleanup } = useUnsavedChangesGuard()

    return {
      unlockModal,
      openUnlockModal,
      unlockSegment,
      initUnsavedChangesGuard: init,
      cleanupUnsavedChangesGuard: cleanup,
    }
  },

  data () {
    return {
      claimLoading: null,
      editingSegmentIds: [],
      hasUnsavedChanges: false,
      isLoading: false,
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

    ...mapState('Place', {
      placeItems: 'items',
    }),

    ...mapState('AssignableUser', {
      assignableUsersObject: 'items',
    }),

    assignableUsers () {
      const users = Object.values(this.assignableUsersObject).map(user => ({
        name: user.attributes.firstname + ' ' + user.attributes.lastname,
        id: user.id,
      }))

      return [{ name: Translator.trans('not.assigned'), id: 'noAssigneeId' }, ...users]
    },

    assigneeBySegment () {
      return segmentId => {
        const segment = this.segments[segmentId]

        // Bypass segment.rel() to avoid library crash on null relationships
        if (!segment.hasRelationship('assignee') || !segment.relationships?.assignee?.data) {
          return {
            id: '',
            name: '',
            orgaName: '',
          }
        }

        const assignee = segment.rel('assignee')
        const orga = assignee.rel('orga')

        return {
          id: assignee.id || '',
          name: (assignee.attributes?.firstname || '') + ' ' + (assignee.attributes?.lastname || ''),
          orgaName: orga?.attributes?.name || '',
        }
      }
    },

    hasSegments () {
      return Object.keys(this.segments).length > 0 && hasPermission('area_statement_segmentation')
    },

    lockTooltip () {
      return hasPermission('feature_administrate_segment_lock') ?
        Translator.trans('segment.unlock.click.hint') :
        Translator.trans('segment.lock.hint')
    },

    places () {
      return Object.values(this.placeItems).map(place => ({
        name: place.attributes.name,
        id: place.id,
        locked: place.attributes.locked,
      }))
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
      saveStatementAction: 'save',
    }),

    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list',
    }),

    ...mapActions('Place', {
      fetchPlaces: 'list',
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem',
    }),

    addToEditing (id) {
      this._localSegmentTexts[id] = this.segments[id]?.attributes?.text || ''

      if (!this.editingSegmentIds.includes(id)) {
        this.editingSegmentIds.push(id)
      }

      this.checkForUnsavedChanges()
    },

    checkForUnsavedChanges () {
      const hasSegmentChanges = this.editingSegmentIds.some(segmentId => this.hasSegmentUnsavedChanges(segmentId))
      const hasStatementChanges = this.hasStatementUnsavedChanges()

      this.hasUnsavedChanges = hasSegmentChanges || hasStatementChanges
    },

    getSegmentInitialText (segmentId) {
      return this.segments[segmentId]?.attributes?.text ?? ''
    },

    hasSegmentUnsavedChanges (segmentId) {
      if (this._localSegmentTexts[segmentId] === undefined) {
        return false
      }

      const originalText = this.segments[segmentId]?.attributes?.text || ''
      const currentText = this._localSegmentTexts[segmentId] || ''

      return originalText !== currentText
    },

    hasStatementUnsavedChanges () {
      if (this._localStatementText === null) {
        return false
      }

      const originalText = this.statement.attributes?.fullText || ''
      const currentText = this._localStatementText || ''

      return originalText !== currentText
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
      if (this.isSegmentLocked(segment)) {
        return false
      }

      return segment?.relationships?.assignee?.data?.id === this.currentUser.id
    },

    isSegmentLocked (segment) {
      if (!hasPermission('feature_segment_lock_by_workflow_place')) {
        return false
      }

      const placeId = segment?.relationships?.place?.data?.id

      return !!this.placeItems[placeId]?.attributes?.locked
    },

    /**
     * Required by useUnsavedChangesGuard composable
     * Discard all unsaved changes
     */
    onDiscardChanges () {
      const segmentsToReset = [...this.editingSegmentIds]

      segmentsToReset.forEach(segmentId => {
        this.reset(segmentId)
      })

      if (this._localStatementText !== null) {
        this.resetStatement()
      }

      return Promise.resolve()
    },


    reset (segmentId) {
      delete this._localSegmentTexts[segmentId]

      const editField = this.$refs[`editField_${segmentId}`]?.[0]

      if (editField) {
        editField.loading = false
        editField.editingEnabled = false
      }

      const segmentIdIndex = this.editingSegmentIds.indexOf(segmentId)

      if (segmentIdIndex > -1) {
        this.editingSegmentIds.splice(segmentIdIndex, 1)
      }

      this.checkForUnsavedChanges()
    },

    resetStatement () {
      this.restoreStatementAction(this.statement.id)
      this._localStatementText = null
      this.checkForUnsavedChanges()
    },

    saveSegment (segmentId) {
      const textToSave = this._localSegmentTexts[segmentId] ?? ''

      if (!textToSave) {
        const editField = this.$refs[`editField_${segmentId}`]?.[0]

        if (editField) {
          editField.loading = false
        }

        dplan.notify.error(Translator.trans('error.segment.empty.text'))

        return Promise.resolve(false)
      }

      const segment = this.segments[segmentId]

      this.setSegment({
        ...segment,
        id: segmentId,
        attributes: {
          ...segment.attributes,
          text: textToSave,
        },
      })

      return this.saveSegmentAction(segmentId)
        .then(() => {
          this.reset(segmentId)

          return true
        })
        .catch(() => {
          this.restoreSegmentAction(segmentId)
          dplan.notify.error(Translator.trans('error.api.generic'))

          const editField = this.$refs[`editField_${segmentId}`]?.[0]

          if (editField) {
            editField.loading = false
          }

          return false
        })
    },

    /**
     * Required by useUnsavedChangesGuard composable
     * Save all segments and/or statement that have unsaved changes
     * @returns {Promise} Resolves if all saves succeed, rejects if any save fails
     */
    async saveUnsavedChanges () {
      const segmentsToSave = this.editingSegmentIds.filter(segmentId => this.hasSegmentUnsavedChanges(segmentId))
      const shouldSaveStatement = this.hasStatementUnsavedChanges()

      if (segmentsToSave.length === 0 && !shouldSaveStatement) {
        return
      }

      const savePromises = [
        ...segmentsToSave.map(segmentId => this.saveSegment(segmentId)),
      ]

      if (shouldSaveStatement) {
        savePromises.push(this.saveStatement())
      }

      const results = await Promise.all(savePromises)

      const allSucceeded = results.every(result => result === true)

      if (!allSucceeded) {
        throw new Error('Failed to save one or more segments/statement')
      }
    },

    saveStatement () {
      const textToSave = this._localStatementText ?? this.statement.attributes.fullText

      const updatedStatement = {
        ...this.statement,
        attributes: {
          ...this.statement.attributes,
          fullText: textToSave,
        },
      }

      this.setStatement({ ...updatedStatement, id: this.statement.id })

      return this.saveStatementAction(this.statement.id)
        .then(() => {
          this._localStatementText = null
          this.checkForUnsavedChanges()
          this.$emit('statementText:updated')

          return true
        })
        .catch(() => {
          this.restoreStatementAction(this.statement.id)
          dplan.notify.error(Translator.trans('error.api.generic'))

          return false
        })
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
      const assigneeData = segment.relationships?.assignee?.data
      const userIdToSet = (segment.hasRelationship('assignee') && assigneeData?.id === this.currentUser.id) ? null : this.currentUser.id
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
      this._localSegmentTexts[segmentId] = val
      this.checkForUnsavedChanges()
    },

    getStatementInitialText () {
      return this.statement?.attributes?.fullText || ''
    },

    updateStatementText (val) {
      this._localStatementText = val
      this.checkForUnsavedChanges()
    },

    transformObscureTag (segmentId, val) {
      this._localSegmentTexts[segmentId] = val
      this.checkForUnsavedChanges()
    },

    transformObscureStatementTag (val) {
      this._localStatementText = val
      this.checkForUnsavedChanges()
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
        'orderInProcedure',
        'polygon',
        'recommendation',
      ]

      if (hasPermission('field_segments_custom_fields')) {
        statementSegmentFields.push('customFields')
      }

      const response = await this.listSegments({
        include: ['assignee', 'comments', 'place', 'tags', 'assignee.orga', 'comments.submitter', 'comments.place'].join(),
        sort: 'orderInProcedure',
        fields: {
          Place: [
            'name',
            ...(hasPermission('feature_segment_lock_by_workflow_place') ? ['locked'] : []),
            'sortIndex',
          ].join(),
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
    // Non-reactive buffers for editor content (avoid controlled component issues)
    this._localSegmentTexts = {}
    this._localStatementText = null

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

      // Assignable users and places are needed to populate the unlock modal
      if (hasPermission('feature_administrate_segment_lock')) {
        this.fetchAssignableUsers()
        this.fetchPlaces({
          fields: {
            Place: [
              'description',
              ...(hasPermission('feature_segment_lock_by_workflow_place') ? ['locked'] : []),
              'name',
              'solved',
              'sortIndex',
            ].join(),
          },
          sort: 'sortIndex',
        })
      }
    }

    this.initUnsavedChangesGuard({
      hasUnsavedChanges: () => this.hasUnsavedChanges,
      saveUnsavedChanges: () => this.saveUnsavedChanges(),
      onDiscardChanges: () => this.onDiscardChanges(),
      componentId: `statement-segments-edit-${this.statementId}`,
    })
  },

  beforeUnmount () {
    if (this.editingSegmentIds.length > 0 && hasPermission('area_statement_segmentation')) {
      this.editingSegmentIds.forEach(segmentId => this.reset(segmentId))
    }

    if (this.hasSegments === false && this.statement) {
      this.resetStatement()
    }

    this.cleanupUnsavedChangesGuard()
  },
}
</script>
