/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for document_admin_edit.html.twig
 */

import { DpInput, DpLabel } from 'demosplan-ui/components'
import DpRadio from '@DpJs/components/core/form/DpRadio'
import DpTextArea from '@DpJs/components/core/form/DpTextArea'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpInput,
  DpLabel,
  DpRadio,
  DpTextArea,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
