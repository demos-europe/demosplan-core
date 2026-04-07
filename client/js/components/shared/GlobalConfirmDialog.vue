<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-confirm-dialog
    id="globalConfirmDialog"
    ref="confirmDialog"
    :confirm-button-text="confirmButtonText"
    :decline-button-text="declineButtonText"
    :message="message"
    third-action-text="Änderung verwerfen"
    @handle-confirm="handleConfirm"
    @third-action="handleThirdAction"
  />
</template>

<script>
import { DpConfirmDialog } from '@demos-europe/demosplan-ui'

export default {
  name: 'GlobalConfirmDialog',

  components: {
    DpConfirmDialog,
  },

  data () {
    return {
      confirmButtonText: '',
      declineButtonText: '',
      message: '',
      currentResolver: null,
    }
  },

  methods: {
    /**
     * Show confirm dialog and return a promise
     * @param {Object} options
     * @param {string} options.message - The message to display
     * @param {string} options.confirmButtonText - Text for confirm button
     * @param {string} options.declineButtonText - Text for decline button
     * @returns {Promise<string>} - Resolves to 'save', 'discard', or 'cancel'
     */
    show ({ message, confirmButtonText, declineButtonText }) {
      this.message = message
      this.confirmButtonText = confirmButtonText
      this.declineButtonText = declineButtonText

      // Create a promise that will be resolved by the event handlers
      return new Promise((resolve) => {
        this.currentResolver = resolve
        this.$refs.confirmDialog.open()
      })
    },

    handleConfirm (isConfirmed) {
      if (isConfirmed) {
        if (this.currentResolver) {
          this.currentResolver('save')
          this.currentResolver = null
        }
      } else {
        if (this.currentResolver) {
          this.currentResolver('cancel')
          this.currentResolver = null
        }
      }
    },

    handleThirdAction () {
      if (this.currentResolver) {
        this.currentResolver('discard')
        this.currentResolver = null
      }
    },
  },

  mounted () {
    // Listen for global confirm dialog requests
    document.addEventListener('global-confirm-dialog:show', async (event) => {
      try {
        const result = await this.show(event.detail)
        // Dispatch result event with action (save/discard/cancel)
        const resultEvent = new CustomEvent('global-confirm-dialog:result', {
          detail: { action: result },
        })
        document.dispatchEvent(resultEvent)
      } catch (error) {
        // User cancelled or error occurred
        const resultEvent = new CustomEvent('global-confirm-dialog:result', {
          detail: { action: 'cancel' },
        })
        document.dispatchEvent(resultEvent)
      }
    })
  },
}
</script>
