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
     * @returns {Promise<boolean>} - Resolves to true if confirmed, false if declined
     */
    async show ({ message, confirmButtonText, declineButtonText }) {
      this.message = message
      this.confirmButtonText = confirmButtonText
      this.declineButtonText = declineButtonText

      return this.$refs.confirmDialog.open()
    },
  },

  mounted () {
    // Listen for global confirm dialog requests
    document.addEventListener('global-confirm-dialog:show', async (event) => {
      try {
        const result = await this.show(event.detail)
        // Dispatch result event
        const resultEvent = new CustomEvent('global-confirm-dialog:result', {
          detail: { confirmed: result },
        })
        document.dispatchEvent(resultEvent)
      } catch (error) {
        // User cancelled or error occurred
        const resultEvent = new CustomEvent('global-confirm-dialog:result', {
          detail: { confirmed: false },
        })
        document.dispatchEvent(resultEvent)
      }
    })
  },
}
</script>
