/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Mixin to guard against navigation when there are unsaved changes.
 *
 * Components using this mixin must:
 * 1. Implement a `hasUnsavedChanges` computed property that returns a boolean
 *
 * The mixin will:
 * - Show native browser dialog on page close/refresh/back
 * - Show global custom confirm dialog on in-page link navigation
 *
 * **IMPORTANT**: This mixin only works in projects that have GlobalConfirmDialog rendered.
 * If GlobalConfirmDialog is not available, the mixin does nothing (navigation works normally).
 *
 * No need to import or add DpConfirmDialog to template - the global dialog is used automatically.
 */
import { showUnsavedChangesConfirm } from '@DpJs/lib/core/globalConfirm'

/**
 * Check if GlobalConfirmDialog component is available in the DOM
 * @returns {boolean}
 */
function isGlobalConfirmDialogAvailable () {
  return document.querySelector('global-confirm-dialog') !== null
}

export default {
  data () {
    return {
      /**
       * Flag to track if the mixin is active in this project
       */
      isUnsavedChangesGuardActive: false,
    }
  },

  computed: {
    /**
     * Components MUST implement this computed property.
     * Should return true if there are unsaved changes, false otherwise.
     *
     * @returns {boolean}
     */
    hasUnsavedChanges () {
      if (process.env.NODE_ENV === 'development') {
        console.warn(
          `Component "${this.$options.name || 'Unknown'}" is using unsavedChangesGuardMixin but hasn't implemented "hasUnsavedChanges" computed property.`
        )
      }
      return false
    },
  },

  methods: {
    /**
     * Handles the native browser beforeunload event.
     * Shows browser's native "leave page" dialog when user tries to:
     * - Close the browser tab/window
     * - Refresh the page
     * - Use browser back/forward buttons
     *
     * @param {Event} event - The beforeunload event
     */
    handleBeforeUnload (event) {
      if (this.hasUnsavedChanges) {
        event.preventDefault()
        event.returnValue = ''
      }
    },

    /**
     * Handles clicks on links within the page.
     * Shows global custom confirm dialog before allowing navigation to proceed.
     *
     * @param {Event} event - The click event
     */
    async handleLinkClick (event) {
      if (!this.hasUnsavedChanges) {
        return
      }

      const target = event.target.closest('a')
      if (!target || !target.href) {
        return
      }

      // Prevent default navigation
      event.preventDefault()
      event.stopPropagation()

      try {
        const confirmed = await showUnsavedChangesConfirm()

        if (confirmed) {
          window.removeEventListener('beforeunload', this.handleBeforeUnload)
          window.location.href = target.href
        }
      } catch (error) {
        console.debug('Navigation cancelled by user', error)
      }
    },
  },

  mounted () {
    // Check if GlobalConfirmDialog is available
    if (!isGlobalConfirmDialogAvailable()) {
      return
    }

    this.isUnsavedChangesGuardActive = true

    // Listen for browser-level navigation attempts (close, refresh, back)
    window.addEventListener('beforeunload', this.handleBeforeUnload)

    // Listen for link clicks (in-page navigation)
    document.addEventListener('click', this.handleLinkClick, true)
  },

  beforeUnmount () {
    if (!this.isUnsavedChangesGuardActive) {
      return
    }

    window.removeEventListener('beforeunload', this.handleBeforeUnload)
    document.removeEventListener('click', this.handleLinkClick, true)
  },
}
