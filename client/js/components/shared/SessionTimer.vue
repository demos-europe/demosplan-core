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
import { DpIcon, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'SessionTimer',

  mixins: [prefixClassMixin],

  components: {
    DpIcon
  },

  props: {
    showNotifications: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  data() {
    return {
      timeLeft: 0,
      intervalId: null,
      warningsShown: new Set(),
      initialDuration: 0,
      warning3minLeft: 0,
      warning10minLeft: 0
    }
  },

  computed: {
    shouldShowTimer() {
      return this.hasPermission('feature_auto_logout_warning') &&
             this.dplan?.loggedIn &&
             this.dplan?.expirationTimestamp
    },

    displayTime() {
      if (this.timeLeft <= 0) return '00:00'

      const hours = Math.floor(this.timeLeft / (60 * 60 * 1000))
      const minutes = Math.floor((this.timeLeft % (60 * 60 * 1000)) / (60 * 1000))
      const seconds = Math.floor((this.timeLeft % (60 * 1000)) / 1000)

      if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
      }

      return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
    },

    totalMinutesLeft() {
      return Math.floor(this.timeLeft / (60 * 1000))
    },

    isWarning() {
      return this.totalMinutesLeft <= 10
    }
  },

  mounted() {
    if (this.shouldShowTimer) {
      this.initializeTimer()
    }
  },

  beforeUnmount() {
    this.cleanup()
  },

  methods: {
    initializeTimer() {
      const timestampInMsecs = this.dplan.expirationTimestamp * 1000
      const now = Date.now()
      this.initialDuration = timestampInMsecs - now
      const initialTotalMinutes = Math.floor(this.initialDuration / (60 * 1000))

      this.warning10minLeft = initialTotalMinutes - 1
      this.warning3minLeft = initialTotalMinutes - 2

      this.updateTimer()
      this.intervalId = setInterval(this.updateTimer, 1000)
    },

    updateTimer() {
      if (!this.dplan?.expirationTimestamp) {
        this.cleanup()
        return
      }

      const sessionExpiration = this.dplan.expirationTimestamp * 1000
      this.timeLeft = sessionExpiration - Date.now()

      this.checkWarnings()
    },

    checkWarnings() {
      if (this.totalMinutesLeft === this.warning10minLeft && !this.warningsShown.has('10min')) {
        this.showWarning(this.Translator.trans('session.expiration.warning', {minutes: 10}))
        this.warningsShown.add('10min')
      }

      if (this.totalMinutesLeft === this.warning3minLeft && !this.warningsShown.has('3min')) {
        this.showWarning(this.Translator.trans('session.expiration.warning', {minutes: 3}))
        this.warningsShown.add('3min')
      }
    },

    showWarning(message) {
      if (this.showNotifications && this.dplan?.notify?.info) {
        this.dplan.notify.info(message)
      }
    },

    cleanup() {
      if (this.intervalId) {
        clearInterval(this.intervalId)
        this.intervalId = null
      }
    }
  }
}
</script>


