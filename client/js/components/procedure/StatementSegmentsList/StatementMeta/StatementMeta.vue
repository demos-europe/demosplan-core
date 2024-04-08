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

    <dp-accordion
      class="mt-2"
      :title="Translator.trans('statement.final.send')">
      <template v-if="!localStatement.attributes.sendFinalMail">
        {{ Translator.trans('explanation.no.statement.final.sent') }}
      </template>
      <template v-else-if="'demosplan\\DemosPlanCoreBundle\\Entity\\Statement\\Statement::EXTERNAL' === localStatement.attributes.publicStatement && !localStatement.attributes.authorFeedback">
        {{ Translator.trans('explanation.no.statement.final.no.feedback.wanted') }}
      </template>
      <template v-else-if="localStatement.attributes.email2.length === 0">
        {{ Translator.trans('explanation.no.statement.final.no.email') }}
      </template>
      <template v-else>
        <template v-if="localStatement.attributes.finalEmailOnlyToVoters">
          {{ Translator.trans('explanation.statement.final.sent.only.voters') }}
        </template>

        <p v-if="localStatement.attributes.sentAssessment">
          {{ localStatement.attributes.sentAssessment
            ? Translator.trans('confirm.statement.final.sent.date', { date: dplanDate(localStatement.attributes.sentAssessmentDate, 'd.m.Y | H:i') })
            : Translator.trans('confirm.statement.final.not.sent') }}
        </p>

        <label v-if="hasPermission('field_organisation_email2_cc')">
          {{ Translator.trans('email.recipient') }}
          <p class="lbl--text color--grey">
            <template v-if="localStatement.attributes.publicStatement === 'external'">
              {{ localStatement.attributes.publicStatement === 'external'
                ? Translator.trans('explanation.statement.final.citizen.email.hidden')
                : localStatement.attributes.email2 }}
            </template>
            <template v-if="localStatement.attributes.ccEmail2">
              {{ `, ${Translator.trans('recipients.additional')}: ${localStatement.attributes.ccEmail2}` }}
            </template>
          </p>
        </label>

        <dp-input
          id="r_send_emailCC"
          :label="{
            text: Translator.trans('email.cc'),
            hint: Translator.trans('explanation.email.cc')
          }"
          v-model="emailsCC"
          :disabled="!editable" />

        <dp-input
          id="r_send_title"
          :label="{
            text: Translator.trans('subject'),
          }"
          :value="Translator.trans('statement.final.email.subject', { procedureName: procedure.name })"
          :disabled="!editable" />

        <dp-label
          :text="Translator.trans('final.mail')"
          for="r_final_mail" />
        <dp-editor
          v-if="true /*hasPermission('field_statement_memo') @todo wich permission?*/"
          :disabled="!editable"
          id="r_final_mail"
          name="r_final_mail"
          reduced-height
          v-model="finalMailDefaultText" />
      </template>
    </dp-accordion>
  </div>
</template>

<script>
import {
  DpAccordion,
  DpButtonRow,
  DpDatepicker,
  DpEditor,
  DpIcon,
  DpInput,
  DpLabel,
  DpSelect,
  DpTextArea,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import SimilarStatementSubmitters from '@DpJs/components/procedure/Shared/SimilarStatementSubmitters/SimilarStatementSubmitters'
import StatementMetaAttachments from './StatementMetaAttachments'

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

export default {
  name: 'StatementMeta',

  components: {
    DpAccordion,
    DpButtonRow,
    DpDatepicker,
    DpEditor,
    DpIcon,
    DpInput,
    DpLabel,
    DpSelect,
    DpTextArea,
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
