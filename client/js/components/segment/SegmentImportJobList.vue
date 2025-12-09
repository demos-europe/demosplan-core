<template>
  <div>
    <!-- Loading State -->
    <dp-loading v-if="isLoading" />

    <template v-else>
      <!-- Last Refresh Timestamp -->
      <div
        v-if="lastRefreshAt"
        class="u-mb-0_5 color--grey">
        {{ Translator.trans('import.job.last_refresh') }}: {{ formatDateTime(lastRefreshAt) }}
      </div>

      <!-- Data Table (no pagination - shows last 20 jobs) -->
      <dp-data-table
        v-if="items.length > 0"
        :header-fields="headerFields"
        :items="items"
        data-cy="segmentImportJobList">

        <!-- Job ID Column -->
        <template v-slot:id="{ id }">
          <span class="u-1-of-8-palm">{{ id.substring(0, 8) }}</span>
        </template>

        <!-- Status Column with Icons -->
        <template v-slot:status="rowData">
          <dp-contextual-help
            v-if="rowData.status === 'pending'"
            icon="clock"
            :text="Translator.trans('import.job.status.pending')" />
          <dp-contextual-help
            v-else-if="rowData.status === 'processing'"
            icon="hourglass"
            :text="Translator.trans('import.job.status.processing')" />
          <dp-contextual-help
            v-else-if="rowData.status === 'completed'"
            icon="check"
            color="success"
            :text="Translator.trans('import.job.status.completed')" />
          <dp-contextual-help
            v-else-if="rowData.status === 'failed'"
            icon="times"
            color="error"
            :text="Translator.trans('import.job.status.failed')" />
        </template>

        <!-- Result Column -->
        <template v-slot:result="rowData">
          <span v-if="rowData.status === 'completed' && rowData.result">
            {{ rowData.result.statements || 0 }} {{ Translator.trans('statements') }} , {{ rowData.result.segments || 0 }} {{ Translator.trans('segments') }}
          </span>
          <details v-else-if="rowData.status === 'failed' && rowData.error">
            <summary>{{ Translator.trans('import.job.error') }}</summary>
            <pre class="u-mt-0_5">{{ rowData.error }}</pre>
          </details>
          <span v-else>-</span>
        </template>

        <!-- Timestamps -->
        <template v-slot:createdAt="{ createdAt }">
          {{ formatDateTime(createdAt) }}
        </template>
        <template v-slot:lastActivityAt="{ lastActivityAt }">
          {{ lastActivityAt ? formatDateTime(lastActivityAt) : '-' }}
        </template>
      </dp-data-table>

      <p v-else class="u-mt">
        {{ Translator.trans('import.job.waiting') }}
      </p>
    </template>
  </div>
</template>

<script>
import { DpContextualHelp, DpDataTable, DpLoading, formatDate } from '@demos-europe/demosplan-ui'

export default {
  name: 'SegmentImportJobList',

  components: {
    DpContextualHelp,
    DpDataTable,
    DpLoading
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },
    initUrl: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      items: [],
      isLoading: true,
      isInitialLoad: true,        // Track if this is the first load
      lastRefreshAt: null,        // Track last refresh time
      pollInterval: 5000,        // Start at 5 seconds (adaptive polling)
      maxPollInterval: 60000,    // Max 60 seconds
      pollTimeoutId: null         // Use setTimeout for adaptive intervals
    }
  },

  computed: {
    headerFields () {
      return [
        { field: 'id', label: Translator.trans('import.job.id') },
        { field: 'fileName', label: Translator.trans('import.job.filename') },
        { field: 'status', label: Translator.trans('import.job.status') },
        { field: 'createdAt', label: Translator.trans('import.job.created') },
        { field: 'lastActivityAt', label: Translator.trans('import.job.last_activity') },
        { field: 'result', label: Translator.trans('import.job.result') }
      ]
    },

    hasActiveJobs () {
      return this.items.some(item =>
        item.status === 'pending' || item.status === 'processing'
      )
    }
  },

  methods: {
    async fetchJobs () {
      // Skip fetch if tab is hidden to save resources
      if (document.hidden) {
        return
      }

      // Only show loading spinner on initial load, not during polling
      if (this.isInitialLoad) {
        this.isLoading = true
      }

      try {
        const response = await fetch(this.initUrl)
        const data = await response.json()

        this.items = data.items  // Last 20 jobs from backend
        this.lastRefreshAt = new Date()  // Update last refresh timestamp

        // Manage polling based on active jobs
        if (this.hasActiveJobs) {
          this.startPolling()
        } else {
          this.stopPolling()
        }
      } catch (error) {
        console.error('Failed to fetch import jobs:', error)
        dplan.notify.error(Translator.trans('error.generic'))
      } finally {
        if (this.isInitialLoad) {
          this.isLoading = false
          this.isInitialLoad = false
        }
      }
    },

    startPolling () {
      // Stop existing timeout if any
      this.stopPolling()

      // Only poll if there are active jobs and tab is visible
      const hasActiveJobs = this.items.some(
        item => ['pending', 'processing'].includes(item.status)
      )

      if (!hasActiveJobs || document.hidden) {
        return
      }

      // Adaptive polling with exponential backoff (5s → 60s)
      this.pollTimeoutId = setTimeout(() => {
        this.fetchJobs().then(() => {
          // Gradually increase interval (1.2x multiplier)
          this.pollInterval = Math.min(
            this.pollInterval * 1.2,
            this.maxPollInterval
          )
          this.startPolling()  // Recursively schedule next poll
        })
      }, this.pollInterval)
    },

    stopPolling () {
      if (this.pollTimeoutId) {
        clearTimeout(this.pollTimeoutId)
        this.pollTimeoutId = null
      }
    },

    handleVisibilityChange () {
      if (document.hidden) {
        // Tab hidden - stop polling to save resources
        this.stopPolling()
      } else {
        // Tab visible - reset to fast polling and refresh immediately
        this.pollInterval = 5000
        this.isInitialLoad = true  // Show loading spinner when returning to tab
        this.fetchJobs()
        this.startPolling()
      }
    },

    formatDateTime (dateTimeString) {
      if (!dateTimeString) {
        return '-'
      }

      return formatDate(dateTimeString, 'long')
    }
  },

  mounted () {
    this.fetchJobs()

    // Listen for tab visibility changes to optimize polling
    document.addEventListener('visibilitychange', this.handleVisibilityChange)
  },

  beforeDestroy () {
    // Clean up polling interval and event listener
    this.stopPolling()
    document.removeEventListener('visibilitychange', this.handleVisibilityChange)
  }
}
</script>
