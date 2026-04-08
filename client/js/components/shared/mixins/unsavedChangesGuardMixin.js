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
 * Components may optionally implement:
 * 1. `saveUnsavedChanges()` - Called when user clicks "Save" button
 * 2. `onDiscardChanges()` - Called when user clicks "Discard" button (stays on page)
 * 3. `onCancelNavigation()` - Called when user clicks "Cancel" button (stays on page)
 *
 * The mixin will:
 * - Show native browser dialog on page close/refresh/back
 * - Show global custom confirm dialog on in-page link navigation
 * - Execute appropriate callbacks based on user's choice
 *
 * **IMPORTANT**: This mixin only works in projects that have GlobalConfirmDialog rendered.
 * If GlobalConfirmDialog is not available, the mixin does nothing (navigation works normally).
 *
 * No need to import or add DpConfirmDialog to template - the global dialog is used automatically.
 */
import { showUnsavedChangesConfirm } from '@DpJs/lib/core/globalConfirm'

/**
 * Check if GlobalConfirmDialog component is available in the DOM
 */
function isGlobalConfirmDialogAvailable () {
  return document.querySelector('#globalConfirmDialog') !== null
}

export default {
  data () {
    return {
      isUnsavedChangesGuardActive: false,
      isNavigationConfirmed: false,
    }
  },

  computed: {
    /**
     * Components MUST implement this computed property.
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
     * Components SHOULD implement this method to save changes.
     * This will be called when user clicks "Save" in the confirm dialog.
     */
    async saveUnsavedChanges () {
      if (process.env.NODE_ENV === 'development') {
        console.warn(
          `Component "${this.$options.name || 'Unknown'}" is using unsavedChangesGuardMixin but hasn't implemented "saveUnsavedChanges" method.`
        )
      }
    },

    /**
     * This will be called when user clicks "Discard" in the confirm dialog.
     */
    async onDiscardChanges () {
      // Optional hook - no warning needed
    },

    /**
     * This will be called when user clicks "Cancel" in the confirm dialog.
     */
    async onCancelNavigation () {
      // Optional hook - no warning needed
    },

    /**
     * Shows browser's native "leave page" dialog when user tries to:
     * - Close the browser tab/window
     * - Refresh the page
     * - Use browser back/forward buttons
     */
    handleBeforeUnload (event) {
      if (this.hasUnsavedChanges && !this.isNavigationConfirmed) {
        event.preventDefault()
        event.returnValue = ''
      }
    },

    /**
     * Handles clicks on links within the page.
     * Shows global custom confirm dialog before allowing navigation to proceed.
     */
    handleLinkClick (event) {
      if (!this.hasUnsavedChanges) {
        return
      }

      const link = event.target.closest('a')

      if (!link?.href) {
        return
      }

      // Prevent default navigation
      event.preventDefault()
      event.stopPropagation()

      showUnsavedChangesConfirm()
        .then(action => {
          if (action === 'save') {
            const savePromise = this.saveUnsavedChanges()

            return savePromise.then(() => {
              this.isNavigationConfirmed = true
              globalThis.location.href = link.href
            })
          }

          if (action === 'discard') {
            return this.onDiscardChanges()
          }

          if (action === 'cancel') {
            return this.onCancelNavigation()
          }
        })
        .catch(() => this.onCancelNavigation())
    },
  },

  mounted () {
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
