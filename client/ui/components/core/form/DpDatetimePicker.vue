<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-label
      v-if="label !== ''"
      :for="`datePicker:${id}`"
      :hint="hint"
      :required="required"
      :text="label" />
    <div class="o-form__control-wrapper o-form__group">
      <dp-datepicker
        class="o-form__group-item shrink"
        :calendars-after="2"
        :disabled="disabled"
        :id="`datePicker:${id}`"
        :max-date="maxDate"
        :min-date="minDate"
        :required="required"
        v-model="date"
        @input="$emit('input', currentDatetime)" />
      <dp-time-picker
        class="o-form__group-item"
        :disabled="disabled"
        :id="`timePicker:${id}`"
        v-model="time"
        :min-value="minTime"
        @input="$emit('input', currentDatetime)" />
      <input
        type="hidden"
        :disabled="disabled"
        :name="name"
        :value="currentDatetime"
        v-if="hiddenInput && name">
    </div>
  </div>
</template>

<script>
import customParseFormat from 'dayjs/plugin/customParseFormat'
import dayjs from 'dayjs'
import DpDatepicker from './DpDatepicker'
import DpTimePicker from './DpTimePicker'

dayjs.extend(customParseFormat)

export default {
  name: 'DpDatetimePicker',

  components: {
    DpDatepicker,
    DpLabel: async () => {
      const { DpLabel } = await import('demosplan-ui/components')
      return DpLabel
    },
    DpTimePicker
  },

  props: {
    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Add a hidden input field to submit value of `currentDatetime`
     * if used in a form that actually gets submitted.
     */
    hiddenInput: {
      type: Boolean,
      required: false,
      default: false
    },

    hint: {
      type: String,
      required: false,
      default: ''
    },

    /*
     * The ids of date and time pickers are derived from this prop.
     */
    id: {
      type: String,
      required: true
    },

    /*
     * If set, a label is rendered above the datetime picker. However this is not exactly
     * best practice in terms of accessibility right now, since this label points to none
     * of the date or time inputs.
     */
    label: {
      type: String,
      required: false,
      default: ''
    },

    /*
     * Set a maxDate for the date field. Since this is passed to the datepicker component,
     * in contrast to the `value` field a string in the format of 'dd.mm.yyyy' is expected.
     */
    maxDate: {
      type: String,
      required: false,
      default: ''
    },

    /*
     * Set a minDate for the date field. Since this is passed to the datepicker component,
     * in contrast to the `value` field a string in the format of 'dd.mm.yyyy' is expected.
     */
    minDate: {
      type: String,
      required: false,
      default: ''
    },

    // Used in conjunction with `hiddenInput` to render a hidden input field with this name.
    name: {
      type: String,
      required: false,
      default: ''
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    // Expects ISO datetime
    value: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      date: '',
      time: '00:00'
    }
  },

  computed: {
    currentDatetime () {
      const parsedDateTime = dayjs(`${this.date} ${this.time}`, 'DD.MM.YYYY HH:mm')
      return parsedDateTime.isValid() ? parsedDateTime.format() : ''
    },

    isCurrentDateSelected () {
      return this.minDate !== '' ? this.date === this.minDate : false
    },

    /**
     * Set minTime only if the current day is selected in the datepicker
     * @return {string|string}
     */
    minTime () {
      return this.isCurrentDateSelected ? new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) : ''
    }
  },

  watch: {
    value: function (newVal) {
      this.setDatetime(newVal)
    }
  },

  methods: {
    setDatetime (datetime) {
      const parsedDateTime = dayjs(datetime)
      if (parsedDateTime.isValid()) {
        this.date = parsedDateTime.format('DD.MM.YYYY')
        this.time = parsedDateTime.format('HH:mm')
      }
    }
  },

  mounted () {
    this.setDatetime(this.value)
  }
}
</script>
