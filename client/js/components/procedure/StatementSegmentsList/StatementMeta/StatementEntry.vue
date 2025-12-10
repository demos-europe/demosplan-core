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
      <div
        v-else
        class="mb-2"
      >
        <dp-label
          :text="Translator.trans('statement.date.authored')"
          for="authoredDateDatepicker"
        />
        <dp-datepicker
          id="authoredDateDatepicker"
          class="o-form__control-wrapper"
          data-cy="statementEntry:authoredDate"
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
      <div v-else>
        <dp-label
          :text="Translator.trans('statement.date.submitted')"
          for="submitDateDatepicker"
        />
        <dp-datepicker
          id="submitDateDatepicker"
          class="o-form__control-wrapper"
          data-cy="statementEntry:submitDate"
          :max-date="currentDate"
          :min-date="localStatement.attributes.authoredDate ? localStatement.attributes.authoredDate : ''"
          :value="getFormattedDate(localStatement.attributes.submitDate)"
          @input="val => setDate(val, 'submitDate')"
        />
      </div>

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
    </div>
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
  DpLabel,
  DpSelect,
  DpTextArea,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { mapState } from 'vuex'
export default {
  name: 'StatementEntry',

  components: {
    DpButtonRow,
    DpDatepicker,
    DpInput,
    DpLabel,
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
    ...mapState('Statement', {
      statements: 'items',
    }),

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
      // If authorName has been changed, change submitName as well, see https://yaits.demos-deutschland.de/T20363#479858
      if (this.localStatement.attributes.authorName !== this.statement.attributes.authorName) {
        this.syncAuthorAndSubmitter()
      }

      // Get current statement from store (includes any relationship changes from other components)
      const currentStatement = this.statements[this.statement.id]

      const updatedStatement = {
        ...currentStatement,
        attributes: {
          ...currentStatement.attributes,
          authoredDate: this.localStatement.attributes.authoredDate,
          submitDate: this.localStatement.attributes.submitDate,
          submitType: this.localStatement.attributes.submitType,
          internId: this.localStatement.attributes.internId,
          procedurePhase: this.localStatement.attributes.procedurePhase,
          memo: this.localStatement.attributes.memo,
          authorName: this.localStatement.attributes.authorName,
          submitName: this.localStatement.attributes.submitName,
        },
      }

      this.$emit('save', updatedStatement)
    },

    setDate (val, field) {
      this.localStatement.attributes[field] = val
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.authoredDate = this.getFormattedDate(this.localStatement.attributes.authoredDate)
      this.localStatement.attributes.submitDate = this.getFormattedDate(this.localStatement.attributes.submitDate)
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    },
  },

  created () {
    this.setInitValues()
  },
}
</script>
