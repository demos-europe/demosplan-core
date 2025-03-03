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
      :message="explanationNoSendingEmail"
      type="info" />
    <template v-else>
      <dp-inline-notification
        v-if="finalEmailOnlyToVoters"
        :message="Translator.trans('explanation.statement.final.sent.only.voters')"
        type="info" />
      <dp-inline-notification
        v-if="statement.attributes.sentAssessment"
        :message="Translator.trans('confirm.statement.final.sent.date', { date: formatedSentAssessmentDate })"
        type="info" />
      <dp-inline-notification
        v-else
        :message="Translator.trans('confirm.statement.final.not.sent')"
        type="info" />
      <dp-input
        v-if="hasPermission('field_organisation_email2_cc')"
        id="email2"
        :label="{
          text: Translator.trans('email.recipient')
        }"
        read-only
        :value="email2" />
      <dp-input
        v-if="ccEmail2"
        id="email2cc"
        :label="{
          text: Translator.trans('recipients.additional')
        }"
        read-only
        :value="ccEmail2" />
      <dp-input
        id="emailCC"
        v-model="emailsCC"
        :disabled="!editable"
        :label="{
          text: Translator.trans('email.cc'),
          hint: Translator.trans('explanation.email.cc')
        }" />
      <dp-input
        id="emailSubject"
        v-model="emailSubject"
        :disabled="!editable"
        :label="{
          text: Translator.trans('subject')
        }" />
      <detail-view-final-email-body
        class="u-mb-0_5"
        data-cy="statementDetail:emailBodyText"
        :init-text="initTextEmailBody"
        :procedure-id="procedure.id">
      </detail-view-final-email-body>
    </template>
  </fieldset>
</template>
<script>
import { DpInlineNotification, DpInput, formatDate } from '@demos-europe/demosplan-ui'
import DetailViewFinalEmailBody from '@DpJs/components/statement/assessmentTable/DetailView/DetailViewFinalEmailBody'
export default {
  name: 'StatementMetaFinalEmail',

  components: {
    DetailViewFinalEmailBody,
    DpInlineNotification,
    DpInput
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
      emailSubject: Translator.trans('statement.final.email.subject', { procedureName: this.procedure.name }),
      emailsCC: '',
      initTextEmailBody: ''
    }
  },

  computed: {
    email2 () {
      const email2 = ''
      if (this.statement.attributes.publicStatement === 'external') {

        return Translator.trans('explanation.statement.final.citizen.email.hidden')
      } else {

        return email2
      }
    },

    explanationNoSendingEmail () {
      let sendFinalEmail = false
      const authorFeedback = false
      if ((this.statement.attributes.feedback === 'snailmail' && this.statement.relationships.votes.data) ||
          this.statement.attributes.feedback === 'email') {
        sendFinalEmail = true
      }
      const statementExternal = false // Konstante, muss über twig kommen
      const email2 = false

      if (!sendFinalEmail) {

        return Translator.trans('explanation.no.statement.final.sent')
      } else if (statementExternal && !authorFeedback) {

        return Translator.trans('explanation.no.statement.final.no.feedback.wanted')
      } else if (!email2) {

        return Translator.trans('explanation.no.statement.final.no.email')
      }

      return ''
    },

    finalEmailOnlyToVoters () {
      let finalEmailOnlyToVoters = false
      if (this.statement.attributes.feedback === 'snailmail' && this.statement.relationships.votes.data) {
        finalEmailOnlyToVoters = true
      }

      return finalEmailOnlyToVoters
    },

    formatedSentAssessmentDate () {
      return formatDate(this.statement.attributes.sentAssessmentDate, 'DD.MM.YYYY HH:mm')
    }
  }
}
</script>
