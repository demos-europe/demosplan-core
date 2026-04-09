/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Mixin that prevents navigation when there are unsaved changes.
 *
 * Components using this mixin must implement:
 * - `hasUnsavedChanges` (computed): returns whether the component has unsaved changes
 *
 * Components may optionally override:
 * - `saveUnsavedChanges()`: called when the user clicks "Save"
 * - `onDiscardChanges()`: called when the user clicks "Discard" (stays on the page)
 * - `onCancelNavigation()`: called when the user clicks "Cancel" (stays on the page)
 *
 * The mixin shows the native browser dialog on page unload and a custom unsaved changes dialog on link navigation.
 *
 * IMPORTANT: Works only if `UnsavedChangesDialog` is rendered.
 * If it is not available, the mixin stays inactive and navigation works normally.
 */

/**
 * Check if UnsavedChangesDialog component is available in the DOM
 */
function isUnsavedChangesDialogAvailable () {
  return document.querySelector('#unsavedChangesDialog') !== null
}

/**
 * @returns {Promise<'save' | 'discard' | 'cancel'>}
 */
function showUnsavedChangesConfirmDialog () {
  return new Promise((resolve) => {
    const handleResult = (event) => {
      document.removeEventListener('unsaved-changes-dialog:result', handleResult)
      resolve(event.detail.action)
    }

    document.addEventListener('unsaved-changes-dialog:result', handleResult)
    document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:show'))
  })
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
          `Component "${this.$options.name || 'Unknown'}" is using unsavedChangesGuardMixin but hasn't implemented "hasUnsavedChanges" computed property.`,
        )
      }
      return false
    },
  },

  methods: {
    async saveUnsavedChanges () {
      if (process.env.NODE_ENV === 'development') {
        console.warn(
          `Component "${this.$options.name || 'Unknown'}" is using unsavedChangesGuardMixin but hasn't implemented "saveUnsavedChanges" method.`,
        )
      }
    },

    async onDiscardChanges () {},

    async onCancelNavigation () {},

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

      event.preventDefault()
      event.stopPropagation()

      showUnsavedChangesConfirmDialog()
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
    if (!isUnsavedChangesDialogAvailable()) {
      return
    }

    this.isUnsavedChangesGuardActive = true

    window.addEventListener('beforeunload', this.handleBeforeUnload)
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
