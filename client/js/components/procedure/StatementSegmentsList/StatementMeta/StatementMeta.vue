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
      class="u-mt-0_5"
      data-dp-validate="statementMetaData">
      <div class="inline-block u-1-of-2 align-top">
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
          v-if="!this.localStatement.attributes.isSubmittedByCitizen"
          id="statementOrgaName"
          v-model="localStatement.attributes.initialOrganisationName"
          class="u-mb-0_5"
          :disabled="statement.isManual ? false : !editable"
          :label="{
            text: Translator.trans('organisation')
          }"
          @input="(val) => emitInput('initialOrganisationName', val)" />
        <dp-input
          v-if="!this.localStatement.attributes.isSubmittedByCitizen"
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
      </div><!--

   --><div class="inline-block u-1-of-2 u-pl">
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

        <dp-text-area
          v-if="hasPermission('field_statement_memo')"
          :disabled="!editable"
          id="r_memo"
          :label="Translator.trans('memo')"
          name="r_memo"
          reduced-height
          v-model="localStatement.attributes.memo" />
      </div>

      <dp-button-row
        v-if="editable"
        class="u-mt-0_5"
        primary
        secondary
        :secondary-text="Translator.trans('discard.changes')"
        @primary-action="dpValidateAction('statementMetaData', save, false)"
        @secondary-action="reset" />
    </div>

    <statement-meta-attachments
      :editable="editable"
      :attachments="attachments"
      class="u-pt-0_5 border--bottom u-pb-0_5"
      :procedure-id="procedureId"
      :statement-id="statement.id"
      @change="(value) => emitInput('attachments', value)" />

    <similar-statement-submitters
      :procedure-id="procedureId"
      :editable="editable"
      :similar-statement-submitters="similarStatementSubmitters"
      :statement-id="statement.id" />
  </div>
</template>

<script>
import {
  DpButtonRow,
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

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

export default {
  name: 'StatementMeta',

  inject: ['procedureId'],

  components: {
    DpButtonRow,
    DpDatepicker,
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
      localStatement: null
    }
  },

  computed: {
    ...mapState('Statement', {
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
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    }
  },

  created () {
    this.setInitValues()
  }
}
</script>
