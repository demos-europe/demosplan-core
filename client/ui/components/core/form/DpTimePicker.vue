<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="c-timepicker display--inline-block"
    v-click-outside="closeFlyout"
    @keydown.esc="closeFlyout"
    @keydown.enter="e => handleEnter(e)">
    <dp-label
      v-if="label !== ''"
      for="timeInput"
      :text="label" />
    <dp-resettable-input
      v-if="!isMobileDevice"
      :id="`timeInput:${id}`"
      :ref="`timeInput:${id}`"
      class="width-100"
      button-variant="small"
      default-value="00:00"
      :input-attributes="{ disabled: disabled, autocomplete: 'off' }"
      @reset="handleReset"
      @enter="val => handleEnter(val)"
      @focus="handleFocus"
      @blur="handleBlur"
      @input="val => handleInput(val)"
      pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]"
      :value="currentTime" />
    <dp-input
      v-else
      :id="`timeInput:${id}`"
      class="width-100"
      type="time"
      pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]"
      :value="currentTime"
      @input="val => handleInput(val)"
      autocomplete="off" />

    <div
      ref="flyout"
      v-show="showFlyout"
      class="flex flex-items-start flex-content-evenly c-timepicker__flyout font-size-small"
      tabindex="0">
      <ul class="u-m-0_25 u-mr-0 u-pr-0_25 overflow-y-scroll height-130">
        <li
          v-for="hour in availableHours"
          :key="`${id}:hour:${hour}`"
          class="c-timepicker__flyout-item"
          :class="{'is-selected': currentHour === hour}">
          <button
            @click.prevent="handleInput(hour, 'hour')"
            class="btn--blank u-ph-0_125 u-pv-0_125"
            tabindex="0"
            :value="hour"
            :id="`${id}:hour:${hour}`">
            {{ hour }}
          </button>
        </li>
      </ul>
      <ul
        class="u-m-0_25 height-130"
        :class="minutes.length > 5 ? 'overflow-y-scroll' : ''">
        <li
          v-for="minute in availableMinutes"
          :key="`${id}:minute:${minute}`"
          class="c-timepicker__flyout-item"
          :class="{'is-selected': currentMinutes === minute}">
          <button
            @click.prevent="handleInput(minute, 'minute')"
            tabindex="0"
            class="btn--blank u-ph-0_125 u-pv-0_125"
            :value="minute"
            :id="`${id}:minute:${minute}`">
            {{ minute }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import isMobile from 'ismobilejs'

const DEFAULT_TIME = '00:00'

export default {
  name: 'DpTimePicker',

  components: {
    DpInput: async () => {
      const { DpInput } = await import('demosplan-ui/components')
      return DpInput
    },
    DpLabel: async () => {
      const { DpLabel } = await import('demosplan-ui/components')
      return DpLabel
    },
    DpResettableInput: () => import('../DpResettableInput')
  },

  directives: {
    ClickOutside
  },

  props: {
    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    id: {
      type: String,
      required: true
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    minuteSteps: {
      type: Number,
      required: false,
      default: 15
    },

    /**
     * Minimum allowed value in the format 'hh:mm'
     */
    minValue: {
      type: String,
      required: false,
      default: ''
    },

    // Expects ISO datetime
    value: {
      type: String,
      required: false,
      default: DEFAULT_TIME
    }
  },

  data: () => ({
    currentHour: '00',
    currentMinutes: '00',
    isInputFocused: false,
    showFlyout: false
  }),

  computed: {
    availableHours () {
      if (this.minValue !== '') {
        const values = this.minValue.split(':')
        const minHour = values[0]
        return this.hours.filter(h => this.availableMinutes.length ? h >= minHour : h > minHour)
      }
      return this.hours
    },

    availableMinutes () {
      if (this.minValue !== '') {
        const values = this.minValue.split(':')
        const minHour = values[0]
        const minMinutes = values[1]
        if (this.currentHour === minHour) {
          return this.minutes.filter(m => m >= minMinutes)
        } else {
          return this.minutes
        }
      }
      return this.minutes
    },

    currentTime () {
      return `${this.currentHour}:${this.currentMinutes}`
    },

    hours () {
      let hours = Array.from({ length: 24 }, (v, k) => k)
      hours = hours.map(h => {
        return h.toString().padStart(2, '0')
      })
      return hours
    },

    isHour () {
      return e => e.target.id.includes('hour')
    },

    isMinute () {
      return e => e.target.id.includes('minute')
    },

    isMobileDevice () {
      return isMobile(window.navigator).any
    },

    inputAttributes () {
      return this.isMobileDevice ? { disabled: this.disabled, autocomplete: 'off', type: 'time' } : { disabled: this.disabled, autocomplete: 'off' }
    },

    minutes () {
      let minutes = Array.from({ length: 60 }, (v, k) => k)
      minutes = minutes.filter(m => !(m % this.minuteSteps))
      minutes = minutes.map(h => {
        return h.toString().padStart(2, '0')
      })
      return minutes
    }
  },

  watch: {
    isInputFocused () {
      const input = this.$refs[`timeInput:${this.id}`].$el
      if (this.isInputFocused) {
        input.addEventListener('keydown', this.handleShiftDown)
      } else {
        input.removeEventListener('keydown', this.handleShiftDown)
      }
    },

    showFlyout () {
      if (this.showFlyout) {
        document.body.addEventListener('keydown', this.handleKeyDown)
      } else {
        document.body.removeEventListener('keydown', this.handleKeyDown)
      }
    },

    value () {
      this.updateTime(this.value)
    }
  },

  methods: {
    closeFlyout () {
      if (this.showFlyout) {
        this.toggleFlyout()
      }
    },

    focusHour () {
      const hourId = `hour:${this.currentHour}`
      const hourEl = document.getElementById(hourId)
      hourEl.focus()
    },

    handleBlur () {
      if (this.isInputFocused) {
        this.toggleInputFocus()
      }
    },

    /**
     * If a minValue is set, and the minHour is selected, and the currently selected minutes are not included in the
     * available minutes, we set the selected minutes to the first value from availableMinutes
     * @param hour
     * @param minutes
     * @return {*}
     */
    handleCurrentMinutesUnavailable (hour, minutes) {
      if (this.minValue !== '') {
        const minValues = this.minValue.split(':')
        const minHour = minValues[0]

        if (hour === minHour && !this.availableMinutes.includes(minutes)) {
          minutes = this.availableMinutes[0]
        }
      }

      return minutes
    },

    handleEnter (val) {
      if (val.target) {
        // On enter, there is a pointer event setting the value of the currently active element (why tho), so we only set the complementary value here
        if (this.isHour(val)) {
          this.handleInput(this.currentMinutes, 'minute')
        }
        if (this.isMinute(val)) {
          this.handleInput(this.currentHour, 'hour')
        }
      } else {
        this.handleInput(val)
      }

      if (this.showFlyout) {
        this.toggleFlyout()
      }
    },

    handleFocus () {
      if (!this.showFlyout) {
        this.toggleFlyout()
      }

      if (!this.isInputFocused) {
        this.toggleInputFocus()
      }
    },

    handleInput (val, type = '') {
      if (type === '') {
        this.updateTime(val)
      }
      if (type === 'hour') {
        this.setHour(val)
      }
      if (type === 'minute') {
        this.setMinutes(val)
      }
      this.$emit('input', this.currentTime)
    },

    handleKeyDown (e) {
      if (e.key === 'Escape') {
        this.toggleFlyout()
      }

      if ((e.key === ' ')) {
        e.preventDefault()
        this.handleEnter()
      }
    },

    handleReset () {
      this.handleInput(DEFAULT_TIME)
    },

    handleShiftDown (e) {
      if (e.shiftKey && (e.key === 'Down' || e.key === 'ArrowDown')) {
        this.focusHour()
      }
    },

    setHour (hour) {
      this.currentHour = hour
    },

    setMinutes (minutes) {
      this.currentMinutes = minutes
    },

    toggleFlyout () {
      this.showFlyout = !this.showFlyout
    },

    toggleInputFocus () {
      this.isInputFocused = !this.isInputFocused
    },

    updateTime (val) {
      const values = val.split(':')
      const hour = values[0]

      const minutes = this.handleCurrentMinutesUnavailable(hour, values[1])

      this.setHour(hour)
      this.setMinutes(minutes)
    }
  }
}
</script>
