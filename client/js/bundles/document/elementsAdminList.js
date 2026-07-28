/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for elements_admin_list.html.twig
 */

import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import DpMapSettingsPreview from '@DpJs/components/document/DpMapSettingsPreview'
import { DpUploadFiles } from '@demos-europe/demosplan-ui'
import ElementsAdminList from '@DpJs/components/document/ElementsAdminList'
import { initialize } from '@DpJs/InitVue'

const components = {
  AddonWrapper,
  ElementsAdminList,
  DpMapSettingsPreview,
  DpUploadFiles,
}

const apiStores = ['Elements']

/**
 * Marks that an import finished, so the confirmation can be raised after the reload that follows
 * it. Kept in sessionStorage rather than the url: a query parameter would be re-read on every
 * manual refresh, and it was a leftover parameter that caused the reload loop in the first place.
 */
const IMPORT_FINISHED_KEY = 'dpElementImportFinished'

const POLL_INTERVAL = 5000

/**
 * How many consecutive failed status requests are tolerated before polling gives up.
 *
 * A single failure means nothing — a restarting container, a dropped connection, a machine waking
 * from sleep. Stopping on the first one used to leave the progress display frozen with no
 * explanation while the import kept running.
 */
const MAX_CONSECUTIVE_FAILURES = 5

/**
 * Lease held by whichever tab is currently polling, so only one of them does.
 *
 * Every page showing the administration list polls, and an import can run for many minutes, so
 * several open tabs otherwise produce a burst of simultaneous requests every interval for the whole
 * duration. Requests are cheap individually but concurrent ones are not: they were observed
 * arriving five per second, and the session lost its authentication shortly after.
 *
 * The lease expires so that a tab which was closed or crashed does not keep the others waiting.
 */
const POLL_LOCK_KEY = 'dpElementImportPollLock'
const POLL_LOCK_TTL = POLL_INTERVAL * 3
const TAB_ID = `${Date.now()}-${Math.random().toString(36).slice(2)}`

initialize(components, {}, apiStores).then(() => {
  notifyIfImportJustFinished()
  pollImportJob()
})

/**
 * Report the progress of a Planunterlagen import that is running in a background worker.
 *
 * Saving an import no longer happens inside the request, so this page is reached immediately after
 * submitting and the documents appear only once the worker is done. Without this the user would be
 * looking at an unchanged list with no indication that anything is happening.
 *
 * Whether an import is running is decided server-side and expressed by the presence of the progress
 * element — not by a url parameter, so a reload or a fresh tab picks the import back up.
 */
function pollImportJob () {
  const progressElement = document.getElementById('js_importJobProgress')

  if (!progressElement) {
    return
  }

  // Rendered by the template, because Twig does not run over this file.
  const statusUrl = progressElement.dataset.statusUrl
  const processedElement = document.getElementById('js_importJobProcessed')
  const totalElement = document.getElementById('js_importJobTotal')
  let consecutiveFailures = 0

  progressElement.classList.remove('hidden')

  let handle = null
  const stopPolling = () => {
    clearInterval(handle)
    releasePollLock()
  }

  // Hand the lease over immediately instead of making the next tab wait for it to expire.
  window.addEventListener('beforeunload', releasePollLock)

  handle = setInterval(() => {
    // Another tab is already asking; this one just keeps showing what the server rendered.
    if (!claimPollLock()) {
      return
    }

    fetch(statusUrl, { headers: { Accept: 'application/json' } })
      .then(response => {
        /*
         * An expired session does not answer with an error code: the request is redirected to the
         * login page and fetch follows it, so what arrives is a perfectly successful html
         * response. The content type is therefore the reliable signal, not the status.
         */
        const isJson = (response.headers.get('content-type') || '').includes('application/json')

        if (!isJson || 401 === response.status || 403 === response.status) {
          throw new StatusRequestError(response.status, true)
        }

        if (!response.ok) {
          throw new StatusRequestError(response.status, false)
        }

        return response.json()
      })
      .then(data => {
        consecutiveFailures = 0

        processedElement.textContent = data.filesProcessed
        totalElement.textContent = data.filesTotal

        if (data.status === 'completed') {
          stopPolling()
          // The list is rendered server-side, so it only picks up the new documents on reload.
          reloadForFinishedImport()
        }

        if (data.status === 'failed') {
          stopPolling()
          // A frozen counter next to an error message reads as though it were still running.
          progressElement.classList.add('hidden')
          dplan.notify.error(data.error || Translator.trans('error.elementimport.failed'))
        }
      })
      .catch(error => {
        // Retrying cannot recover an authentication problem, so stop and say what is wrong.
        if (error instanceof StatusRequestError && error.isAuthenticationProblem) {
          stopPolling()
          dplan.notify.error(Translator.trans('warning.session.expired'))

          return
        }

        consecutiveFailures++

        if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
          stopPolling()
          /*
           * The import itself is unaffected — it runs in a worker and does not depend on this page.
           * Say so, otherwise a frozen counter reads as a failed import.
           */
          dplan.notify.warning(Translator.trans('warning.elementimport.status.unavailable'))
        }
      })
  }, POLL_INTERVAL)
}

/**
 * Take or renew the polling lease, and report whether this tab now holds it.
 *
 * localStorage has no atomic compare-and-set, so the lease is written and then read back: if
 * another tab wrote after us, it wins and we stay passive. Two tabs can still both claim on the
 * exact same tick, which costs one duplicate request — the point is to keep the number of
 * simultaneous requests near one instead of growing with the number of open tabs.
 */
function claimPollLock () {
  const now = Date.now()

  try {
    const held = JSON.parse(window.localStorage.getItem(POLL_LOCK_KEY) || 'null')

    // Someone else holds a lease that has not expired yet.
    if (held && held.tabId !== TAB_ID && now - held.claimedAt < POLL_LOCK_TTL) {
      return false
    }

    window.localStorage.setItem(POLL_LOCK_KEY, JSON.stringify({ tabId: TAB_ID, claimedAt: now }))

    const confirmed = JSON.parse(window.localStorage.getItem(POLL_LOCK_KEY) || 'null')

    return null !== confirmed && confirmed.tabId === TAB_ID
  } catch {
    // Without usable localStorage every tab polls, which is the behaviour this replaces.
    return true
  }
}

function releasePollLock () {
  try {
    const held = JSON.parse(window.localStorage.getItem(POLL_LOCK_KEY) || 'null')

    // Never drop a lease another tab has meanwhile taken over.
    if (held && held.tabId === TAB_ID) {
      window.localStorage.removeItem(POLL_LOCK_KEY)
    }
  } catch {
    // Nothing to release.
  }
}

/**
 * A status request that did not yield usable json, carrying whether retrying could help.
 */
class StatusRequestError extends Error {
  constructor (status, isAuthenticationProblem) {
    super(`Import status request failed with status ${status}`)
    this.status = status
    this.isAuthenticationProblem = isAuthenticationProblem
  }
}

/**
 * Reload so the server-side rendered list picks up the imported documents.
 *
 * The job id is stripped for the benefit of urls that still carry one — an open tab or a bookmark
 * from before it was dropped from the redirect. Left in place it used to make the page poll the
 * finished job again after every reload, and reload forever.
 *
 * replace() rather than assign() keeps that url out of the history, so going back cannot drop the
 * user into the same loop.
 */
function reloadForFinishedImport () {
  window.sessionStorage.setItem(IMPORT_FINISHED_KEY, '1')

  const url = new URL(window.location.href)
  url.searchParams.delete('importJob')
  window.location.replace(url.toString())
}

/**
 * Raise the confirmation for an import that finished just before the current page load. Raising it
 * before the reload would be pointless — the notification dies with the page.
 */
function notifyIfImportJustFinished () {
  if (null === window.sessionStorage.getItem(IMPORT_FINISHED_KEY)) {
    return
  }

  window.sessionStorage.removeItem(IMPORT_FINISHED_KEY)
  dplan.notify.confirm(Translator.trans('confirm.elementimport.finished'))
}
