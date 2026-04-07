/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Check if GlobalConfirmDialog component is available in the DOM
 * @returns {boolean}
 */
function isGlobalConfirmDialogAvailable () {
  return document.querySelector('#globalConfirmDialog') !== null
}

/**
 * Show a global confirm dialog (custom DpConfirmDialog, not native browser confirm).
 * Returns true immediately if GlobalConfirmDialog is not available (allows navigation).
 *
 * @param {Object} options
 * @param {string} options.message - The message to display
 * @param {string} options.confirmButtonText - Text for confirm button
 * @param {string} options.declineButtonText - Text for decline button
 * @returns {Promise<string>} - 'save', 'discard', or 'cancel'
 */
export function showGlobalConfirm ({
  message,
  confirmButtonText = window.Translator?.trans('confirm') || 'Confirm',
  declineButtonText = window.Translator?.trans('abort') || 'Cancel',
}) {
  // This ensures the mixin doesn't block navigation in projects without the dialog
  if (!isGlobalConfirmDialogAvailable()) {
    return Promise.resolve(true)
  }

  return new Promise((resolve) => {
    const handleResult = (event) => {
      document.removeEventListener('global-confirm-dialog:result', handleResult)
      resolve(event.detail.action)
    }

    document.addEventListener('global-confirm-dialog:result', handleResult)

    const showEvent = new CustomEvent('global-confirm-dialog:show', {
      detail: {
        message,
        confirmButtonText,
        declineButtonText,
      },
    })
    document.dispatchEvent(showEvent)
  })
}

/**
 * Show unsaved changes confirm dialog
 * @returns {Promise<string>} - 'save', 'discard', or 'cancel'
 */
export function showUnsavedChangesConfirm () {
  return showGlobalConfirm({
    message: 'Ihre Änderungen wurden noch nicht gespeichert.\n' + '\n' + 'Möchten Sie die Änderungen speichern, bevor Sie die Seite verlassen?',
    confirmButtonText: 'Speichern und verlassen',
    declineButtonText: 'Weiter bearbeiten',
  })
}
