/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for document_admin_edit.html.twig
 */

import { DpInput, DpLabel, DpRadio, DpTextArea, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpInput,
  DpLabel,
  DpRadio,
  DpTextArea,
  DpUploadFiles,
}

initialize(components).then(() => {
  dpValidate()
})
