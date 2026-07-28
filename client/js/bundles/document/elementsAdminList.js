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

const POLL_INTERVAL = 3000

/**
 * How many consecutive failed status requests are tolerated before polling gives up.
 *
 * A single failure means nothing — a restarting container, a dropped connection, a machine waking
 * from sleep. Stopping on the first one used to leave the progress display frozen with no
 * explanation while the import kept running.
 */
const MAX_CONSECUTIVE_FAILURES = 5

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

  const handle = setInterval(() => {
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
          clearInterval(handle)
          // The list is rendered server-side, so it only picks up the new documents on reload.
          reloadForFinishedImport()
        }

        if (data.status === 'failed') {
          clearInterval(handle)
          // A frozen counter next to an error message reads as though it were still running.
          progressElement.classList.add('hidden')
          dplan.notify.error(data.error || Translator.trans('error.elementimport.failed'))
        }
      })
      .catch(error => {
        // Retrying cannot recover an authentication problem, so stop and say what is wrong.
        if (error instanceof StatusRequestError && error.isAuthenticationProblem) {
          clearInterval(handle)
          dplan.notify.error(Translator.trans('warning.session.expired'))

          return
        }

        consecutiveFailures++

        if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
          clearInterval(handle)
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
