<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset>
    <legend
      id="entry"
      class="mb-3 color-text-muted font-normal">
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
        :value="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : '-'" />

      <!-- authoredDate: if not manual statement -->
      <div v-else>
        <dp-label
          :text="Translator.trans('statement.date.authored')"
          for="authoredDateDatepicker" />
        <dp-datepicker
          id="authoredDateDatepicker"
          class="o-form__control-wrapper"
          :max-date="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : currentDate"
          :value="localStatement.attributes.authoredDate"
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
        :value="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : '-'" />

      <!-- submitDate: if not manual statement -->
      <div v-else>
        <dp-label
          :text="Translator.trans('statement.date.submitted')"
          for="submitDateDatepicker" />
        <dp-datepicker
          id="submitDateDatepicker"
          class="o-form__control-wrapper"
          :max-date="currentDate"
          :min-date="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : ''"
          :value="convertDate(localStatement.attributes.submitDate)"
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
        :options="submitTypeOptions" />

      <dp-input
        id="statementInternId"
        v-model="localStatement.attributes.internId"
        class="mb-2"
        :disabled="!editable"
        :label="{
          text: Translator.trans('internId')
        }"
        width="w-1/4" />

      <template v-if="hasPermission('field_statement_phase')">
        <dp-select
          id="statementProcedurePhase"
          v-model="localStatement.attributes.procedurePhase.key"
          class="mb-3"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('procedure.public.phase')
          }"
          :options="availableProcedurePhases"/>
      </template>
    </div>
    <dp-text-area
      v-if="hasPermission('field_statement_memo')"
      id="r_memo"
      v-model="localStatement.attributes.memo"
      :disabled="!editable"
      :label="Translator.trans('memo')"
      name="r_memo"
      reduced-height />

    <dp-button-row
      v-if="editable"
      class="mt-2 w-full"
      primary
      secondary
      @primary-action="dpValidateAction('statementMetaData', save, false)"
      @secondary-action="reset" />
  </fieldset>
</template>

<script>
import StatementMetaAttachments from "./StatementMetaAttachments.vue";

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

import {
  DpButtonRow,
  DpDatepicker,
  DpInput,
  DpLabel,
  DpSelect,
  DpTextArea,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
export default {
  name: 'StatementEntry',

  components: {
    DpButtonRow,
    DpDatepicker,
    DpInput,
    DpLabel,
    DpSelect,
    DpTextArea
  },

  mixins: [dpValidateMixin],

  props: {
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

  computed : {
    isStatementManual() {
      return this.localStatement.attributes.isManual
    }
  },

  methods: {
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
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.authoredDate = this.convertDate(this.localStatement.attributes.authoredDate)
      this.localStatement.attributes.submitDate = this.convertDate(this.localStatement.attributes.submitDate)
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    },
  },

  created() {
    this.setInitValues()
  },
}
</script>
