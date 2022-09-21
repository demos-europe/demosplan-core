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
import DpRadio from '@DemosPlanCoreBundle/components/form/DpRadio'
import DpTextArea from '@DemosPlanCoreBundle/components/form/DpTextArea'
import DpUploadFiles from '@DemosPlanCoreBundle/components/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

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
