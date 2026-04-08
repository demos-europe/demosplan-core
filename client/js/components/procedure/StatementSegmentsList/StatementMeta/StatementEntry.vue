<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset data-dp-validate="statementEntryData">
    <legend
      id="entry"
      class="mb-3 color-text-muted font-normal"
    >
      {{ Translator.trans('entry') }}
    </legend>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
      <!-- authoredDate: if manual statement -->
      <dp-input
        v-if="isStatementManual ? true : !editable"
        id="statementAuthoredDate"
        class="o-form__group-item mb-2"
        data-cy="statementEntry:authoredDate"
        disabled
        :label="{
          text: Translator.trans('statement.date.authored')
        }"
        :model-value="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : '-'"
      />

      <!-- authoredDate: if not manual statement -->
      <dp-datepicker
        v-else
        id="authoredDateDatepicker"
        class="o-form__control-wrapper"
        data-cy="statementEntry:authoredDate"
        :label="{
          text: Translator.trans('statement.date.authored')
        }"
        :max-date="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : currentDate"
        :value="localStatement.attributes.authoredDate"
        @input="val => setDate(val, 'authoredDate')"
      />
    </div>

    <!-- submitDate: if manual statement -->
    <dp-input
      v-if="isStatementManual ? true : !editable"
      id="statementSubmitDate"
      class="o-form__group-item mb-2"
      data-cy="statementEntry:submitDate"
      :disabled="true"
      :label="{
        text: Translator.trans('statement.date.submitted')
      }"
      :model-value="localStatement.attributes.submitDate ? localStatement.attributes.submitDate : '-'"
    />

    <!-- submitDate: if not manual statement -->
    <dp-datepicker
      v-else
      id="submitDateDatepicker"
      class="o-form__control-wrapper"
      data-cy="statementEntry:submitDate"
      :label="{
        text: Translator.trans('statement.date.submitted')
      }"
      :max-date="currentDate"
      :min-date="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : ''"
      :value="getFormattedDate(localStatement.attributes.submitDate)"
      @input="val => setDate(val, 'submitDate')"
    />

    <dp-select
      v-if="editable"
      id="statementSubmitType"
      v-model="localStatement.attributes.submitType"
      class="space-y-0.5 mb-2"
      data-cy="statementEntry:submitType"
      :label="{
        text: Translator.trans('submit.type')
      }"
      :options="submitTypeOptions"
    />
    <dl
      v-else
      class="u-mb-0_5"
    >
      <dt class="font-semibold u-mb-0_25">
        {{ Translator.trans('submit.type') }}
      </dt>
      <dd class="text-muted">
        {{ submitTypeOptions.find(opt => opt.value === localStatement.attributes.submitType)?.label || '-' }}
      </dd>
    </dl>

    <dp-input
      v-if="editable"
      id="statementInternId"
      v-model="localStatement.attributes.internId"
      class="mb-2"
      data-cy="statementEntry:internId"
      :disabled="!editable"
      :label="{
        text: Translator.trans('internId')
      }"
    />

    <dp-input
      v-else
      id="statementInternId"
      class="mb-2"
      :model-value="localStatement.attributes.internId || '-'"
      data-cy="statementEntry:internId"
      disabled
      :label="{
        text: Translator.trans('internId')
      }"
    />

    <template v-if="hasPermission('field_statement_phase')">
      <dp-select
        v-if="availableProcedurePhases.length > 1"
        id="statementProcedurePhase"
        v-model="localStatement.attributes.procedurePhase.key"
        class="mb-3"
        data-cy="statementEntry:procedurePhase"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('procedure.public.phase')
        }"
        :options="availableProcedurePhases"
      />
      <dl
        v-else
        class="mb-3"
      >
        <dt class="font-semibold u-mb-0_25">
          {{ Translator.trans('procedure.public.phase') }}
        </dt>
        <dd class="text-muted">
          {{ localStatement.attributes.procedurePhase?.name || '-' }}
        </dd>
      </dl>
    </template>

    <dp-text-area
      v-if="hasPermission('field_statement_memo')"
      id="r_memo"
      v-model="localStatement.attributes.memo"
      data-cy="statementEntry:memo"
      :disabled="!editable"
      :label="Translator.trans('memo')"
      name="r_memo"
      reduced-height
    />

    <dp-button-row
      v-if="editable"
      class="mt-2 w-full"
      primary
      secondary
      @primary-action="dpValidateAction('statementEntryData', save, false)"
      @secondary-action="reset"
    />
  </fieldset>
</template>

<script>
import {
  DpButtonRow,
  DpDatepicker,
  DpInput,
  DpSelect,
  DpTextArea,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementEntry',

  components: {
    DpButtonRow,
    DpDatepicker,
    DpInput,
    DpSelect,
    DpTextArea,
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false,
    },

    statement: {
      type: Object,
      required: true,
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => [],
    },
  },

  emits: [
    'save',
  ],

  data () {
    return {
      localStatement: null,
    }
  },

  computed: {
    availableProcedurePhases () {
      const phases = this.statement.attributes?.availableProcedurePhases || []

      return phases.map(phase => ({
        label: phase.name,
        value: phase.key,
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

    isStatementManual () {
      return this.localStatement.attributes.isManual
    },

    hasUnsavedChanges () {
      if (!this.localStatement || !this.statement) {
        return false
      }

      const initialAttributes = JSON.parse(JSON.stringify(this.statement.attributes))
      initialAttributes.authoredDate = this.getFormattedDate(initialAttributes.authoredDate)
      initialAttributes.submitDate = this.getFormattedDate(initialAttributes.submitDate)

      const currentAttributes = this.localStatement.attributes

      return JSON.stringify(currentAttributes) !== JSON.stringify(initialAttributes)
    },
  },

  methods: {
    getFormattedDate (date) {
      if (!date) {
        return ''
      }
      return date.match(/[0-9]{2}.[0-9]{2}.[0-9]{4}/) ?
        date :
        this.formatDate(date)
    },

    formatDate (dateString) {
      const date = dateString.split('T')[0].split('-')

      return date[2] + '.' + date[1] + '.' + date[0]
    },

    reset () {
      this.setInitValues()
    },

    save () {
      const attrs = this.localStatement.attributes
      const changes = {
        attributes: {
          authoredDate: attrs.authoredDate,
          submitDate: attrs.submitDate,
          submitType: attrs.submitType,
          internId: attrs.internId,
        },
      }
      if (hasPermission('field_statement_phase')) {
        changes.attributes.procedurePhase = attrs.procedurePhase
      }
      if (hasPermission('field_statement_memo')) {
        changes.attributes.memo = attrs.memo
      }
      this.$emit('save', changes)
    },

    setDate (val, field) {
      this.localStatement.attributes[field] = val
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.authoredDate = this.getFormattedDate(this.localStatement.attributes.authoredDate)
      this.localStatement.attributes.submitDate = this.getFormattedDate(this.localStatement.attributes.submitDate)
    },
  },

  created () {
    this.setInitValues()
  },
}
</script>
