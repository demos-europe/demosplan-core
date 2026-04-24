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

let isGuardActive = false
let isNavigationConfirmed = false
const registeredComponents = new Map()

/**
 * Global beforeunload handler
 */
function handleBeforeUnload (event) {
  const hasAnyUnsavedChanges = Array.from(registeredComponents.values()).some(
    component => component.hasUnsavedChanges(),
  )

  if (hasAnyUnsavedChanges && !isNavigationConfirmed) {
    event.preventDefault()
    event.returnValue = ''
  }
}

/**
 * Handle save action for all components with unsaved changes
 */
function handleSaveAllAndNavigate (link) {
  const componentsWithChanges = Array.from(registeredComponents.values()).filter(
    component => component.hasUnsavedChanges(),
  )

  const savePromises = componentsWithChanges.map(component => component.saveUnsavedChanges())

  return Promise.all(savePromises).then(() => {
    isNavigationConfirmed = true
    globalThis.location.href = link.href
  })
}

/**
 * Handle discard action for all components with unsaved changes
 */
function handleDiscardAll () {
  const componentsWithChanges = Array.from(registeredComponents.values()).filter(
    component => component.hasUnsavedChanges(),
  )

  const discardPromises = componentsWithChanges.map(component => component.onDiscardChanges())

  return Promise.all(discardPromises).then(() => {})
}

/**
 * Handle cancel action for all components with unsaved changes
 */
function handleCancelAll () {
  const componentsWithChanges = Array.from(registeredComponents.values()).filter(
    component => component.hasUnsavedChanges(),
  )

  const cancelPromises = componentsWithChanges.map(component => component.onCancelNavigation())

  return Promise.all(cancelPromises).then(() => {})
}

function handleDialogResult (action, link) {
  if (action === 'save') {
    return handleSaveAllAndNavigate(link)
  }

  if (action === 'discard') {
    return handleDiscardAll()
  }

  if (action === 'cancel') {
    return handleCancelAll()
  }

  return Promise.resolve()
}

/**
 * Global link click handler
 */
function handleLinkClick (event) {
  const hasAnyUnsavedChanges = Array.from(registeredComponents.values()).some(
    component => component.hasUnsavedChanges(),
  )

  if (!hasAnyUnsavedChanges) {
    return
  }

  const link = event.target.closest('a')

  if (!link?.href) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  showUnsavedChangesConfirmDialog()
    .then(action => handleDialogResult(action, link))
    .catch(() => handleCancelAll())
}

/**
 * Initialize global event listeners (only once)
 */
function initializeGlobalListeners () {
  if (isGuardActive) {
    return
  }

  if (!isUnsavedChangesDialogAvailable()) {
    return
  }

  isGuardActive = true
  window.addEventListener('beforeunload', handleBeforeUnload)
  document.addEventListener('click', handleLinkClick, true)
}

/**
 * Remove global event listeners
 */
function removeGlobalListeners () {
  if (!isGuardActive) {
    return
  }

  window.removeEventListener('beforeunload', handleBeforeUnload)
  document.removeEventListener('click', handleLinkClick, true)
  isGuardActive = false
}

/**
 * Composable that prevents navigation when there are unsaved changes.
 *
 * Returns an init function that should be called from mounted() with component methods.
 * Returns a cleanup function that should be called from beforeUnmount().
 *
 * The composable uses a singleton pattern to ensure only ONE global event listener
 * handles unsaved changes across ALL components on the page.
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
 *       componentId: this._uid, // Unique component identifier
 *     })
 *   },
 *   beforeUnmount() {
 *     this.cleanupUnsavedChangesGuard()
 *   }
 * }
 */
export function useUnsavedChangesGuard () {
  let componentId = null

  /**
   * Initialize the unsaved changes guard with component-specific callbacks
   *
   * @param {Object} options - Configuration options
   * @param {Function} options.hasUnsavedChanges - Function that returns boolean indicating unsaved changes
   * @param {Function} [options.saveUnsavedChanges] - Optional function to save changes
   * @param {Function} [options.onDiscardChanges] - Optional function called when discarding changes
   * @param {Function} [options.onCancelNavigation] - Optional function called when canceling navigation
   * @param {string|number} [options.componentId] - Unique component identifier (e.g., component._uid)
   */
  const init = (options = {}) => {
    const {
      hasUnsavedChanges = () => false,
      saveUnsavedChanges = async () => {},
      onDiscardChanges = async () => {},
      onCancelNavigation = async () => {},
      componentId: providedComponentId = Math.random().toString(36),
    } = options

    componentId = providedComponentId

    // Register this component
    registeredComponents.set(componentId, {
      hasUnsavedChanges,
      saveUnsavedChanges,
      onDiscardChanges,
      onCancelNavigation,
    })

    // Initialize global listeners (only once for all components)
    initializeGlobalListeners()
  }

  /**
   * Cleanup function to unregister component and remove listeners if last one
   */
  const cleanup = () => {
    if (componentId !== null) {
      registeredComponents.delete(componentId)
    }

    // If no more components are registered, remove global listeners
    if (registeredComponents.size === 0) {
      removeGlobalListeners()
    }
  }

  return {
    init,
    cleanup,
  }
}
