/**
 * Persistent async-export polling.
 *
 * The background export runs in a Symfony Messenger worker, decoupled from the browser, so the job
 * always finishes even when the user refreshes the page or navigates away. The gap this closes is
 * on the client: the poll loop lives in JavaScript and dies with the page, losing the handle to the
 * (still running) job — so the finished file is never fetched. We persist the job's status/download
 * URLs in localStorage and resume polling on every page load (see resumePendingExports), so the
 * download is triggered wherever the user happens to be once the file is ready.
 */

const STORAGE_PREFIX = 'dplan.export.job.'

const storageKey = key => `${STORAGE_PREFIX}${key}`

/**
 * Remember an in-flight export job (its status/download URLs) so polling can be resumed on any
 * later page load.
 * @param {String} key  context identifier (e.g. `assessment.<procedureId>` or `procedure`)
 * @param {Object} urls { statusUrl, downloadUrl }
 */
export function storeExportJob (key, urls) {
  try {
    window.localStorage.setItem(storageKey(key), JSON.stringify(urls))
  } catch (e) {
    /*
     * Storage may be unavailable (localStorage in private mode or at quota). Polling still works
     * for this page load; only cross-navigation resume is lost.
     */
  }
}

/**
 * Forget a stored export job once it reached a terminal state.
 * @param {String} key context identifier
 */
export function clearExportJob (key) {
  try {
    window.localStorage.removeItem(storageKey(key))
  } catch (e) {
    // Nothing to clean up if storage is unavailable.
  }
}

/**
 * Read and parse a stored job entry by its full (prefixed) localStorage key.
 * @param {String} fullKey
 * @return {Object|null} { statusUrl, downloadUrl } or null
 */
function readExportJob (fullKey) {
  try {
    return JSON.parse(window.localStorage.getItem(fullKey))
  } catch (e) {
    return null
  }
}

/**
 * Poll a background export job until it reaches a terminal state, then auto-download the file or
 * report the error. The job's URLs are persisted under `key` so it survives a refresh/navigation
 * and is cleared once the job finishes.
 *
 * @param {Object}   options
 * @param {String}   options.key         localStorage key identifying this export context
 * @param {String}   options.statusUrl   endpoint returning `{ status }`
 * @param {String}   options.downloadUrl endpoint streaming the finished file
 * @param {Boolean} [options.immediate]  poll right away instead of after the interval (used on resume)
 */
export function pollExportJob ({ key, statusUrl, downloadUrl, immediate = false }) {
  storeExportJob(key, { statusUrl, downloadUrl })

  const poll = () => {
    fetch(statusUrl, { credentials: 'same-origin' })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'completed') {
          clearExportJob(key)
          dplan.notify.confirm(Translator.trans('export.done'))
          window.location.href = downloadUrl
        } else if (data.status === 'failed' || data.status === 'not_found') {
          clearExportJob(key)
          dplan.notify.error(Translator.trans('error.export'))
        } else {
          setTimeout(poll, 3000)
        }
      })
      .catch(() => {
        /*
         * A transient network error (or the page unloading) must not discard the job: keep polling
         * so a blip recovers, and leave the entry in storage so the next page load resumes it too.
         */
        setTimeout(poll, 3000)
      })
  }

  setTimeout(poll, immediate ? 0 : 3000)
}

/**
 * Resume polling for every export job persisted in localStorage. Called once per page load (from
 * the global Vue bootstrap) so a background export started on another page is picked up and
 * downloaded wherever the user currently is.
 */
export function resumePendingExports () {
  let fullKeys
  try {
    fullKeys = Object.keys(window.localStorage)
  } catch (e) {
    return
  }

  fullKeys
    .filter(fullKey => fullKey.startsWith(STORAGE_PREFIX))
    .forEach(fullKey => {
      const job = readExportJob(fullKey)
      if (job && job.statusUrl && job.downloadUrl) {
        pollExportJob({
          key: fullKey.slice(STORAGE_PREFIX.length),
          statusUrl: job.statusUrl,
          downloadUrl: job.downloadUrl,
          immediate: true,
        })
      } else {
        // Corrupt or legacy entry — drop it so it does not linger across page loads.
        try {
          window.localStorage.removeItem(fullKey)
        } catch (e) {
          // Ignore storage errors during cleanup.
        }
      }
    })
}
