<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="flex flex-items-center">
    <dp-datepicker
      :id="startId"
      :name="startName"
      :min-date="minDate"
      :max-date="maxStartDate"
      :calendars-after="calendarsAfter"
      :calendars-before="calendarsBefore"
      :disabled="startDisabled"
      :value="startValue"
      :required="required || (endDate !== '' && endDate < currentDate)"
      data-cy="startDateDescription"
      @input="handleInputStartDate" />
    <span>-</span>
    <dp-datepicker
      :id="endId"
      :name="endName"
      :min-date="minEndDate"
      :max-date="maxDate"
      :calendars-after="calendarsAfter"
      :calendars-before="calendarsBefore"
      :disabled="endDisabled"
      :value="endValue"
      :required="required"
      data-cy="endDateDescription"
      @input="handleInputEndDate" />
  </div>
</template>

<script>
import DpDatepicker from './DpDatepicker'
import { formatDate } from 'demosplan-utils'

export default {
  name: 'DpDateRangePicker',

  components: {
    DpDatepicker
  },

  props: {
    calendarsAfter: {
      type: Number,
      required: false,
      default: 0
    },

    calendarsBefore: {
      type: Number,
      required: false,
      default: 0
    },

    endDisabled: {
      type: Boolean,
      required: false,
      default: false
    },

    endId: {
      type: String,
      required: true
    },

    endName: {
      type: String,
      required: false,
      default: ''
    },

    endValue: {
      type: String,
      required: false,
      default: ''
    },

    enforcePlausibleDates: {
      type: Boolean,
      required: false,
      default: false
    },

    maxDate: {
      type: String,
      required: false,
      default: ''
    },

    minDate: {
      type: String,
      required: false,
      default: ''
    },

    placeholder: {
      type: String,
      required: false,
      default: ''
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    startDisabled: {
      type: Boolean,
      required: false,
      default: false
    },

    startId: {
      type: String,
      required: true
    },

    startName: {
      type: String,
      required: false,
      default: ''
    },

    startValue: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      endDate: this.endValue,
      maxStartDate: this.enforcePlausibleDates ? this.endValue : '',
      minEndDate: this.enforcePlausibleDates ? this.startValue : ''
    }
  },

  computed: {
    currentDate () {
      return formatDate()
    }
  },

  watch: {
    startValue (newVal) {
      this.minEndDate = this.enforcePlausibleDates ? newVal : ''
    },

    endValue (newVal) {
      this.maxStartDate = this.enforcePlausibleDates ? newVal : ''
    }
  },

  methods: {
    handleInputEndDate (value) {
      this.updateMaxStartDate(value)
      this.$emit('input:end-date', value)
    },

    handleInputStartDate (value) {
      this.updateMinEndDate(value)
      this.$emit('input:start-date', value)
    },

    updateMaxStartDate (value) {
      this.endDate = value
      this.maxStartDate = this.enforcePlausibleDates && value ? value : ''
    },

    updateMinEndDate (value) {
      this.minEndDate = this.enforcePlausibleDates && value ? value : ''
    }
  }
}
</script>
