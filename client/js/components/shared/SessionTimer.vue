<template>
  <div
    v-if="shouldShowTimer"
    :class="prefixClass('flex items-baseline space-x-1')"
  >
    <dp-icon
      :class="[prefixClass('self-center'), { 'text-message-severe': isWarning }]"
      aria-hidden="true"
      icon="clock"
    />

    <span :class="{ [prefixClass('text-message-severe')]: isWarning }">
      {{ displayTime }}
    </span>

    <dp-contextual-help
      :class="prefixClass('self-center')"
      :text="Translator.trans('time.left.till.automatic.logout')"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpIcon, prefixClassMixin } from '@demos-europe/demosplan-ui'

const millisecondsPerSecond = 1000
const millisecondsPerMinute = 60 * millisecondsPerSecond
const millisecondsPerHour = 60 * millisecondsPerMinute

export default {
  name: 'SessionTimer',

  components: {
    DpContextualHelp,
    DpIcon,
  },

  mixins: [prefixClassMixin],

  data () {
    return {
      intervalId: null,
      timeLeft: 0,
      warningsShown: new Set(),
      threeMinutesThreshold: 0,
      tenMinutesThreshold: 0,
    }
  },

  computed: {
    displayTime () {
      if (this.timeLeft <= 0) {
        return '00:00'
      }

      const timeUnits = this.getTimeUnits(this.timeLeft)

      return this.formatTimeString(timeUnits)
    },

    isWarning () {
      return this.timeLeft <= 3 * millisecondsPerMinute
    },

    shouldShowTimer () {
      return this.dplan?.expirationTimestamp > 0
    },
  },

  methods: {
    checkWarnings () {
      const now = Date.now()

      if (now >= this.tenMinutesThreshold && !this.warningsShown.has('10min')) {
        this.showWarning(Translator.trans('session.expiration.warning', { minutes: 10 }))
        this.warningsShown.add('10min')
      }

      if (now >= this.threeMinutesThreshold && !this.warningsShown.has('3min')) {
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

      return hours > 0 ?
        `${hours}:${pad(minutes)}:${pad(seconds)}` :
        `${pad(minutes)}:${pad(seconds)}`
    },

    getTimeUnits (milliseconds) {
      const hours = Math.floor(milliseconds / millisecondsPerHour)
      const minutes = Math.floor((milliseconds % millisecondsPerHour) / millisecondsPerMinute)
      const seconds = Math.floor((milliseconds % millisecondsPerMinute) / millisecondsPerSecond)

      return { hours, minutes, seconds }
    },

    async handleSessionTimeout () {
      try {
        window.location.href = this.Routing.generate('DemosPlan_user_logout')
      } catch (error) {
        console.error('Session timeout logout failed:', error)
      }
    },

    initializeTimer () {
      const timestampInMsecs = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.tenMinutesThreshold = timestampInMsecs - (10 * millisecondsPerMinute)
      this.threeMinutesThreshold = timestampInMsecs - (3 * millisecondsPerMinute)
      this.updateTimer()
      this.intervalId = setInterval(this.updateTimer, millisecondsPerSecond)
    },

    showWarning (message) {
      if (this.dplan?.notify?.info) {
        this.dplan.notify.info({ message, persist: true })
      }
    },

    updateTimer () {
      if (!this.dplan?.expirationTimestamp) {
        this.cleanup()

        return
      }

      const sessionExpiration = this.dplan.expirationTimestamp * millisecondsPerSecond
      this.timeLeft = sessionExpiration - Date.now()

      if (this.timeLeft <= 0) {
        this.cleanup()
        this.handleSessionTimeout()

        return
      }

      this.checkWarnings()
    },
  },

  mounted () {
    if (this.shouldShowTimer) {
      this.initializeTimer()
    }
  },

  beforeUnmount () {
    this.cleanup()
  },
}
</script>
