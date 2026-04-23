/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
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

/**
 * Composable that prevents navigation when there are unsaved changes.
 *
 * Returns an init function that should be called from mounted() with component methods.
 * Returns a cleanup function that should be called from beforeUnmount().
 *
 * The composable shows the native browser dialog on page unload and a custom unsaved changes dialog on link navigation.
 *
 * IMPORTANT: Works only if `UnsavedChangesDialog` is rendered.
 * If it is not available, the composable stays inactive and navigation works normally.
 *
 * @returns {Object} { init, cleanup }
 *
 * @example
 * import { useUnsavedChangesGuard } from '@DpJs/composables/useUnsavedChangesGuard'
 *
 * export default {
 *   setup() {
 *     const { init, cleanup } = useUnsavedChangesGuard()
 *
 *     return {
 *       initUnsavedChangesGuard: init,
 *       cleanupUnsavedChangesGuard: cleanup,
 *     }
 *   },
 *   computed: {
 *     hasUnsavedChanges() {
 *       return this.myData !== this.originalData
 *     }
 *   },
 *   methods: {
 *     saveUnsavedChanges() {
 *       return this.save()
 *     },
 *     onDiscardChanges() {
 *       this.reset()
 *     }
 *   },
 *   mounted() {
 *     this.initUnsavedChangesGuard({
 *       hasUnsavedChanges: () => this.hasUnsavedChanges,
 *       saveUnsavedChanges: () => this.saveUnsavedChanges(),
 *       onDiscardChanges: () => this.onDiscardChanges(),
 *       componentName: 'MyComponent'
 *     })
 *   },
 *   beforeUnmount() {
 *     this.cleanupUnsavedChangesGuard()
 *   }
 * }
 */
export function useUnsavedChangesGuard () {
  let isUnsavedChangesGuardActive = false
  let isNavigationConfirmed = false
  let handleBeforeUnload = null
  let handleLinkClick = null

  /**
   * Initialize the unsaved changes guard with component-specific callbacks
   *
   * @param {Object} options - Configuration options
   * @param {Function} options.hasUnsavedChanges - Function that returns boolean indicating unsaved changes
   * @param {Function} [options.saveUnsavedChanges] - Optional function to save changes
   * @param {Function} [options.onDiscardChanges] - Optional function called when discarding changes
   * @param {Function} [options.onCancelNavigation] - Optional function called when canceling navigation
   * @param {string} [options.componentName] - Component name for debugging
   */
  const init = (options = {}) => {
    const {
      hasUnsavedChanges = () => false,
      saveUnsavedChanges = async () => {
        if (process.env.NODE_ENV === 'development') {
          console.warn(
            `Component "${options.componentName || 'Unknown'}" is using useUnsavedChangesGuard but hasn't implemented "saveUnsavedChanges" method.`,
          )
        }
      },
      onDiscardChanges = async () => {},
      onCancelNavigation = async () => {},
    } = options
    66
    if (!isUnsavedChangesDialogAvailable()) {
      return
    }

    isUnsavedChangesGuardActive = true

    /**
     * Shows browser's native "leave page" dialog when user tries to:
     * - Close the browser tab/window
     * - Refresh the page
     * - Use browser back/forward buttons
     */
    handleBeforeUnload = (event) => {
      if (hasUnsavedChanges() && !isNavigationConfirmed) {
        event.preventDefault()
        event.returnValue = ''
      }
    }

    /**
     * Handles clicks on links within the page.
     * Shows global custom confirm dialog before allowing navigation to proceed.
     */
    handleLinkClick = (event) => {
      if (!hasUnsavedChanges()) {
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
            const savePromise = saveUnsavedChanges()

            return savePromise.then(() => {
              isNavigationConfirmed = true
              globalThis.location.href = link.href
            })
          }

          if (action === 'discard') {
            return onDiscardChanges()
          }

          if (action === 'cancel') {
            return onCancelNavigation()
          }
        })
        .catch(() => onCancelNavigation())
    }

    window.addEventListener('beforeunload', handleBeforeUnload)
    document.addEventListener('click', handleLinkClick, true)
  }

  /**
   * Cleanup function to remove event listeners
   * Should be called from beforeUnmount()
   */
  const cleanup = () => {
    if (!isUnsavedChangesGuardActive) {
      return
    }

    if (handleBeforeUnload) {
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }

    if (handleLinkClick) {
      document.removeEventListener('click', handleLinkClick, true)
    }

    isUnsavedChangesGuardActive = false
  }

  return {
    init,
    cleanup,
  }
}
