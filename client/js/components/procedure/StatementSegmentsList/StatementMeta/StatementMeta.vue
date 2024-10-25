<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-statement-meta-box mb-2">
    <div class="relative flex border--bottom pb-2">
      <h2 class="mb-0 text-lg">
        {{ Translator.trans('statement.metadata') }}
      </h2>
      <button
        class="btn--blank o-link--default ml-auto"
        @click="close">
        <dp-icon icon="close" />
      </button>
    </div>

    <form
      class="mt-2 mr-5"
      data-dp-validate="statementMetaData">
      <fieldset>
        <legend class="mb-3 color-text-muted font-normal">
          {{ Translator.trans('entry') }}
        </legend>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <!-- authoredDate: if manual statement -->
            <dp-input
              v-if="isStatementManual ? true : !editable"
              id="statementAuthoredDate"
              class="o-form__group-item"
              :disabled="true"
              :label="{
                text: Translator.trans('statement.date.authored')
              }"
              :value="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : '-'"
              @input="val => emitInput('authoredDate', val)" />

            <!-- authoredDate: if not manual statement -->
            <div v-else>
              <dp-label
                :text="Translator.trans('statement.date.authored')"
                for="authoredDateDatepicker" />
              <dp-datepicker
                class="o-form__control-wrapper"
                id="authoredDateDatepicker"
                :value="localStatement.attributes.authoredDate"
                :max-date="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : currentDate"
                @input="val => setDate(val, 'authoredDate')" />
            </div>

            <!-- submitDate: if manual statement -->
            <dp-input
              v-if="isStatementManual ? true : !editable"
              id="statementSubmitDate"
              class="o-form__group-item"
              :disabled="true"
              :label="{
                text: Translator.trans('statement.date.submitted')
              }"
              :value="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : '-'"
              @input="val => emitInput('submitDate', val)" />

            <!-- submitDate: if not manual statement -->
            <div v-else>
              <dp-label
                :text="Translator.trans('statement.date.submitted')"
                for="submitDateDatepicker" />
              <dp-datepicker
                class="o-form__control-wrapper"
                id="submitDateDatepicker"
                :value="convertDate(localStatement.attributes.submitDate)"
                :max-date="currentDate"
                :min-date="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : ''"
                @input="val => setDate(val, 'submitDate')" />
            </div>

          <dp-select
            id="statementSubmitType"
            v-model="localStatement.attributes.submitType"
            class="mb-2"
            :disabled="!editable"
            :label="{
              text: Translator.trans('submit.type')
            }"
            :options="submitTypeOptions"
            @select="val => emitInput('submitType', val)" />

          <dp-input
            id="statementInternId"
            v-model="localStatement.attributes.internId"
            class="mb-2"
            :disabled="!editable"
            :label="{
              text: Translator.trans('internId')
            }"
            width="w-1/4"
            @input="val => emitInput('internId', val)" />

          <template v-if="hasPermission('field_statement_phase')">
            <dp-select
              v-if="hasPermission('field_show_internal_procedure_phases_in_dropdown') && !localStatement.attributes.isSubmittedByCitizen"
              id="statementProcedureInternalPhase"
              v-model="localStatement.attributes.phase"
              class="mb-3"
              :disabled="!editable || !isStatementManual"
              :label="{
                text: Translator.trans('procedure.public.phase')
              }"
              :options="availableInternalPhases"
              @select="val => emitInput('phase', val)" />

            <dp-select
              v-else
              id="statementProcedureExternalPhase"
              v-model="localStatement.attributes.phase"
              class="mb-3"
              :disabled="!editable || !isStatementManual"
              :label="{
                text: Translator.trans('procedure.public.phase')
              }"
              :options="availableExternalPhases"
              @select="val => emitInput('phase', val)" />
          </template>
        </div>
        <dp-text-area
          v-if="hasPermission('field_statement_memo')"
          :disabled="!editable"
          id="r_memo"
          :label="Translator.trans('memo')"
          name="r_memo"
          reduced-height
          v-model="localStatement.attributes.memo" />

        <dp-button-row
          v-if="editable"
          class="mt-2 w-full"
          primary
          secondary
          @primary-action="dpValidateAction('statementMetaData', save, false)"
          @secondary-action="reset" />
      </fieldset>

      <fieldset>
        <legend
          class="mb-3 color-text-muted font-normal"
          id="submitter">
          {{ Translator.trans('submitted.author') }}
        </legend>

        <!--  TO DO: add if not participationGuestOnly  -->
        <div
          v-if="hasPermission('field_statement_meta_orga_name')"
          aria-labelledby="submitter"
          class="mb-2">
          {{ submitterRole }}
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
          <dp-input
            v-if="hasPermission('field_statement_meta_orga_department_name') && !this.localStatement.attributes.isSubmittedByCitizen"
            id="statementDepartmentName"
            v-model="localStatement.attributes.initialOrganisationDepartmentName"
            class="mb-2"
            :disabled="isStatementManual ? false : !editable"
            :label="{
              text: Translator.trans('department')
            }"
            @input="val => emitInput('initialOrganisationDepartmentName', val)" />

          <!--  TO DO: add if not participationGuestOnly -->
          <dp-input
            v-if="!this.localStatement.attributes.isSubmittedByCitizen"
            id="statementOrgaName"
            v-model="localStatement.attributes.initialOrganisationName"
            class="mb-2"
            :disabled="isStatementManual ? false : !editable"
            :label="{
              text: Translator.trans('organisation')
            }"
            @input="val => emitInput('initialOrganisationName', val)" />

          <dp-contextual-help
            v-if="isSubmitterAnonymized()"
            class="float-right mt-0.5"
            :text="submitterHelpText" />
          <dp-input
            v-if="hasPermission('field_statement_meta_submit_name') && this.statementFormDefinitions.name.enabled"
            id="statementSubmitterName"
            v-model="statementSubmitterValue"
            class="mb-2"
            :disabled="!isStatementManual || !editable || isSubmitterAnonymized()"
            :label="{
              text: Translator.trans('name')
            }"
            @input="val => emitInput('statementSubmitterField', val)" />

          <dp-input
            v-if="hasPermission('field_statement_submitter_email_address') || isStatementManual"
            id="statementEmailAddress"
            v-model="localStatement.attributes.submitterEmailAddress"
            class="mb-2"
            :disabled="isStatementManual ? false : !editable"
            :label="{
              text: Translator.trans('email')
            }"
            type="email"
            @input="val => emitInput('submitterEmailAddress', val)" />

          <dp-input
            v-if="localStatement.attributes.represents"
            id="statementRepresentation"
            disabled
            :label="{
              text: Translator.trans('statement.representation.assessment')
            }"
            :value="localStatement.attributes.represents" />
          <dp-input
            v-if="localStatement.attributes.represents"
            id="representationCheck"
            v-model="localStatement.attributes.representationChecked"
            :disabled="isStatementManual ? false : !editable"
            :label="{
              text: Translator.trans('statement.representation.checked')
            }"
            type="checkbox" />

          <div class="o-form__group mb-2">
            <dp-input
              id="statementStreet"
              v-model="localStatement.attributes.initialOrganisationStreet"
              class="o-form__group-item"
              :disabled="isStatementManual ? false : !editable"
              :label="{
                text: Translator.trans('street')
              }"
              @input="val => emitInput('initialOrganisationStreet', val)" />
            <dp-input
              id="statementHouseNumber"
              v-model="localStatement.attributes.initialOrganisationHouseNumber"
              class="o-form__group-item shrink"
              :disabled="isStatementManual ? false : !editable"
              :label="{
                text: Translator.trans('street.number.short')
              }"
              :size="3"
              @input="val => emitInput('initialOrganisationHouseNumber', val)" />
          </div>
          <div class="o-form__group mb-2">
            <dp-input
              id="statementPostalCode"
              v-model="localStatement.attributes.initialOrganisationPostalCode"
              class="o-form__group-item shrink"
              :disabled="isStatementManual ? false : !editable"
              :label="{
                text: Translator.trans('postalcode')
              }"
              pattern="^[0-9]{4,5}$"
              :size="5"
              @input="val => emitInput('initialOrganisationPostalCode', val)" />
            <dp-input
              id="statementCity"
              v-model="localStatement.attributes.initialOrganisationCity"
              class="o-form__group-item"
              :disabled="isStatementManual ? false : !editable"
              :label="{
                text: Translator.trans('city')
              }"
              @input="val => emitInput('initialOrganisationCity', val)" />
          </div>
        </div>

        <dp-button-row
          v-if="editable"
          class="mt-2 w-full"
          primary
          secondary
          @primary-action="dpValidateAction('statementMetaData', save, false)"
          @secondary-action="reset" />
      </fieldset>

      <fieldset>
        <legend class="mb-3 color-text-muted font-normal">
          {{ Translator.trans('publication.and.voting') }}
        </legend>
        <div class="font-semibold mb-3">
          {{ Translator.trans('publish.on.platform') }}
        </div>

        <statement-publish
          class="mb-4"
          :editable="editable"
          @update="val => localStatement.attributes.publicVerified = val" />

        <statement-voter
          id="dp-statement-voter"
          :init-voters="statement.attributes.votes"
          :anonym-votes-string="localStatement.attributes.numberOfAnonymVotes?.toString() || '0'"
          :is-manual="statement.attributes.isManual ? 'true' : ''"
          :public-verified="localStatement.attributes.publicVerified"
          :readonly="!editable ? '1' : '0'"  />

        <dp-button-row
          v-if="editable"
          class="mt-2 w-full"
          primary
          secondary
          @primary-action="dpValidateAction('statementMetaData', save, false)"
          @secondary-action="reset" />
      </fieldset>

      <similar-statement-submitters
        class="mb-4"
        :editable="editable"
        :procedure-id="procedure.id"
        :similar-statement-submitters="similarStatementSubmitters"
        :statement-id="statement.id" />

      <!-- need to add statement.attributes.counties and availableCounties in the BE (Array) -->
      <statement-meta-multiselect
        v-if="hasPermission('field_statement_county')"
        :editable="editable"
        :label="Translator.trans('counties')"
        name="counties"
        :options="availableCounties"
        :value="localStatement.attributes.counties"
        @change="updateLocalStatementProperties" />

      <!-- need to add statement.attributes.municipalities and availableMunicipalities in the BE (Array) -->
      <statement-meta-multiselect
        v-if="hasPermission('field_statement_municipality') && formDefinitions.mapAndCountyReference.enabled"
        :editable="editable"
        :label="Translator.trans('municipalities')"
        name="municipalities"
        :options="availableMunicipalities"
        :value="localStatement.attributes.municipalities"
        @change="updateLocalStatementProperties" />

      <!-- need to add statement.attributes.priorityAreas and availablePriorityAreas in the BE (Array) -->
      <statement-meta-multiselect
        v-if="procedureStatementPriorityArea && formDefinitions.mapAndCountyReference.enabled"
        :editable="editable"
        :label="Translator.trans('priorityAreas')"
        name="priorityAreas"
        :options="availablePriorityAreas"
        :value="localStatement.attributes.priorityAreas"
        @change="updateLocalStatementProperties" />

      <!--    <dp-button-row-->
      <!--      v-if="editable"-->
      <!--      class="u-mt-0_5 w-full"-->
      <!--      primary-->
      <!--      secondary-->
      <!--      @primary-action="dpValidateAction('statementMetaData', save, false)"-->
      <!--      @secondary-action="reset" />-->

      <fieldset>
        <legend class="mb-3 color-text-muted font-normal">
          {{ Translator.trans('location.and.document.reference') }}
        </legend>
        <div class="font-semibold mb-1">
          {{ Translator.trans('location') }}
        </div>
        <dp-button
          :aria-label="Translator.trans('location.reference_view')"
          :text="Translator.trans('see')"
          variant="outline" />
      </fieldset>

      <fieldset>
        <legend class="mb-3 color-text-muted font-normal">
          {{ Translator.trans('attachments') }}
        </legend>
        <statement-meta-attachments
          :attachments="attachments"
          :editable="editable"
          :procedure-id="procedure.id"
          :statement-id="statement.id"
          @change="(value) => emitInput('attachments', value)" />
      </fieldset>
    </form>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpContextualHelp,
  DpDatepicker,
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
import StatementMetaMultiselect from './StatementMetaMultiselect'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

export default {
  name: 'StatementMeta',

  components: {
    DpButton,
    DpButtonRow,
    DpContextualHelp,
    DpDatepicker,
    DpIcon,
    DpInput,
    DpLabel,
    DpSelect,
    DpTextArea,
    SimilarStatementSubmitters,
    StatementMetaAttachments,
    StatementMetaMultiselect,
    StatementPublish,
    StatementVoter
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

    statementFormDefinitions: {
      required: true,
      type: Object
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
    ...mapState('Statement', {
      storageStatement: 'items'
    }),

    availableExternalPhases () {
      const externalPhases = this.statement.attributes?.availableExternalPhases || []

      return externalPhases.map(phase => ({
        label: phase.name,
        value: phase.key
      }))
    },

    availableInternalPhases () {
      const internalPhases = this.statement.attributes?.availableInternalPhases || []

      return internalPhases.map(phase => ({
        label: phase.name,
        value: phase.key
      }))
    },

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

    isStatementManual () {
      return this.statement.attributes.isManual
    },

    similarStatementSubmitters () {
      if (typeof this.statement.hasRelationship === 'function' && this.statement.hasRelationship('similarStatementSubmitters')) {
        return Object.values(this.statement.relationships.similarStatementSubmitters.list())
      }
      return null
    },

    statementSubmitterField () {
      const attr = this.localStatement.attributes
      let submitterField = 'authorName'
      // If submitter is an orga and name has a value
      if (attr.submitName && !attr.isSubmittedByCitizen) {
        submitterField = 'submitName'
      }

      return submitterField
    },

    statementSubmitterValue: {
      get () {
        return this.isSubmitterAnonymized() ? Translator.trans('anonymized') : this.localStatement.attributes[this.statementSubmitterField]
      },
      set (value) {
        this.localStatement.attributes[this.statementSubmitterField] = value
      }
    },

    submitterHelpText () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes
      let helpText = ''

      const isAnonymized = hasPermission('area_statement_anonymize') && submitterAndAuthorMetaDataAnonymized

      if (consentRevoked) {
        helpText = Translator.trans('personal.data.usage.revoked')

        if (isAnonymized) {
          helpText = helpText + `<br><br>${Translator.trans('statement.anonymized.submitter.data')}`
        }
      }

      if (!consentRevoked && isAnonymized) {
        helpText = Translator.trans('statement.anonymized.submitter.data')
      }

      return helpText
    },

    submitterRole () {
      const isSubmittedByCitizen = this.localStatement.attributes.isSubmittedByCitizen &&
        this.localStatement.attributes.submitterRole !== 'publicagency'

      return isSubmittedByCitizen ? Translator.trans('role.citizen') : Translator.trans('institution')
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
    ...mapActions('Statement', {
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapMutations('Statement', {
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
      return date.match(/[0-9]{2}.[0-9]{2}.[0-9]{4}/)
        ? date
        : convert(date)
    },

    isSubmitterAnonymized () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes

      return consentRevoked || submitterAndAuthorMetaDataAnonymized
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
        hasStatementText: this.localStatement.attributes.fullText.length < 2000 ? 0 : 1,
        orgaName: this.procedure.orgaName,
        procedureName: this.procedure.name,
        statementText: this.localStatement.attributes.fullText,
        statementRecommendation: this.localStatement.attributes.recommendation
      })
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    },

    updateLocalStatementProperties (value, field) {
      this.localStatement.attributes[field] = value
      this.localStatement.attributes[field].sort((a, b) => a.name.localeCompare(b.name))
    }
  },

  created () {
    this.setInitValues()
  }
}
</script>
