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
 * **IMPORTANT - USE IN PARENT COMPONENTS ONLY**:
 * - Only add this mixin to ONE parent/container component per page
 * - DO NOT add this mixin to multiple child components on the same page
 * - Parent component should aggregate `hasUnsavedChanges` from all children
 * - Parent component should coordinate save/discard actions for all children
 *
 * Components using this mixin must:
 * 1. Implement a `hasUnsavedChanges` computed property that returns a boolean
 *    - For parent components: aggregate from all child components
 *    - Example: `return this.$refs.child1?.hasUnsavedChanges || this.$refs.child2?.hasUnsavedChanges`
 *
 * Components may optionally implement:
 * 1. `saveUnsavedChanges()` - Called when user clicks "Save" button
 *    - For parent components: should call save methods on all child components with changes
 *    - Example: `return Promise.all([this.$refs.child1.save(), this.$refs.child2.save()])`
 * 2. `onDiscardChanges()` - Called when user clicks "Discard" button (before navigation)
 *    - For parent components: should call discard/reset methods on all child components
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
 * @returns {boolean}
 */
function isGlobalConfirmDialogAvailable () {
  return document.querySelector('#globalConfirmDialog') !== null
}

export default {
  data () {
    return {
      /**
       * Flag to track if the mixin is active in this project
       */
      isUnsavedChangesGuardActive: false,
      /**
       * Flag to prevent beforeunload when intentionally navigating
       */
      allowNavigation: false,
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
     * Components SHOULD implement this method to save changes.
     * This will be called when user clicks "Save" in the confirm dialog.
     * If not implemented, the "Save" option will not be available.
     *
     * @returns {Promise<void>}
     */
    async saveUnsavedChanges () {
      if (process.env.NODE_ENV === 'development') {
        console.warn(
          `Component "${this.$options.name || 'Unknown'}" is using unsavedChangesGuardMixin but hasn't implemented "saveUnsavedChanges" method.`
        )
      }
    },

    /**
     * Components MAY implement this method to perform custom actions when user discards changes.
     * This will be called when user clicks "Discard" in the confirm dialog.
     * Called AFTER the user has confirmed they want to discard, but BEFORE navigation.
     *
     * @returns {Promise<void>}
     */
    async onDiscardChanges () {
      // Optional hook - no warning needed
    },

    /**
     * Components MAY implement this method to perform custom actions when user cancels navigation.
     * This will be called when user clicks "Cancel" in the confirm dialog.
     * Called when the user decides to stay on the current page.
     *
     * @returns {Promise<void>}
     */
    async onCancelNavigation () {
      // Optional hook - no warning needed
    },

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
      if (this.hasUnsavedChanges && !this.allowNavigation) {
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
    handleLinkClick (event) {
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

      showUnsavedChangesConfirm()
        .then(action => {
          if (action === 'save') {
            // User wants to save changes - call the save method
            const savePromise = this.saveUnsavedChanges ? this.saveUnsavedChanges() : Promise.resolve()

            return savePromise.then(() => {
              this.allowNavigation = true
              window.location.href = target.href
            })
          } else if (action === 'discard') {
            if (this.onDiscardChanges) {
              return this.onDiscardChanges()
            }
          } else if (action === 'cancel') {
            if (this.onCancelNavigation) {
              return this.onCancelNavigation()
            }
          }
        })
        .catch(error => {
          console.debug('Navigation cancelled by user', error)
          // On error, also call cancel hook
          if (this.onCancelNavigation) {
            return this.onCancelNavigation()
          }
        })
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
