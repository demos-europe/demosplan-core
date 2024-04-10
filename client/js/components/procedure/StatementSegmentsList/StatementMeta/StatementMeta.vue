<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-statement-meta-box u-mb-0_5">
    <div class="relative border--bottom u-pb-0_5">
      {{ Translator.trans(editable ? 'statement.info.edit' : 'statement.info') }}
      <button
        class="btn--blank o-link--default float-right"
        @click="close">
        <dp-icon icon="close" />
      </button>
    </div>

    <div
      class="u-mt-0_5 flex gap-2"
      data-dp-validate="statementMetaData">
      <div class="inline-block w-1/2 align-top">
        <dp-input
          id="statementSubmitter"
          v-model="localStatement.attributes.authorName"
          class="u-mb-0_5"
          :disabled="statement.isManual ? false : !editable"
          :label="{
            text: Translator.trans('submitter')
          }"
          @input="(val) => emitInput('authorName', val)" />

        <dp-input
          id="statementEmailAddress"
          v-model="localStatement.attributes.submitterEmailAddress"
          class="u-mb-0_5"
          :disabled="statement.isManual ? false : !editable"
          :label="{
            text: Translator.trans('email')
          }"
          type="email"
          @input="(val) => emitInput('submitterEmailAddress', val)" />
        <dp-input
          v-if="!localStatement.attributes.isSubmittedByCitizen"
          id="statementOrgaName"
          v-model="localStatement.attributes.initialOrganisationName"
          class="u-mb-0_5"
          :disabled="statement.isManual ? false : !editable"
          :label="{
            text: Translator.trans('organisation')
          }"
          @input="(val) => emitInput('initialOrganisationName', val)" />
        <dp-input
          v-if="hasPermission('field_statement_meta_orga_department_name') && !localStatement.attributes.isSubmittedByCitizen"
          id="statementDepartmentName"
          v-model="localStatement.attributes.initialOrganisationDepartmentName"
          class="u-mb-0_5"
          :disabled="statement.isManual ? false : !editable"
          :label="{
            text: Translator.trans('department')
          }"
          @input="(val) => emitInput('initialOrganisationDepartmentName', val)" />
        <div class="o-form__group u-mb-0_5">
          <dp-input
            id="statementStreet"
            v-model="localStatement.attributes.initialOrganisationStreet"
            class="o-form__group-item"
            :disabled="statement.isManual ? false : !editable"
            :label="{
              text: Translator.trans('street')
            }"
            @input="(val) => emitInput('initialOrganisationStreet', val)" />
          <dp-input
            id="statementHouseNumber"
            v-model="localStatement.attributes.initialOrganisationHouseNumber"
            class="o-form__group-item shrink"
            :disabled="statement.isManual ? false : !editable"
            :label="{
              text: Translator.trans('street.number.short')
            }"
            :size="3"
            @input="(val) => emitInput('initialOrganisationHouseNumber', val)" />
        </div>
        <div class="o-form__group u-mb-0_5">
          <dp-input
            id="statementPostalCode"
            v-model="localStatement.attributes.initialOrganisationPostalCode"
            class="o-form__group-item shrink"
            :disabled="statement.isManual ? false : !editable"
            :label="{
              text: Translator.trans('postalcode')
            }"
            pattern="^[0-9]{4,5}$"
            :size="5"
            @input="(val) => emitInput('initialOrganisationPostalCode', val)" />
          <dp-input
            id="statementCity"
            v-model="localStatement.attributes.initialOrganisationCity"
            class="o-form__group-item"
            :disabled="statement.isManual ? false : !editable"
            :label="{
              text: Translator.trans('city')
            }"
            @input="(val) => emitInput('initialOrganisationCity', val)" />
        </div>
      </div>
      <div class="inline-block w-1/2">
        <dp-input
          id="statementInternId"
          v-model="localStatement.attributes.internId"
          class="u-mb-0_5"
          :disabled="!editable"
          :label="{
            text: Translator.trans('internId')
          }"
          @input="(val) => emitInput('internId', val)" />

        <div class="o-form__group u-mb-0_5">
          <!-- authoredDate: if manual statement -->
          <dp-input
            v-if="statement.isManual ? true : !editable"
            id="statementAuthoredDate"
            class="o-form__group-item"
            :disabled="true"
            :label="{
              text: Translator.trans('statement.date.authored')
            }"
            :value="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : '-'"
            @input="(val) => emitInput('authoredDate', val)" />

          <!-- authoredDate: if not manual statement -->
          <div
            class="o-form__group-item"
            v-else>
            <dp-label
              :text="Translator.trans('statement.date.authored')"
              for="authoredDateDatepicker" />
            <dp-datepicker
              class="o-form__control-wrapper"
              id="authoredDateDatepicker"
              :value="localStatement.attributes.authoredDate"
              :max-date="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : currentDate"
              @input="(val) => setDate(val, 'authoredDate')" />
          </div>

          <!-- submitDate: if manual statement -->
          <dp-input
            v-if="statement.isManual ? true : !editable"
            id="statementSubmitDate"
            class="o-form__group-item"
            :disabled="true"
            :label="{
              text: Translator.trans('statement.date.submitted')
            }"
            :value="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : '-'"
            @input="(val) => emitInput('submitDate', val)" />

          <!-- submitDate: if not manual statement -->
          <div
            class="o-form__group-item"
            v-else>
            <dp-label
              :text="Translator.trans('statement.date.submitted')"
              for="submitDateDatepicker" />
            <dp-datepicker
              class="o-form__control-wrapper"
              id="submitDateDatepicker"
              :value="convertDate(localStatement.attributes.submitDate)"
              :max-date="currentDate"
              :min-date="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : ''"
              @input="(val) => setDate(val, 'submitDate')" />
          </div>
        </div>

        <dp-select
          id="statementSubmitType"
          v-model="localStatement.attributes.submitType"
          class="u-mb-0_5"
          :disabled="!editable"
          :label="{
            text: Translator.trans('submit.type')
          }"
          :options="submitTypeOptions"
          @select="(val) => emitInput('submitType', val)" />

        <dp-select
          id="statementProcedurePhase"
          v-model="localStatement.attributes.phase"
          class="u-mb-0_5"
          :disabled="!editable"
          :label="{
            text: Translator.trans('procedure.phase')
          }"
          :options="availableInternalPhases"
          @select="(val) => emitInput('submitType', val)" />

        <dp-select
          id="statementProcedurePhase"
          v-model="localStatement.attributes.phase"
          class="u-mb-0_5"
          :disabled="!editable"
          :label="{
            text: Translator.trans('procedure.public.phase')
          }"
          :options="availableExternalPhases"
          @select="(val) => emitInput('submitType', val)" />

        <dp-text-area
          v-if="hasPermission('field_statement_memo')"
          :disabled="!editable"
          id="r_memo"
          :label="Translator.trans('memo')"
          name="r_memo"
          reduced-height
          v-model="localStatement.attributes.memo" />

      </div>
    </div>

    <statement-meta-multiselect
      v-if="hasPermission('field_statement_municipality')"
      :editable="editable"
      :label="Translator.trans('counties')"
      name="counties"
      :options="availableCounties"
      :value="localStatement.attributes.counties"
      @input="updateCounties" />

    <statement-meta-multiselect
      v-if="hasPermission('field_statement_municipality') && formDefinitions.mapAndCountyReference.enabled"
      :editable="editable"
      :label="Translator.trans('municipalities')"
      name="municipalities"
      :options="availableMunicipalities"
      :value="localStatement.attributes.municipalities"
      @input="updateMunicipalities" />

    <statement-meta-multiselect
      v-if="procedureStatementPriorityArea && formDefinitions.mapAndCountyReference.enabled"
      :editable="editable"
      :label="Translator.trans('priorityAreas')"
      name="priorityAreas"
      :options="availablePriorityAreas"
      :value="localStatement.attributes.priorityAreas"
      @input="updatePriorityAreas" />

    <dp-button-row
      v-if="editable"
      class="u-mt-0_5 w-full"
      primary
      secondary
      :secondary-text="Translator.trans('discard.changes')"
      @primary-action="dpValidateAction('statementMetaData', save, false)"
      @secondary-action="reset" />

    <statement-meta-attachments
      :attachments="attachments"
      class="u-pt-0_5 border--bottom u-pb-0_5"
      :editable="editable"
      :procedure-id="procedure.id"
      :statement-id="statement.id"
      @change="(value) => emitInput('attachments', value)" />

    <similar-statement-submitters
      :editable="editable"
      :procedure-id="procedure.id"
      :similar-statement-submitters="similarStatementSubmitters"
      :statement-id="statement.id" />

    <section class="mt-4">
      <dp-accordion
        v-if="hasPermission('field_send_final_email')"
        class="mt-2"
        :title="Translator.trans('statement.final.send')">
        <p v-if="!localStatement.attributes.sendFinalMail">
          {{ Translator.trans('explanation.no.statement.final.sent') }}
        </p>
        <p v-else-if="localStatement.attributes.publicStatement === externalConstant && !localStatement.attributes.authorFeedback">
          {{ Translator.trans('explanation.no.statement.final.no.feedback.wanted') }}
        </p>
        <p v-else-if="localStatement.attributes.email2?.length === 0">
          {{ Translator.trans('explanation.no.statement.final.no.email') }}
        </p>
        <template v-else>
          <p v-if="localStatement.attributes.finalEmailOnlyToVoters">
            {{ Translator.trans('explanation.statement.final.sent.only.voters') }}
          </p>
          <p>
            {{ localStatement.attributes.sentAssessment
              ? Translator.trans('confirm.statement.final.sent.date', { date: dplanDate(localStatement.attributes.sentAssessmentDate, 'd.m.Y | H:i') })
              : Translator.trans('confirm.statement.final.not.sent') }}
          </p>
          <template v-if="hasPermission('field_organisation_email2_cc')">
            <dp-input
              id="email2"
              class="u-mb-0_5"
              disabled
              :label="{
                text: Translator.trans('email.recipient')
              }"
              :value="email2Value" />
            <dp-input
              v-if="localStatement.attributes.ccEmail2"
              id="email2_cc"
              class="u-mb-0_5"
              disabled
              :label="{
                text: Translator.trans('recipients.additional')
              }"
              :value="localStatement.attributes.ccEmail2" />
          </template>
          <dp-input
            id="sendEmailCC"
            class="u-mb-0_5"
            :label="{
              text: Translator.trans('email.cc'),
              hint: Translator.trans('explanation.email.cc')
            }"
            v-model="emailsCC"
            :disabled="!editable" />
          <dp-input
            id="sendTitle"
            class="u-mb-0_5"
            :label="{
              text: Translator.trans('subject'),
            }"
            :value="Translator.trans('statement.final.email.subject', { procedureName: procedure.name })"
            :disabled="!editable" />

          <detail-view-final-email-body
            class="u-mb-0_5"
            :init-text="finalMailDefaultText"
            :procedure-id="procedure.id" />
          <dp-upload-files
            v-if="editable"
            id="uploadEmailAttachments"
            allowed-file-types="all"
            :basic-auth="dplan.settings.basicAuth"
            :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedure.id })"
            :max-file-size="250 * 1024 * 1024 /* 250 MB */"
            :max-number-of-files="20"
            name="uploadEmailAttachments"
            :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '250 MB' }) }"
            :tus-endpoint="dplan.paths.tusEndpoint" />
          <dp-button
            class="u-mt-0_5"
            :text="Translator.trans('send')"
            @click="sendEmail()" />
        </template>
      </dp-accordion>
    </section>
  </div>
</template>

<script>
import {
  DpAccordion,
  DpButton,
  DpButtonRow,
  DpDatepicker,
  DpIcon,
  DpInput,
  DpLabel,
  DpSelect,
  DpTextArea,
  DpUploadFiles,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DetailViewFinalEmailBody from '@DpJs/components/statement/assessmentTable/DetailView/DetailViewFinalEmailBody'
import SimilarStatementSubmitters from '@DpJs/components/procedure/Shared/SimilarStatementSubmitters/SimilarStatementSubmitters'
import StatementMetaAttachments from './StatementMetaAttachments'

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

export default {
  name: 'StatementMeta',

  components: {
    DetailViewFinalEmailBody,
    DpAccordion,
    DpButton,
    DpButtonRow,
    DpDatepicker,
    DpIcon,
    DpInput,
    DpLabel,
    DpSelect,
    DpTextArea,
    DpUploadFiles,
    SimilarStatementSubmitters,
    StatementMetaAttachments
  },

  mixins: [dpValidateMixin],

  props: {
    attachments: {
      type: Object,
      required: true
    },

    availableCounties: {
      type: Array,
      required: false,
      default: () => []
    },

    availableExternalPhases: {
      type: Array,
      required: false,
      default: () => []
    },

    availableInternalPhases: {
      type: Array,
      required: false,
      default: () => []
    },

    availableMunicipalities: {
      type: Array,
      required: false,
      default: () => []
    },

    availablePriorityAreas: {
      type: Array,
      required: false,
      default: () => []
    },

    currentUserId: {
      type: String,
      required: false,
      default: ''
    },

    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    externalConstant: {
      type: String,
      required: true
    },

    procedure: {
      type: Object,
      required: true
    },

    procedureStatementPriorityArea: {
      type: Boolean,
      required: false,
      default: false
    },

    statement: {
      type: Object,
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
      email2Value: '',
      finalMailDefaultText: '',
      localStatement: null
    }
  },

  computed: {
    ...mapState('statement', {
      storageStatement: 'items'
    }),

    currentDate () {
      let today = new Date()
      const dd = today.getDate().toString().padStart(2, '0')
      const mm = (today.getMonth() + 1).toString().padEnd(2, '0') // January is 0
      const yyyy = today.getFullYear()

      today = dd + '.' + mm + '.' + yyyy
      return today
    },

    isCurrentUserAssigned () {
      if (this.storageStatement[this.statement.id].relationships.assignee.data) {
        return this.currentUserId === this.storageStatement[this.statement.id].relationships.assignee.data.id
      }
      return false
    },

    similarStatementSubmitters () {
      if (typeof this.statement.hasRelationship === 'function' && this.statement.hasRelationship('similarStatementSubmitters')) {
        return Object.values(this.statement.relationships.similarStatementSubmitters.list())
      }
      return null
    },

    submitType () {
      if (!this.statement.attributes.submitType) {
        return '-'
      }
      const option = this.submitTypeOptions.find(option => option.value === this.statement.attributes.submitType)
      return option ? Translator.trans(option.label) : ''
    }
  },

  methods: {
    ...mapActions('statement', {
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapMutations('statement', {
      setStatement: 'setItem'
    }),

    close () {
      this.reset()
      this.$emit('close')
    },

    emitInput (fieldName, value) {
      this.$emit('input', { fieldName, value })
    },

    convertDate (date) {
      if (!date) {
        return ''
      }
      return date.match(/\d{2}.\d{2}.\d{4}/)
        ? date
        : convert(date)
    },

    reset () {
      this.setInitValues()
    },

    save () {
      // If authorName has been changed, change submitName as well, see https://yaits.demos-deutschland.de/T20363#479858
      if (this.localStatement.attributes.authorName !== this.statement.attributes.authorName) {
        this.syncAuthorAndSubmitter()
      }
      this.$emit('save', this.localStatement)
    },

    sendEmail () {
      const { isSubmittedByCitizen, votes } = this.localStatement.attributes
      let sentTo = Translator.trans('check.mail.result.citizen')

      if (!isSubmittedByCitizen) {
        sentTo = Translator.trans('check.mail.result.citizenAndVoters')
      }

      if (isSubmittedByCitizen && hasPermission('feature_statements_vote') && votes.length > 0) {
        sentTo = Translator.trans('check.mail.result.citizen')
      }

      if (dpconfirm(Translator.trans('check.mail.result', { sentTo: sentTo }))) {
        // TO DO: send email
      }
    },

    setDate (val, field) {
      this.localStatement.attributes[field] = val
      this.emitInput(field, val)
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.authoredDate = this.convertDate(this.localStatement.attributes.authoredDate)
      this.localStatement.attributes.submitDate = this.convertDate(this.localStatement.attributes.submitDate)

      this.finalMailDefaultText = Translator.trans('statement.send.final_mail.default', {
        orgaName: this.procedure.orgaName,
        procedureName: this.procedure.name,
        statementText: this.localStatement.attributes.fullText.length < 2000
          ? Translator.trans('statement.send.final_mail.your_statement') + this.localStatement.attributes.fullText
          : '',
        statementRecommendation: this.localStatement.attributes.recommendation
      })

      this.email2Value = this.localStatement.attributes.publicStatement === this.externalConstant
        ? Translator.trans('explanation.statement.final.citizen.email.hidden')
        : this.localStatement.attributes.email2
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    },

    updateCounties (val) {
      console.log(val, this.localStatement.attributes.counties)
    },

    updateMunicipalities (val) {
      console.log(val, this.localStatement.attributes.municipaities)
    }
  },

  created () {
    this.setInitValues()
  }
}
</script>
