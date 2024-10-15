/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for edit_orga.html.twig
 */

import { DpAccordion, DpEditor, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import OrganisationDataForm from '@DpJs/components/user/orgaDataEntry/OrganisationDataForm'
import EmailNotificationSettings from '@DpJs/components/user/orgaDataEntry/EmailNotificationSettings'
import PaperCopyPreferences from '@DpJs/components/user/orgaDataEntry/PaperCopyPreferences'
import OrganisationBrandingSettings from '@DpJs/components/user/orgaDataEntry/OrganisationBrandingSettings'


import UrlPreview from '@DpJs/lib/shared/UrlPreview'

const components = {
  DpAccordion,
  DpEditor,
  EmailNotificationSettings,
  OrganisationBrandingSettings,
  OrganisationDataForm,
  PaperCopyPreferences
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
