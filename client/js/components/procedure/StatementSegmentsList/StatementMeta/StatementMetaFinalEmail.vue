<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset data-dp-validate="statementFinalEmail">
    <legend
      id="finalEmail"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('statement.final.send') }}
    </legend>
    <dp-inline-notification
      v-if="explanationNoSendingEmail"
      class="mb-3"
      :message="explanationNoSendingEmail"
      type="info" />
    <template v-else>
      <dp-inline-notification
        v-if="finalEmailOnlyToVoters"
        class="mb-3"
        :message="Translator.trans('explanation.statement.final.sent.only.voters')"
        type="info" />
      <dp-inline-notification
        v-if="statement.attributes.sentAssessment"
        class="mb-3"
        :message="Translator.trans('confirm.statement.final.sent.date', { date: formattedSentAssessmentDate })"
        type="info" />
      <dp-inline-notification
        v-else
        class="mb-3"
        :message="Translator.trans('confirm.statement.final.not.sent')"
        type="info" />
      <dp-input
        v-if="hasPermission('field_organisation_email2_cc')"
        id="email2"
        class="mb-2"
        data-cy="statementFinalEmail:email2"
        :label="{
          text: Translator.trans('email.recipient')
        }"
        read-only
        :value="email2InputValue" />
      <dp-input
        v-if="ccEmail2"
        id="email2cc"
        class="mb-2"
        data-cy="statementFinalEmail:email2cc"
        :label="{
          text: Translator.trans('recipients.additional')
        }"
        read-only
        :value="ccEmail2" />
      <dp-input
        id="emailCC"
        v-model="emailsCC"
        class="mb-2"
        data-cy="statementFinalEmail:emailCC"
        :disabled="!editable"
        :label="{
          text: Translator.trans('email.cc'),
          hint: Translator.trans('explanation.email.cc')
        }" />
      <dp-input
        id="emailSubject"
        v-model="emailSubject"
        class="mb-2"
        data-cy="statementFinalEmail:emailSubject"
        :disabled="!editable"
        :label="{
          text: Translator.trans('subject')
        }" />
      <detail-view-final-email-body
        ref="emailBody"
        class="u-mb-0_5"
        data-cy="statementDetail:emailBodyText"
        :editable="editable"
        :init-text="emailDefaultText"
        :procedure-id="procedure.id"
        @emailBody:input="updateEmailBodyText" />
      <template v-if="editable">
        <dp-label
          :text="Translator.trans('documents.attach')"
          for="uploadEmailAttachments" />
        <dp-upload-files
          id="uploadEmailAttachments"
          ref="uploadEmailAttachments"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          :max-file-size="10 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="20"
          name="uploadEmailAttachments"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '10GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="removeAttachment"
          @upload-success="addAttachment" />
      </template>
      <div class="text-right">
        <dp-button
          data-cy="statementMeta:sendFinalEmail"
          :disabled="!editable"
          icon="mail"
          name="sendFinalEmail"
          :text="Translator.trans('send')"
          @click="sendFinalEmail" />
      </div>
    </template>
  </fieldset>
</template>
<script>
import { checkResponse, DpButton, DpInlineNotification, DpInput, DpLabel, dpRpc, DpUploadFiles, formatDate } from '@demos-europe/demosplan-ui'
import DetailViewFinalEmailBody from '@DpJs/components/statement/assessmentTable/DetailView/DetailViewFinalEmailBody'
import { mapState } from 'vuex'

export default {
  name: 'StatementMetaFinalEmail',

  components: {
    DetailViewFinalEmailBody,
    DpButton,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpUploadFiles
  },

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    procedure: {
      type: Object,
      required: true
    },

    statement: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      ccEmail2: '',
      email2: '',
      emailAttachments: [],
      emailBodyText: '',
      emailDefaultText: '',
      emailSubject: Translator.trans('statement.final.email.subject', { procedureName: this.procedure.name }),
      emailsCC: '',
      finalEmailOnlyToVoters: false,
      formattedSentAssessmentDate: '',
      statementUser: null,
      statementUserOrga: null
    }
  },

  computed: {
    ...mapState('Orga', {
      orgas: 'items'
    }),

    ...mapState('User', {
      users: 'items'
    }),

    email2InputValue () {
      if (this.statement.attributes.publicStatement === 'external') {
        return Translator.trans('explanation.statement.final.citizen.email.hidden')
      } else {
        return this.email2
      }
    },

    explanationNoSendingEmail () {
      const { authorFeedback, feedback, publicStatement } = this.statement.attributes
      let sendFinalEmail = false

      if ((feedback === 'snailmail' && this.statement.relationships.votes.data) ||
          feedback === 'email') {
        sendFinalEmail = true
      }

      if (!sendFinalEmail) {
        return Translator.trans('explanation.no.statement.final.sent')
      }

      if (sendFinalEmail && publicStatement === 'external' && !authorFeedback) {
        return Translator.trans('explanation.no.statement.final.no.feedback.wanted')
      }

      if (!this.email2 && publicStatement !== 'external' && authorFeedback) {
        return Translator.trans('explanation.no.statement.final.no.email')
      }

      return ''
    }
  },

  methods: {
    addAttachment (file) {
      this.emailAttachments.push(file)
    },

    formatAttachments () {
      return this.emailAttachments.map(file => `${file.name}:${file.fileId}:${file.type}`)
    },

    formatSentAssessmentDate () {
      this.formattedSentAssessmentDate = formatDate(this.statement.attributes.sentAssessmentDate, 'DD.MM.YYYY | HH:mm')
    },

    initValues () {
      this.formatSentAssessmentDate()
      this.setCCEmail2()
      this.setEmail2()
      this.setEmailDefaultText()
      this.setFinalEmailOnlyToVoters()
      this.setStatementUser()
      this.setStatementUserOrga()
      this.setDefaultEmailSubject()
    },

    removeAttachment (file) {
      this.emailAttachments = this.emailAttachments.filter(attachment => attachment.fileId !== file.fileId)
    },

    resetEmailData () {
      this.emailAttachments = []
      this.setEmailDefaultText()
      this.emailsCC = ''
      this.setDefaultEmailSubject()
      this.$refs.emailBody.resetText()
      this.$refs.uploadEmailAttachments.clearFilesList()
    },

    sendFinalEmail () {
      let sentToTransKey = ''

      if (!this.statement.attributes.isSubmittedByCitizen) {
        sentToTransKey = 'check.mail.result.institutions'
      } else if (hasPermission('feature_statements_vote') && this.statement.relationships?.votes) {
        sentToTransKey = 'check.mail.result.citizenAndVoters'
      } else {
        sentToTransKey = 'check.mail.result.citizen'
      }

      if (dpconfirm(Translator.trans('check.mail.result', { sentTo: Translator.trans(sentToTransKey) }))) {
        const formattedAttachments = this.formatAttachments()
        const params = {
          statementId: this.statement.id,
          subject: this.emailSubject,
          body: this.emailBodyText,
          sendEmailCC: this.emailsCC,
          emailAttachments: formattedAttachments
        }
        dpRpc('statement.email.sender', params, this.procedure.id)
          .then(checkResponse)
          .then(() => {
            this.resetEmailData()
          })
      }
    },

    setCCEmail2 () {
      if (this.statementUserOrga) {
        this.ccEmail2 = this.statementUserOrga.attributes.ccEmail2
      }
    },

    setDefaultEmailSubject () {
      this.emailSubject = Translator.trans('statement.final.email.subject', { procedureName: this.procedure.name })
    },

    setEmail2 () {
      const { publicStatement, initialOrganisationEmail } = this.statement.attributes

      if ((this.statementUserOrga && publicStatement === 'external' && initialOrganisationEmail) ||
        (!this.statementUserOrga && initialOrganisationEmail)) {
        this.email2 = initialOrganisationEmail
      } else if (this.statementUserOrga && this.statement.attributes.publicStatement === 'external' && !initialOrganisationEmail) {
        this.email2 = this.statementUserOrga.attributes.email2
      }
    },

    setEmailDefaultText  () {
      this.emailDefaultText = Translator.trans('statement.send.final_mail.default', {
        hasStatementText: this.statement.attributes.fullText.length < 2000,
        orgaName: this.procedure.orgaName,
        procedureName: this.procedure.name,
        statementText: this.statement.attributes.fullText,
        statementRecommendation: this.statement.attributes.recommendation
      })
    },

    setFinalEmailOnlyToVoters () {
      if (this.statement.attributes.feedback === 'snailmail' && this.statement.relationships.votes.data) {
        this.finalEmailOnlyToVoters = true
      }
    },

    setStatementUser () {
      if (this.statement.relationships?.user?.data?.id && this.users[this.statement.relationships.user.data.id]) {
        this.statementUser = this.users[this.statement.relationships.user.data.id]
      }
    },

    setStatementUserOrga () {
      if (this.statementUser?.relationships?.orga?.data?.id) {
        this.statementUserOrga = this.orgas[this.statementUser.relationships.orga.data.id]
      }
    },

    updateEmailBodyText (text) {
      this.emailBodyText = text
    }
  },

  mounted () {
    this.initValues()
  }
}
</script>
