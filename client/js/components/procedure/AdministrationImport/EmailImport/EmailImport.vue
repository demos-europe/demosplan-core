<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-loading v-if="isLoading" />
    <template v-else>
      <template v-if="isInbox">
        <email-import-hint
          :allowed-email-addresses="allowedEmailAddresses"
          :import-email-address="importEmailAddress" />

        <dp-inline-notification
          v-if="importEmailAddress"
          dismissible
          :dismissible-key="lsKeyNoAttachments"
          :message="Translator.trans('statement.import_email.no_attachments_hint')"
          type="warning" />

        <dp-data-table
          v-if="items.length && importEmailAddress"
          has-flyout
          :header-fields="headerFields"
          is-expandable
          :items="items"
          track-by="id">
          <template v-slot:subject="{ subject }">
            <div class="o-hellip__wrapper">
              <div
                v-text="subject"
                class="o-hellip--nowrap" />
            </div>
          </template>
          <template v-slot:from="{ from }">
            <span
              v-text="from"
              class="whitespace--nowrap" />
          </template>
          <template v-slot:date="{ date }">
            <email-import-date :date="date" />
          </template>
          <template v-slot:status="{ statementId }">
            <email-import-status-badge :imported="statementId !== null" />
          </template>
          <template v-slot:flyout="{ statementId, id }">
            <div class="text--right">
              <button
                v-if="statementId === null"
                v-text="Translator.trans('import.verb')"
                v-tooltip="Translator.trans('statement.import_email.text')"
                class="btn--blank o-link--default"
                @click.prevent="importEmailId(id)" />
              <a
                v-else
                v-text="Translator.trans('statement')"
                v-tooltip="Translator.trans('statement.edit')"
                :href="Routing.generate('dplan_statement_segments_list',{ statementId: statementId, procedureId: procedureId })" />
            </div>
          </template>
          <template v-slot:expandedContent="{ text }">
            <span v-cleanhtml="{ content: text, options: cleanHtmlOptions }" />
          </template>
        </dp-data-table>

        <dp-inline-notification
          v-else-if="items.length === 0 && importEmailAddress"
          :message="Translator.trans('items.none.currently')"
          type="info" />
      </template>

      <template v-if="emailToImport !== null && !renderInbox">
        <div class="u-mb-0_5">
          <button
            @click.prevent="renderEmailList"
            class="btn--blank o-link--default"
            v-text="Translator.trans('back.to.import.email')" />
        </div>
      </template>

      <dp-inline-notification
        v-if="!renderInbox"
        type="confirm"
        :message="Translator.trans('email.import.statement.info')" />

      <dp-simplified-new-statement-form
        v-if="!renderInbox"
        :allow-file-upload="true"
        :expand-all="true"
        :init-values="emailData"
        :newest-intern-id="newestInternId"
        :procedure-id="procedureId"
        :statement-import-email-id="emailToImport"
        :submit-type-options="submitTypeOptions"
        :used-intern-ids="usedInternIds" />
    </template>
  </div>
</template>

<script>
import { CleanHtml, dpApi, DpDataTable, DpInlineNotification, DpLoading } from '@demos-europe/demosplan-ui'
import DpSimplifiedNewStatementForm from '@DpJs/components/procedure/DpSimplifiedNewStatementForm'
import EmailImportDate from './EmailImportDate'
import EmailImportHint from './EmailImportHint'
import EmailImportStatusBadge from './EmailImportStatusBadge'

/*
 * Html emails may come with strange content. That is why the `v-cleanhtml` directive is configured
 * to limit the allowed tags to just those we tend to allow within the statement text.
 */
const cleanHtmlOptions = {
  ALLOWED_TAGS: [
    'a',
    'abbr',
    'b',
    'br',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'i',
    'img',
    'ins',
    'li',
    'mark',
    'ol',
    'p',
    's',
    'span',
    'strike',
    'strong',
    'sup',
    'table',
    'td',
    'th',
    'thead',
    'tr',
    'u',
    'ul'
  ]
}

export default {
  name: 'EmailImport',

  inject: ['currentUserId', 'newestInternId', 'procedureId', 'submitTypeOptions', 'usedInternIds'],

  components: {
    DpDataTable,
    DpInlineNotification,
    DpLoading,
    DpSimplifiedNewStatementForm,
    EmailImportDate,
    EmailImportHint,
    EmailImportStatusBadge
  },

  directives: {
    cleanhtml: CleanHtml
  },

  data () {
    return {
      allowedEmailAddresses: [],
      cleanHtmlOptions: cleanHtmlOptions,
      headerFields: [
        { field: 'subject', label: Translator.trans('subject') },
        { field: 'from', label: Translator.trans('email.from') },
        { field: 'date', label: Translator.trans('date') },
        { field: 'status', label: Translator.trans('status') }
      ],
      importEmailAddress: null,
      items: [],
      lsKeyNoAttachments: `${this.currentUserId}:emailImportNoAttachmentsHint`,
      step: 'loading',
      isLoading: true,
      isInbox: true,
      emailToImport: null
    }
  },

  computed: {
    renderInbox () {
      return this.isInbox
    },

    emailData () {
      const mail = this.items.find(item => item.id === this.emailToImport)
      return {
        submittedDate: mail ? mail.date : '',
        text: mail ? mail.text : ''
      }
    }
  },

  methods: {
    fetchImportEmailAddresses () {
      const url = Routing.generate('api_resource_list', { resourceType: 'MaillaneConnection' })
      const params = {
        filter: {
          procedureFilter: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        fields: {
          MaillaneConnection: [
            'allowedSenderEmailAddresses',
            'recipientEmailAddress'
          ].join()
        }
      }

      return dpApi.get(url, params, { serialize: true })
        .then(response => {
          this.allowedEmailAddresses = response.data?.data[0]?.attributes.allowedSenderEmailAddresses ?? []
          this.importEmailAddress = response.data?.data[0]?.attributes.recipientEmailAddress ?? null
        })
        .catch((e) => {
          console.error(e)
        })
    },

    fetchStatementImportEmails () {
      const emailRoute = Routing.generate('api_resource_list', { resourceType: 'StatementImportEmail' })
      const params = {
        include: [
          'forwardingUser',
          'createdStatements',
          'createdStatements.statements'
        ].join(),
        fields: {
          StatementImportEmail: [
            'creationDate',
            'subject',
            'from',
            'htmlTextContent',
            'plainTextContent',
            'forwardingUser',
            'createdStatements'
          ].join(),
          User: [
            'firstname',
            'lastname'
          ].join(),
          OriginalStatement: [
            'id',
            'statements'
          ].join(),
          Statement: [
            'id'
          ].join()
        }
      }

      return dpApi.get(emailRoute, params, { serialize: true })
        .then(({ data }) => {
          const items = data.data
            .map(item => {
              return this.flattenItem(item, data.included)
            })
            .sort((a, b) => {
              if (!!a.statementId === !!b.statementId) {
                return a.date < b.date ? 1 : -1
              } else {
                return Number(!!a.statementId) - Number(!!b.statementId)
              }
            })

          this.items = Object.freeze(items)
        })
        .catch((e) => {
          console.error(e)
        })
    },

    fetchResources () {
      Promise.all([this.fetchStatementImportEmails(), this.fetchImportEmailAddresses()])
        .then(() => {
          this.isLoading = false
        })
    },

    flattenItem ({ attributes, id, relationships }, included) {
      let forwardingUser = null
      if (relationships.forwardingUser.data) {
        forwardingUser = included.find(user => user.id === relationships.forwardingUser.data.id)
      }

      const statementId = this.getStatementId(relationships.createdStatements.data, included)

      // Show either the original `From` string or the name of the matching user
      let from = attributes.from
      if (forwardingUser && forwardingUser.attributes) {
        from = `${forwardingUser.attributes.firstname} ${forwardingUser.attributes.lastname}`
      }

      let text = attributes.plainTextContent
      if (attributes.htmlTextContent) {
        text = attributes.htmlTextContent
      }

      return {
        id: id,
        subject: attributes.subject,
        from: from,
        date: attributes.creationDate,
        statementId: statementId,
        text: text
      }
    },

    getStatementId (createdStatements, included) {
      if (createdStatements.length === 0) {
        return null
      }
      const createdStatement = included.find(statement => statement.id === createdStatements[0].id)

      return createdStatement.relationships.statements.data[0].id
    },

    importEmailId (id) {
      this.emailToImport = id
      this.isInbox = false
    },

    renderEmailList () {
      this.isInbox = true
      this.emailToImport = null
    }
  },

  mounted () {
    this.fetchResources()
  }
}
</script>
