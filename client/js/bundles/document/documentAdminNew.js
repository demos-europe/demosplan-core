/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for document_admin_new.html.twig
 */

import { DpInput, DpRadio, DpTextArea, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpInput,
  DpRadio,
  DpTextArea,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
