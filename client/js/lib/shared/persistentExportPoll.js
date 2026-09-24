/**
 * Polls a background export job until it is done, then downloads the file. The job is remembered in
 * localStorage so polling resumes after a refresh or navigation (see resumePendingExports).
 */
const STORAGE_PREFIX = 'dplan.export.job.'
const POLL_INTERVAL = 3000
const FINAL_STATUSES = ['completed', 'failed', 'not_found']

// The localStorage may be unavailable (private mode, quota); polling still works, only resuming is lost
const withStorage = action => {
  try {
    return action(window.localStorage)
  } catch (e) {
    return null
  }
}

export function pollExportJob ({ key, statusUrl, downloadUrl }, delay = POLL_INTERVAL) {
  const storageKey = STORAGE_PREFIX + key

  withStorage(storage => storage.setItem(storageKey, JSON.stringify({ statusUrl, downloadUrl })))

  const poll = () => fetch(statusUrl, { credentials: 'same-origin' })
    .then(response => response.json())
    .then(({ status }) => {
      if (!FINAL_STATUSES.includes(status)) {
        setTimeout(poll, POLL_INTERVAL)

        return
      }

      withStorage(storage => storage.removeItem(storageKey))
      if (status === 'completed') {
        dplan.notify.confirm(Translator.trans('export.done'))
        window.location.href = downloadUrl
      } else {
        dplan.notify.error(Translator.trans('error.export'))
      }
    })
    // Keep polling through network blips; the stored entry also lets the next page load resume it
    .catch(() => setTimeout(poll, POLL_INTERVAL))

  setTimeout(poll, delay)
}

export function resumePendingExports () {
  const storageKeys = withStorage(storage => Object.keys(storage)) ?? []

  storageKeys
    .filter(storageKey => storageKey.startsWith(STORAGE_PREFIX))
    .forEach(storageKey => {
      const job = withStorage(storage => JSON.parse(storage.getItem(storageKey)))

      if (job?.statusUrl && job?.downloadUrl) {
        pollExportJob({ key: storageKey.slice(STORAGE_PREFIX.length), ...job }, 0)
      } else {
        withStorage(storage => storage.removeItem(storageKey))
      }
    })
}
