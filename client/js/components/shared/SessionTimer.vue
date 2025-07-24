<template>
  <div
    v-if="shouldShowTimer"
    :class="prefixClass('flex items-baseline space-x-1')"
  >
    <i
      :class="[prefixClass('fa'), prefixClass('fa-clock-o opacity-80')]"
      aria-hidden="true"
    />
    <span
      :class="{ 'color-message-warning-text': isWarning }"
    >
      {{ displayTime }}
    </span>
  </div>
</template>

<script>
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

// Time constants to avoid magic numbers
const millisecondsPerSecond = 1000
const millisecondsPerMinute = 60 * millisecondsPerSecond
const millisecondsPerHour = 60 * millisecondsPerMinute
const testWarning10Minutes = 119 * millisecondsPerMinute // For testing - will be 10 * millisecondsPerMinute in production
const testWarning3Minutes = 118 * millisecondsPerMinute  // For testing - will be 3 * millisecondsPerMinute in production
const updateInterval = millisecondsPerSecond

export default {
  name: 'SessionTimer',

  mixins: [prefixClassMixin],

  props: {
    showNotifications: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  data() {
    return {
      intervalId: null,
      timeLeft: 0,
      warningsShown: new Set(),
      warning3minLeft: 0,
      warning10minLeft: 0
    }
  },

  computed: {
    shouldShowTimer () {
      return this.hasPermission('feature_auto_logout_warning') && this.isValidSession()
    },

    displayTime () {
      if (this.timeLeft <= 0) {
        return '00:00'
      }

      const timeComponents = this.getTimeUnits(this.timeLeft)
      return this.formatTimeString(timeComponents)
    },

    isWarning () {
      // Use reactive timeLeft instead of non-reactive Date.now()
      return this.timeLeft <= testWarning10Minutes
    }
  },

  mounted () {
    if (this.shouldShowTimer) {
      this.initializeTimer()
    }
  },

  beforeUnmount () {
    this.cleanup()
  },

  methods: {
    isValidSession () {
      return this.dplan?.loggedIn && this.dplan?.expirationTimestamp
    },

    getTimeUnits (milliseconds) {
      const hours = Math.floor(milliseconds / millisecondsPerHour)
      const minutes = Math.floor((milliseconds % millisecondsPerHour) / millisecondsPerMinute)
      const seconds = Math.floor((milliseconds % millisecondsPerMinute) / millisecondsPerSecond)
      return { hours, minutes, seconds }
    },

    formatTimeString ({ hours, minutes, seconds }) {
      const pad = (num) => String(num).padStart(2, '0')
      return hours > 0
        ? `${ hours }:${ pad(minutes) }:${ pad(seconds) }`
        : `${ pad(minutes) }:${ pad(seconds) }`
    },

    initializeTimer () {
      const timestampInMsecs = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.warning10minLeft = timestampInMsecs - testWarning10Minutes // 119 min (for testing)
      this.warning3minLeft = timestampInMsecs - testWarning3Minutes  // 118 min
      this.updateTimer()
      this.intervalId = setInterval(this.updateTimer, updateInterval)
    },

    updateTimer () {
      if (!this.isValidSession()) {
        this.cleanup()
        return
      }

      const sessionExpiration = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.timeLeft = sessionExpiration - Date.now()
      this.checkWarnings()
    },

    checkWarnings () {
      const now = Date.now()

      if (now >= this.warning10minLeft && !this.warningsShown.has('10min')) {
        this.showWarning(this.Translator.trans('session.expiration.warning', { minutes: 10 }))
        this.warningsShown.add('10min')
      }

      if (now >= this.warning3minLeft && !this.warningsShown.has('3min')) {
        this.showWarning(this.Translator.trans('session.expiration.warning', { minutes: 3 }))
        this.warningsShown.add('3min')
      }
    },

    showWarning (message) {
      if (this.showNotifications && this.dplan?.notify?.info) {
        this.dplan.notify.info(message)
      }
    },

    cleanup () {
      if (this.intervalId) {
        clearInterval(this.intervalId)
        this.intervalId = null
      }
    }
  }
}
</script>


