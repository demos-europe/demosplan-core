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

  progressElement.classList.remove('hidden')

  const handle = setInterval(() => {
    fetch(statusUrl, { headers: { Accept: 'application/json' } })
      .then(response => response.json())
      .then(data => {
        processedElement.textContent = data.filesProcessed
        totalElement.textContent = data.filesTotal

        if (data.status === 'completed') {
          clearInterval(handle)
          /*
           * The list is rendered server-side, so it only picks up the new documents on reload.
           * Drop the job id on the way: reloading with it still in the url would start polling
           * the finished job again and reload forever.
           */
          reloadWithoutJobId()
        }

        if (data.status === 'failed') {
          clearInterval(handle)
          dplan.notify.error(data.error || Translator.trans('error.elementimport.failed'))
        }
      })
      .catch(() => clearInterval(handle))
  }, 3000)
}

/**
 * Reload so the server-side rendered list picks up the imported documents, without the job id.
 *
 * Using replace() rather than assign() keeps the polling url out of the history, so going back
 * does not drop the user onto a page that starts polling a finished job again.
 */
function reloadWithoutJobId () {
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
