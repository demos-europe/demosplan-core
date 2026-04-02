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
      resolve(event.detail.confirmed)
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

export function showUnsavedChangesConfirm () {
  return showGlobalConfirm({
    message: window.Translator?.trans('check.lock.loose.text') || 'You have unsaved changes. Do you really want to leave?',
    confirmButtonText: window.Translator?.trans('leave') || 'Leave',
    declineButtonText: window.Translator?.trans('abort') || 'Cancel',
  })
}
