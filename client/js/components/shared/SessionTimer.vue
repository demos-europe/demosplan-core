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

    <dp-contextual-help
      :class="prefixClass('self-center')"
      :text="Translator.trans('time.left.till.automatic.logout')"
    />
  </div>
</template>

<script>
import { DpContextualHelp, prefixClassMixin } from '@demos-europe/demosplan-ui'

const millisecondsPerSecond = 1000
const millisecondsPerMinute = 60 * millisecondsPerSecond
const millisecondsPerHour = 60 * millisecondsPerMinute

export default {
  name: 'SessionTimer',

  components: {
    DpContextualHelp
  },

  mixins: [ prefixClassMixin ],

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
    displayTime () {
      if (this.timeLeft <= 0) {
        return '00:00'
      }

      const timeComponents = this.getTimeUnits(this.timeLeft)
      return this.formatTimeString(timeComponents)
    },

    isWarning () {
      return this.timeLeft <= 119 * millisecondsPerMinute
    },

    shouldShowTimer () {
      return this.dplan?.expirationTimestamp > 0
    }
  },

  methods: {
    checkWarnings () {
      const now = Date.now()

      if (now >= this.warning10minLeft && !this.warningsShown.has('10min')) {
        this.showWarning(Translator.trans('session.expiration.warning', { minutes: 10 }))
        this.warningsShown.add('10min')
      }

      if (now >= this.warning3minLeft && !this.warningsShown.has('3min')) {
        this.showWarning(Translator.trans('session.expiration.warning', { minutes: 3 }))
        this.warningsShown.add('3min')
      }
    },

    cleanup () {
      if (this.intervalId) {
        clearInterval(this.intervalId)
        this.intervalId = null
      }
    },

    formatTimeString ({ hours, minutes, seconds }) {
      const pad = (num) => String(num).padStart(2, '0')
      return hours > 0
        ? `${ hours }:${ pad(minutes) }:${ pad(seconds) }`
        : `${ pad(minutes) }:${ pad(seconds) }`
    },

    getTimeUnits (milliseconds) {
      const hours = Math.floor(milliseconds / millisecondsPerHour)
      const minutes = Math.floor((milliseconds % millisecondsPerHour) / millisecondsPerMinute)
      const seconds = Math.floor((milliseconds % millisecondsPerMinute) / millisecondsPerSecond)
      return { hours, minutes, seconds }
    },

    initializeTimer () {
      const timestampInMsecs = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.warning10minLeft = timestampInMsecs - (119 * millisecondsPerMinute) // for testing - will be 10
      this.warning3minLeft = timestampInMsecs - (118 * millisecondsPerMinute) // will be 3
      this.updateTimer()
      this.intervalId = setInterval(this.updateTimer, millisecondsPerSecond)
    },

    showWarning (message) {
      if (this.showNotifications && this.dplan?.notify?.info) {
        this.dplan.notify.info(message)
      }
    },

    updateTimer () {
      if (!this.dplan?.expirationTimestamp) {
        this.cleanup()
        return
      }

      const sessionExpiration = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.timeLeft = sessionExpiration - Date.now()
      this.checkWarnings()
    }
  },

  mounted () {
    if (this.shouldShowTimer) {
      this.initializeTimer()
    }
  },

  beforeUnmount () {
    this.cleanup()
  }
}
</script>


