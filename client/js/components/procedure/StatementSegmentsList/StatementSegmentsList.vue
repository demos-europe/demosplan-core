<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div v-if="statement">
    <dp-slidebar @close="resetSlidebar">
      <dp-version-history
        v-show="slidebar.showTab === 'history'"
        class="u-pr"
        :procedure-id="procedureId" />
      <segment-comments-list
        v-if="hasPermission('feature_segment_comment_list_on_segment')"
        v-show="slidebar.showTab === 'comments'"
        ref="commentsList"
        class="u-mb-2 u-pr"
        :current-user="currentUser" />
      <segment-location-map
        v-show="slidebar.showTab === 'map'"
        ref="locationMap"
        :map-data="procedureMapSettings"
        :procedure-id="procedureId"
        :segment-id="slidebar.segmentId"
        :statement-id="statementId" />
    </dp-slidebar>

    <dp-sticky-element>
      <header class="border--bottom u-pv-0_5 flow-root">
        <div class="inline-flex space-inline-m">
          <status-badge
            class="mr-2"
            :status="statement.attributes.status || 'new'" />
          <h1 class="font-size-larger align-middle inline-block u-m-0">
            #{{ statementExternId }}
          </h1>
          <div
            v-if="hasPermission('feature_segment_recommendation_edit')"
            class="btn-group">
            <button
              class="btn btn--outline btn--primary"
              :class="{'is-current': currentAction === 'addRecommendation'}"
              data-cy="addRecommendation"
              @click="currentAction = 'addRecommendation'">
              {{ Translator.trans('segment.recommendation') }}
            </button>
            <button
              class="btn btn--outline btn--primary"
              :class="{'is-current': currentAction === 'editText'}"
              data-cy="editText"
              @click="currentAction = 'editText'">
              {{ Translator.trans('edit') }}
            </button>
          </div>
        </div>
        <ul class="float-right space-inline-s flex items-center">
          <li v-if="!statement.attributes.synchronized">
            <dp-claim
              class="o-flyout__trigger u-ph-0_25 line-height--2"
              :assigned-id="currentAssignee.id"
              :assigned-name="currentAssignee.name"
              :assigned-organisation="currentAssignee.orgaName"
              :current-user-id="currentUser.id"
              entity-type="statement"
              :is-loading="isLoading"
              :label="Translator.trans(`${currentUser.id === currentAssignee.id ? 'assigned' : 'assign'}`)"
              @click="toggleClaimStatement" />
          </li>
          <li>
            <statement-export-modal
              data-cy="statementSegmentsList:export"
              @export="showHintAndDoExport"
              is-single-statement-export />
          </li>
          <li v-if="hasPermission('feature_read_source_statement_via_api')">
            <dp-flyout :disabled="isDisabledAttachmentFlyout">
              <template slot="trigger">
                <span>
                  {{ Translator.trans('attachments') }}
                  <span v-text="attachmentsAndOriginalPdfCount" />
                  <i
                    class="fa fa-angle-down"
                    aria-hidden="true" />
                </span>
              </template>
              <template v-if="statement">
                <div class="overflow-x-scroll break-words max-h-13 max-w-14 w-max">
                  <span class="block weight--bold">{{ Translator.trans('original.pdf') }}</span>
                  <statement-meta-attachments-link
                    v-if="originalAttachment.hash"
                    class="block whitespace-normal u-mr-0_75"
                    :attachment="originalAttachment"
                    :procedure-id="procedureId" />
                  <span
                    v-if="additionalAttachments.length > 0"
                    class="block weight--bold">{{ Translator.trans('more.attachments') }}</span>
                  <statement-meta-attachments-link
                    v-for="attachment in additionalAttachments"
                    class="block whitespace-normal u-mr-0_75"
                    :attachment="attachment"
                    :key="attachment.hash"
                    :procedure-id="procedureId" />
                </div>
              </template>
            </dp-flyout>
          </li>
          <li>
            <dp-flyout
              ref="metadataFlyout"
              :has-menu="false">
              <template v-slot:trigger>
                <span>
                  {{ Translator.trans('statement.metadata') }}
                  <i
                    class="fa fa-angle-down"
                    aria-hidden="true" />
                </span>
              </template>
              <statement-meta-tooltip
                v-if="statement"
                :statement="statement"
                :submit-type-options="submitTypeOptions"
                toggle-button
                @toggle="toggleInfobox" />
            </dp-flyout>
          </li>
        </ul>
      </header>
    </dp-sticky-element>

    <div class="u-mt-0_5">
      <!--Statement meta data -->
      <statement-meta
        v-if="showInfobox && statement"
        :attachments="filteredAttachments"
        :current-user-id="currentUser.id"
        :editable="editable"
        :statement="statement"
        :submit-type-options="submitTypeOptions"
        @close="showInfobox = false"
        @save="(statement) => saveStatement(statement)"
        @input="checkStatementClaim" />
      <segments-recommendations
        v-if="currentAction === 'addRecommendation' && hasPermission('feature_segment_recommendation_edit')"
        :current-user="currentUser"
        :procedure-id="procedureId"
        :statement-id="statementId" />
      <statement-segments-edit
        v-else-if="currentAction === 'editText'"
        :current-user="currentUser"
        :editable="editable"
        :segment-draft-list="this.statement.attributes.segmentDraftList"
        :statement-id="statementId"
        @statement-text-updated="checkStatementClaim"
        @save-statement="saveStatement" />
    </div>
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpButton,
  DpFlyout,
  DpSlidebar,
  DpStickyElement
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import DpClaim from '@DpJs/components/statement/DpClaim'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory'
import SegmentCommentsList from './SegmentCommentsList'
import SegmentLocationMap from './SegmentLocationMap'
import SegmentsRecommendations from './SegmentsRecommendations'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'
import StatementMeta from './StatementMeta/StatementMeta'
import StatementMetaAttachmentsLink from './StatementMeta/StatementMetaAttachmentsLink'
import StatementMetaTooltip from '@DpJs/components/statement/StatementMetaTooltip'
import StatementSegmentsEdit from './StatementSegmentsEdit'
import StatusBadge from '../Shared/StatusBadge.vue'

export default {
  name: 'StatementSegmentsList',

  components: {
    DpClaim,
    DpButton,
    DpFlyout,
    DpSlidebar,
    DpStickyElement,
    DpVersionHistory,
    SegmentCommentsList,
    SegmentLocationMap,
    SegmentsRecommendations,
    StatementExportModal,
    StatementMeta,
    StatementMetaAttachmentsLink,
    StatementMetaTooltip,
    StatementSegmentsEdit,
    StatusBadge
  },

  provide () {
    return {
      procedureId: this.procedureId,
      recommendationProcedureIds: this.recommendationProcedureIds
    }
  },

  props: {
    currentUser: {
      type: Object,
      required: true
    },

    /**
     * If inside a source procedure that is already coupled, HEARING_AUTHORITY_ADMIN users may copy statements to the
     * respective target procedure, while HEARING_AUTHORITY_WORKER users may see which statements are synchronized.
     */
    isSourceAndCoupledProcedure: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    recommendationProcedureIds: {
      type: Array,
      required: false,
      default: () => ([])
    },

    statementId: {
      type: String,
      required: true
    },

    statementExternId: {
      type: String,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      currentAction: 'addRecommendation',
      isLoading: false,
      procedureMapSettings: {},
      segmentDraftList: '',
      // Add key to meta box to rerender the component in case the save request fails and the data is store in set back to initial values
      showInfobox: false,
      statementClaimChecked: false,
      submittersList: ''
    }
  },

  computed: {
    ...mapState('AssignableUser', {
      assignableUsersObject: 'items'
    }),

    ...mapState('StatementSegment', {
      segments: 'items'
    }),

    ...mapState('Statement', {
      statements: 'items'
    }),

    ...mapState('SegmentSlidebar', ['slidebar']),

    ...mapGetters('SegmentSlidebar', ['commentsList']),

    additionalAttachments () {
      /**
       * Until we move completely to the "new way" of handling files,
       * We have to get the additional files directly from files since that's the place where they get stored.
       * When crating a new 'additionalFile' via API, the backend creates this kind of relationship as a sideeffect
       */
      if (this?.statement?.hasRelationship('files')) {
        const files = this.statement.relationships.files.list()

        return Object.values(files).map(file => {
          return {
            hash: file.attributes.hash,
            filename: file.attributes.filename,
            type: file.type
          }
        })
      } else {
        return []
      }
    },

    assignableUsers () {
      return Object.keys(this.assignableUsersObject).length
        ? Object.values(this.assignableUsersObject).map(user => ({
          name: user.attributes.firstname + ' ' + user.attributes.lastname,
          id: user.id
        }))
        : []
    },

    attachments () {
      if (this?.statement?.hasRelationship('attachments')) {
        const attachments = this.statement.relationships.attachments.list()

        return Object.values(attachments).map(attachment => {
          const file = attachment?.relationships?.file.get()
          return {
            hash: file.attributes.hash,
            filename: file.attributes.filename,
            type: attachment.attributes.attachmentType
          }
        })
      } else {
        return []
      }
    },

    attachmentsAndOriginalPdfCount () {
      // While waiting for the API response, a placeholder string is shown
      if (this.statement === null) {
        return '...'
      }
      const originalPdfCount = this.originalAttachment.hash ? '1' : '-'
      const additionalAttachmentsCount = this.additionalAttachments.length === 0 ? '-' : this.additionalAttachments.length

      return ' (' + originalPdfCount + '/' + additionalAttachmentsCount + ')'
    },

    currentAssignee () {
      let currentAssigneeId = null

      if (this.statement?.relationships?.assignee.data && this.statement.relationships.assignee.data.id !== null) {
        currentAssigneeId = this.statement.relationships.assignee.data.id
      }

      if (currentAssigneeId) {
        const assignee = this.assignableUsersObject[currentAssigneeId] || { attributes: {} }
        const assigneeOrga = assignee.rel ? assignee.rel('orga') : null

        return {
          id: currentAssigneeId,
          name: `${assignee.attributes.firstname} ${assignee.attributes.lastname}`,
          orgaName: assigneeOrga ? assigneeOrga.attributes.name : ''
        }
      }

      return {
        id: '',
        name: '',
        orgaName: ''
      }
    },

    editable () {
      return this.isCurrentUserAssigned && !this.statement.attributes.synchronized
    },

    filteredAttachments () {
      return {
        additionalAttachments: this.additionalAttachments,
        originalAttachment: this.originalAttachment
      }
    },

    hasSegments () {
      return Object.keys(this.segments).length > 0
    },

    isCurrentUserAssigned () {
      if (this.statement.relationships && this.statement?.relationships?.assignee?.data !== null) {
        return this.currentUser.id === this.statement.relationships.assignee.data.id
      }

      return false
    },

    isDisabledAttachmentFlyout () {
      // If the statement has not loaded yet, attachments can not be determined, the control is disabled in that case.
      if (!this.statement) {
        return true
      }

      return !this.originalAttachment.hash && this.additionalAttachments.length === 0
    },

    originalAttachment () {
      return this.attachments.find((attachment) => attachment.type === 'source_statement') || {}
    },

    statement () {
      return this.statements[this.statementId] || null
    }
  },

  watch: {
    currentAction () {
      this.showInfobox = this.currentAction === 'editText'
    }
  },

  methods: {
    ...mapMutations('SegmentSlidebar', [
      'setContent',
      'setProperty'
    ]),

    ...mapMutations('Statement', {
      setStatement: 'setItem'
    }),

    ...mapActions('AssignableUser', {
      listAssignableUser: 'list'
    }),

    ...mapActions('ProcedureMapSettings', {
      fetchLayers: 'fetchLayers',
      fetchProcedureMapSettings: 'fetchProcedureMapSettings'
    }),

    ...mapActions('Statement', {
      getStatementAction: 'get',
      saveStatementAction: 'save',
      updateStatementAction: 'update',
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapActions('SegmentSlidebar', [
      'toggleSlidebarContent'
    ]),

    checkStatementClaim () {
      if (this.statementClaimChecked === false) {
        this.statementClaimChecked = true
        const isAssignedToCurrentUser = this.statement.hasRelationship('assignee') && this.statement.relationships.assignee.data.id === this.currentUser.id

        if (isAssignedToCurrentUser === false) {
          const isAssignedToOtherUser = this.statement.hasRelationship('assignee') && this.statement.relationships.assignee.data.id !== this.currentUser.id
          if (isAssignedToOtherUser && window.dpconfirm(Translator.trans('warning.statement.needLock.generic')) === false) {
            return false
          }
        }
      }
    },

    /**
     * Returns an error if claiming fails
     * @return {Promise<*>}
     */
    claimStatement () {
      this.isLoading = true
      const payload = {
        data: {
          id: this.statement.id,
          type: 'Statement',
          relationships: {
            assignee: {
              data: {
                type: 'Claim',
                id: this.currentUser.id
              }
            }
          }
        }
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: this.statement.id }), {}, payload)
        .then(response => { checkResponse(response) })
        .then(() => {
          const dataToUpdate = this.setDataToUpdate(true)

          this.setStatement({ ...dataToUpdate, id: this.statement.id })
          dplan.notify.notify('confirm', Translator.trans('confirm.statement.assignment.assigned'))
        })
        .catch((err) => {
          // Restore statement in store in case request failed
          this.restoreStatementAction(this.statement.id)
          return err
        })
        .finally(() => {
          this.isLoading = false
        })
    },

    getStatement () {
      const statementFields = [
        'assignee',
        'attachments',
        'similarStatementSubmitters',
        'authoredDate',
        'authorName',
        'files',
        'fullText',
        'isSubmittedByCitizen',
        'initialOrganisationCity',
        'initialOrganisationDepartmentName',
        'initialOrganisationHouseNumber',
        'initialOrganisationName',
        'initialOrganisationPostalCode',
        'initialOrganisationStreet',
        'internId',
        'isManual',
        'memo',
        'recommendation',
        'segmentDraftList',
        'status',
        'submitDate',
        'submitName',
        'submitType',
        'submitterEmailAddress'
      ]

      if (this.isSourceAndCoupledProcedure) {
        statementFields.push('synchronized')
      }

      if (hasPermission('area_statement_segmentation')) {
        statementFields.push('segmentDraftList')
      }

      return this.getStatementAction({
        id: this.statementId,
        include: [
          'assignee',
          'attachments',
          'attachments.file',
          'files',
          'similarStatementSubmitters'
        ].join(),
        fields: {
          Statement: statementFields.join(),
          SimilarStatementSubmitter: [
            'city',
            'emailAddress',
            'fullName',
            'postalCode',
            'streetName',
            'streetNumber'
          ].join(),
          StatementAttachment: [
            'file',
            'attachmentType'
          ].join(),
          File: [
            'hash',
            'filename'
          ].join()
        }
      })
    },

    resetSlidebar () {
      this.$refs.commentsList.$refs.createForm.resetCurrentComment(!this.commentsList.show)
      if (this.$refs.locationMap) {
        this.$refs.locationMap.resetCurrentMap()
      }

      this.setContent({ prop: 'slidebar', val: { isOpen: false, showTab: '', segmentId: '' } })
    },

    saveStatement (statement) {
      this.synchronizeAssignee(statement)
      this.synchronizeFullText(statement)
      // The key isManual is readonly, so we should remove it before saving
      delete statement.attributes.isManual
      this.setStatement({ ...statement, id: statement.id })

      this.saveStatementAction(statement.id)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    setDataToUpdate (claimingStatement = false) {
      return {
        ...this.statement,
        ...{
          relationships: {
            ...this.statements[this.statement.id].relationships,
            assignee: {
              data: {
                type: 'Claim',
                id: claimingStatement ? this.currentUser.id : null
              }
            }
          }
        }
      }
    },

    setInitialAction () {
      const queryParams = new URLSearchParams(window.location.search)
      let action = queryParams.get('action')

      if (action && action.includes('?')) {
        action = action.split('?')[0]
      }

      const defaultAction = hasPermission('feature_segment_recommendation_edit') ? 'addRecommendation' : 'editText'
      this.currentAction = action || defaultAction
    },

    showHintAndDoExport ({ route, docxHeaders, fileNameTemplate }) {
      const parameters = {
        procedureId: this.procedureId,
        statementId: this.statementId
      }

      if (docxHeaders) {
        parameters.tableHeaders = {
          col1: docxHeaders.col1,
          col2: docxHeaders.col2,
          col3: docxHeaders.col3
        }
      }

      if (fileNameTemplate) {
        parameters.fileNameTemplate = fileNameTemplate
      }

      if (window.dpconfirm(Translator.trans('export.statements.hint'))) {
        window.location.href = Routing.generate(route, parameters)
      }
    },

    /**
     * If `this.statement` has changed its assignee (which does not propagate to the
     * localStatement in StatementMeta), it must be synced back before applying the
     * StatementMeta data to `this.statement`.
     * @param {object} statement - The local statement of StatementMeta.vue.
     */
    synchronizeAssignee (statement) {
      const oldAssignee = JSON.stringify(statement.relationships.assignee.data)
      const newAssignee = JSON.stringify(this.statement.relationships.assignee.data)

      if (oldAssignee !== newAssignee) {
        statement.relationships.assignee.data = this.statement.relationships.assignee.data
      }
    },

    /**
     * This prevents the user from unintentionally deleting an unsaved text by synchronizing the local
     * statement in StatementMeta.vue (which also emits the local statement when saving only metadata)
     * with the statements from store. The editor automatically updates the state of statements in the
     * store when registering an input. This only occurs when a statement has not been segmented already.
     *
     * @param {object} statement - The local statement of StatementMeta.vue.
     */
    synchronizeFullText (statement) {
      if (statement.attributes.fullText !== this.statement.attributes.fullText && dpconfirm(Translator.trans('statement.save.text'))) {
        statement.attributes.fullText = this.statement.attributes.fullText
      }
    },

    toggleClaimStatement () {
      if (this.statements[this.statementId].relationships?.assignee?.data === null || this.currentUser.id !== this.statements[this.statementId].relationships?.assignee?.data?.id) {
        this.claimStatement()
      } else {
        this.unclaimStatement()
      }
    },

    toggleInfobox () {
      this.showInfobox = true
      this.$refs.metadataFlyout.isExpanded = false
    },

    unclaimStatement () {
      this.isLoading = true
      const payload = {
        data: {
          type: 'Statement',
          id: this.statement.id,
          relationships: {
            assignee: {
              data: null
            }
          }
        }
      }
      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: this.statement.id }), {}, payload)
        .then(response => checkResponse(response))
        .then(() => {
          const dataToUpdate = this.setDataToUpdate()

          this.setStatement({ ...dataToUpdate, id: this.statement.id })
          dplan.notify.notify('confirm', Translator.trans('confirm.statement.assignment.unassigned'))
        })
        .catch((err) => {
          this.restoreStatementAction(this.statement.id)
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
        })
    }
  },

  created () {
    this.setInitialAction()
    this.$root.$on('statement-attachments-added', this.getStatement)
  },

  mounted () {
    this.getStatement()
    this.listAssignableUser({
      include: 'orga',
      fields: {
        Orga: 'name'
      }
    })
    this.setContent({ prop: 'commentsList', val: { ...this.commentsList, procedureId: this.procedureId, statementId: this.statementId } })
    this.fetchProcedureMapSettings({ procedureId: this.procedureId })
      .then(response => {
        this.procedureMapSettings = { ...this.procedureMapSettings, ...response.attributes }
      })

    this.fetchLayers(this.procedureId)
      .then(response => {
        this.procedureMapSettings.layers = response.data
          .filter(layer => layer.attributes.isEnabled && layer.attributes.hasDefaultVisibility)
          .map(layer => layer.attributes)
      })
  }
}
</script>
