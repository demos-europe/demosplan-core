<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div v-if="!hasTabs">
    <dp-inline-notification
      v-if="transformedStatements.length === 0"
      :message="Translator.trans('statement.list.empty')"
      type="info" />
    <div class="space-stack-m">
      <dp-public-statement
        v-for="(statement, idx) in transformedStatements"
        v-bind="statement"
        :key="idx"
        :menu-items-generator="menuItemCallback"
        :procedure-id="procedureId"
        :show-author="showAuthor"
        :show-checkbox="showCheckbox"
        @open-map-modal="openMapModal"
        @open-statement-modal-from-list="(id) => $parent.$emit('open-statement-modal-from-list', id)"/>
      <dp-map-modal
        ref="mapModal"
        :procedure-id="procedureId" />
    </div>
  </div>
  <dp-tabs
    v-else
    :active-id="activeTabId"
    @change="id => activeTabId = id">
    <slot>
      <dp-tab
        id="publicStatements"
        :is-active="activeTabId === 'publicStatements'"
        :label="Translator.trans('statements.draft.organisation')">
        <div class="space-stack-m pt-2">
          <dp-inline-notification
            v-if="hasPublicStatements"
            :message="Translator.trans('statement.list.empty')"
            type="info" />
          <dp-public-statement
            v-for="(statement, idx) in publicStatements"
            v-bind="statement"
            :key="idx"
            :menu-items-generator="menuItemCallback"
            :procedure-id="procedureId"
            :show-author="showAuthor"
            :show-checkbox="showCheckbox"
            @open-map-modal="openMapModal"
            @open-statement-modal-from-list="(id) => $parent.$emit('open-statement-modal-from-list', id)" />
          <dp-map-modal
            ref="mapModal"
            :procedure-id="procedureId" />
        </div>
      </dp-tab>
      <dp-tab
        id="privateStatements"
        :is-active="activeTabId === 'privateStatements'"
        :label="Translator.trans('statements.draft')">
        <div class="space-stack-m pt-2">
          <dp-inline-notification
            v-if="hasNoPublicStatements"
            :message="Translator.trans('statement.list.empty')"
            type="info" />
          <dp-public-statement
            v-for="(statement, idx) in privateStatements"
            v-bind="statement"
            :key="'authorOnly-' + idx"
            :menu-items-generator="menuItemCallback"
            :procedure-id="procedureId"
            :show-author="showAuthor"
            :show-checkbox="showCheckbox"
            @open-map-modal="openMapModal"
            @open-statement-modal-from-list="(id) => $parent.$emit('open-statement-modal-from-list', id)" />
        </div>
      </dp-tab>
    </slot>
  </dp-tabs>
</template>

<script>
import { DpInlineNotification, DpTab, DpTabs, dpSelectAllMixin, formatDate, getFileInfo } from '@demos-europe/demosplan-ui'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicStatement from './DpPublicStatement'
import { generateMenuItems } from './menuItems'

const editPermissions = {
  draft: 'feature_statements_draft_edit',
  released: 'feature_statements_released_edit',
  released_group: 'feature_statements_released_group_edit',
  final_group: 'feature_statements_final_group_edit'
}

const deletePermissions = {
  draft: 'feature_statements_draft_delete',
  released: 'feature_statements_released_delete',
  released_group: 'feature_statements_released_group_delete',
  final_group: 'feature_statements_final_group_delete'
}

const emailPermissions = {
  draft: 'feature_statements_draft_email',
  released: 'feature_statements_released_email',
  released_group: 'feature_statements_released_group_email',
  final_group: 'feature_statements_final_email' // Feature name is not the same as target name
}

export default {
  name: 'DpPublicStatementList',

  components: {
    DpInlineNotification,
    DpMapModal,
    DpPublicStatement,
    DpTabs,
    DpTab
  },

  mixins: [dpSelectAllMixin],

  props: {
    counties: {
      type: Array,
      required: false,
      default: () => ([])
    },

    hasTabs: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    showAuthor: {
      type: Boolean,
      required: false,
      default: false

    },

    showCheckbox: {
      type: Boolean,
      required: false,
      default: false
    },

    showDelete: {
      type: Boolean,
      required: false,
      default: false
    },

    showEdit: {
      type: Boolean,
      required: false,
      default: false
    },

    showEmail: {
      type: Boolean,
      required: false,
      default: false
    },

    showPdfDownload: {
      type: Boolean,
      default: false
    },

    showPublish: {
      type: Boolean,
      required: false,
      default: false
    },

    showReject: {
      type: Boolean,
      required: false,
      default: false
    },

    showVersions: {
      type: Boolean,
      required: false,
      default: false
    },

    statements: {
      type: Array,
      required: true
    },

    target: {
      type: String,
      required: true
    }
  },

  emits: [
    'open-statement-modal-from-list'
  ],

  data () {
    return {
      transformedStatements: this.transformStatements(this.statements),
      activeTabId: 'publicStatements'
    }
  },

  computed: {
    actionFields () {
      const fields = []
      if (hasPermission(editPermissions[this.target]) && this.showEdit) {
        fields.push('edit')
      }

      if (hasPermission(deletePermissions[this.target]) && this.showDelete) {
        fields.push('delete')
      }

      if (hasPermission('feature_statements_released_group_reject') && this.showReject) {
        fields.push('reject')
      }

      if (hasPermission(emailPermissions[this.target]) && this.showEmail) {
        fields.push('email')
      }

      if (hasPermission('feature_statements_public') && this.showPublish) {
        fields.push('publish')
      }

      if (hasPermission('feature_statements_draft_versions') && this.showVersions) {
        fields.push('versions')
      }

      if (this.showPdfDownload) {
        fields.push('pdf')
      }

      return fields
    },

    hasNoPublicStatements () {
      return this.transformedStatements.filter(statement => statement.authorOnly).length === 0;
    },

    hasPublicStatements () {
      return this.transformedStatements.filter(statement => !statement.authorOnly).length === 0;
    },

    menuItemCallback () {
      return (id, elementId, paragraphId, isPublished) => generateMenuItems({
        fields: this.actionFields,
        target: this.target,
        procedureId: this.procedureId,
        id,
        elementId,
        paragraphId,
        isPublished
      })
    },

    publicStatements () {
      return this.transformedStatements.filter(statement => !statement.authorOnly);
    },

    privateStatements () {
      return this.transformedStatements.filter(statement => statement.authorOnly);
    }
  },

  methods: {
    openMapModal (polygon) {
      this.$refs.mapModal.toggleModal(polygon)
    },

    transformStatement (statement) {
      const {
        authorOnly,
        document,
        element,
        externId,
        files,
        ident,
        paragraph,
        statementAttributes,
        number,
        uName,
        dName,
        oName,
        phase,
        polygon,
        elementId,
        paragraphId,
        showToAll,
        submitted,
        rejectedReason
      } = statement

      // Depending on `votedStatement` or `own Statement`, we receive one or the other from the Backend
      const submittedDate = statement.submittedDate || statement.submit
      const createdDate = statement.createdDate || statement.created

      const attachments = files.map(f => getFileInfo(f))

      let county = {}
      if (hasPermission('field_statement_county')) {
        county = statementAttributes.county && this.counties.find(c => c.value === statementAttributes.county)
        county = { county: (county && county.label) || Translator.trans('notspecified') }
      }

      let priorityAreas = {}
      if (dplan.procedureStatementPriorityArea) {
        priorityAreas = { priorityAreas: statementAttributes.priorityAreaKey ? statementAttributes.priorityAreaKey : Translator.trans('notspecified') }
      }

      let statementDocument = element?.title || ''
      if (document?.title) {
        statementDocument += ` / ${document.title}`
      }

      const statementParagraph = (paragraph && paragraph.title) || Translator.trans('notspecified')
      const text = statement.text

      const transformedSubmitDate = submitted === false ? {} : { submittedDate: formatDate(submittedDate, 'DD.MM.YYYY HH:mm') }
      const transformedCreatedDate = formatDate(createdDate, 'DD.MM.YYYY HH:mm')

      const transformedPolygon = polygon === '' ? {} : JSON.parse(polygon)

      return {
        authorOnly,
        ...county,
        attachments,
        createdDate: transformedCreatedDate,
        department: dName,
        document: statementDocument,
        id: ident,
        externId,
        organisation: oName,
        paragraph: statementParagraph,
        phase,
        polygon: transformedPolygon,
        ...priorityAreas,
        ...transformedSubmitDate,
        text,
        number,
        user: uName,
        elementId,
        paragraphId,
        isPublished: showToAll,
        rejectedReason
      }
    },

    transformStatements (statements) {
      return statements.map(s => this.transformStatement(s))
    }
  }
}
</script>
