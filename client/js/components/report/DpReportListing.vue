<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-loading
    v-if="isFirstLoad"
    class="u-mt-2" />

  <div v-else>
    <div
      class="float-right u-mt-0_25"
      v-if="hasPermission('feature_export_protocol')">
      <a
        data-cy="exportTriggerPdf"
        :href="Routing.generate('dplan_export_report', { procedureId })">
        <i
          class="fa fa-share-square"
          aria-hidden="true" />
        {{ Translator.trans('export.trigger.pdf') }}
      </a>
    </div>

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_general')"
      group="general"
      group-label="general"
      content-label="act"
      :items="generalItems"
      :current-page="generalCurrentPage"
      :total-pages="generalTotalPages"
      :is-loading="generalLoading"
      @page-change="handlePageChange('general', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_public_phase')"
      group="publicPhase"
      group-label="procedure.public.phase"
      content-label="procedure.public.phase"
      :items="publicPhaseItems"
      :current-page="publicPhaseCurrentPage"
      :total-pages="publicPhaseTotalPages"
      :is-loading="publicPhaseLoading"
      @page-change="handlePageChange('publicPhase', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_invitations')"
      group="invitations"
      group-label="email.invitations"
      content-label="act"
      :items="invitationsItems"
      :current-page="invitationsCurrentPage"
      :total-pages="invitationsTotalPages"
      :is-loading="invitationsLoading"
      @page-change="handlePageChange('invitations', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_register_invitations')"
      group="registerInvitations"
      group-label="email.register.invitations"
      content-label="act"
      :items="registerInvitationsItems"
      :current-page="registerInvitationsCurrentPage"
      :total-pages="registerInvitationsTotalPages"
      :is-loading="registerInvitationsLoading"
      @page-change="handlePageChange('registerInvitations', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_final_mails')"
      group="finalMails"
      group-label="text.protocol.finalMails"
      content-label="text.protocol.finalMails"
      :items="finalMailsItems"
      :current-page="finalMailsCurrentPage"
      :total-pages="finalMailsTotalPages"
      :is-loading="finalMailsLoading"
      @page-change="handlePageChange('finalMails', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_statements')"
      group="statements"
      group-label="statements"
      content-label="statement"
      :items="statementsItems"
      :current-page="statementsCurrentPage"
      :total-pages="statementsTotalPages"
      :is-loading="statementsLoading"
      @page-change="handlePageChange('statements', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_elements')"
      group="elements"
      group-label="plandocument.and.drawing.categories"
      content-label="category"
      :items="elementsItems"
      :current-page="elementsCurrentPage"
      :total-pages="elementsTotalPages"
      :is-loading="elementsLoading"
      @page-change="handlePageChange('elements', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_single_documents')"
      group="singleDocuments"
      group-label="plandocument.and.drawing.documents"
      content-label="plandocument"
      :items="singleDocumentsItems"
      :current-page="singleDocumentsCurrentPage"
      :total-pages="singleDocumentsTotalPages"
      :is-loading="singleDocumentsLoading"
      @page-change="handlePageChange('singleDocuments', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_paragraphs')"
      group="paragraphs"
      group-label="plandocument.and.drawing.paragraphs"
      content-label="paragraph"
      :items="paragraphsItems"
      :current-page="paragraphsCurrentPage"
      :total-pages="paragraphsTotalPages"
      :is-loading="paragraphsLoading"
      @page-change="handlePageChange('paragraphs', $event)" />

    <dp-report-group
      v-if="hasPermission('feature_procedure_report_drawings')"
      group="drawings"
      group-label="plandocument.and.drawing.drawings"
      content-label="drawing"
      :items="drawingsItems"
      :current-page="drawingsCurrentPage"
      :total-pages="drawingsTotalPages"
      :is-loading="drawingsLoading"
      @page-change="handlePageChange('drawings', $event)" />
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import { DpLoading } from '@demos-europe/demosplan-ui'
import DpReportGroup from './DpReportGroup'
import { scrollTo } from 'vue-scrollto'

export default {
  name: 'DpReportListing',

  components: {
    DpLoading,
    DpReportGroup
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      isFirstLoad: true
    }
  },

  computed: {
    ...mapState('report/general', {
      generalItems: 'items',
      generalCurrentPage: 'currentPage',
      generalTotalPages: 'totalPages',
      generalLoading: 'loading'
    }),
    ...mapState('report/publicPhase', {
      publicPhaseItems: 'items',
      publicPhaseCurrentPage: 'currentPage',
      publicPhaseTotalPages: 'totalPages',
      publicPhaseLoading: 'loading'
    }),
    ...mapState('report/invitations', {
      invitationsItems: 'items',
      invitationsCurrentPage: 'currentPage',
      invitationsTotalPages: 'totalPages',
      invitationsLoading: 'loading'
    }),
    ...mapState('report/registerInvitations', {
      registerInvitationsItems: 'items',
      registerInvitationsCurrentPage: 'currentPage',
      registerInvitationsTotalPages: 'totalPages',
      registerInvitationsLoading: 'loading'
    }),
    ...mapState('report/finalMails', {
      finalMailsItems: 'items',
      finalMailsCurrentPage: 'currentPage',
      finalMailsTotalPages: 'totalPages',
      finalMailsLoading: 'loading'
    }),
    ...mapState('report/statements', {
      statementsItems: 'items',
      statementsCurrentPage: 'currentPage',
      statementsTotalPages: 'totalPages',
      statementsLoading: 'loading'
    }),
    ...mapState('report/elements', {
      elementsItems: 'items',
      elementsCurrentPage: 'currentPage',
      elementsTotalPages: 'totalPages',
      elementsLoading: 'loading'
    }),
    ...mapState('report/singleDocuments', {
      singleDocumentsItems: 'items',
      singleDocumentsCurrentPage: 'currentPage',
      singleDocumentsTotalPages: 'totalPages',
      singleDocumentsLoading: 'loading'
    }),
    ...mapState('report/paragraphs', {
      paragraphsItems: 'items',
      paragraphsCurrentPage: 'currentPage',
      paragraphsTotalPages: 'totalPages',
      paragraphsLoading: 'loading'
    }),
    ...mapState('report/drawings', {
      drawingsItems: 'items',
      drawingsCurrentPage: 'currentPage',
      drawingsTotalPages: 'totalPages',
      drawingsLoading: 'loading'
    })
  },

  methods: {
    ...mapActions({
      listGeneral: 'report/general/list',
      listPublicPhase: 'report/publicPhase/list',
      listInvitations: 'report/invitations/list',
      listRegisterInvitations: 'report/registerInvitations/list',
      listFinalMails: 'report/finalMails/list',
      listStatements: 'report/statements/list',
      listElements: 'report/elements/list',
      listSingleDocuments: 'report/singleDocuments/list',
      listParagraphs: 'report/paragraphs/list',
      listDrawings: 'report/drawings/list'
    }),

    handlePageChange (group, page) {
      // Convert snail to camel and prepend list
      const actionName = 'list' + group.charAt(0).toUpperCase() + group.slice(1).replace(/_([a-z])/g, (match, p1) => p1.toUpperCase())
      return this[actionName]({
        procedureId: this.procedureId,
        page: {
          number: page
        }
      })
        .then(() => {
          if (!this.isFirstLoad) {
            scrollTo('#report__' + group)
          }
        })
    }
  },

  mounted () {
    Promise.all([
      'general',
      'public_phase',
      'invitations',
      'register_invitations',
      'final_mails',
      'statements',
      'elements',
      'single_documents',
      'paragraphs',
      'drawings'
    ]
      .filter(groupName => {
        /*
         * This returns one of those permissions:
         * - feature_procedure_report_general
         * - feature_procedure_report_public_phase
         * - feature_procedure_report_register_invitations
         * - feature_procedure_report_final_mails
         * - feature_procedure_report_statements
         * - feature_procedure_report_elements
         * - feature_procedure_report_single_documents
         * - feature_procedure_report_paragraphs
         * - feature_procedure_report_drawings
         */
        return hasPermission('feature_procedure_report_' + groupName)
      })
      .map(groupName => {
        return this.handlePageChange(groupName, 1)
      })).then(() => { this.isFirstLoad = false })
  }
}
</script>
