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

initialize(components, {}, apiStores)

/*
 * Gate the element-import submit on the upload pipeline (virus scan + flysystem
 * move) finishing. tus exposes the file id early via the recovery cache so the
 * user can click Submit before the blob is in place. Poll core_file_ready and
 * only submit once the backend confirms the file is consumable.
 */
function initElementImportGate () {
  const form = document.getElementById('elementImportForm')
  const submit = document.getElementById('elementImportSubmit')
  if (!form || !submit) return

  let pending = false
  const originalLabel = submit.value
  const processingLabel = (typeof Translator === 'undefined') ?
    'Datei wird geprüft …' :
    Translator.trans('elementimport.processing')

  form.addEventListener('submit', (event) => {
    if (pending) return
    /*
     * DpUploadFiles renders the hidden input as `uploadedFiles[<name>]`, with
     * the hash array bound directly as the value (csv for multi-upload).
     */
    const input = form.querySelector('input[name="uploadedFiles[r_zipImport]"]')
    if (!input?.value) return
    const fileId = input.value.split(',')[0].trim()
    if (!fileId) return

    event.preventDefault()
    pending = true
    submit.disabled = true
    submit.value = processingLabel

    const url = Routing.generate('core_file_ready', { hash: fileId })
    let attempts = 0
    const tick = () => {
      attempts += 1
      fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
        .then(res => (res.ok ? res.json() : { ready: false }))
        .then(data => {
          if (data?.ready) {
            submit.value = originalLabel
            submit.disabled = false
            pending = false
            form.submit()
            return
          }
          if (attempts > 600) {
            submit.value = originalLabel
            submit.disabled = false
            pending = false
            return
          }
          setTimeout(tick, 3000)
        })
        .catch(() => setTimeout(tick, 5000))
    }
    tick()
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initElementImportGate)
} else {
  initElementImportGate()
}
