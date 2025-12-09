<template>
  <div>
    <!-- Loading State -->
    <dp-loading v-if="isLoading" />

    <template v-else>
      <!-- Last Refresh Timestamp -->
      <div
        v-if="lastRefreshAt"
        class="mb-2 text-muted">
        {{ Translator.trans('import.job.last_refresh') }}: {{ formatDateTime(lastRefreshAt) }}
      </div>

      <!-- Data Table (no pagination - shows last 20 jobs) -->
      <dp-data-table
        v-if="items.length > 0"
        :header-fields="headerFields"
        :items="items"
        is-expandable
        track-by="id"
        data-cy="segmentImportJobList">

        <!-- Job ID Column -->
        <template v-slot:id="{ id }">
          <span class="u-1-of-8-palm">{{ id.substring(0, 8) }}</span>
        </template>

        <!-- Status Column with Icons -->
        <template v-slot:status="rowData">
          <div class="text-center">
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
              :text="Translator.trans('terminated')" />
            <dp-contextual-help
              v-else-if="rowData.status === 'failed'"
              icon="warning"
              :text="Translator.trans('error')" />
          </div>
        </template>

        <!-- Result Column -->
        <template v-slot:result="rowData">
          <span v-if="rowData.status === 'completed' && rowData.result">
            {{ rowData.result.statements || 0 }} {{ Translator.trans('statements') }}, {{ rowData.result.segments || 0 }} {{ Translator.trans('segments') }}
          </span>
          <span v-else-if="rowData.status === 'failed'">
           {{ Translator.trans('error.occurred') }}
          </span>
          <span v-else>-</span>
        </template>

        <!-- Expanded Row Content - Shows full error -->
        <template v-slot:expandedContent="rowData">
          <div
            v-if="rowData.status === 'failed' && rowData.error"
            class="px-1 pb-1">
            <strong class="mb-1 block">{{ Translator.trans('import.job.result') }}:</strong>
            <dp-inline-notification
              :message="Translator.trans('error.occurred')"
              type="error">
              <pre class="m-0 mt-2 max-h-[200px] overflow-auto text-sm">{{ rowData.error }}</pre>
            </dp-inline-notification>
          </div>
        </template>

        <!-- Timestamps -->
        <template v-slot:createdAt="{ createdAt }">
          {{ formatDateTime(createdAt) }}
        </template>
        <template v-slot:lastActivityAt="{ lastActivityAt }">
          {{ lastActivityAt ? formatDateTime(lastActivityAt) : '-' }}
        </template>
      </dp-data-table>

      <p v-else class="mt-4">
        {{ Translator.trans('import.job.waiting') }}
      </p>
    </template>
  </div>
</template>

<script>
import {
  DpContextualHelp,
  DpDataTable,
  DpInlineNotification,
  DpLoading,
  formatDate
} from '@demos-europe/demosplan-ui'

export default {
  name: 'SegmentImportJobList',

  components: {
    DpContextualHelp,
    DpDataTable,
    DpInlineNotification,
    DpLoading
  },

  props: {
    initUrl: {
      type: String,
      required: true
    },
    procedureId: {
      type: String,
      required: true
    },

  },

  data () {
    return {
      items: [],
      isLoading: true,
      isInitialLoad: true,
      lastRefreshAt: null,
      pollInterval: 5000,        // Start at 5 seconds (adaptive polling)
      maxPollInterval: 60000,    // Max 60 seconds
      pollTimeoutId: null         // Use setTimeout for adaptive intervals
    }
  },

  computed: {
    hasActiveJobs () {
      return this.items.some(item =>
        item.status === 'pending' || item.status === 'processing'
      )
    },

    headerFields () {
      return [
        { field: 'id', label: Translator.trans('import.job.id') },
        { field: 'fileName', label: Translator.trans('file.name') },
        { field: 'status', label: Translator.trans('status') },
        { field: 'createdAt', label: Translator.trans('created') },
        { field: 'lastActivityAt', label: Translator.trans('import.job.last_activity') },
        { field: 'result', label: Translator.trans('result') }
      ]
    },
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

        // Disable expand buttons in rows without error, since there will be no content in the expanded row
        await this.$nextTick(() => {
          this.setExpandButtonStates()
        })

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

    /**
     * Disables expand buttons for rows without errors, since there will be no content in the expanded row
     * Enables expand buttons for rows with errors, since the error message will be displayed in the expanded row
      */
    setExpandButtonStates () {
      this.items.forEach((item, index) => {
        const expandButton = this.$el.querySelector(`[data-cy="isExpandableWrapTrigger:${index}"]`)

        if (expandButton) {
          const hasError = item.status === 'failed' && item.error

          // Remove old event listener if it exists
          if (expandButton._clickHandler) {
            expandButton.removeEventListener('click', expandButton._clickHandler, true)
          }

          const tdElement = expandButton.closest('td')

          if (hasError) {
            expandButton.classList.remove('opacity-50')
            expandButton.setAttribute('aria-disabled', 'false')
            expandButton.style.cursor = ''
            // Restore title on td if available
            if (tdElement && tdElement._originalTitle) {
              tdElement.setAttribute('title', tdElement._originalTitle)
            }
          } else {
            expandButton.classList.add('opacity-50')
            expandButton.setAttribute('aria-disabled', 'true')
            expandButton.style.cursor = 'default'
            // Store title attribute from td and remove it
            if (tdElement && tdElement.hasAttribute('title')) {
              tdElement._originalTitle = tdElement.getAttribute('title')
              tdElement.removeAttribute('title')
            }

            // Prevent click for non-error rows
            expandButton._clickHandler = (e) => {
              e.preventDefault()
              e.stopPropagation()
              e.stopImmediatePropagation()
            }

            expandButton.addEventListener('click', expandButton._clickHandler, true)
          }
        }
      })
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

  updated () {
    // Update expand buttons whenever component re-renders
    this.$nextTick(() => {
      this.setExpandButtonStates()
    })
  },

  beforeUnmount () {
    // Clean up polling interval and event listener
    this.stopPolling()
    document.removeEventListener('visibilitychange', this.handleVisibilityChange)
  }
}
</script>
