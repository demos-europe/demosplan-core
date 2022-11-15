/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for document_admin_new.html.twig
 */

import { DpInput } from 'demosplan-ui/components'
import { DpRadio, DpTextArea, DpUploadFiles } from 'demosplan-ui/components/core'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpInput,
  DpRadio,
  DpTextArea,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
