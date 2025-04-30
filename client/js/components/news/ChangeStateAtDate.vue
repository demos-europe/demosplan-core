<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<documentation>
  <!-- This component renders a dropdown/select with a checkbox. If the checkbox is checked, the dropdown is read-only
  (and shows the current state) and a datepicker gets active and another dropdown with the same options as the first one.

  It is designed to be used for activating a time-controlled change of the options. -->

  <usage>
    <change-state-at-date
      regular-dropdown-id="id_for_the_regular_dropdown_that_gets_send_if_the_checkbox_is_unchecked"
      delayed-switch-dropdown-id="id_for_the_dropdown_that_gets_send_if_the_checkbox_is_checked"
      label="trans.key"
      date-id="id_for_the_date_input"
      :status-options="[{ 'value': 'myValue', 'label': 'trans.key.one'}, {'value': 'anotherValue', 'label': 'trans.key.two'}]"
      init-status="myValue" />
  </usage>
</documentation>

<template>
  <div>
    <div class="layout u-mb-0_75">
      <dp-select
        v-model="actualStatus"
        class="layout__item u-4-of-12"
        :data-cy="label"
        :label="{
          text: Translator.trans(label)
        }"
        :name="regularDropdownId"

        :options="statusOptions" />
    </div>
    <div class="layout">
      <div class="layout__item u-5-of-12 u-12-of-12-lap-down u-mt-0_125">
        <span>
          <input
            :id="checkSwitchId"
            :name="checkSwitchId"
            value="1"
            type="checkbox"
            :checked="active"
            @click="toggleCheckbox">
          <label
            :for="checkSwitchId"
            class="o-form__label inline-block">
            {{ Translator.trans('change.state.at.date') }}
          </label>
        </span>
      </div><!--
   --><div
        v-if="active"
        class="u-mt-0_125">
        <div
          class="layout__item u-2-of-12 u-6-of-12-lap-down"
          :class="{ 'color--grey': active === false }">
          <dp-label
            required
            :text="Translator.trans('on')"
            :for="dateId" />
          <dp-datepicker
            class="o-form__control-wrapper"
            required
            :id="dateId"
            :name="dateId"
            :min-date="disabledDates.to"
            :disabled="active === false"
            v-model="changeDate"
            @change="dateChanged"
            :calendars-after="2" />
        </div><!--
     --><dp-select
          v-model="futureStatus"
          class="layout__item u-5-of-12 u-6-of-12-lap-down"
          :label="{
            text: Translator.trans('change.state.to')
          }"
          :name="delayedSwitchDropdownId"
          :options="statusOptions" />
      </div>
    </div>
  </div>
</template>

<script>
import {
  DpDatepicker,
  DpLabel,
  DpSelect,
  formatDate,
  toDate
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ChangeStateAtDate',

  components: {
    DpDatepicker,
    DpLabel,
    DpSelect
  },

  props: {
    dateId: {
      required: true,
      type: String
    },

    delayedSwitchDropdownId: {
      required: true,
      type: String
    },

    checkSwitchId: {
      required: false,
      type: String,
      default: 'change_state_delay_toggle'
    },

    activeDelay: {
      required: false,
      type: Boolean,
      default: false
    },

    defaultNewState: {
      required: false,
      type: String,
      default: ''
    },

    defaultCurrentState: {
      required: false,
      type: String,
      default: ''
    },

    regularDropdownId: {
      required: true,
      type: String
    },

    label: {
      required: false,
      type: String,
      default: ''
    },

    initDate: {
      required: false,
      type: String,
      default: null
    },

    // Has to match a value from the statusOptions
    initStatus: {
      required: false,
      type: String,
      default: null
    },

    // Array of Objects with { value, label }
    statusOptions: {
      required: true,
      type: Array
    }
  },

  emits: [
    'date:changed',
    'status:changed'
  ],

  data () {
    return {
      active: this.activeDelay,
      changeDate: '',
      actualStatus: this.defaultCurrentState,
      futureStatus: this.defaultNewState,
      disabledDates: {
        to: formatDate(this.getTomorrowDate()) // Disable all dates in the past
      }
    }
  },

  computed: {
    initLabel () {
      const initLabel = this.statusOptions.find(el => el.value === this.initStatus).label
      return Translator.trans(initLabel)
    }
  },

  methods: {
    dateChanged () {
      this.$emit('date:changed', toDate(this.changeDate))
    },

    getTomorrowDate () {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)

      return tomorrow
    },

    statusChanged () {
      this.$emit('status:changed', this.newStatus)
    },

    toggleCheckbox () {
      this.active = this.active === false
    }
  },

  mounted () {
    if (this.initDate !== '' && this.initDate !== null) {
      this.changeDate = this.initDate
    } else {
      this.changeDate = formatDate()
    }
  }
}
</script>
