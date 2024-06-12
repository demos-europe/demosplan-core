<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <label
      for="status"
      class="u-mb-0_25 inline-block">
      {{ Translator.trans('status') }}
      <p class="lbl__hint">
        {{ surveyStatusHint }}
      </p>
    </label>
    <dp-contextual-help
      class="float-right"
      :text="Translator.trans('survey.status.explanation')" />
    <select
      id="status"
      name="status"
      class="o-form__control-select u-1-of-4 block"
      v-model="currentStatus">
      <option
        v-for="(option, idx) in statusOptions"
        :key="idx"
        :value="option.value">
        {{ Translator.trans(option.label) }}
      </option>
    </select>
    <div
      v-if="currentStatus === 'participation' && isPeriodValid"
      class="u-mt">
      <label class="u-mb-0_5 inline-block">
        {{ Translator.trans('period.public.participation') }}*
        <p class="lbl__hint">
          {{ Translator.trans('survey.date.hint') }}
        </p>
      </label>
      <dp-contextual-help
        class="u-pt-0_25 inline-block float-right"
        :text="Translator.trans('survey.date.explanation')" />
      <div class="block u-mb-2">
        <datepicker
          id="startDate"
          name="startDate"
          v-model="currentStartDate"
          format="dd.MM.yyyy"
          monday-first
          class="inline-block w-8 u-mr-0_5"
          input-class="o-form__control-input"
          :language="de" /><!--
   --><span>-</span><!--
   --><datepicker
        id="endDate"
        name="endDate"
        v-model="currentEndDate"
        format="dd.MM.yyyy"
        monday-first
        class="inline-block w-8 u-ml-0_5"
        input-class="o-form__control-input"
        :language="de" />
      </div>
    </div>
    <div v-if="currentStatus === 'participation' && isPeriodValid === false">
      <p class="flash flash-info">
        {{ Translator.trans('survey.period.invalid') }}
      </p>
    </div>
  </div>
</template>

<script>
import { DpContextualHelp, toDate } from '@demos-europe/demosplan-ui'
// @improve use DpDatepicker
import Datepicker from 'vuejs-datepicker'
import { de } from 'vuejs-datepicker/dist/locale'

export default {
  name: 'DpSurveyStatus',

  components: {
    DpContextualHelp,
    Datepicker
  },

  props: {
    initialEndDate: {
      type: String,
      default: ''
    },

    initialStartDate: {
      type: String,
      default: ''
    },

    initialStatus: {
      type: String,
      default: ''
    },

    procedureStartDate: {
      type: String,
      default: ''
    },

    statusOptions: {
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      currentEndDate: '',
      currentStartDate: '',
      currentStatus: {}
    }
  },

  computed: {
    isPeriodValid () {
      return toDate(this.initialStartDate, 'DD.MM.YYYY') <= toDate(this.initialEndDate, 'DD.MM.YYYY')
    },

    surveyStatusHint () {
      return Translator.trans('survey.status.hint', { start: this.procedureStartDate, end: this.initialEndDate })
    }
  },

  created () {
    this.de = de
  },

  mounted () {
    this.currentStatus = this.initialStatus
    this.currentStartDate = toDate(this.initialStartDate, 'DD.MM.YYYY')
    this.currentEndDate = toDate(this.initialEndDate, 'DD.MM.YYYY')
  }
}
</script>
