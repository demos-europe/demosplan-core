/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for edit_orga.html.twig
 */

import DpAccordion from '@DpJs/components/core/DpAccordion'
import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import UrlPreview from '@DemosPlanUserBundle/lib/UrlPreview'

const components = {
  DpAccordion,
  DpTiptap
}

initialize(components).then(() => {
  UrlPreview()
  dpValidate()

  if (hasPermission('feature_change_submission_type')) {
    const form = document.querySelector('#content form')
    const orgaId = form.getAttribute('data-orga-id')
    const precheckSubmit = (e) => {
      const currentSubmissionType = document.querySelector('input[name="' + orgaId + ':current_submission_type"]').value
      const selectedSubmissionType = document.querySelector('input[name="' + orgaId + ':submission_type"]:checked').value

      if (currentSubmissionType !== selectedSubmissionType &&
        !window.dpconfirm(Translator.trans('confirm.statement.orgaedit.change'))) {
        e.preventDefault()
        return false
      }

      return true
    }

    document.querySelector('#content form [type=submit]').addEventListener('click', precheckSubmit)
  }
})
