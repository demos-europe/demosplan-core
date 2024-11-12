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

import UrlPreview from '@DpJs/lib/shared/UrlPreview'

const components = {
  DpAccordion,
  DpEditor,
  OrganisationDataForm
}

initialize(components).then(() => {
  UrlPreview()
  dpValidate()
})
